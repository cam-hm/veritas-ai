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
      const assistantMessage = { role: 'assistant', content: '', thinking: false };
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
        // Check if response is OK before reading stream
        if (!response.ok) {
          return response.text().then(text => {
            let errorMessage = `HTTP request returned status code ${response.status}`;
            try {
              const errorData = JSON.parse(text);
              if (errorData.error || errorData.message) {
                errorMessage = errorData.error || errorData.message;
              }
            } catch (e) {
              // If response is not JSON, use the text as error message
              if (text) {
                errorMessage = text;
              }
            }
            throw new Error(errorMessage);
          });
        }
        
        const reader = response.body.getReader();
        let decoder = new TextDecoder();
        let buffer = '';
        let rafScheduled = false;
        let pendingUpdate = false;
        let lastUpdateTime = 0;
        // Optimized interval for smooth rendering without lag
        const UPDATE_INTERVAL = 16; // ~60fps for smooth character-by-character rendering
        
        const processChunk = ({ done, value }) => {
          if (done) {
            // Final update if there are pending changes
            if (pendingUpdate) {
              this.messages = [...this.messages];
            }
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
          buffer = lines.pop() || '';
          
          // Process chunks and accumulate updates
          for (let line of lines) {
            if (line.startsWith('data: ')) {
              let chunk = line.slice(6);
              try {
                let data = JSON.parse(chunk);
                
                // Handle thinking message
                if (data.type === 'thinking') {
                  const lastIndex = this.messages.length - 1;
                  this.messages[lastIndex].thinking = true;
                  this.messages[lastIndex].content = data.message || 'Đang xử lý...';
                  this.messages = [...this.messages];
                  continue;
                }
                
                // Handle ready message - clear thinking state
                if (data.type === 'ready') {
                  const lastIndex = this.messages.length - 1;
                  this.messages[lastIndex].thinking = false;
                  this.messages[lastIndex].content = '';
                  this.messages = [...this.messages];
                  continue;
                }
                
                if (data.error) {
                  console.error('Stream error:', data.error);
                  const lastIndex = this.messages.length - 1;
                  this.messages[lastIndex].content = `Error: ${data.error}`;
                  this.messages[lastIndex].thinking = false;
                  this.messages = [...this.messages];
                  this.isStreaming = false;
                  this.saveMessageToDB({ role: 'assistant', content: `Error: ${data.error}` });
                  return;
                }
                
                // Handle Ollama streaming response - accumulate content for smooth rendering
                if (data.message && data.message.content) {
                  const lastIndex = this.messages.length - 1;
                  this.messages[lastIndex].thinking = false;
                  this.messages[lastIndex].content += data.message.content;
                  pendingUpdate = true;
                }
                
                if (data.done) {
                  if (pendingUpdate) {
                    this.messages = [...this.messages];
                  }
                  this.isStreaming = false;
                  const assistantMsg = this.messages[this.messages.length - 1];
                  if (assistantMsg.role === 'assistant') {
                    this.saveMessageToDB(assistantMsg);
                  }
                  return;
                }
              } catch (e) {
                // Fallback: handle plain text chunks from Ollama
                if (chunk && chunk.trim()) {
                  const lastIndex = this.messages.length - 1;
                  this.messages[lastIndex].thinking = false;
                  this.messages[lastIndex].content += chunk;
                  pendingUpdate = true;
                }
              }
            }
          }
          
          // Optimized rendering: batch updates for smooth performance
          const now = performance.now();
          if (pendingUpdate && !rafScheduled) {
            if (now - lastUpdateTime >= UPDATE_INTERVAL) {
              // Update immediately if enough time has passed
              this.messages = [...this.messages];
              pendingUpdate = false;
              lastUpdateTime = now;
            } else {
              // Schedule RAF for next frame
              rafScheduled = true;
              requestAnimationFrame(() => {
                this.messages = [...this.messages];
                pendingUpdate = false;
                rafScheduled = false;
                lastUpdateTime = performance.now();
              });
            }
          }
          
          return reader.read().then(processChunk);
        };
        return reader.read().then(processChunk);
      }).catch((error) => {
        console.error('Stream error:', error);
        const errorMessage = error.message || 'Sorry, a connection error occurred.';
        const lastIndex = this.messages.length - 1;
        if (lastIndex >= 0 && this.messages[lastIndex].role === 'assistant') {
          this.messages[lastIndex].content = `Error: ${errorMessage}`;
          this.messages[lastIndex].thinking = false;
          this.messages = [...this.messages];
        } else {
          this.messages.push({ 
            role: 'assistant', 
            content: `Error: ${errorMessage}`,
            thinking: false 
          });
          this.messages = [...this.messages];
        }
        this.isStreaming = false;
        this.saveMessageToDB({ role: 'assistant', content: `Error: ${errorMessage}` });
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

