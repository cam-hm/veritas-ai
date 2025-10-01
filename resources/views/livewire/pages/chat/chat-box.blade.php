<div
    x-data
    x-init="
        let messageBox = document.getElementById('message-box');
        messageBox.scrollTop = messageBox.scrollHeight;
        const observer = new MutationObserver(() => {
            messageBox.scrollTop = messageBox.scrollHeight;
        });
        observer.observe(messageBox, { childList: true, subtree: true });
    "
    class="flex flex-col h-[80vh] bg-white rounded-lg shadow-sm"
>
    <div id="message-box" class="flex-1 p-6 overflow-y-auto">
        <div class="space-y-6">
            @foreach ($messages as $i => $message)
                <div wire:key="msg-{{ $i }}" class="flex items-start gap-4 @if($message['role'] === 'user') flex-row-reverse @endif">
                    <div class="w-10 h-10 rounded-full flex-shrink-0 @if($message['role'] === 'user') bg-indigo-500 @else bg-gray-300 @endif"></div>
                    <div class="p-4 rounded-lg max-w-lg @if($message['role'] === 'user') bg-indigo-50 text-indigo-900 @else bg-gray-100 text-gray-800 @endif">
                        <p @class(['text-sm prose', 'typing-indicator' => $message['content'] === ''])>
                            {!! nl2br(e($message['content'])) !!}
                        </p>
                        {{-- Optionally show a spinner if assistant is typing --}}
                        @if($message['role'] === 'assistant' && $message['content'] === '')
                            <span class="animate-pulse text-gray-400">Thinking...</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="p-4 border-t">
        <form wire:submit.prevent="ask" class="flex items-center gap-4">
            <x-text-input
                wire:model="question"
                class="flex-1"
                placeholder="Ask a question about the document..."
                autocomplete="off"
            />
            <x-primary-button type="submit">
                {{ __('Ask') }}
            </x-primary-button>
        </form>
    </div>

    <style>
      .typing-indicator::after {
        content: '▌';
        animation: blink 1s step-start infinite;
      }
      @keyframes blink { 50% { opacity: 0; } }
    </style>
</div>