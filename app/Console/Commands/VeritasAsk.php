<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentChunk;
use App\Models\Document;
use Camh\Ollama\Facades\Ollama;
use Pgvector\Laravel\Distance;
use Illuminate\Contracts\Console\Isolatable;

class VeritasAsk extends Command implements Isolatable
{
    protected $signature = 'veritas:ask {question} {--doc=}';
    protected $description = 'Ask a question based on the embedded documents.';

    public function handle(): int
    {
        ini_set('memory_limit', '512M');
        $question = $this->argument('question');
        $documentId = $this->option('doc');

        if ($documentId) {
            $document = Document::find($documentId);
            if (!$document) {
                $this->error("No document found with ID: {$documentId}");
                return self::FAILURE;
            }
            $this->info("Asking a question about document: '{$document->name}'...");
        } else {
            $this->info("Asking a question across all documents...");
        }

        $questionEmbedding = Ollama::embed($question);

        $query = DocumentChunk::query();

        if ($documentId) {
            $query->where('document_id', $documentId);
        }

        $relevantChunks = $query
            ->nearestNeighbors('embedding', $questionEmbedding, Distance::Cosine)
//            ->take(3)
            ->get();

        if ($relevantChunks->isEmpty()) {
            $this->warn("I couldn't find any relevant information to answer your question.");
            return self::SUCCESS;
        }

        $this->info("Found " . $relevantChunks->count() . " relevant context chunks. Asking the AI...");

        $context = $relevantChunks->pluck('content')->implode("\n\n---\n\n");

        $prompt = "
            Based *only* on the following context, please answer the question.
            If the context does not contain the answer, say 'I do not know based on the provided context.'

            Context:
            {$context}

            Question:
            {$question}
        ";

        // ** REVERT TO STREAMING HERE **
        $this->line("\nAnswer:");
        Ollama::stream($prompt, function ($chunk) {
            $data = json_decode($chunk, true);
            // Use output->write() for continuous streaming without newlines
            $this->output->write($data['response'] ?? '');
        });

        // Add a final newline for clean formatting
        $this->output->newLine();

        return self::SUCCESS;
    }
}