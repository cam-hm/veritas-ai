<?php

namespace App\Services;

use Spatie\PdfToText\Pdf;
use Exception;
use PhpOffice\PhpWord\IOFactory; // 1. Import the main class from PHPWord

class TextExtractionService
{
    public function extract(string $filePath): string
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        switch (strtolower($extension)) {
            case 'pdf':
                return Pdf::getText($filePath);

            // 2. Add the new case for .docx files
            case 'docx':
                $phpWord = IOFactory::load($filePath);
                $text = '';
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if (method_exists($element, 'getText')) {
                            $text .= $element->getText() . ' ';
                        }
                    }
                }
                return $text;

            case 'txt':
            case 'md':
                return file_get_contents($filePath);

            default:
                throw new Exception("Unsupported file type: {$extension}");
        }
    }
}
