<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Document;

class ChatBox extends Component
{
    public Document $document;
    public array $messages = [];

    public function mount(Document $document): void
    {
        $this->document = $document;
        // Fetch all chat messages for this document, ordered by creation time
        $this->messages = $document->chatMessages()->orderBy('created_at')->get(['role', 'content'])->toArray();
        // If no messages, add default assistant greeting
        if (empty($this->messages)) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => "Hello! How can I help you with the document '{$this->document->name}'?"
            ];
        }
    }

    public function saveMessage(string $content, string $role)
    {
        // Validate the role to ensure it's either 'user' or 'assistant'
        if (!in_array($role, ['user', 'assistant'])) {
            return;
        }

        $this->document->chatMessages()->create([
            'user_id' => auth()->id(),
            'role' => $role,
            'content' => $content,
        ]);
    }

    public function render()
    {
        return view('livewire.pages.chat.chat-box');
    }
}