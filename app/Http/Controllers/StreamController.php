<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\TokenEstimationService;
use App\Services\RetrievalService;
use Camh\Ollama\Facades\Ollama;
use Pgvector\Laravel\Distance;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class StreamController extends Controller
{
    public function stream(Request $request)
    {
        $documentId = $request->input('document_id');
        $document = $documentId ? Document::findOrFail($documentId) : null;
        $messages = $request->input('messages', []);

        return new StreamedResponse(function () use ($document, $messages) {
            try {
                // Send thinking message immediately to show user that processing has started
                echo "data: " . json_encode(['type' => 'thinking', 'message' => 'Đang tìm kiếm thông tin liên quan...']) . "\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();

                $lastQuestion = collect($messages)->last(fn ($msg) => $msg['role'] === 'user')['content'] ?? '';

                $questionEmbedding = Ollama::embed($lastQuestion);
                $query = DocumentChunk::query();
                if ($document) {
                    $query->where('document_id', $document->id);
                }
                else {
                    // Limit general chat retrieval to the current user's documents
                    $userId = Auth::id();
                    $query->whereHas('document', function ($q) use ($userId) {
                        $q->where('user_id', $userId);
                    });
                }

                // Retrieve more chunks initially for better context selection
                // Reduced from 20 to 15 for faster re-ranking while maintaining quality
                $candidateChunks = $query
                    ->nearestNeighbors('embedding', $questionEmbedding, Distance::Cosine, 3)
                    ->take(15) // Retrieve more initially for re-ranking
                    ->get();

                Log::info('Candidate Chunks: ' . $candidateChunks->pluck('id')->implode(', '));

                // Initialize services
                $tokenService = new TokenEstimationService();
                $retrievalService = new RetrievalService($tokenService);
                $maxContextTokens = Config::get('ollama.max_context_tokens', 4000);
                
                // Re-rank chunks by multiple factors (similarity + keyword + length)
                $rerankedChunks = $retrievalService->rerankChunks($candidateChunks, $lastQuestion);
                
                Log::info('Re-ranking completed', [
                    'total_candidates' => $candidateChunks->count(),
                    'top_scores' => $rerankedChunks->take(5)->map(fn($item) => [
                        'chunk_id' => $item['chunk']->id,
                        'score' => round($item['score'], 3),
                        'similarity' => round($item['similarity_score'], 3),
                        'keyword' => round($item['keyword_score'], 3),
                        'length' => round($item['length_score'], 3),
                    ])->toArray(),
                ]);
                
                // Estimate tokens for system prompt base
                $scope = $document ? "this document ('{$document->name}')" : 'the available documents';
                $systemPromptBase = "Based only on the following context from {$scope}, answer the user's question. If you are not sure, say you are not sure and suggest where to look.\n\nContext:\n";
                $baseTokens = $tokenService->estimateTokens($systemPromptBase);
                
                // Estimate tokens for user messages
                $userMessagesTokens = 0;
                foreach ($messages as $message) {
                    if ($message['role'] === 'user') {
                        $userMessagesTokens += $tokenService->estimateTokens($message['content'] ?? '');
                    }
                }
                
                // Reserve tokens for system prompt, user messages, and response
                // Reserve ~20% for response generation
                $reservedTokens = $baseTokens + $userMessagesTokens + (int)($maxContextTokens * 0.2);

                // Select chunks that fit within token limit (using re-ranked order)
                $selectedChunks = $retrievalService->selectChunksWithinTokenLimit(
                    $rerankedChunks,
                    $maxContextTokens,
                    $reservedTokens
                );

                $usedTokens = $reservedTokens;
                foreach ($selectedChunks as $chunk) {
                    $usedTokens += $tokenService->estimateTokens($chunk->content) 
                                 + $tokenService->estimateTokens("\n\n---\n\n");
                }

                Log::info('Context window management', [
                    'candidate_chunks' => $candidateChunks->count(),
                    'selected_chunks' => $selectedChunks->count(),
                    'estimated_tokens' => $usedTokens,
                    'reserved_tokens' => $reservedTokens,
                    'max_tokens' => $maxContextTokens,
                ]);

                $context = $selectedChunks->pluck('content')->implode("\n\n---\n\n");
                if (trim($context) === '') {
                    $systemPrompt = "You are a helpful assistant. If the context is empty or insufficient, answer based on your general knowledge about the user's documents if possible, and otherwise ask a clarifying question.";
                } else {
                    $systemPrompt = $systemPromptBase . $context;
                }

                $messagesForAI = $messages;
                
                // Filter and validate messages - remove invalid ones and ensure proper format
                $validatedMessages = [];
                foreach ($messagesForAI as $index => $msg) {
                    // Skip messages without role or content field
                    if (!isset($msg['role']) || !array_key_exists('content', $msg)) {
                        Log::warning('Skipping invalid message', [
                            'index' => $index,
                            'message' => $msg,
                        ]);
                        continue;
                    }
                    
                    // Validate role
                    if (!in_array($msg['role'], ['system', 'user', 'assistant'])) {
                        Log::warning('Skipping message with invalid role', [
                            'index' => $index,
                            'role' => $msg['role'] ?? 'missing',
                        ]);
                        continue;
                    }
                    
                    // Ensure content is a string (convert null to empty string)
                    $content = $msg['content'] ?? '';
                    if (!is_string($content)) {
                        $content = (string) $content;
                    }
                    
                    // Skip empty assistant messages (they are placeholders for streaming)
                    if ($msg['role'] === 'assistant' && trim($content) === '') {
                        continue;
                    }
                    
                    $validatedMessages[] = [
                        'role' => $msg['role'],
                        'content' => $content,
                    ];
                }
                
                // Add system prompt at the beginning
                array_unshift($validatedMessages, ['role' => 'system', 'content' => $systemPrompt]);
                
                // Ensure we have at least a system message
                if (empty($validatedMessages)) {
                    throw new \InvalidArgumentException('No valid messages to send to Ollama');
                }
                
                $messagesForAI = $validatedMessages;

                Log::debug('Sending request to Ollama', [
                    'message_count' => count($messagesForAI),
                    'system_prompt_length' => mb_strlen($systemPrompt),
                ]);

                // Send ready message before starting stream
                echo "data: " . json_encode(['type' => 'ready']) . "\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();

                try {
                    $stream = Ollama::chat($messagesForAI, ['stream' => true]);
                } catch (\Exception $ollamaError) {
                    Log::error('Ollama API error', [
                        'error' => $ollamaError->getMessage(),
                        'messages_count' => count($messagesForAI),
                    ]);
                    throw $ollamaError;
                }

                // Optimized streaming: flush first chunk immediately for lowest latency
                // Then buffer subsequent chunks for efficiency
                $buffer = '';
                $chunkCount = 0;
                $isFirstChunk = true;
                $bufferSize = 3; // Buffer size for subsequent chunks

                foreach ($stream as $chunk) {
                    // Ensure chunk is a string (Ollama returns JSON string)
                    $chunkString = is_string($chunk) ? $chunk : json_encode($chunk);
                    $chunkData = "data: " . $chunkString . "\n\n";
                    
                    // Flush first chunk immediately for fastest Time to First Token
                    if ($isFirstChunk) {
                        echo $chunkData;
                        if (ob_get_level() > 0) ob_flush();
                        flush();
                        $isFirstChunk = false;
                        continue;
                    }
                    
                    // Buffer subsequent chunks for efficiency
                    $buffer .= $chunkData;
                    $chunkCount++;

                    // Flush when buffer is full
                    if ($chunkCount >= $bufferSize) {
                        echo $buffer;
                        if (ob_get_level() > 0) ob_flush();
                        flush();
                        $buffer = '';
                        $chunkCount = 0;
                    }
                }

                // Flush remaining buffer
                if (!empty($buffer)) {
                    echo $buffer;
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                }
            } catch (Exception $e) {
                Log::error('Stream error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errorData = json_encode(['error' => $e->getMessage()]);
                echo "data: " . $errorData . "\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }
}
