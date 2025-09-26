<?php

namespace App\Services;

class TextChunkingService
{
    /**
     * Splits a text into smaller, overlapping chunks.
     *
     * @param string $text The text to split.
     * @param int $chunkSize The approximate size of each chunk in characters.
     * @param int $overlapSize The number of characters to overlap between chunks.
     * @return array An array of text chunks.
     */
    public function chunk(string $text, int $chunkSize = 1000, int $overlapSize = 200): array
    {
        $chunks = [];
        $length = mb_strlen($text);
        $start = 0;

        while ($start < $length) {
            $end = min($start + $chunkSize, $length);
            $chunks[] = mb_substr($text, $start, $end - $start);

            $start += $chunkSize - $overlapSize;

            if ($start + $overlapSize >= $length) {
                break;
            }
        }

        return $chunks;
    }
}