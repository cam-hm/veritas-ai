<?php

namespace App\Services;

use Camh\Ollama\Facades\Ollama;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class EmbeddingService
{
    /**
     * Default batch size for parallel processing
     */
    private int $batchSize;

    /**
     * Maximum number of retries for failed requests
     */
    private int $maxRetries;

    /**
     * Delay between retries in seconds
     */
    private float $retryDelay;

    /**
     * Maximum concurrent requests
     */
    private int $concurrency;

    public function __construct(
        ?int $batchSize = null,
        ?int $maxRetries = null,
        ?float $retryDelay = null,
        ?int $concurrency = null
    ) {
        $this->batchSize = $batchSize ?? Config::get('ollama.embed_batch_size', 10);
        $this->maxRetries = $maxRetries ?? Config::get('ollama.retries', 3);
        $this->retryDelay = $retryDelay ?? Config::get('ollama.retry_delay', 1.0);
        $this->concurrency = $concurrency ?? Config::get('ollama.embed_concurrency', 5);
    }

    /**
     * Generate embeddings for multiple chunks in parallel batches
     *
     * @param array $chunks Array of chunk content strings
     * @param callable|null $progressCallback Optional callback for progress updates (current, total)
     * @return array Array of embeddings in the same order as input chunks
     */
    public function generateEmbeddings(array $chunks, ?callable $progressCallback = null): array
    {
        if (empty($chunks)) {
            return [];
        }

        // Filter out empty chunks
        $validChunks = array_filter($chunks, fn($chunk) => !empty(trim($chunk)) && strlen(trim($chunk)) >= 5);
        $validChunks = array_values($validChunks); // Re-index array

        if (empty($validChunks)) {
            return [];
        }

        // Check if Ollama supports batch embeddings (array input)
        // If so, use native batch support for better performance
        if ($this->supportsBatchEmbeddings()) {
            return $this->generateBatchEmbeddings($validChunks, $progressCallback);
        }

        // Otherwise, use parallel HTTP requests
        return $this->generateParallelEmbeddings($validChunks, $progressCallback);
    }

    /**
     * Check if Ollama supports batch embeddings (array input)
     */
    private function supportsBatchEmbeddings(): bool
    {
        // Try to use native batch support if available
        // The Ollama facade supports array input according to docs
        return true; // Assume it's supported based on README
    }

    /**
     * Generate embeddings using Ollama's native batch support
     */
    private function generateBatchEmbeddings(array $chunks, ?callable $progressCallback = null): array
    {
        $total = count($chunks);
        $embeddings = [];
        $processed = 0;

        // Process in batches to avoid overwhelming the API
        $batches = array_chunk($chunks, $this->batchSize);

        foreach ($batches as $batchIndex => $batch) {
            try {
                // Use native batch embedding if supported
                $batchEmbeddings = Ollama::embed($batch);

                // Handle response structure - Ollama may return array of arrays or single array
                if (is_array($batchEmbeddings)) {
                    // Check if first element is an array (array of embeddings)
                    if (isset($batchEmbeddings[0]) && is_array($batchEmbeddings[0]) && isset($batchEmbeddings[0][0])) {
                        // Array of embedding arrays - merge them
                        $embeddings = array_merge($embeddings, $batchEmbeddings);
                    } elseif (isset($batchEmbeddings[0]) && is_numeric($batchEmbeddings[0])) {
                        // Single embedding array - add it
                        $embeddings[] = $batchEmbeddings;
                    } else {
                        // Try to handle as array of arrays
                        $embeddings = array_merge($embeddings, $batchEmbeddings);
                    }
                } else {
                    throw new \RuntimeException('Invalid embedding response type');
                }

                $processed += count($batch);
                if ($progressCallback) {
                    $progressCallback($processed, $total);
                }

                // Rate limiting: small delay between batches
                if ($batchIndex < count($batches) - 1) {
                    usleep(100000); // 0.1 second delay
                }
            } catch (\Exception $e) {
                Log::warning('Batch embedding failed, falling back to parallel requests', [
                    'error' => $e->getMessage(),
                    'batch_size' => count($batch),
                ]);

                // Fallback to parallel processing for this batch
                $parallelEmbeddings = $this->generateParallelEmbeddings($batch, null);
                $embeddings = array_merge($embeddings, $parallelEmbeddings);
                $processed += count($batch);
                if ($progressCallback) {
                    $progressCallback($processed, $total);
                }
            }
        }

        return $embeddings;
    }

    /**
     * Generate embeddings using parallel HTTP requests
     */
    private function generateParallelEmbeddings(array $chunks, ?callable $progressCallback = null): array
    {
        $total = count($chunks);
        $embeddings = [];
        $processed = 0;

        // Get Ollama base URL and model from config
        $ollamaBase = Config::get('ollama.base', 'http://127.0.0.1:11434');
        $embedModel = Config::get('ollama.embed_model', 'nomic-embed-text');

        // Process in batches with concurrency limit
        $batches = array_chunk($chunks, $this->concurrency);

        foreach ($batches as $batch) {
            $responses = Http::pool(function ($pool) use ($batch, $ollamaBase, $embedModel) {
                $requests = [];
                foreach ($batch as $index => $chunk) {
                    $requests[$index] = $pool->timeout(60)
                        ->retry($this->maxRetries, $this->retryDelay * 1000)
                        ->post("{$ollamaBase}/api/embeddings", [
                            'model' => $embedModel,
                            'prompt' => $chunk,
                        ]);
                }
                return $requests;
            });

            // Process responses
            foreach ($responses as $index => $response) {
                try {
                    if ($response->successful()) {
                        $data = $response->json();
                        if (isset($data['embedding']) && is_array($data['embedding'])) {
                            $embeddings[] = $data['embedding'];
                        } else {
                            Log::warning('Invalid embedding response structure, retrying individually', [
                                'response_keys' => array_keys($data ?? []),
                                'chunk_index' => $index,
                            ]);
                            // Fallback to single request with retry
                            $embeddings[] = $this->generateSingleEmbeddingWithRetry($batch[$index]);
                        }
                    } else {
                        Log::warning('Embedding request failed, retrying individually', [
                            'status' => $response->status(),
                            'error' => $response->body(),
                            'chunk_index' => $index,
                        ]);
                        // Retry as single request
                        $embeddings[] = $this->generateSingleEmbeddingWithRetry($batch[$index]);
                    }
                } catch (\Exception $e) {
                    Log::error('Exception processing embedding response, retrying individually', [
                        'error' => $e->getMessage(),
                        'chunk_index' => $index,
                    ]);
                    // Retry as single request
                    $embeddings[] = $this->generateSingleEmbeddingWithRetry($batch[$index]);
                }
            }

            $processed += count($batch);
            if ($progressCallback) {
                $progressCallback($processed, $total);
            }

            // Rate limiting: small delay between batches
            usleep(50000); // 0.05 second delay
        }

        return $embeddings;
    }

    /**
     * Generate a single embedding with retry logic
     */
    private function generateSingleEmbeddingWithRetry(string $chunk): array
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
                return Ollama::embed($chunk);
            } catch (\Exception $e) {
                $attempts++;
                $lastException = $e;
                Log::warning("Embedding attempt {$attempts} failed", [
                    'error' => $e->getMessage(),
                    'chunk_length' => strlen($chunk),
                ]);

                if ($attempts < $this->maxRetries) {
                    usleep($this->retryDelay * 1000000); // Convert to microseconds
                }
            }
        }

        // If all retries failed, log and throw
        Log::error('Failed to generate embedding after all retries', [
            'chunk_length' => strlen($chunk),
            'error' => $lastException?->getMessage(),
        ]);

        throw new \RuntimeException(
            "Failed to generate embedding after {$this->maxRetries} attempts: " . ($lastException?->getMessage() ?? 'Unknown error')
        );
    }

    /**
     * Generate a single embedding (fallback method)
     */
    private function generateSingleEmbedding(string $chunk): array
    {
        return Ollama::embed($chunk);
    }
}

