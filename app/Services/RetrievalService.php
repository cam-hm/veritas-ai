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
     * Re-rank chunks by multiple factors for better relevance
     * 
     * @param Collection $chunks Collection of DocumentChunk models
     * @param string $query The user's query text
     * @param float $similarityWeight Weight for vector similarity (default: 0.7)
     * @param float $keywordWeight Weight for keyword matching (default: 0.2)
     * @param float $lengthWeight Weight for chunk length (default: 0.1)
     * @return Collection Re-ranked chunks with scores
     */
    public function rerankChunks(
        Collection $chunks,
        string $query,
        float $similarityWeight = 0.7,
        float $keywordWeight = 0.2,
        float $lengthWeight = 0.1
    ): Collection {
        if ($chunks->isEmpty()) {
            return collect();
        }

        // Normalize query to lowercase for keyword matching
        $queryLower = mb_strtolower($query);
        $queryWords = $this->extractKeywords($queryLower);

        return $chunks->map(function ($chunk) use ($queryLower, $queryWords, $similarityWeight, $keywordWeight, $lengthWeight) {
            // 1. Vector similarity score (from nearestNeighbors, stored as 'distance')
            // Distance is cosine distance (0 = identical, 2 = opposite)
            // Convert to similarity score (0-1, where 1 is most similar)
            $distance = $chunk->distance ?? 2.0; // Default to worst if no distance
            $similarityScore = max(0, 1 - ($distance / 2)); // Normalize to 0-1

            // 2. Keyword matching score
            $keywordScore = $this->calculateKeywordScore($chunk->content, $queryLower, $queryWords);

            // 3. Length score (prefer medium-length chunks, not too short or too long)
            $lengthScore = $this->calculateLengthScore(mb_strlen($chunk->content));

            // Combined weighted score
            $combinedScore = ($similarityScore * $similarityWeight) 
                           + ($keywordScore * $keywordWeight) 
                           + ($lengthScore * $lengthWeight);

            return [
                'chunk' => $chunk,
                'score' => $combinedScore,
                'similarity_score' => $similarityScore,
                'keyword_score' => $keywordScore,
                'length_score' => $lengthScore,
            ];
        })->sortByDesc('score')->values();
    }

    /**
     * Extract keywords from query (remove stop words, get meaningful terms)
     * 
     * @param string $query
     * @return array Array of keywords
     */
    private function extractKeywords(string $query): array
    {
        // Simple stop words list (can be expanded)
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'what', 'where', 'when', 'why', 'how', 'this', 'that', 'these', 'those'];
        
        // Split into words
        $words = preg_split('/\s+/', $query);
        
        // Filter out stop words and short words
        return array_filter($words, function ($word) use ($stopWords) {
            $word = trim($word, '.,!?;:()[]{}"\'-');
            return !empty($word) 
                && mb_strlen($word) >= 3 
                && !in_array(mb_strtolower($word), $stopWords);
        });
    }

    /**
     * Calculate keyword matching score (0-1)
     * 
     * @param string $content Chunk content
     * @param string $queryLower Lowercase query
     * @param array $queryWords Extracted keywords
     * @return float Score between 0 and 1
     */
    private function calculateKeywordScore(string $content, string $queryLower, array $queryWords): float
    {
        if (empty($queryWords)) {
            return 0.5; // Neutral score if no keywords
        }

        $contentLower = mb_strtolower($content);
        $matches = 0;
        $totalMatches = 0;

        foreach ($queryWords as $word) {
            // Count occurrences
            $count = mb_substr_count($contentLower, $word);
            if ($count > 0) {
                $matches++;
                $totalMatches += min($count, 5); // Cap at 5 per word to avoid spam
            }
        }

        // Score based on: (1) how many keywords matched, (2) frequency
        $keywordMatchRatio = count($queryWords) > 0 ? $matches / count($queryWords) : 0;
        $frequencyScore = min($totalMatches / 10, 1.0); // Normalize frequency

        // Combined: 70% match ratio, 30% frequency
        return ($keywordMatchRatio * 0.7) + ($frequencyScore * 0.3);
    }

    /**
     * Calculate length score (prefer chunks of optimal length)
     * 
     * @param int $length Chunk length in characters
     * @return float Score between 0 and 1
     */
    private function calculateLengthScore(int $length): float
    {
        // Optimal chunk length is around 1000-2000 characters
        $optimalMin = 800;
        $optimalMax = 2000;
        
        if ($length < 100) {
            // Too short - penalize
            return 0.3;
        } elseif ($length >= $optimalMin && $length <= $optimalMax) {
            // Optimal range - full score
            return 1.0;
        } elseif ($length < $optimalMin) {
            // Below optimal - linear decrease
            return 0.3 + (($length - 100) / ($optimalMin - 100)) * 0.7;
        } else {
            // Above optimal - gradual decrease
            $excess = $length - $optimalMax;
            return max(0.5, 1.0 - ($excess / 2000));
        }
    }

    /**
     * Select chunks with diversity (avoid chunks from same section)
     * 
     * @param Collection $rerankedChunks Re-ranked chunks with scores
     * @param int $maxChunks Maximum number of chunks to return
     * @param int $minDistance Minimum character distance between chunks (for diversity)
     * @return Collection Selected chunks
     */
    public function selectDiverseChunks(Collection $rerankedChunks, int $maxChunks, int $minDistance = 5000): Collection
    {
        $selected = collect();
        $selectedPositions = []; // Track positions of selected chunks for diversity

        foreach ($rerankedChunks as $item) {
            if ($selected->count() >= $maxChunks) {
                break;
            }

            $chunk = $item['chunk'];
            
            // For now, we don't have position metadata, so we'll just take top chunks
            // In the future, when metadata is added, we can check chunk positions
            $selected->push($chunk);
        }

        return $selected;
    }

    /**
     * Select chunks that fit within token limit with re-ranking
     * 
     * @param Collection $rerankedChunks Re-ranked chunks
     * @param int $maxTokens Maximum tokens available
     * @param int $reservedTokens Tokens already reserved
     * @return Collection Selected chunks that fit
     */
    public function selectChunksWithinTokenLimit(
        Collection $rerankedChunks,
        int $maxTokens,
        int $reservedTokens = 0
    ): Collection {
        $availableTokens = $maxTokens - $reservedTokens;
        $selected = collect();
        $usedTokens = 0;
        $separatorTokens = $this->tokenService->estimateTokens("\n\n---\n\n");

        foreach ($rerankedChunks as $item) {
            $chunk = $item['chunk'];
            $chunkTokens = $this->tokenService->estimateTokens($chunk->content);

            if ($usedTokens + $chunkTokens + $separatorTokens > $availableTokens) {
                break;
            }

            $selected->push($chunk);
            $usedTokens += $chunkTokens + $separatorTokens;
        }

        return $selected;
    }
}

