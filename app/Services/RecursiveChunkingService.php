<?php

namespace App\Services;

class RecursiveChunkingService
{
    /**
     * Splits a text into semantic chunks using a recursive approach.
     *
     * @param string $text The text to split.
     * @param int $chunkSize The target size of each chunk in characters.
     * @return array An array of text chunks.
     */
    public function chunk(string $text, int $chunkSize = 1000): array
    {
        // 1. Basic text cleanup
        $text = trim($text);

        // If the text is already small enough, return it as a single chunk
        if (mb_strlen($text) <= $chunkSize) {
            return [$text];
        }

        // 2. Start by splitting into paragraphs
        $paragraphs = explode("\n\n", $text);
        $chunks = [];
        $currentChunk = '';

        // 3. Group paragraphs into chunks of the desired size
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) continue;

            // If a single paragraph is too large, recursively split it by sentences
            if (mb_strlen($paragraph) > $chunkSize) {
                $chunks = array_merge($chunks, $this->splitBySentence($paragraph, $chunkSize));
                continue;
            }

            // If adding the next paragraph makes the current chunk too big,
            // save the current chunk and start a new one.
            if (mb_strlen($currentChunk) + mb_strlen($paragraph) > $chunkSize) {
                if (!empty($currentChunk)) {
                    $chunks[] = $currentChunk;
                }
                $currentChunk = $paragraph;
            } else {
                $currentChunk .= "\n\n" . $paragraph;
            }
        }

        // Add the final remaining chunk
        if (!empty($currentChunk)) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    /**
     * A helper function to split a text by sentences if a paragraph is too long.
     */
    private function splitBySentence(string $text, int $chunkSize): array
    {
        $sentences = preg_split('/(?<=[.?!])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $chunks = [];
        $currentChunk = '';

        foreach ($sentences as $sentence) {
            if (mb_strlen($currentChunk) + mb_strlen($sentence) > $chunkSize) {
                if (!empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);
                }
                $currentChunk = $sentence;
            } else {
                $currentChunk .= ' ' . $sentence;
            }
        }

        if (!empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }
}