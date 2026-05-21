<?php
// Підключаємо наш файл бази даних
require 'db.php';

// Вказуємо браузеру, що ми віддаємо саме JSON
header('Content-Type: application/json; charset=utf-8');

// Отримуємо параметр 'action' з URL (наприклад: api.php?action=get_conversations)
$action = $_GET['action'] ?? '';

// Ендпоінт 1: Отримання списку всіх діалогів (для бокової панелі)
if ($action === 'get_conversations') {
    try {
        // Витягуємо чати, сортуючи від найновіших до найстаріших
        $stmt = $pdo->query("SELECT id, title, platform, client_type, created_at FROM conversations ORDER BY created_at DESC");
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(["status" => "success", "data" => $conversations]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
} 

// Ендпоінт 2: Отримання конкретного діалогу з усіма повідомленнями
elseif ($action === 'get_messages') {
    $conversation_id = $_GET['conversation_id'] ?? '';
    
    if (empty($conversation_id)) {
        echo json_encode(["status" => "error", "message" => "Відсутній ID чату"]);
        exit;
    }

    try {
        // 1. Спочатку перевіряємо, чи існує такий чат, і беремо його метадані
        $stmt_chat = $pdo->prepare("SELECT * FROM conversations WHERE id = ?");
        $stmt_chat->execute([$conversation_id]);
        $chat = $stmt_chat->fetch(PDO::FETCH_ASSOC);

        if (!$chat) {
            echo json_encode(["status" => "error", "message" => "Чат не знайдено"]);
            exit;
        }

        // 2. Витягуємо всі повідомлення цього чату (хронологічно)
        $stmt_msg = $pdo->prepare("SELECT id, role, content_raw, timestamp FROM messages WHERE conversation_id = ? ORDER BY timestamp ASC");
        $stmt_msg->execute([$conversation_id]);
        $messages = $stmt_msg->fetchAll(PDO::FETCH_ASSOC);

        // 3. Підтягуємо картинки (якщо вони є) до кожного повідомлення
        foreach ($messages as &$msg) {
            $stmt_att = $pdo->prepare("SELECT file_path FROM attachments WHERE message_id = ?");
            $stmt_att->execute([$msg['id']]);
            // Отримуємо масив шляхів до файлів (наприклад, ['ai_images/img1.png'])
            $msg['attachments'] = $stmt_att->fetchAll(PDO::FETCH_COLUMN); 
        }

        // Віддаємо все разом єдиним красивим пакетом
        echo json_encode([
            "status" => "success", 
            "chat" => $chat,
            "messages" => $messages
        ]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// Якщо передали невідомий action
else {
    echo json_encode(["status" => "error", "message" => "Невідома дія або параметр action відсутній"]);
    exit;
}
?>
