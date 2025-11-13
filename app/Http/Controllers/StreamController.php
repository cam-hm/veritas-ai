<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\Document;
use App\Models\DocumentChunk;
use Camh\Ollama\Facades\Ollama;
use Pgvector\Laravel\Distance;
use Exception;
use Illuminate\Support\Facades\Auth;

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

                $relevantChunks = $query
                    ->nearestNeighbors('embedding', $questionEmbedding, Distance::Cosine, 3)
                    ->take(5)
                    ->get();

                Log::info('Relevant Chunks: ' . $relevantChunks->pluck('id')->implode(', '));

                $context = $relevantChunks->pluck('content')->implode("\n\n---\n\n");
                if (trim($context) === '') {
                    $systemPrompt = "You are a helpful assistant. If the context is empty or insufficient, answer based on your general knowledge about the user's documents if possible, and otherwise ask a clarifying question.";
                } else {
                    $scope = $document ? "this document ('{$document->name}')" : 'the available documents';
                    $systemPrompt = "Based only on the following context from {$scope}, answer the user's question. If you are not sure, say you are not sure and suggest where to look.\n\nContext:\n{$context}";
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