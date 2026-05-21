document.addEventListener('DOMContentLoaded', () => {
    const parseBtn = document.getElementById('parseBtn');
    const statusText = document.getElementById('status');

    parseBtn.addEventListener('click', async () => {
        // Змінюємо статус під час роботи
        statusText.innerText = "Збирання даних...";
        statusText.style.color = "#333";
        
        // Отримуємо активну вкладку
        let [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
        
        // Відправляємо повідомлення в content.js цієї вкладки
        chrome.tabs.sendMessage(tab.id, { action: "start_parsing" }, (response) => {
            // Перевіряємо, чи є помилка (наприклад, розширення запущено не на сайті ШІ)
            if (chrome.runtime.lastError) {
                statusText.innerText = "❌ Відкрийте чат (ChatGPT/Gemini) і оновіть сторінку.";
                statusText.style.color = "red";
            } else if (response && response.status === "started") {
                statusText.innerText = "✅ Чат відправлено в чергу!";
                statusText.style.color = "green";
                
                // Автоматично закриваємо вікно через 1.5 секунди
                setTimeout(() => window.close(), 1500);
            }
        });
    });
});
