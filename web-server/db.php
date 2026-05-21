<?php
// Шлях до файлу бази даних SQLite. 
// Вона буде автоматично створена у цій самій папці при першому запуску.
$dbPath = __DIR__ . '/ai_database.sqlite';

try {
    // Підключення до бази через PDO (PHP Data Objects)
    $pdo = new PDO("sqlite:" . $dbPath);
    
    // Налаштовуємо PDO для відображення помилок та оптимізації
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Timeout = 5 секунд. Це критично важливо, щоб уникнути помилки "database is locked"
    // якщо n8n записує великий масив даних, а веб-сайт одночасно намагається їх прочитати.
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5); 
    
    // Вмикаємо підтримку зовнішніх ключів (Foreign Keys) для зв'язку таблиць
    $pdo->exec("PRAGMA foreign_keys = ON;");

    // =========================================================================
    // СТВОРЕННЯ ТАБЛИЦЬ (Виконується лише якщо їх ще не існує)
    // =========================================================================
    
    // Таблиця 1: Діалоги
    $pdo->exec("CREATE TABLE IF NOT EXISTS conversations (
        id TEXT PRIMARY KEY, 
        platform TEXT NOT NULL,          -- Наприклад: chatgpt, gemini
        client_type TEXT DEFAULT 'web',  -- web (розширення) або android_app (мобільний парсер)
        title TEXT,
        url TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Таблиця 2: Повідомлення
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        conversation_id TEXT NOT NULL,
        role TEXT NOT NULL,              -- user або ai (assistant)
        content_raw TEXT NOT NULL,       -- Повний текст повідомлення (Markdown)
        timestamp TEXT NOT NULL,         -- Час повідомлення
        hash TEXT,                       -- MD5 хеш тексту (для дедуплікації від мобільного парсера)
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
    )");

    // Таблиця 3: Зображення (Вкладення)
    $pdo->exec("CREATE TABLE IF NOT EXISTS attachments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        message_id INTEGER NOT NULL,
        file_path TEXT NOT NULL,         -- Шлях до файлу в папці ai_images
        FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
    )");

    // Таблиця 4: Тимчасові токени для доступу сторонніх ШІ
    $pdo->exec("CREATE TABLE IF NOT EXISTS share_tokens (
        token TEXT PRIMARY KEY,
        expires_at DATETIME NOT NULL
    )");

} catch (PDOException $e) {
    // Якщо щось пішло не так при підключенні — зупиняємо скрипт
    die("Помилка підключення до бази даних: " . $e->getMessage());
}
?>
