<?php

namespace App\Services;

class RecursiveChunkingService
{
    /**
     * Splits a text into semantic chunks using a recursive approach.
     *
     * @param string $text The text to split.
     * @param int $chunkSize The target size of each chunk in characters.
     * @return array An array of structured chunks, each with 'content' and 'metadata'.
     */
    public function chunk(string $text, int $chunkSize = 1500): array
    {
        $text = trim($text);

        if (mb_strlen($text) <= $chunkSize) {
            return [$this->createChunk($text)];
        }

        // Define splitters from largest to smallest semantic unit
        $splitters = ["\n\n", "\n", ". ", " "];

        foreach ($splitters as $splitter) {
            $parts = explode($splitter, $text);
            if (count($parts) > 1) {
                // If splitting works, recursively process the parts
                return $this->recursivelyProcessParts($parts, $splitter, $chunkSize);
            }
        }

        // If no splitters work, just split by character length as a last resort
        return str_split($text, $chunkSize);
    }

    private function recursivelyProcessParts(array $parts, string $separator, int $chunkSize): array
    {
        $chunks = [];
        $currentChunk = '';

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            // If a single part is still too large, recursively chunk it further
            if (mb_strlen($part) > $chunkSize) {
                $chunks = array_merge($chunks, $this->chunk($part, $chunkSize));
                continue;
            }

            if (mb_strlen($currentChunk) + mb_strlen($separator . $part) > $chunkSize) {
                if (!empty($currentChunk)) {
                    $chunks[] = $this->createChunk($currentChunk);
                }
                $currentChunk = $part;
            } else {
                $currentChunk .= (empty($currentChunk) ? '' : $separator) . $part;
            }
        }

        if (!empty($currentChunk)) {
            $chunks[] = $this->createChunk($currentChunk);
        }

        return $chunks;
    }

    private function createChunk(string $content): array
    {
        return [
            'content' => $content,
            // We can add more metadata here in the future
            'metadata' => [
                'length' => mb_strlen($content),
            ],
        ];
    }
}