
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chat Archive</title>
    <style>
        :root {
            --bg-color: #1e1e1e;
            --sidebar-bg: #252526;
            --text-color: #d4d4d4;
            --msg-user: #2d2d30;
            --msg-ai: #3e3e42;
            --border: #333;
            --accent: #007acc;
        }
        body {
            margin: 0; padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color:         #sidebar {
            width: 300px;
            background-color: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }
        .sidebar-header {
            padding: 15px;
            border-bottom: 1px solid var(--border);
            font-weight: bold;
            display: flex;
            justify-content: space-between;
        }
        #chat-list {
            flex-grow: 1;
            overflow-y: auto;
        }
        .chat-item {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.2s;
        }
        .chat-item:hover, .chat-item.active {
            background-color: var(--msg-ai);
        }
        .chat-title { font-weight: bold; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .chat-meta { font-size: 11px; color: #888; margin-top: 5px; }

        /* Вікно повідомлень */
        #main-chat {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        #chat-header {
            padding: 15px;
            border-bottom: 1px solid var(--border);
            background-color: var(--sidebar-bg);
            font-weight: bold;
        }
        #messages-container {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .message {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 8px;
            line-height: 1.5;
        }
        .message.user {
            background-color: var(--msg-user);
            align-self: flex-end;
            border-bottom-right-radius: 0;
        }
        .message.ai {
            background-color: var(--msg-ai);
            align-self: flex-start;
            border-bottom-left-radius: 0;
        }
        /* Стиль для кнопки "Поділитися" */
        .btn-share {
            background: var(--accent);
            color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;
        }
    </style>
</head>
<body>

    <div id="sidebar">
        <div class="sidebar-header">
            <span>🗄️ Архів Діалогів</span>
        </div>
        <div id="chat-list">
            <!-- Список чатів буде завантажено сюди через JS -->
            <div style="padding:15px;text-align:center;color:#888;">Завантаження...</div>
        </div>
    </div>

    <div id="main-chat">
        <div id="chat-header">Виберіть діалог зліва</div>
        <div id="messages-container">
            <!-- Повідомлення будуть завантажені сюди -->
        </div>
    </div>

    <script>
        // 1. Завантаження списку чатів
        async function loadConversations() 
            try {
                const res = await fetch('api.php?action=get_conversations');
                const json = await res.json();
                
                const list = document.getElementById('chat-list');
                list.innerHTML = '';

                if(json.status !== 'success' || json.data.length === 0) {
                    list.innerHTML = '<div style="padding:15px;text-align:center;color:#888;">База порожня</div>';
                    return;
                }

                json.data.forEach(chat => {
                    const div = document.createElement('div');
                    div.className = 'chat-item';
                    div.innerHTML = `
                        <div class="chat-title">${chat.title || 'Новий діалог'}</div>
                        <div class="chat-meta">📅 ${chat.created_at} | 🤖 ${chat.platform}</div>
                    `;
                    div.onclick = () => loadMessages(chat.id, chat.title);
                    list.appendChild(div);
                });
            } catch (error) {
                console.error("Помилка завантаження:", error);
            }
        }

        // 2. Завантаження повідомлень конкретного чату
        async function loadMessages(chatId, title) {
            document.getElementById('chat-header
