<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Document;
use App\Services\TextExtractionService;
use App\Services\TextChunkingService;
use Camh\Ollama\Facades\Ollama;

class VeritasProcessDocument extends Command
{
    protected $signature = 'veritas:process {documentId}';
    protected $description = 'Extract, chunk, and embed a document.';

    public function handle(TextExtractionService $extractor, TextChunkingService $chunker): int
    {
        $documentId = $this->argument('documentId');
        $document = Document::find($documentId);

        if (!$document) {
            $this->error("Document with ID {$documentId} not found.");
            return self::FAILURE;
        }

        $this->info("Processing document: {$document->name}");

        try {
            $this->line('Extracting text...');
            $text = $extractor->extract(storage_path('app/' . $document->path));

            $this->line('Chunking text...');
            $chunks = $chunker->chunk($text);

            $this->line('Generating and storing embeddings...');
            $bar = $this->output->createProgressBar(count($chunks));
            $bar->start();

            foreach ($chunks as $chunkContent) {
                // ** THE FIX IS HERE **
                // 1. Trim whitespace from the chunk.
                $trimmedChunk = trim($chunkContent);

                // 2. If the chunk is empty or very short after trimming, skip it.
                if (empty($trimmedChunk) || strlen($trimmedChunk) < 5) {
                    $bar->advance();
                    continue; // Go to the next chunk
                }

                $embedding = Ollama::embed($trimmedChunk);

                $document->chunks()->create([
                    'content' => $trimmedChunk,
                    'embedding' => $embedding,
                ]);
                $bar->advance();
            }

            $bar->finish();
            $this->info("\nSuccessfully processed and embedded document.");

        } catch (\Exception $e) {
            $this->error("\nAn error occurred: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}