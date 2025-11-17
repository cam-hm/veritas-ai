<?php

namespace App\Services;

use App\Models\DocumentChunk;
use Illuminate\Support\Collection;

class RetrievalService
{
    private TokenEstimationService $tokenService;

    public function __construct(TokenEstimationService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Re-rank chunks by combining multiple scoring factors:
     * - Vector similarity (70% weight)
     * - Keyword matching (20% weight)
     * - Chunk length (10% weight)
     * 
     * @param Collection $candidateChunks Collection of DocumentChunk models
     * @param string $question The user's question
     * @return Collection Collection of arrays with 'chunk', 'score', and individual scores
     */
    public function rerankChunks(Collection $candidateChunks, string $question): Collection
    {
        return $candidateChunks->map(function (DocumentChunk $chunk) use ($question) {
            // Get similarity score (distance is already calculated by pgvector)
            // Lower distance = higher similarity, so we normalize it
            // Assuming distance is between 0-2 for cosine distance
            $similarityScore = $chunk->distance !== null 
                ? max(0, 1 - ($chunk->distance / 2)) // Normalize to 0-1 range
                : 0.5; // Default if distance not available

            // Calculate keyword matching score
            $keywordScore = $this->keywordScore($chunk->content, $question);

            // Calculate length score (prefer medium-length chunks)
            $lengthScore = $this->lengthScore(mb_strlen($chunk->content));

            // Combine scores with weights
            $combinedScore = ($similarityScore * 0.7) + ($keywordScore * 0.2) + ($lengthScore * 0.1);

            return [
                'chunk' => $chunk,
                'score' => $combinedScore,
                'similarity_score' => $similarityScore,
                'keyword_score' => $keywordScore,
                'length_score' => $lengthScore,
            ];
        })->sortByDesc('score');
    }

    /**
     * Calculate keyword matching score between chunk content and question
     * 
     * @param string $chunkContent The chunk text
     * @param string $question The user's question
     * @return float Score between 0 and 1
     */
    private function keywordScore(string $chunkContent, string $question): float
    {
        // Extract unique words from question and chunk (case-insensitive)
        $questionWords = array_unique(
            preg_split('/\s+/', mb_strtolower(trim($question)), -1, PREG_SPLIT_NO_EMPTY)
        );
        
        if (empty($questionWords)) {
            return 0.0;
        }

        $chunkWords = array_unique(
            preg_split('/\s+/', mb_strtolower(trim($chunkContent)), -1, PREG_SPLIT_NO_EMPTY)
        );

        // Count matching words
        $matches = count(array_intersect($questionWords, $chunkWords));
        
        // Return ratio of matched words
        return $matches > 0 ? min(1.0, $matches / count($questionWords)) : 0.0;
    }

    /**
     * Calculate length score - prefer chunks of optimal length
     * 
     * Optimal: 800-2000 characters (good balance of detail and context)
     * Acceptable: 400-2500 characters
     * Too short or too long: lower score
     * 
     * @param int $length Character length of the chunk
     * @return float Score between 0 and 1
     */
    private function lengthScore(int $length): float
    {
        // Prefer chunks between 800 and 2000 characters
        if ($length >= 800 && $length <= 2000) {
            return 1.0;
        } 
        // Acceptable range: 400-2500 characters
        elseif ($length > 400 && $length < 2500) {
            return 0.5;
        }
        // Too short or too long
        return 0.1;
    }

    /**
     * Select chunks from re-ranked list that fit within token limit
     * 
     * @param Collection $rerankedChunks Collection of re-ranked chunk arrays
     * @param int $maxContextTokens Maximum tokens available for context
     * @param int $reservedTokens Tokens reserved for system prompt, user messages, and response
     * @return Collection Collection of DocumentChunk models that fit within limit
     */
    public function selectChunksWithinTokenLimit(
        Collection $rerankedChunks,
        int $maxContextTokens,
        int $reservedTokens
    ): Collection {
        $selectedChunks = collect();
        $currentTokens = $reservedTokens;
        $separatorTokens = $this->tokenService->estimateTokens("\n\n---\n\n");

        foreach ($rerankedChunks as $item) {
            $chunk = $item['chunk'];
            $chunkTokens = $this->tokenService->estimateTokens($chunk->content);

            // Check if adding this chunk would exceed the limit
            if (($currentTokens + $chunkTokens + $separatorTokens) <= $maxContextTokens) {
                $selectedChunks->push($chunk);
                $currentTokens += $chunkTokens + $separatorTokens;
            } else {
                // Stop adding chunks if limit is reached
                break;
            }
        }

        return $selectedChunks;
    }
}
