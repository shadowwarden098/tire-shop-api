<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Alexander - Importaciones Adan</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .chat-container {
            width: 420px;
            height: 650px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .chat-header {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: white;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .avatar {
            width: 44px; height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            background: #e94560;
        }
        /* Admin avatar distinto */
        .avatar.admin { background: #f6ad55; }

        .header-info h3 { font-size: 16px; }
        .header-info span { font-size: 12px; color: #a0aec0; }
        .status-dot {
            width: 8px; height: 8px;
            background: #48bb78;
            border-radius: 50%;
            display: inline-block;
            margin-right: 4px;
        }
        /* Badge de rol */
        .role-badge {
            margin-left: auto;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .role-badge.admin    { background: #f6ad55; color: #1a1a2e; }
        .role-badge.employee { background: #4a5568; color: white; }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .message {
            max-width: 80%;
            padding: 10px 14px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.5;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .message.bot      { background: #f0f2f5; color: #1a1a2e; align-self: flex-start; border-bottom-left-radius: 4px; }
        .message.user     { background: #e94560; color: white;   align-self: flex-end;   border-bottom-right-radius: 4px; }
        .message.typing   { background: #f0f2f5; align-self: flex-start; color: #718096; font-style: italic; }

        .chat-input {
            padding: 16px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 10px;
        }
        .chat-input input {
            flex: 1;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 24px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        .chat-input input:focus { border-color: #e94560; }
        .chat-input button {
            width: 42px; height: 42px;
            background: #e94560;
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            transition: background 0.2s, transform 0.1s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .chat-input button:hover   { background: #c53030; }
        .chat-input button:active  { transform: scale(0.95); }
        .chat-input button:disabled { background: #a0aec0; cursor: not-allowed; }
        .chat-messages::-webkit-scrollbar { width: 4px; }
        .chat-messages::-webkit-scrollbar-thumb { background: #cbd5e0; border-radius: 4px; }
    </style>
</head>
<body>

<div class="chat-container">
    <div class="chat-header">
        {{-- Avatar con clase según rol --}}
        <div class="avatar {{ auth()->user()->role }}">A</div>
        <div class="header-info">
            <h3>Alexander</h3>
            <span>
                <span class="status-dot"></span>
                Importaciones Adan · {{ auth()->user()->name }}
            </span>
        </div>
        {{-- Badge de rol visible --}}
        <span class="role-badge {{ auth()->user()->role }}">
            {{ auth()->user()->role === 'admin' ? '🔐 Admin' : '👤 Empleado' }}
        </span>
    </div>

    <div class="chat-messages" id="chatMessages">
        <div class="message bot">
            ¡Hola, <strong>{{ auth()->user()->name }}</strong>! 👋 Soy Alexander.<br><br>
            @if(auth()->user()->role === 'admin')
                Tienes acceso <strong>completo</strong> — costos, márgenes, proveedores, todo. 🔐<br>
                ¿En qué te ayudo hoy, jefe? 😄
            @else
                Estoy aquí para ayudarte con precios, stock y características de nuestras llantas. 🚗<br>
                ¿Qué necesitas saber? ¡Pregunta sin miedo, que aquí no pinchamos! 😄
            @endif
        </div>
    </div>

    <div class="chat-input">
        <input type="text" id="userInput" placeholder="Escribe tu consulta..." autocomplete="off" />
        <button id="sendBtn" onclick="sendMessage()">➤</button>
    </div>
</div>

<script>
    const chatMessages = document.getElementById('chatMessages');
    const userInput    = document.getElementById('userInput');
    const sendBtn      = document.getElementById('sendBtn');
    let history        = [];

    userInput.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) sendMessage();
    });

    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function appendMessage(role, text) {
        const div = document.createElement('div');
        div.className = `message ${role}`;
        div.innerHTML = text.replace(/\n/g, '<br>');
        chatMessages.appendChild(div);
        scrollToBottom();
        return div;
    }

    async function sendMessage() {
        const message = userInput.value.trim();
        if (!message) return;

        appendMessage('user', message);
        userInput.value  = '';
        sendBtn.disabled = true;

        const typingDiv = appendMessage('typing', 'Alexander está escribiendo...');

        try {
            const res = await fetch('/api/chat', {
                method: 'POST',
                headers: {
                    'Content-Type' : 'application/json',
                    'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ message, history }),
            });

            const data = await res.json();
            typingDiv.remove();
            appendMessage('bot', data.reply);

            // Actualizar historial
            history.push({ role: 'user',      content: message    });
            history.push({ role: 'assistant',  content: data.reply });

            // Máximo 20 mensajes en historial
            if (history.length > 20) history = history.slice(-20);

        } catch (error) {
            typingDiv.remove();
            appendMessage('bot', '❌ Error de conexión. Intenta de nuevo.');
        } finally {
            sendBtn.disabled = false;
            userInput.focus();
        }
    }
</script>

</body>
</html>