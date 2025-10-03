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

class StreamController extends Controller
{
    public function stream(Request $request)
    {
        $document = Document::findOrFail($request->input('document_id'));
        $messages = $request->input('messages', []);

        return new StreamedResponse(function () use ($document, $messages) {
            try {
                $lastQuestion = collect($messages)->last(fn ($msg) => $msg['role'] === 'user')['content'];

                $questionEmbedding = Ollama::embed($lastQuestion);
                $relevantChunks = DocumentChunk::query()
                    ->where('document_id', $document->id)
                    ->nearestNeighbors('embedding', $questionEmbedding, Distance::Cosine, 3)
                    ->take(5)
                    ->get();

                Log::info('Relevant Chunks: ' . $relevantChunks->pluck('id')->implode(', '));

                $context = $relevantChunks->pluck('content')->implode("\n\n---\n\n");
                $systemPrompt = "Based *only* on the following context...\n\nContext:\n{$context}";

                $messagesForAI = $messages;
                array_unshift($messagesForAI, ['role' => 'system', 'content' => $systemPrompt]);

                $stream = Ollama::chat($messagesForAI, ['stream' => true]);
                $lastKeepAlive = time();

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