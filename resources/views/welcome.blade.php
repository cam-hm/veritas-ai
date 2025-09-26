<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ollama Stream</title>
</head>
<body>
<form id="chat-form">
    @csrf
    <input type="text" id="prompt" placeholder="Enter your prompt..." />
    <button type="submit">Send</button>
</form>
<div id="response"></div>

<script>
  document.getElementById('chat-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const promptInput = document.getElementById('prompt');
    const responseDiv = document.getElementById('response');
    responseDiv.innerHTML = '';

    // Construct the URL with the prompt as a query parameter
    const url = '/chat/stream?prompt=' + encodeURIComponent(promptInput.value);

    // EventSource only supports GET, so we remove the POST options
    const eventSource = new EventSource(url);

    eventSource.onmessage = function (event) {
      const data = JSON.parse(event.data);
      if (data.response) {
        responseDiv.innerHTML += data.response;
      }
      // Check the 'done' flag to close the connection
      if (data.done) {
        eventSource.close();
      }
    };

    eventSource.onerror = function (err) {
      console.error("EventSource failed:", err);
      responseDiv.innerHTML += '<br><strong>Error receiving response.</strong>';
      eventSource.close();
    };

    // Clear the input after sending
    promptInput.value = '';
  });
</script>
</body>
</html>