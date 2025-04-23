<?php
function renderAIChat($moduleId) {
    ?>
    <div class="ai-chat-container">
        <div class="ai-chat-header" onclick="toggleAIChat()">
            <i class="bi bi-robot"></i>
            <span>AI-assistent</span>
        </div>
        <div class="ai-chat-body" id="aiChatBody">
            <div class="ai-messages" id="aiMessages"></div>
            <div class="ai-input-container">
                <input type="text" class="ai-input" id="aiInput" placeholder="Ställ en fråga...">
                <button class="ai-send-btn" onclick="sendAIMessage()">
                    <i class="bi bi-send"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
    function toggleAIChat() {
        const chatBody = document.getElementById('aiChatBody');
        chatBody.classList.toggle('active');
    }

    function sendAIMessage() {
        const input = document.getElementById('aiInput');
        const message = input.value.trim();
        if (!message) return;

        // Add user message
        addMessage(message, 'user');
        input.value = '';

        // Show typing indicator
        const typingIndicator = document.createElement('div');
        typingIndicator.className = 'ai-typing';
        typingIndicator.innerHTML = `
            <div class="ai-typing-dot"></div>
            <div class="ai-typing-dot"></div>
            <div class="ai-typing-dot"></div>
        `;
        document.getElementById('aiMessages').appendChild(typingIndicator);

        // Send to server
        fetch('ai_chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                module_id: <?= $moduleId ?>,
                message: message
            })
        })
        .then(response => response.json())
        .then(data => {
            // Remove typing indicator
            typingIndicator.remove();
            // Add AI response
            addMessage(data.response, 'assistant');
        })
        .catch(error => {
            console.error('Error:', error);
            typingIndicator.remove();
            addMessage('Ett fel uppstod. Försök igen senare.', 'assistant');
        });
    }

    function addMessage(content, type) {
        const messages = document.getElementById('aiMessages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `ai-message ai-message-${type}`;
        messageDiv.innerHTML = `
            <div class="ai-message-content">
                ${content}
            </div>
        `;
        messages.appendChild(messageDiv);
        messages.scrollTop = messages.scrollHeight;
    }
    </script>
    <?php
} 