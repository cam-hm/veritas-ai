<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentChunk;
use Pgvector\Laravel\Distance;

class TestVectorSearchSpeed extends Command
{
    protected $signature = 'veritas:test-speed';
    protected $description = 'Benchmark the vector search query speed.';

    public function handle(): int
    {
        $firstChunk = DocumentChunk::first();
        if (!$firstChunk) {
            $this->error('No document chunks found to test.');
            return self::FAILURE;
        }

        $embedding = $firstChunk->embedding;

        $this->info('Benchmarking nearest neighbor search...');

        $startTime = microtime(true);
        $results = DocumentChunk::query()
            ->nearestNeighbors('embedding', $embedding, Distance::Cosine, 3)
            ->take(5)
            ->get();
        $endTime = microtime(true);

        $duration = ($endTime - $startTime) * 1000; // in milliseconds

        $this->info("Found {$results->count()} neighbors.");
        $this->info(sprintf("Query took: %.2f ms", $duration));

        return self::SUCCESS;
    }
}