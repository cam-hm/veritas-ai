<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Document;
use App\Services\TextExtractionService;
use App\Services\TextChunkingService;
use Camh\Ollama\Facades\Ollama;
use Illuminate\Support\Facades\Storage; // 1. Import the Storage facade

class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Document $document)
    {
    }

    public function handle(TextExtractionService $extractor, TextChunkingService $chunker): void
    {
        try {
            // Get the absolute path using the Storage facade.
            $absolutePath = Storage::path($this->document->path);

            // Extract Text using the absolute path
            $text = $extractor->extract($absolutePath);

            $chunks = $chunker->chunk($text);

            foreach ($chunks as $chunkContent) {
                $trimmedChunk = trim($chunkContent);
                if (empty($trimmedChunk) || strlen($trimmedChunk) < 5) {
                    continue;
                }

                $embedding = Ollama::embed($trimmedChunk);

                $this->document->chunks()->create([
                    'content' => $trimmedChunk,
                    'embedding' => $embedding,
                ]);
            }
        } catch (\Exception $e) {
            // ... (error handling)
            throw $e;
        }
    }
}