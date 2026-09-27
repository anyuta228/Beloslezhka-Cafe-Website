<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается']);
    exit;
}

$vkToken = 'vk1.a.QHdnDIOLf_OdAuHKoji22YKwZNiky-y0tQgicbBbyfcDBj8--xfCBlFHyc4voxSIOG1JWEQRhfgy2-Pqoi-l-B4GIDU7lyOJi51ZSgoQiKEDjQca9bFGk38BXdvLnWHt0YwANAbdefGjiHtBDdpxvZTmo19F0QYiIBwZY5_Izhr6Ntvk59abN-rGvlSe2isUxDD9nQ2JXPWavZFs96jK9g';
$vkUserId = '710846762';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Пустые данные']);
    exit;
}

$name = htmlspecialchars($input['name'] ?? '');
$phone = htmlspecialchars($input['phone'] ?? $input['contact'] ?? '');
$date = htmlspecialchars($input['date'] ?? 'Не указана');
$time = htmlspecialchars($input['time'] ?? 'Не указано');
$guests = htmlspecialchars((string)($input['guests'] ?? 'Не указано'));
$comment = htmlspecialchars($input['comment'] ?? $input['text'] ?? '—');
$type = htmlspecialchars($input['type'] ?? 'booking');

if (empty($name) || empty($phone)) {
    http_response_code(400);
    echo json_encode(['error' => 'Заполните имя и контактные данные']);
    exit;
}

if ($type === 'complaint' || !empty($input['isComplaint']) || strpos($comment, '[ЖАЛОБА]') !== false) {
    $cleanComment = trim(preg_replace('/^\[ЖАЛОБА\]\s*(Оставить жалобу)?\s*(Ваше имя:[^\n]+\n)?\s*(Телефон или e-mail для связи:[^\n]+\n)?\s*(Суть жалобы или замечания:)?/u', '', $comment));
    if (empty($cleanComment)) {
        $cleanComment = !empty($input['text']) ? trim($input['text']) : $comment;
    }
    $message = "⚠️ [ЖАЛОБА] Оставить жалобу\n\n"
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
    $message = "⭐ [ОТЗЫВ] Новый отзыв от гостя!\n\n"
             . "👤 Имя: {$name}\n"
             . "📞 Контакт: {$phone}\n"
             . "✨ Оценка: {$rating} из 5 {$stars}\n"
             . "💬 Отзыв: {$cleanComment}";
} else {
    $message = "🍽 [БРОНИРОВАНИЕ] Новая заявка на бронь!\n\n"
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