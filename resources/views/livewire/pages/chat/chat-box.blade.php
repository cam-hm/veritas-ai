<div
    x-data="chatBoxComponent({{ json_encode($messages) }}, {{ $document?->id ?? 'null' }}, '{{ route('chat.stream') }}', '{{ csrf_token() }}')"
    x-init="
        let messageBox = $refs.messageBox;
        let rafId = null;
        let scrollTimeout = null;
        let lastScrollTime = 0;
        const SCROLL_THROTTLE = 16; // ~60fps
        
        let observer = new MutationObserver(() => {
            // Throttle scroll updates to ~60fps for smoother performance
            const now = performance.now();
            if (now - lastScrollTime < SCROLL_THROTTLE) {
                return; // Skip if too soon
            }
            
            // Cancel pending scrolls
            if (rafId) cancelAnimationFrame(rafId);
            if (scrollTimeout) clearTimeout(scrollTimeout);
            
            // Use RAF for smooth scrolling
            rafId = requestAnimationFrame(() => {
                messageBox.scrollTop = messageBox.scrollHeight;
                lastScrollTime = performance.now();
            });
        });
        // Only observe childList changes (new messages) for better performance
        // Remove characterData observation to reduce overhead
        observer.observe(messageBox, { 
            childList: true, 
            subtree: false  // Only direct children, not deep subtree
        });
    "
    class="flex flex-col h-[80vh] bg-white rounded-lg shadow-sm"
>
    <div x-ref="messageBox" class="flex-1 p-6 overflow-y-auto">
        <div class="space-y-6">
            <template x-for="(message, index) in messages" :key="index">
                <div class="flex items-start gap-4" :class="{ 'flex-row-reverse': message.role === 'user' }">
                    <div class="w-10 h-10 rounded-full flex-shrink-0" :class="{ 'bg-indigo-500': message.role === 'user', 'bg-gray-300': message.role === 'assistant' }"></div>
                    <div class="p-4 rounded-lg max-w-lg" :class="{ 'bg-indigo-50 text-indigo-900': message.role === 'user', 'bg-gray-100 text-gray-800': message.role === 'assistant' }">
                        <p class="text-sm prose whitespace-pre-wrap break-words" 
                           :class="{ 
                               'typing-indicator': (message.content === '' || message.thinking) && isStreaming,
                               'text-gray-500 italic': message.thinking
                           }" 
                           x-text="message.content || ''"></p>
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
