<div
    x-data="chatBoxComponent({{ json_encode($messages) }}, {{ $document->id }}, '{{ route('chat.stream') }}', '{{ csrf_token() }}')"
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

<script>
  function chatBoxComponent(initialMessages, documentId, streamUrl, csrfToken) {
    return {
      messages: initialMessages,
      question: '',
      isStreaming: false,
      ask() {
        if (this.question.trim() === '' || this.isStreaming) return;

        this.isStreaming = true;
        this.messages.push({ role: 'user', content: this.question });
        this.messages.push({ role: 'assistant', content: '' });

        fetch(streamUrl, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            document_id: documentId,
            messages: this.messages,
          }),
        }).then(response => {
          const reader = response.body.getReader();
          let decoder = new TextDecoder();
          let buffer = '';
          const processChunk = ({ done, value }) => {
            if (done) {
              this.isStreaming = false;
              return;
            }
            buffer += decoder.decode(value, { stream: true });
            let lines = buffer.split('\n\n');
            buffer = lines.pop();
            for (let line of lines) {
              if (line.startsWith('data: ')) {
                let chunk = line.slice(6);
                try {
                  let data = JSON.parse(chunk);
                  if (data.error) {
                    this.messages[this.messages.length - 1].content = `Error: ${data.error}`;
                    this.isStreaming = false;
                    return;
                  }
                  if (data.message && data.message.content) {
                    this.messages[this.messages.length - 1].content += data.message.content;
                  }
                  if (data.done) {
                    this.isStreaming = false;
                    return;
                  }
                } catch {
                  // Fallback for non-json chunks
                }
              }
            }
            return reader.read().then(processChunk);
          };
          return reader.read().then(processChunk);
        }).catch(() => {
          this.messages[this.messages.length - 1].content = 'Sorry, a connection error occurred.';
          this.isStreaming = false;
        });

        this.question = '';
      }
    }
  }
</script>