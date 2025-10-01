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
        $this->messages[] = ['role' => 'assistant', 'content' => "Hello! How can I help you with the document '{$this->document->name}'?"];
    }

    public function render()
    {
        return view('livewire.pages.chat.chat-box');
    }
}