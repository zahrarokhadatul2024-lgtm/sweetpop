/**
 * Sweetie AI Chatbot Client Script
 * SweetPop Bakery & Aesthetic Dessert Store
 */

document.addEventListener('DOMContentLoaded', function () {
    const launcherBtn = document.getElementById('sweetieLauncherBtn');
    const tooltip = document.getElementById('sweetieTooltip');
    const chatWindow = document.getElementById('sweetieChatWindow');
    const closeBtn = document.getElementById('sweetieCloseBtn');
    const resetBtn = document.getElementById('sweetieResetBtn');
    const chatBody = document.getElementById('sweetieChatBody');
    const chatForm = document.getElementById('sweetieChatForm');
    const chatInput = document.getElementById('sweetieChatInput');
    const sendBtn = document.getElementById('sweetieSendBtn');
    const typingIndicator = document.getElementById('sweetieTypingIndicator');

    if (!launcherBtn || !chatWindow) return;

    // Unique session ID stored in sessionStorage
    let sessionId = sessionStorage.getItem('sweetie_session_id');
    if (!sessionId) {
        sessionId = 'sp_' + Math.random().toString(36).substring(2, 11) + '_' + Date.now();
        sessionStorage.setItem('sweetie_session_id', sessionId);
    }

    // Toggle chat window
    function toggleChat(forceOpen = null) {
        const isOpen = chatWindow.classList.contains('open');
        const shouldOpen = forceOpen !== null ? forceOpen : !isOpen;

        if (shouldOpen) {
            chatWindow.classList.add('open');
            if (tooltip) tooltip.style.display = 'none';
            setTimeout(() => chatInput && chatInput.focus(), 300);
            scrollToBottom();
        } else {
            chatWindow.classList.remove('open');
        }
    }

    launcherBtn.addEventListener('click', () => toggleChat());
    if (tooltip) tooltip.addEventListener('click', () => toggleChat(true));
    if (closeBtn) closeBtn.addEventListener('click', () => toggleChat(false));

    // Reset Chat Memory & Session
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (confirm('Mulai sesi obrolan baru dengan Sweetie? Topik percakapan sebelumnya akan dibersihkan.')) {
                fetch('ajax_chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reset', sessionId: sessionId })
                }).catch(() => {});

                sessionStorage.removeItem('sweetie_chat_history');
                sessionId = 'sp_' + Math.random().toString(36).substring(2, 11) + '_' + Date.now();
                sessionStorage.setItem('sweetie_session_id', sessionId);

                if (chatBody) {
                    chatBody.innerHTML = `
                        <div class="chat-msg bot">
                            <div style="width:30px; height:30px; border-radius:50%; background:linear-gradient(135deg, #ba007c, #ff7597); color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.9rem; flex-shrink:0; margin-top:2px;">
                                <i class="fa-solid fa-cake-candles"></i>
                            </div>
                            <div style="flex:1;">
                                <div class="chat-bubble">
                                    Halo Kak! 🧁 Sesi obrolan baru telah dimulai! Aku <strong>Sweetie</strong>, asisten virtual resmi toko kue <strong>SweetPop</strong>.<br><br>
                                    Ada yang bisa Sweetie bantu seputar kue lezat atau jadwal pengantaran hari ini? 🎀
                                    <span class="chat-time">Sweetie • Baru</span>
                                </div>
                                <div class="sweetie-ai-suggestions">
                                    <button type="button" class="ai-chip" onclick="window.sweetieAsk('🍰 Menu Best Seller SweetPop')">🍰 Menu Best Seller</button>
                                    <button type="button" class="ai-chip" onclick="window.sweetieAsk('🚚 Ketentuan Jam & Slot Pengantaran')">🚚 Slot Pengantaran</button>
                                    <button type="button" class="ai-chip" onclick="window.sweetieAsk('🏷️ Cara Dapat Promo Gratis Ongkir')">🏷️ Promo Gratis Ongkir</button>
                                    <button type="button" class="ai-chip" onclick="window.sweetieAsk('🎂 Personalisasi Ucapan & Lilin Gratis')">🎂 Custom Ucapan & Lilin</button>
                                </div>
                            </div>
                        </div>
                    `;
                }
                if (chatInput) chatInput.focus();
            }
        });
    }

    function scrollToBottom() {
        if (chatBody) {
            chatBody.scrollTop = chatBody.scrollHeight;
        }
    }

    function getCurrentTime() {
        const d = new Date();
        const h = String(d.getHours()).padStart(2, '0');
        const m = String(d.getMinutes()).padStart(2, '0');
        return `${h}:${m}`;
    }

    // Parse simple Markdown to HTML
    function formatMessageText(text) {
        if (!text) return '';
        let escaped = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Bold **text**
        escaped = escaped.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        // Italic *text*
        escaped = escaped.replace(/\*(.*?)\*/g, '<em>$1</em>');

        // Links [Label](url)
        escaped = escaped.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1 <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.75em;"></i></a>');

        // Bullet points
        escaped = escaped.replace(/(?:^|\n)[•-]\s*(.+)/g, '<br>• $1');

        // Line breaks
        escaped = escaped.replace(/\n/g, '<br>');

        return escaped;
    }

    // Append user message
    function appendUserMessage(text) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'chat-msg user';
        msgDiv.innerHTML = `
            <div class="chat-bubble">
                ${formatMessageText(text)}
                <span class="chat-time">${getCurrentTime()}</span>
            </div>
        `;
        chatBody.appendChild(msgDiv);
        scrollToBottom();
        saveHistory('user', text);
    }

    // Append bot message with optional chips
    function appendBotMessage(text, suggestions = []) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'chat-msg bot';
        
        let chipsHtml = '';
        if (suggestions && suggestions.length > 0) {
            chipsHtml = '<div class="sweetie-ai-suggestions">';
            suggestions.forEach(s => {
                chipsHtml += `<button type="button" class="ai-chip" onclick="window.sweetieAsk('${s.replace(/'/g, "\\'")}')">${s}</button>`;
            });
            chipsHtml += '</div>';
        }

        msgDiv.innerHTML = `
            <div style="width:30px; height:30px; border-radius:50%; background:linear-gradient(135deg, #ba007c, #ff7597); color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.9rem; flex-shrink:0; margin-top:2px;">
                <i class="fa-solid fa-cake-candles"></i>
            </div>
            <div style="flex:1;">
                <div class="chat-bubble">
                    ${formatMessageText(text)}
                    <span class="chat-time">Sweetie • ${getCurrentTime()}</span>
                </div>
                ${chipsHtml}
            </div>
        `;
        chatBody.appendChild(msgDiv);
        scrollToBottom();
        saveHistory('bot', text, suggestions);
    }

    // Persist chat to sessionStorage
    function saveHistory(sender, text, suggestions = []) {
        try {
            const hist = JSON.parse(sessionStorage.getItem('sweetie_chat_history') || '[]');
            hist.push({ sender, text, suggestions, time: getCurrentTime() });
            if (hist.length > 30) hist.shift(); // Keep last 30
            sessionStorage.setItem('sweetie_chat_history', JSON.stringify(hist));
        } catch (e) {
            console.warn('Storage quota exceeded', e);
        }
    }

    // Restore chat history
    function restoreHistory() {
        try {
            const hist = JSON.parse(sessionStorage.getItem('sweetie_chat_history') || '[]');
            if (hist.length > 0) {
                // Clear default greeting if we have stored history
                chatBody.innerHTML = '';
                hist.forEach(h => {
                    if (h.sender === 'user') {
                        const m = document.createElement('div');
                        m.className = 'chat-msg user';
                        m.innerHTML = `<div class="chat-bubble">${formatMessageText(h.text)}<span class="chat-time">${h.time}</span></div>`;
                        chatBody.appendChild(m);
                    } else {
                        let chipsHtml = '';
                        if (h.suggestions && h.suggestions.length > 0) {
                            chipsHtml = '<div class="sweetie-ai-suggestions">';
                            h.suggestions.forEach(s => {
                                chipsHtml += `<button type="button" class="ai-chip" onclick="window.sweetieAsk('${s.replace(/'/g, "\\'")}')">${s}</button>`;
                            });
                            chipsHtml += '</div>';
                        }
                        const m = document.createElement('div');
                        m.className = 'chat-msg bot';
                        m.innerHTML = `
                            <div style="width:30px; height:30px; border-radius:50%; background:linear-gradient(135deg, #ba007c, #ff7597); color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.9rem; flex-shrink:0; margin-top:2px;">
                                <i class="fa-solid fa-cake-candles"></i>
                            </div>
                            <div style="flex:1;">
                                <div class="chat-bubble">
                                    ${formatMessageText(h.text)}
                                    <span class="chat-time">Sweetie • ${h.time}</span>
                                </div>
                                ${chipsHtml}
                            </div>
                        `;
                        chatBody.appendChild(m);
                    }
                });
                scrollToBottom();
            }
        } catch (e) {
            console.error('Failed to restore chat history', e);
        }
    }

    // Global helper for chips
    window.sweetieAsk = function (query) {
        if (!query) return;
        if (!chatWindow.classList.contains('open')) {
            toggleChat(true);
        }
        submitQuery(query);
    };

    function submitQuery(queryText) {
        const text = (queryText || (chatInput ? chatInput.value : '')).trim();
        if (!text) return;

        if (chatInput) chatInput.value = '';
        appendUserMessage(text);

        // Show typing indicator
        if (typingIndicator) {
            typingIndicator.style.display = 'flex';
            chatBody.appendChild(typingIndicator);
            scrollToBottom();
        }

        if (sendBtn) sendBtn.disabled = true;

        // POST to ajax_chat.php
        fetch('ajax_chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                message: text,
                sessionId: sessionId
            })
        })
        .then(response => {
            if (!response.ok) throw new Error('HTTP status ' + response.status);
            return response.json();
        })
        .then(data => {
            if (typingIndicator) typingIndicator.style.display = 'none';
            if (sendBtn) sendBtn.disabled = false;

            if (data && data.reply) {
                appendBotMessage(data.reply, data.suggestions || []);
            } else {
                appendBotMessage('Maaf Kak, terjadi kendala saat memproses jawaban. Silakan coba lagi ya! 🍰');
            }
        })
        .catch(err => {
            console.error('Chat error:', err);
            if (typingIndicator) typingIndicator.style.display = 'none';
            if (sendBtn) sendBtn.disabled = false;
            appendBotMessage('Ups, koneksi terputus sebentar. Boleh tanyakan ulang seputar menu kue atau pesananmu Kak? 🧁');
        });
    }

    if (chatForm) {
        chatForm.addEventListener('submit', function (e) {
            e.preventDefault();
            submitQuery();
        });
    }

    // Initialize history
    restoreHistory();
});
