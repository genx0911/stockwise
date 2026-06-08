<?php // includes/footer.php ?>
<style>
@media print {
    .sidebar, .page-header .btn, .btn-logout, #toast-container, #stockbot-wrap { display: none !important; }
    .main { margin-left: 0 !important; padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
}

/* ── StockBot widget ── */
#stockbot-wrap {
    position: fixed; bottom: 24px; right: 24px; z-index: 10000;
    display: flex; flex-direction: column; align-items: flex-end; gap: 12px;
}
#stockbot-toggle {
    width: 52px; height: 52px; border-radius: 50%;
    background: #2563EB; color: white; border: none;
    box-shadow: 0 4px 16px rgba(37,99,235,0.45);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; cursor: pointer; transition: transform 0.2s, background 0.2s;
    flex-shrink: 0;
}
#stockbot-toggle:hover { background: #1D4ED8; transform: scale(1.08); }
#stockbot-toggle.open  { background: #475569; }

#stockbot-panel {
    width: 340px;
    background: white;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.14);
    display: flex; flex-direction: column;
    overflow: hidden;
    max-height: 520px;
    transform-origin: bottom right;
    transition: transform 0.2s, opacity 0.2s;
}
#stockbot-panel.hidden {
    transform: scale(0.85); opacity: 0; pointer-events: none;
}
#stockbot-header {
    background: #0F172A; color: white;
    padding: 12px 16px;
    display: flex; align-items: center; gap: 10px;
}
#stockbot-header .bot-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: #2563EB;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0;
}
#stockbot-header .bot-info { flex: 1; }
#stockbot-header .bot-name { font-size: 13px; font-weight: 700; }
#stockbot-header .bot-status { font-size: 11px; color: #94A3B8; display: flex; align-items: center; gap: 4px; }
#stockbot-header .bot-dot { width: 6px; height: 6px; border-radius: 50%; background: #22C55E; }
#stockbot-clear {
    background: none; border: none; color: #64748B; font-size: 14px;
    cursor: pointer; padding: 4px; border-radius: 4px; transition: color 0.15s;
}
#stockbot-clear:hover { color: #EF4444; }

#stockbot-messages {
    flex: 1; overflow-y: auto; padding: 14px;
    display: flex; flex-direction: column; gap: 10px;
    background: #F8FAFC;
    min-height: 280px;
}
.sb-msg {
    max-width: 88%; font-size: 13px; line-height: 1.5;
    padding: 9px 12px; border-radius: 12px; word-break: break-word;
}
.sb-msg.bot {
    background: white; border: 1px solid #E2E8F0; color: #0F172A;
    border-bottom-left-radius: 4px; align-self: flex-start;
}
.sb-msg.user {
    background: #2563EB; color: white;
    border-bottom-right-radius: 4px; align-self: flex-end;
}
.sb-msg.typing { color: #94A3B8; font-style: italic; border-style: dashed; }

.sb-suggestions {
    display: flex; flex-wrap: wrap; gap: 6px; padding: 0 14px 10px;
    background: #F8FAFC;
}
.sb-chip {
    font-size: 11.5px; padding: 4px 10px; border-radius: 99px;
    background: white; border: 1px solid #CBD5E1;
    color: #475569; cursor: pointer; transition: all 0.15s; font-weight: 500;
}
.sb-chip:hover { background: #2563EB; color: white; border-color: #2563EB; }

#stockbot-form {
    display: flex; gap: 8px; padding: 12px;
    border-top: 1px solid #E2E8F0; background: white;
}
#stockbot-input {
    flex: 1; border: 1px solid #E2E8F0; border-radius: 8px;
    padding: 8px 12px; font-size: 13px; outline: none;
    transition: border-color 0.15s;
    font-family: 'Plus Jakarta Sans', sans-serif;
}
#stockbot-input:focus { border-color: #2563EB; }
#stockbot-send {
    background: #2563EB; color: white; border: none;
    border-radius: 8px; padding: 8px 12px;
    font-size: 15px; cursor: pointer; transition: background 0.15s;
    display: flex; align-items: center;
}
#stockbot-send:hover { background: #1D4ED8; }
#stockbot-send:disabled { background: #94A3B8; cursor: not-allowed; }
</style>
</div><!-- /.main -->

<!-- ── StockBot floating widget ── -->
<div id="stockbot-wrap">
    <div id="stockbot-panel" class="hidden">
        <div id="stockbot-header">
            <div class="bot-avatar">🤖</div>
            <div class="bot-info">
                <div class="bot-name">StockBot</div>
                <div class="bot-status"><span class="bot-dot"></span> Online · Ask me anything</div>
            </div>
            <button id="stockbot-clear" title="Clear chat"><i class="bi bi-arrow-counterclockwise"></i></button>
        </div>
        <div id="stockbot-messages">
            <div class="sb-msg bot">
                Hi! I'm StockBot 👋 Ask me about inventory levels, your orders, low-stock items, or anything else.
            </div>
        </div>
        <div class="sb-suggestions" id="sb-suggestions">
            <span class="sb-chip">What's low on stock?</span>
            <span class="sb-chip">My last order status</span>
            <span class="sb-chip">How many products are out of stock?</span>
            <span class="sb-chip">Show pending orders</span>
        </div>
        <form id="stockbot-form">
            <input type="text" id="stockbot-input" placeholder="Ask a question…" autocomplete="off" maxlength="400">
            <button type="submit" id="stockbot-send"><i class="bi bi-send-fill"></i></button>
        </form>
    </div>
    <button id="stockbot-toggle" title="StockBot AI Assistant">
        <i class="bi bi-robot"></i>
    </button>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showToast(msg, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMsg = document.getElementById('toast-msg');
    toast.className = 'toast align-items-center text-white border-0 bg-' + (type === 'success' ? 'success' : 'danger');
    toastMsg.textContent = msg;
    new bootstrap.Toast(toast, { delay: 3500 }).show();
}
// Auto-show toast from URL params
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('success')) showToast(decodeURIComponent(urlParams.get('success')), 'success');
if (urlParams.get('error'))   showToast(decodeURIComponent(urlParams.get('error')),   'danger');

// ── StockBot ──
(function () {
    const toggle   = document.getElementById('stockbot-toggle');
    const panel    = document.getElementById('stockbot-panel');
    const messages = document.getElementById('stockbot-messages');
    const form     = document.getElementById('stockbot-form');
    const input    = document.getElementById('stockbot-input');
    const send     = document.getElementById('stockbot-send');
    const clearBtn = document.getElementById('stockbot-clear');
    const chips    = document.querySelectorAll('.sb-chip');

    let history = []; // [{role, content}]
    let isOpen  = false;

    function togglePanel() {
        isOpen = !isOpen;
        panel.classList.toggle('hidden', !isOpen);
        toggle.classList.toggle('open', isOpen);
        toggle.innerHTML = isOpen
            ? '<i class="bi bi-x-lg"></i>'
            : '<i class="bi bi-robot"></i>';
        if (isOpen) setTimeout(() => input.focus(), 150);
    }

    function scrollBottom() {
        messages.scrollTop = messages.scrollHeight;
    }

    function addMsg(role, text) {
        const div = document.createElement('div');
        div.className = 'sb-msg ' + role;
        div.textContent = text;
        messages.appendChild(div);
        scrollBottom();
        return div;
    }

    function addTyping() {
        const div = document.createElement('div');
        div.className = 'sb-msg bot typing';
        div.id = 'sb-typing';
        div.textContent = 'StockBot is thinking…';
        messages.appendChild(div);
        scrollBottom();
    }

    function removeTyping() {
        const el = document.getElementById('sb-typing');
        if (el) el.remove();
    }

    async function sendMessage(text) {
        text = text.trim();
        if (!text) return;

        addMsg('user', text);
        history.push({ role: 'user', content: text });

        input.value = '';
        send.disabled = true;
        addTyping();

        // Hide suggestion chips after first use
        document.getElementById('sb-suggestions').style.display = 'none';

        try {
            const fd = new FormData();
            fd.append('message', text);
            fd.append('history', JSON.stringify(history.slice(0, -1))); // exclude current

            const res  = await fetch('<?= APP_URL ?>/chatbot.php', { method: 'POST', body: fd });
            const data = await res.json();

            removeTyping();
            const reply = data.reply || 'Sorry, something went wrong.';
            addMsg('bot', reply);
            history.push({ role: 'assistant', content: reply });

        } catch (e) {
            removeTyping();
            addMsg('bot', 'Network error — please try again.');
        }

        send.disabled = false;
        input.focus();
    }

    toggle.addEventListener('click', togglePanel);

    form.addEventListener('submit', e => {
        e.preventDefault();
        sendMessage(input.value);
    });

    chips.forEach(chip => {
        chip.addEventListener('click', () => sendMessage(chip.textContent));
    });

    clearBtn.addEventListener('click', () => {
        history = [];
        messages.innerHTML = '<div class="sb-msg bot">Hi! I\'m StockBot 👋 Ask me about inventory levels, your orders, low-stock items, or anything else.</div>';
        document.getElementById('sb-suggestions').style.display = 'flex';
        input.focus();
    });
})();
</script>
<?php if (isset($extraJs)): ?>
<script><?= $extraJs ?></script>
<?php endif; ?>
</body>
</html>
