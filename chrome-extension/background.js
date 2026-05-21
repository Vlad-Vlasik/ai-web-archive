// Налаштування вашого сервера
const N8N_WEBHOOK_URL = "https://taina-monorhinous-barrett.ngrok-free.dev/webhook/ai-sync";
const SYNC_TOKEN = "ai_super_secret_token"; // Пароль для захисту вашого n8n

// Ініціалізація локальної бази даних IndexedDB
let db;
const request = indexedDB.open("AI_Archive_DB", 1);

request.onupgradeneeded = (event) => {
    db = event.target.result;
    // Створюємо таблицю (store) для повідомлень
    const store = db.createObjectStore("chats", { keyPath: "id", autoIncrement: true });
    // Індекс для швидкого пошуку невідправлених даних
    store.createIndex("sync_status", "sync_status", { unique: false });
};

request.onsuccess = (event) => {
    db = event.target.result;
    console.log("Локальна база IndexedDB успішно завантажена.");
    // Пробуємо синхронізувати дані при кожному запуску браузера
    syncDataWithServer();
};

// Слухаємо повідомлення від content.js
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.action === "save_chat") {
        saveChatLocally(request.payload);
        sendResponse({ status: "success" });
    }
    return true;
});

// Функція збереження в локальну базу
function saveChatLocally(payload) {
    const transaction = db.transaction(["chats"], "readwrite");
    const store = transaction.objectStore("chats");
    
    // Додаємо статус 0 (Очікує відправки)
    payload.sync_status = 0; 
    payload.saved_at = new Date().toISOString();

    const addRequest = store.add(payload);
    
    addRequest.onsuccess = () => {
        console.log("Чат збережено локально. Запускаємо синхронізацію...");
        syncDataWithServer();
    };
}

// Функція відправки даних на Termux (n8n)
function syncDataWithServer() {
    if (!db) return;

    const transaction = db.transaction(["chats"], "readonly");
    const store = transaction.objectStore("chats");
    const index = store.index("sync_status");
    
    // Шукаємо всі записи зі статусом 0
    const getRequest = index.getAll(0);

    getRequest.onsuccess = async () => {
        const unsyncedChats = getRequest.result;
        if (unsyncedChats.length === 0) return; // Немає чого відправляти

        console.log(`Знайдено ${unsyncedChats.length} чатів для синхронізації.`);

        for (let chat of unsyncedChats) {
            try {
                const response = await fetch(N8N_WEBHOOK_URL, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-Sync-Token": SYNC_TOKEN,
                        // 🔥 Заголовок для обходу екрана блокування Ngrok
                        "ngrok-skip-browser-warning": "true"
                    },
                    body: JSON.stringify(chat)
                });

                if (response.ok) {
                    console.log(`Чат відправлено! Оновлюємо статус...`);
                    updateSyncStatus(chat.id, 2); // 2 = Синхронізовано
                } else {
                    console.error("Помилка сервера n8n:", response.status);
                }
            } catch (error) {
                console.warn("Сервер недоступний (Offline-First працює). Спробуємо пізніше.", error);
                break; // Зупиняємо цикл, якщо немає інтернету/тунелю
            }
        }
    };
}

// Оновлення статусу після успішної відправки
function updateSyncStatus(id, newStatus) {
    const transaction = db.transaction(["chats"], "readwrite");
    const store = transaction.objectStore("chats");
    
    const getReq = store.get(id);
    getReq.onsuccess = () => {
        let data = getReq.result;
        data.sync_status = newStatus;
        store.put(data);
    };
}
