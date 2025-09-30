<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Document;
use App\Models\DocumentChunk;
use Camh\Ollama\Facades\Ollama;
use Pgvector\Laravel\Distance;
use Camh\Ollama\Support\Conversation;

class VeritasChat extends Command
{
    protected $signature = 'veritas:chat {--doc=}';
    protected $description = 'Start an interactive chat session with context from your documents.';

    public function handle(): int
    {
        $documentId = $this->option('doc');
        $document = null;

        if ($documentId) {
            $document = Document::find($documentId);
            if (!$document) {
                $this->error("No document found with ID: {$documentId}");
                return self::FAILURE;
            }
            $this->info("Starting chat session about document: '{$document->name}'.");
        } else {
            $this->info("Starting chat session across all documents.");
        }

        $this->comment("Type 'exit' to end the conversation.");

        // Initialize a new conversation
        $conversation = new Conversation('You are a helpful AI assistant.');

        while (true) {
            $question = $this->ask('You');

            if ($question === null || strtolower($question) === 'exit') {
                $this->info('Chat session ended.');
                break;
            }

            // --- RAG Process ---
            $questionEmbedding = Ollama::embed($question);

            $query = DocumentChunk::query();
            if ($documentId) {
                $query->where('document_id', $documentId);
            }

            $relevantChunks = $query
                ->nearestNeighbors('embedding', $questionEmbedding, Distance::Cosine)
                ->get();

            $context = $relevantChunks->pluck('content')->implode("\n\n---\n\n");

            // --- Prompt Construction ---
            $prompt = "
                Based *only* on the following context, please answer the user's question.
                If the context does not contain the answer, say 'I do not know based on the provided context.'

                Context:
                {$context}
            ";

            // Add the user's question to the main conversation history
            $conversation->addUserMessage($question);

            // Create a temporary message list for this specific turn, including the RAG context
            $messagesForThisTurn = $conversation->getMessages();
            array_unshift($messagesForThisTurn, ['role' => 'system', 'content' => $prompt]);

            // --- AI Response ---
            $this->output->write('AI: ');
            $fullResponse = '';

            // We use Ollama::chat here and manually build the message array
            // to inject our RAG context for this turn.
            $stream = Ollama::chat($messagesForThisTurn, ['stream' => true]);

            foreach ($stream as $chunk) {
                $decodedChunk = json_decode($chunk, true);
                if (isset($decodedChunk['message']['content'])) {
                    $content = $decodedChunk['message']['content'];
                    $fullResponse .= $content;
                    $this->output->write($content);
                }
            }

            // Add the AI's full response to the conversation history for the next turn
            $conversation->addAssistantMessage($fullResponse);

            $this->output->newLine();
        }

        return self::SUCCESS;
    }
}
