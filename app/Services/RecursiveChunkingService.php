<?php

namespace App\Services;

class RecursiveChunkingService
{
    /**
     * Splits a text into semantic chunks using a recursive approach with overlap.
     *
     * @param string $text The text to split.
     * @param int $chunkSize The target size of each chunk in characters.
     * @param int $overlap The number of characters to overlap between chunks (default: 200).
     * @return array An array of structured chunks, each with 'content' and 'metadata'.
     */
    public function chunk(string $text, int $chunkSize = 1500, int $overlap = 200): array
    {
        $text = trim($text);

        // Ensure overlap is reasonable (not more than 50% of chunk size)
        $overlap = min($overlap, (int)($chunkSize * 0.5));

        if (mb_strlen($text) <= $chunkSize) {
            return [$this->createChunk($text)];
        }

        // Define splitters from largest to smallest semantic unit
        $splitters = ["\n\n", "\n", ". ", " "];

        foreach ($splitters as $splitter) {
            $parts = explode($splitter, $text);
            if (count($parts) > 1) {
                // If splitting works, recursively process the parts
                return $this->recursivelyProcessParts($parts, $splitter, $chunkSize, $overlap);
            }
        }

        // If no splitters work, split by character length with overlap as last resort
        return $this->splitWithOverlap($text, $chunkSize, $overlap);
    }

    private function recursivelyProcessParts(array $parts, string $separator, int $chunkSize, int $overlap): array
    {
        $chunks = [];
        $currentChunk = '';
        $previousChunkEnd = ''; // Store the end of previous chunk for overlap

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            // If a single part is still too large, recursively chunk it further
            if (mb_strlen($part) > $chunkSize) {
                // If we have overlap from previous chunk, prepend it to the part before chunking
                $partToChunk = (!empty($previousChunkEnd) ? $previousChunkEnd . $separator : '') . $part;
                $subChunks = $this->chunk($partToChunk, $chunkSize, $overlap);
                
                if (!empty($subChunks)) {
                    // Add all sub-chunks
                    foreach ($subChunks as $subChunk) {
                        $chunks[] = $subChunk;
                    }
                    // Update previousChunkEnd from the last sub-chunk
                    $lastSubChunk = end($subChunks);
                    $previousChunkEnd = mb_substr($lastSubChunk['content'], -$overlap);
                }
                continue;
            }

            // Calculate if adding this part would exceed chunk size
            $potentialChunk = $currentChunk . (empty($currentChunk) ? '' : $separator) . $part;
            
            if (mb_strlen($potentialChunk) > $chunkSize) {
                if (!empty($currentChunk)) {
                    // Save the current chunk
                    $chunks[] = $this->createChunk($currentChunk);
                    // Store the end for overlap
                    $previousChunkEnd = mb_substr($currentChunk, -$overlap);
                }
                // Start new chunk with overlap from previous if available
                $currentChunk = (!empty($previousChunkEnd) ? $previousChunkEnd . $separator : '') . $part;
            } else {
                $currentChunk = $potentialChunk;
            }
        }

        if (!empty($currentChunk)) {
            $chunks[] = $this->createChunk($currentChunk);
        }

        return $chunks;
    }

    /**
     * Split text by character length with overlap (fallback method).
     */
    private function splitWithOverlap(string $text, int $chunkSize, int $overlap): array
    {
        $chunks = [];
        $length = mb_strlen($text);
        $start = 0;
        $previousEnd = '';

        while ($start < $length) {
            // Calculate chunk end position
            $chunkEnd = min($start + $chunkSize, $length);
            
            // Extract chunk
            $chunkContent = mb_substr($text, $start, $chunkEnd - $start);
            
            // Add overlap from previous chunk if available
            if (!empty($previousEnd) && $start > 0) {
                $chunkContent = $previousEnd . $chunkContent;
            }
            
            $chunks[] = $this->createChunk($chunkContent);
            
            // Store end of current chunk for next overlap
            $previousEnd = mb_substr($chunkContent, -$overlap);
            
            // Move start position (accounting for overlap)
            $start = $chunkEnd - $overlap;
            
            // Prevent infinite loop
            if ($start <= 0 || $start >= $length) {
                break;
            }
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