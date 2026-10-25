<?php
require_once __DIR__ . '/api/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается']);
    exit;
}

// Защита от спама: не более 5 заявок за 10 минут
checkRateLimit('send_booking', 5, 600);

$vkToken = defined('VK_ACCESS_TOKEN') ? VK_ACCESS_TOKEN : '';
$vkUserId = defined('VK_USER_ID') ? VK_USER_ID : '';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Пустые данные']);
    exit;
}

$name = htmlspecialchars(trim(mb_substr($input['name'] ?? '', 0, 100)));
$rawPhone = trim($input['phone'] ?? $input['contact'] ?? '');
$phone = htmlspecialchars(trim(mb_substr($rawPhone, 0, 30)));
$date = htmlspecialchars(trim(mb_substr($input['date'] ?? 'Не указана', 0, 50)));
$time = htmlspecialchars(trim(mb_substr($input['time'] ?? 'Не указано', 0, 30)));
$guests = htmlspecialchars(trim(mb_substr((string)($input['guests'] ?? 'Не указано'), 0, 30)));
$comment = htmlspecialchars(trim(mb_substr($input['comment'] ?? $input['text'] ?? '—', 0, 2000)));

$rawType = trim($input['type'] ?? 'booking');
$allowedTypes = ['booking', 'banquet', 'review', 'complaint', 'table'];
$type = in_array($rawType, $allowedTypes, true) ? $rawType : 'booking';

if (empty($name) || empty($phone)) {
    http_response_code(400);
    echo json_encode(['error' => 'Заполните имя и контактные данные']);
    exit;
}

// Валидация телефона (минимум 6 цифр)
$phoneDigits = preg_replace('/[^\d]/', '', $phone);
if (strlen($phoneDigits) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Укажите корректный номер телефона']);
    exit;
}

// 0. Сохранение заявки в базу данных MySQL (bel7916394_db)
$bookingId = null;
try {
    if (file_exists(__DIR__ . '/api/config.php')) {
        require_once __DIR__ . '/api/config.php';
        $db = getDB();
        $dbType = ($type === 'complaint' || !empty($input['isComplaint'])) ? 'complaint' : (($type === 'review' || !empty($input['isReview'])) ? 'review' : 'booking');
        $stmt = $db->prepare("INSERT INTO bookings (type, name, phone, booking_date, booking_time, guests, comment, rating, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new')");
        $stmt->execute([
            $dbType,
            $name,
            $phone,
            ($date !== 'Не указана' ? $date : null),
            ($time !== 'Не указано' ? $time : null),
            ($guests !== 'Не указано' ? $guests : null),
            $comment,
            (!empty($input['rating']) ? (int)$input['rating'] : null)
        ]);
        $bookingId = $db->lastInsertId();
    }
} catch (Exception $dbEx) {
    // Резервный режим: если база временно недоступна, не блокируем отправку в VK
    error_log('DB booking insert error: ' . $dbEx->getMessage());
}

    $bIdStr = $bookingId ? " №{$bookingId}" : "";
    if ($type === 'complaint' || !empty($input['isComplaint']) || strpos($comment, '[ЖАЛОБА]') !== false) {
    $cleanComment = trim(preg_replace('/^\[ЖАЛОБА\]\s*(Оставить жалобу)?\s*(Ваше имя:[^\n]+\n)?\s*(Телефон или e-mail для связи:[^\n]+\n)?\s*(Суть жалобы или замечания:)?/u', '', $comment));
    if (empty($cleanComment)) {
        $cleanComment = !empty($input['text']) ? trim($input['text']) : $comment;
    }
    $message = "⚠️ [ЖАЛОБА{$bIdStr}] Оставить жалобу\n\n"
             . "👤 Ваше имя: {$name}\n"
             . "📞 Телефон или e-mail для связи: {$phone}\n"
             . "💬 Суть жалобы или замечания: {$cleanComment}";
} else if ($type === 'review' || !empty($input['isReview']) || strpos($comment, '[ОТЗЫВ]') !== false) {
    $rating = htmlspecialchars((string)($input['rating'] ?? '5'));
    $stars = str_repeat('⭐', max(1, min(5, (int)$rating)));
    $cleanComment = trim(preg_replace('/^\[ОТЗЫВ\]\s*(Оценка:[^\n]+\n)?/u', '', $comment));
    if (empty($cleanComment)) {
        $cleanComment = $comment;
    }
    $message = "⭐ [ОТЗЫВ{$bIdStr}] Новый отзыв от гостя!\n\n"
             . "👤 Имя: {$name}\n"
             . "📞 Контакт: {$phone}\n"
             . "✨ Оценка: {$rating} из 5 {$stars}\n"
             . "💬 Отзыв: {$cleanComment}";
} else {
    $message = "🍽 [БРОНИРОВАНИЕ{$bIdStr}] Новая заявка на бронь!\n\n"
             . "👤 Имя: {$name}\n"
             . "📞 Телефон: {$phone}\n"
             . "📅 Дата: {$date}\n"
             . "⏰ Время: {$time}\n"
             . "👥 Гостей: {$guests}\n"
             . "💬 Комментарий: {$comment}";
}

$params = [
    'access_token' => $vkToken,
    'user_id'      => $vkUserId,
    'message'      => $message,
    'random_id'    => rand(100000, 999999999),
    'v'            => '5.131'
];

$url = 'https://api.vk.com/method/messages.send?' . http_build_query($params);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (isset($result['error'])) {
    http_response_code(500);
    echo json_encode(['error' => $result['error']['error_msg']]);
} else {
    echo json_encode(['success' => true]);
}