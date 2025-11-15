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
                $candidateChunks = $query
                    ->nearestNeighbors('embedding', $questionEmbedding, Distance::Cosine, 3)
                    ->take(20) // Retrieve more initially for re-ranking
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
                array_unshift($messagesForAI, ['role' => 'system', 'content' => $systemPrompt]);

                $stream = Ollama::chat($messagesForAI, ['stream' => true]);

                foreach ($stream as $chunk) {
                    echo "data: " . $chunk . "\n\n";
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                }
            } catch (Exception $e) {
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