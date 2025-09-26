<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentChunk;
use Camh\Ollama\Facades\Ollama;
use Pgvector\Laravel\Distance;
use Illuminate\Contracts\Console\Isolatable;

class VeritasAsk extends Command implements Isolatable
{
    protected $signature = 'veritas:ask {question}';
    protected $description = 'Ask a question based on the embedded documents.';

    public function handle(): int
    {
        // Increase memory limit for this command to handle large contexts
        ini_set('memory_limit', '512M');

        $question = $this->argument('question');

        $this->info("Finding relevant documents for your question...");

        $questionEmbedding = Ollama::embed($question);

        $relevantChunks = DocumentChunk::query()
            ->nearestNeighbors('embedding', $questionEmbedding, Distance::Cosine)
            ->get();

        if ($relevantChunks->isEmpty()) {
            $this->warn("I couldn't find any relevant information in the documents to answer your question.");
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

        // ** THE FIX IS HERE **
        // We are now using the simple, non-streaming 'generate' method.
        $this->line("\nAnswer:");
        $response = Ollama::generate($prompt);
        $this->line($response); // Print the entire response at once

        return self::SUCCESS;
    }
}
