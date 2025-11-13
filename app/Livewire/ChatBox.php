<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Document;

class ChatBox extends Component
{
    public ?Document $document = null;
    public array $messages = [];

    public function mount(Document $document = null): void
    {
        $this->document = $document;

        if ($this->document->id) {
            // Fetch all chat messages for this document, ordered by creation time
            $this->messages = $this->document->chatMessages()->orderBy('created_at')->get(['role', 'content'])->toArray();
            if (empty($this->messages)) {
                $this->messages[] = [
                    'role' => 'assistant',
                    'content' => "Hello! How can I help you with the document '{$this->document->name}'?"
                ];
            }
        } else {
            // General chat: load messages without a document (per user)
            $this->messages = \App\Models\ChatMessage::query()
                ->whereNull('document_id')
                ->where('user_id', auth()->id())
                ->orderBy('created_at')
                ->get(['role', 'content'])
                ->toArray();
            if (empty($this->messages)) {
                $this->messages[] = [
                    'role' => 'assistant',
                    'content' => "Hello! This is the general chat. Ask me anything about your documents."
                ];
            }
        }
    }

    public function saveMessage(string $content, string $role)
    {
        // Validate the role to ensure it's either 'user' or 'assistant'
        if (!in_array($role, ['user', 'assistant'])) {
            return;
        }

        if ($this->document) {
            $this->document->chatMessages()->create([
                'user_id' => auth()->id(),
                'role' => $role,
                'content' => $content,
            ]);
        } else {
            \App\Models\ChatMessage::create([
                'document_id' => null,
                'user_id' => auth()->id(),
                'role' => $role,
                'content' => $content,
            ]);
        }
    }

    public function render()
    {
        return view('livewire.pages.chat.chat-box');
    }
}