// Перетворює картинку зі сторінки у формат Base64
async function getBase64ImageFromUrl(imageUrl) {
    try {
        const response = await fetch(imageUrl);
        const blob = await response.blob();
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    } catch (error) {
        console.error("Помилка конвертації зображення:", error);
        return null;
    }
}

// Головна функція збору даних
async function parseChat() {
    console.log("Парсинг чату запущено...");
    
    let messages = [];
    
    // Заглушка: поки що беремо всі елементи 'article' (зазвичай це блоки повідомлень)
    // Згодом ми підключимо сюди файл rules.json для точного пошуку
    const chatElements = document.querySelectorAll('article'); 
    
    for (let el of chatElements) {
        // Витягуємо текст
        const text = el.innerText || "";
        
        // Витягуємо картинки з цього повідомлення
        const images = el.querySelectorAll('img');
        let base64Images = [];
        
        for (let img of images) {
            // Фільтруємо лише реальні зображення (ігноруємо іконки svg/base64)
            if (img.src && img.src.startsWith('http')) {
                const base64 = await getBase64ImageFromUrl(img.src);
                if (base64) base64Images.push(base64);
            }
        }
        
        messages.push({
            role: "unknown", // Тимчасово. Роль буде визначатися за селекторами
            content_raw: text,
            images: base64Images,
            timestamp: new Date().toISOString()
        });
    }
    
    // Відправляємо зібраний масив у фоновий скрипт
    chrome.runtime.sendMessage({
        action: "save_chat",
        payload: {
            platform: window.location.hostname,
            url: window.location.href,
            messages: messages
        }
    });
    
    console.log(`Зібрано ${messages.length} повідомлень і відправлено на збереження.`);
}

// Слухаємо команду "Старт" від майбутнього Popup-вікна
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.action === "start_parsing") {
        parseChat();
        sendResponse({ status: "started" });
    }
    return true; // Вказує на асинхронну відповідь
});
