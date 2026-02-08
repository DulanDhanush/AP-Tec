/* Live Chat Side-Panel Logic */

document.addEventListener("DOMContentLoaded", () => {
    
    // Elements
    const chatTriggerBtns = document.querySelectorAll('.trigger-live-chat');
    const chatPanel = document.getElementById('liveChatPanel');
    const sidebar = document.querySelector('.sidebar');
    const closeBtn = document.getElementById('closeLiveChat');
    const backdrop = document.getElementById('chatBackdrop');
    
    // Chat Input Logic
    const input = document.getElementById('popupChatInput');
    const body = document.getElementById('popupChatBody');
    const sendBtn = document.getElementById('popupSendBtn');

    // --- OPEN CHAT (Hide Sidebar) ---
    const openChat = () => {
        // 1. Slide Sidebar Out (Left)
        sidebar.classList.add('hidden');
        
        // 2. Slide Chat In (Right)
        chatPanel.classList.add('active');
        backdrop.classList.add('active');
    };

    // --- CLOSE CHAT (Show Sidebar) ---
    const closeChat = () => {
        // 1. Slide Chat Out
        chatPanel.classList.remove('active');
        backdrop.classList.remove('active');

        // 2. Slide Sidebar Back In
        // Small delay to make it look smooth (optional)
        setTimeout(() => {
            sidebar.classList.remove('hidden');
        }, 100);
    };

    // Events
    chatTriggerBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            openChat();
        });
    });

    if(closeBtn) closeBtn.addEventListener('click', closeChat);
    if(backdrop) backdrop.addEventListener('click', closeChat); // Close if clicking outside

    // --- SEND MESSAGE SIMULATION ---
    const sendMessage = () => {
        const text = input.value.trim();
        if(!text) return;

        // User Msg
        const msg = document.createElement('div');
        msg.className = 'message-bubble msg-out fade-in';
        msg.innerHTML = `${text}<span class="msg-time">Just now</span>`;
        body.appendChild(msg);
        
        input.value = '';
        body.scrollTop = body.scrollHeight;

        // Auto Reply
        setTimeout(() => {
            const reply = document.createElement('div');
            reply.className = 'message-bubble msg-in fade-in';
            reply.innerHTML = `Thanks for reaching out! An agent is joining the chat...<span class="msg-time">Now</span>`;
            body.appendChild(reply);
            body.scrollTop = body.scrollHeight;
        }, 1500);
    };

    if(sendBtn) sendBtn.addEventListener('click', sendMessage);
    if(input) {
        input.addEventListener('keypress', (e) => {
            if(e.key === 'Enter') sendMessage();
        });
    }
});