<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Camh\Ollama\Facades\Ollama;

class OllamaTestStream extends Command
{
    protected $signature = 'ollama:test-stream';
    protected $description = 'A simple command to test Ollama streaming functionality.';

    public function handle(): int
    {
        $prompt = 'Tell me a short, 50-word story about a robot who discovers music.';

        $this->info("Sending a simple streaming request to Ollama...");
        $this->line("Prompt: {$prompt}\n");
        $this->line("--- Response ---");

        try {
            Ollama::stream($prompt, function ($chunk) {
                // Decode the JSON chunk from the stream
                $data = json_decode($chunk, true);

                // Check if the 'response' key exists and print it
                if (isset($data['response'])) {
                    $this->output->write($data['response']);
                }

                // If the stream is done, we can add a newline
                if (isset($data['done']) && $data['done'] === true) {
                    $this->output->newLine();
                }
            });
        } catch (\Exception $e) {
            $this->error("\n\nAn error occurred during streaming: " . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("\n\nStream finished.");
        return self::SUCCESS;
    }
}