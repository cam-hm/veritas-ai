<?php

namespace App\Services;

class SentenceChunkingService
{
    /**
     * Splits a text into semantic chunks based on paragraphs.
     *
     * @param string $text The text to split.
     * @param int $chunkSize The approximate size of each chunk in characters.
     * @return array An array of text chunks.
     */
    public function chunk(string $text, int $chunkSize = 1000): array
    {
        // 1. Pre-process the text: remove extra whitespace and newlines
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // If the text is smaller than the chunk size, no need to split.
        if (mb_strlen($text) <= $chunkSize) {
            return [$text];
        }

        // 2. Split the text into sentences. This is a simple but effective way.
        // A more advanced method would be to split by paragraphs.
        $sentences = preg_split('/(?<=[.?!])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $chunks = [];
        $currentChunk = '';

        // 3. Group sentences into chunks of the desired size.
        foreach ($sentences as $sentence) {
            if (mb_strlen($currentChunk) + mb_strlen($sentence) > $chunkSize) {
                $chunks[] = trim($currentChunk);
                $currentChunk = '';
            }
            $currentChunk .= $sentence . ' ';
        }

        // Add the last remaining chunk
        if (!empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }
}
