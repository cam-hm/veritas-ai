<?php

namespace App\Jobs;

use App\Services\RecursiveChunkingService;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Document;
use App\Services\TextExtractionService;
use App\Services\SentenceChunkingService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Document $document)
    {
    }

    public function handle(
        TextExtractionService $extractor,
        RecursiveChunkingService $chunker,
        EmbeddingService $embeddingService
    ): void {
        try {
            // Mark as processing
            $this->document->update(['status' => 'processing', 'error_message' => null]);

            // Get the absolute path using the Storage facade.
            $absolutePath = Storage::path($this->document->path);

            // Extract Text using the absolute path
            $text = $extractor->extract($absolutePath);

            $chunks = $chunker->chunk($text);

            // Prepare chunks for embedding
            $chunkContents = [];
            foreach ($chunks as $chunk) {
                $chunkContent = $chunk['content'] ?? $chunk;
                $trimmedChunk = trim($chunkContent);
                if (!empty($trimmedChunk) && strlen($trimmedChunk) >= 5) {
                    $chunkContents[] = $trimmedChunk;
                }
            }

            if (empty($chunkContents)) {
                throw new \RuntimeException('No valid chunks found after processing document');
            }

            // Generate embeddings in parallel batches
            Log::info('Starting batch embedding generation', [
                'document_id' => $this->document->id,
                'chunk_count' => count($chunkContents),
            ]);

            $embeddings = $embeddingService->generateEmbeddings(
                $chunkContents,
                function ($processed, $total) {
                    Log::debug('Embedding progress', [
                        'document_id' => $this->document->id,
                        'processed' => $processed,
                        'total' => $total,
                        'percentage' => round(($processed / $total) * 100, 2),
                    ]);
                }
            );

            // Store chunks with embeddings
            $count = 0;
            foreach ($chunkContents as $index => $chunkContent) {
                if (isset($embeddings[$index])) {
                    $this->document->chunks()->create([
                        'content' => $chunkContent,
                        'embedding' => $embeddings[$index],
                        // We will add a 'metadata' column to the database later
                    ]);
                    $count++;
                } else {
                    Log::warning('Missing embedding for chunk', [
                        'document_id' => $this->document->id,
                        'chunk_index' => $index,
                    ]);
                }
            }

            if ($count === 0) {
                throw new \RuntimeException('No chunks were successfully embedded');
            }

            $this->document->update([
                'status' => 'completed',
                'processed_at' => now(),
                'num_chunks' => $count,
            ]);

            Log::info('Document processing completed', [
                'document_id' => $this->document->id,
                'chunks_created' => $count,
            ]);
        } catch (\Exception $e) {
            // Update document to failed state and store error message
            try {
                $this->document->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            } catch (\Throwable $t) {
                // Best-effort logging if update fails
                Log::error('Failed to update document status after exception', [
                    'document_id' => $this->document->id,
                    'exception' => $e->getMessage(),
                    'update_error' => $t->getMessage(),
                ]);
            }

            Log::error('Document processing failed', [
                'document_id' => $this->document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}