<?php

namespace App\Services;

class TokenEstimationService
{
    /**
     * Estimate token count from text.
     * 
     * Rough estimation: ~4 characters per token for English text
     * This is a simple heuristic - for more accuracy, use a proper tokenizer
     * 
     * @param string $text The text to estimate tokens for
     * @return int Estimated token count
     */
    public function estimateTokens(string $text): int
    {
        if (empty(trim($text))) {
            return 0;
        }

        // Simple estimation: ~4 characters per token for English
        // This is a conservative estimate (actual may be 3-5 chars per token)
        $charCount = mb_strlen($text);
        return (int) ceil($charCount / 4);
    }

    /**
     * Estimate tokens for multiple text chunks
     * 
     * @param array|string $texts Single text or array of texts
     * @return int|array Estimated token count(s)
     */
    public function estimateTokensFor($texts)
    {
        if (is_array($texts)) {
            return array_map([$this, 'estimateTokens'], $texts);
        }
        
        return $this->estimateTokens($texts);
    }

    /**
     * Check if adding text would exceed token limit
     * 
     * @param int $currentTokens Current token count
     * @param string $textToAdd Text to potentially add
     * @param int $maxTokens Maximum allowed tokens
     * @return bool True if adding would exceed limit
     */
    public function wouldExceedLimit(int $currentTokens, string $textToAdd, int $maxTokens): bool
    {
        $additionalTokens = $this->estimateTokens($textToAdd);
        return ($currentTokens + $additionalTokens) > $maxTokens;
    }
}

