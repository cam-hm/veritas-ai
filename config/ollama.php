<?php
return [
    'base' => env('OLLAMA_BASE', 'http://127.0.0.1:11434'),
    'default_model' => env('OLLAMA_GEN_MODEL', 'llama3.1'),
    'embed_model'   => env('OLLAMA_EMBED_MODEL', 'nomic-embed-text'),
    'timeout' => env('OLLAMA_TIMEOUT', 60),
    'retries' => env('OLLAMA_RETRIES', 1),
    'mask_pii' => true,
    'pii_patterns' => [
        '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
        '/\b\d{10,}\b/',
        '/(sk|pk|token|secret)[^\s]*/i',
    ],
];
