<?php
require 'db.php';

// 1. Приймаємо вхідні дані (JSON)
$jsonRaw = file_get_contents('php://input');
$data = json_decode($jsonRaw, true);

if (!$data) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "Невірний формат JSON"]));
}

// 2. Перевіряємо секретний токен (Безпека)
// Функція getallheaders() може не працювати в деяких конфігураціях, тому шукаємо і в $_SERVER
$headers = getallheaders();
$token = $headers['X-Sync-Token'] ?? $_SERVER['HTTP_X_SYNC_TOKEN'] ?? '';

if ($token !== 'ai_super_secret_token') {
    http_response_code(403);
    die(json_encode(["status" => "error", "message" => "Доступ заборонено: невірний токен"]));
}

// 3. Збираємо метадані
$platform = $data['platform'] ?? 'unknown';
$url = $data['url'] ?? '';
$messages = $data['messages'] ?? [];

// Генеруємо унікальний ID та назву для цього чату
$conversation_id = uniqid('chat_');
// Робимо красиву назву, наприклад "chatgpt.com" -> "Діалог з Chatgpt"
$title = "Діалог з " . ucfirst(str_replace(['www.', '.com'], '', $platform)); 

try {
    // Починаємо транзакцію (щоб якщо буде помилка, нічого частково не записалося)
    $pdo->beginTransaction();

    // 4. Записуємо сам чат
    $stmt = $pdo->prepare("INSERT INTO conversations (id, platform, title, url, created_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$conversation_id, $platform, $title, $url, date('Y-m-d H:i:s')]);

    $stmt_msg = $pdo->prepare("INSERT INTO messages (conversation_id, role, content_raw, timestamp) VALUES (?, ?, ?, ?)");
    $stmt_att = $pdo->prepare("INSERT INTO attachments (message_id, file_path) VALUES (?, ?)");

    // 5. Перебираємо повідомлення
    foreach ($messages as $msg) {
        $role = $msg['role'] ?? 'unknown';
        $content = $msg['content_raw'] ?? '';
        $timestamp = $msg['timestamp'] ?? date('c');

        $stmt_msg->execute([$conversation_id, $role, $content, $timestamp]);
        $message_id = $pdo->lastInsertId(); // Отримуємо ID щойно створеного повідомлення

        // 6. Якщо є картинки — розкодовуємо Base64 і зберігаємо як файли
        if (!empty($msg['images']) && is_array($msg['images'])) {
            foreach ($msg['images'] as $base64) {
                // Витягуємо тип (png, jpeg) та самі дані
                if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
                    $dataWithoutPrefix = substr($base64, strpos($base64, ',') + 1);
                    $type = strtolower($type[1]);
                    $dataDecoded = base64_decode($dataWithoutPrefix);

                    if ($dataDecoded !== false) {
                        // Генеруємо унікальне ім'я для файлу
                        $filename = 'ai_images/' . uniqid('img_') . '.' . $type;
                        // Зберігаємо файл у папку
                        file_put_contents(__DIR__ . '/' . $filename, $dataDecoded);
                        
                        // Записуємо шлях до бази
                        $stmt_att->execute([$message_id, $filename]);
                    }
                }
            }
        }
    }

    $pdo->commit();
    echo json_encode(["status" => "success", "message" => "Чат успішно збережено", "id" => $conversation_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
