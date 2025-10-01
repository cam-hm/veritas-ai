<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Document;
use App\Models\DocumentChunk;
use Camh\Ollama\Facades\Ollama;
use Pgvector\Laravel\Distance;
use Exception;

class ChatBox extends Component
{
    public Document $document;
    public array $messages = [];
    public string $question = '';

    public function mount(Document $document): void
    {
        $this->document = $document;
        $this->messages[] = ['role' => 'assistant', 'content' => "Hello! How can I help you with the document '{$this->document->name}'?"];
    }

    public function ask(): void
    {
        $trimmedQuestion = trim($this->question);
        if (empty($trimmedQuestion)) {
            return;
        }

        $this->messages[] = ['role' => 'user', 'content' => $trimmedQuestion];
        $this->messages[] = ['role' => 'assistant', 'content' => ''];
        $this->reset('question');

        try {
            $questionEmbedding = Ollama::embed($trimmedQuestion);

            $relevantChunks = DocumentChunk::query()
                ->where('document_id', $this->document->id)
                ->nearestNeighbors('embedding', $questionEmbedding, Distance::Cosine, 3)
                ->get();

            $context = $relevantChunks->pluck('content')->implode("\n\n---\n\n");

            $prompt = "
                Based *only* on the following context, please answer the question.
                If the context does not contain the answer, say 'I do not know based on the provided context.'

                Context:
                {$context}

                Question:
                {$trimmedQuestion}
            ";

            // ** THE CHANGE IS HERE: Use non-streaming generate() **
            $response = Ollama::generate($prompt);

            // Update the placeholder message with the full response
            $this->messages[count($this->messages) - 1]['content'] = $response;

        } catch (Exception $e) {
            $this->messages[count($this->messages) - 1]['content'] = "Sorry, an error occurred: " . $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.pages.chat.chat-box');
    }
}