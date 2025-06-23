document.addEventListener('DOMContentLoaded', function() {
    const userInputElement = document.getElementById('user-input');
    const sendButton = document.getElementById('send-button');
    const chatMessagesElement = document.getElementById('chat-messages');
    const logContentElement = document.getElementById('log-content');
    const logHeader = document.getElementById('log-header');

    // --- Core Functions ---

    function appendMessage(role, content) {
        const messageWrapper = document.createElement('div');
        const displayRole = role === 'assistant' ? 'ai' : role;
        messageWrapper.classList.add('message', displayRole);

        const strong = document.createElement('strong');
        strong.textContent = role === 'user' ? 'You: ' : 'AI: ';

        const contentSpan = document.createElement('span');
        contentSpan.innerHTML = content;
        
        messageWrapper.appendChild(strong);
        messageWrapper.appendChild(contentSpan);

        chatMessagesElement.appendChild(messageWrapper);
        chatMessagesElement.scrollTop = chatMessagesElement.scrollHeight;
    }

    function transformIndicatorToMessage(indicator, content) {
        // The "Timed Overflow Switch" Technique
        const animationDuration = 600; // Must match CSS transition duration

        // Step 1: Unify the HTML content and trim leading newlines.
        // The SPAN tag is critical here to ensure the browser correctly renders
        // HTML formatting (like <br> tags) within the content.
        indicator.innerHTML = `<strong>AI:</strong> <span>${content.trim()}</span>`;

        // --- Animation Orchestration ---
        const unfurlDuration = 600; // Corresponds to transform transition in CSS
        const borderDazzleDuration = 2500; // Corresponds to border-dazzle animation
        const backgroundDazzleDuration = 8000; // How long the top gradient is visible

        // Step 2: Trigger the border shimmer and the background dazzle fade-in.
        indicator.classList.add('shimmer-out');
        indicator.classList.add('unfurling-dazzle');

        // Step 3: Remove the 'thinking' state. This triggers the main background
        // color fade and the transform (unfurl) animation.
        indicator.classList.remove('thinking-indicator');
        indicator.classList.remove('visible-indicator');

        // Continuously scroll to keep the message in view during animation.
        const scrollInterval = setInterval(() => {
            chatMessagesElement.scrollTop = chatMessagesElement.scrollHeight;
        }, 50);

        // Clean up temporary classes and styles after animations complete.
        setTimeout(() => {
            indicator.classList.remove('shimmer-out');
        }, borderDazzleDuration);

        setTimeout(() => {
            indicator.classList.remove('unfurling-dazzle'); // Fades out the gradient layer
        }, backgroundDazzleDuration);

        setTimeout(() => {
            clearInterval(scrollInterval);
            // No need to adjust height or overflow with transform animation
            chatMessagesElement.scrollTop = chatMessagesElement.scrollHeight;
        }, unfurlDuration);
    }

    function appendError(content) {
        const messageWrapper = document.createElement('div');
        messageWrapper.classList.add('message', 'message-error');
        messageWrapper.innerHTML = `<strong>System:</strong> <span>${content}</span>`;
        chatMessagesElement.appendChild(messageWrapper);
        chatMessagesElement.scrollTop = chatMessagesElement.scrollHeight;
    }

    function appendLog(role, content) {
        const entry = document.createElement('div');
        entry.classList.add('log-entry', `log-${role.toUpperCase()}`);
        const logMessage = typeof content === 'object' ? JSON.stringify(content, null, 2) : content;
        entry.textContent = `[${new Date().toLocaleTimeString()}] [${role.toUpperCase()}] ${logMessage}`;
        logContentElement.appendChild(entry);
        logContentElement.scrollTop = logContentElement.scrollHeight;
    }

    function showThinkingIndicator(show) {
        let indicator = document.querySelector('.thinking-indicator');
        if (show) {
            if (!indicator) {
                indicator = document.createElement('div');
                // Start with the base and thinking classes. It begins scaled-down.
                indicator.classList.add('message', 'ai', 'thinking-indicator');
                indicator.innerHTML = `<div class="typing-dots"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div>`;
                chatMessagesElement.appendChild(indicator);

                // Use a tiny timeout to allow the browser to apply the initial state (scaled-down)
                // before adding the class that triggers the transition to scale-up.
                setTimeout(() => {
                    indicator.classList.add('visible-indicator');
                    chatMessagesElement.scrollTop = chatMessagesElement.scrollHeight;
                }, 10);
            }
        } else {
            // This 'else' block is now only for hiding the indicator on error.
            if (indicator) {
                indicator.remove();
            }
        }
    }

    // No longer needed, as history is managed by the backend session.
    // function getConversationHistory() { ... }

    async function processTurn(response) {
        if (!response.ok) {
            const errorText = await response.text();
            const indicator = document.querySelector('.thinking-indicator');
            if(indicator) indicator.remove();
            appendError(`Server returned an error: ${response.status} ${response.statusText}. ${errorText}`);
            return;
        }

        const result = await response.json();
        appendLog(result.type, result.content || JSON.stringify(result));
        
        // Display debug information if it exists in the response
        if (result._debug) {
            appendLog('debug', result._debug);
        }

        const indicator = document.querySelector('.thinking-indicator');

        if (result.type === 'tool_call') {
            // New granular approach: execute the specific tool
            appendLog('debug', { 'tool_call_request': result });
            const toolResponse = await fetch('api/ajax_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'execute_tool',
                    tool_name: result.tool_name,
                    arguments: result.arguments
                })
            });
            await processTurn(toolResponse);
        } else if (result.type === 'tool_success') {
            // Legacy support for old approach
            appendLog('debug', { 'intermediate_server_response': result });
            const nextResponse = await fetch('api/ajax_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'continue_processing' })
            });
            await processTurn(nextResponse);
        } else {
            if (indicator) {
                if (result.type === 'response') {
                    transformIndicatorToMessage(indicator, result.content);
                } else if (result.type === 'error') {
                    appendError(result.content);
                    indicator.remove();
                }
            } else { 
                if (result.type === 'response') {
                    appendMessage('assistant', result.content);
                } else if (result.type === 'error') {
                    appendError(result.content);
                }
            }
        }
    }

    async function sendMessage() {
        const userInput = userInputElement.value.trim();
        if (userInput === '') return;

        appendMessage('user', userInput);
        appendLog('user', userInput);
        userInputElement.value = '';
        
        if (userInput === '/reset') {
            try {
                const response = await fetch('api/ajax_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reset_chat' })
                });
                const result = await response.json();
                if (result.type === 'success') {
                    chatMessagesElement.innerHTML = '';
                    appendMessage('ai', 'Chat history has been reset.');
                    appendLog('system', 'Chat history reset successfully.');
                } else {
                    appendError(result.content || 'Failed to reset chat.');
                }
            } catch (error) {
                appendError('An error occurred while resetting the chat.');
            }
            return;
        }
        
        showThinkingIndicator(true);
        
        try {
            const initialResponse = await fetch('api/ajax_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'send_message', message: userInput }) // New action
            });

            const contentType = initialResponse.headers.get("content-type");
            if (!initialResponse.ok || !contentType || !contentType.includes("application/json")) {
                const errorText = await initialResponse.text();
                throw new Error(`Server error: ${initialResponse.status} ${initialResponse.statusText} - ${errorText}`);
            }
            
            await processTurn(initialResponse);

        } catch (error) {
            showThinkingIndicator(false); // Remove indicator on network error
            console.error('Fetch error:', error);
            const errorMessage = error.message.includes('JSON.parse') ? 'Received an invalid response from the server.' : error.message;
            appendError(`A network error occurred: ${errorMessage}`);
            appendLog('error', error.message);
        }
    }

    // --- Event Listeners ---
    if (sendButton) {
        sendButton.addEventListener('click', sendMessage);
    }
    if (userInputElement) {
        userInputElement.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendMessage();
            }
        });
    }
    if(logHeader) {
        const logPanel = document.getElementById('log-panel');
        logHeader.addEventListener('click', () => {
            logPanel.classList.toggle('collapsed');
            logHeader.textContent = logPanel.classList.contains('collapsed') ? 'Debug Log ▼' : 'Debug Log ▲';
        });
        // Collapse by default
        logPanel.classList.add('collapsed');
        logHeader.textContent = 'Debug Log ▼';
    }

    // --- Initial State Fix ---
    // Fix any static AI messages (like the initial greeting) that are not
    // handled by the dynamic transformIndicatorToMessage function. This prevents
    // them from being squashed on the first user message.
    const staticAiMessages = document.querySelectorAll('.message.ai:not(.thinking-indicator)');
    staticAiMessages.forEach(msg => {
        msg.style.overflow = 'visible';
        // Also apply the base background color explicitly.
        msg.style.backgroundColor = '#e0f2f7';
    });
}); 