<div
    x-data="chatBoxComponent({{ json_encode($messages) }}, {{ $document?->id ?? 'null' }}, '{{ route('chat.stream') }}', '{{ csrf_token() }}')"
    x-init="
        let messageBox = $refs.messageBox;
        let observer = new MutationObserver(() => {
            messageBox.scrollTop = messageBox.scrollHeight;
        });
        observer.observe(messageBox, { childList: true, subtree: true });
    "
    class="flex flex-col h-[80vh] bg-white rounded-lg shadow-sm"
>
    <div x-ref="messageBox" class="flex-1 p-6 overflow-y-auto">
        <div class="space-y-6">
            <template x-for="(message, index) in messages" :key="index">
                <div class="flex items-start gap-4" :class="{ 'flex-row-reverse': message.role === 'user' }">
                    <div class="w-10 h-10 rounded-full flex-shrink-0" :class="{ 'bg-indigo-500': message.role === 'user', 'bg-gray-300': message.role === 'assistant' }"></div>
                    <div class="p-4 rounded-lg max-w-lg" :class="{ 'bg-indigo-50 text-indigo-900': message.role === 'user', 'bg-gray-100 text-gray-800': message.role === 'assistant' }">
                        <p class="text-sm prose" :class="{ 'typing-indicator': message.content === '' && isStreaming }" x-html="message.content ? message.content.replace(/\n/g, '<br>') : ''"></p>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div class="p-4 border-t">
        <form @submit.prevent="ask" class="flex items-center gap-4">
            <x-text-input x-model="question" x-bind:disabled="isStreaming" class="flex-1" placeholder="Ask a question..." autocomplete="off" />
            <x-primary-button type="submit" x-bind:disabled="isStreaming">
                <span x-show="!isStreaming">{{ __('Ask') }}</span>
                <span x-show="isStreaming">Thinking...</span>
            </x-primary-button>
        </form>
    </div>

    <style>
      .typing-indicator::after { content: '▌'; animation: blink 1s step-start infinite; }
      @keyframes blink { 50% { opacity: 0; } }
    </style>
</div>
