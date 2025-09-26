<?php

namespace App\Services;

use Spatie\PdfToText\Pdf;
use Exception;

class TextExtractionService
{
    public function extract(string $filePath): string
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        switch ($extension) {
            case 'pdf':
                return Pdf::getText($filePath);
            case 'txt':
            case 'md':
                return file_get_contents($filePath);
            // Add cases for other file types like .doc, .docx later
            default:
                throw new Exception("Unsupported file type: {$extension}");
        }
    }
}