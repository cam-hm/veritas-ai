<?php
return [
    'base' => env('OLLAMA_BASE', 'http://127.0.0.1:11434'),
    'default_model' => env('OLLAMA_GEN_MODEL', 'llama3.1'),
    'embed_model'   => env('OLLAMA_EMBED_MODEL', 'nomic-embed-text'),
    'timeout' => env('OLLAMA_TIMEOUT', 60),
    'retries' => env('OLLAMA_RETRIES', 3),
    'retry_delay' => env('OLLAMA_RETRY_DELAY', 1.0), // seconds
    'embed_batch_size' => env('OLLAMA_EMBED_BATCH_SIZE', 10), // chunks per batch
    'embed_concurrency' => env('OLLAMA_EMBED_CONCURRENCY', 5), // concurrent requests
    'mask_pii' => true,
    'pii_patterns' => [
        '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
        '/\b\d{10,}\b/',
        '/(sk|pk|token|secret)[^\s]*/i',
    ],
];
