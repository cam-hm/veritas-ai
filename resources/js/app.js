import './bootstrap';

// Expose the factory on the global window to ensure Alpine can resolve it in x-data
window.chatBoxComponent = function(initialMessages, documentId, streamUrl, csrfToken) {
  // Normalize the incoming documentId to either a finite number or null
  const normalizedDocumentId = (documentId === null || documentId === undefined || documentId === 'null')
    ? null
    : (Number.isFinite(Number(documentId)) ? Number(documentId) : null);

  return {
    messages: Array.isArray(initialMessages) ? initialMessages : [],
    question: '',
    isStreaming: false,
    // Expose normalized id for internal use
    documentId: normalizedDocumentId,
    ask() {
      if (this.question.trim() === '' || this.isStreaming) return;

      this.isStreaming = true;
      // Add user message to UI and DB
      const userMessage = { role: 'user', content: this.question };
      this.messages.push(userMessage);
      this.saveMessageToDB(userMessage);

      // Add assistant placeholder to UI only (do NOT save to DB yet)
      const assistantMessage = { role: 'assistant', content: '' };
      this.messages.push(assistantMessage);

      fetch(streamUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          document_id: this.documentId,
          messages: this.messages,
        }),
      }).then(response => {
        const reader = response.body.getReader();
        let decoder = new TextDecoder();
        let buffer = '';
        const processChunk = ({ done, value }) => {
          if (done) {
            this.isStreaming = false;
            // Store assistant message only once after streaming is done
            const assistantMsg = this.messages[this.messages.length - 1];
            if (assistantMsg.role === 'assistant') {
              this.saveMessageToDB(assistantMsg);
            }
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
                  this.saveMessageToDB({ role: 'assistant', content: `Error: ${data.error}` });
                  return;
                }
                if (data.message && data.message.content) {
                  this.messages[this.messages.length - 1].content += data.message.content;
                  // Do NOT save assistant chunk to DB here
                }
                if (data.done) {
                  this.isStreaming = false;
                  // Store assistant message only once after streaming is done
                  const assistantMsg = this.messages[this.messages.length - 1];
                  if (assistantMsg.role === 'assistant') {
                    this.saveMessageToDB(assistantMsg);
                  }
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
        this.saveMessageToDB({ role: 'assistant', content: 'Sorry, a connection error occurred.' });
      });

      this.question = '';
    },
    saveMessageToDB(message) {
      // Use $wire to call Livewire method (Alpine context)
      if (this.$wire) {
        this.$wire.saveMessage(message.content, message.role);
        console.log('Called Livewire saveMessage:', message);
      } else {
        console.error('Livewire $wire not available in Alpine context');
      }
    }
  }
}

