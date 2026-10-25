<?php
/**
 * Кафе «Белоснежка» — Бронирования, отзывы и обращения гостей
 * Сохраняет в базу данных MySQL и отправляет уведомление в VK
 */
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getJsonInput();
$db = getDB();

if ($method === 'POST') {
    $action = $_GET['action'] ?? $input['action'] ?? 'create';

    // 1. Создание новой заявки от гостя (публичный доступ с сайта)
    if ($action === 'create') {
        // Защита от спама и флуда: не более 5 заявок за 10 минут с одного IP
        checkRateLimit('booking_create', 5, 600);

        // Строгая валидация и ограничение длины полей (защита от DoS и переполнения)
        $name = htmlspecialchars(trim(mb_substr($input['name'] ?? '', 0, 100)));
        $rawPhone = trim($input['phone'] ?? $input['contact'] ?? '');
        $phone = htmlspecialchars(trim(mb_substr($rawPhone, 0, 30)));
        $date = htmlspecialchars(trim(mb_substr($input['date'] ?? '', 0, 50)));
        $time = htmlspecialchars(trim(mb_substr($input['time'] ?? '', 0, 30)));
        $guests = htmlspecialchars(trim(mb_substr((string)($input['guests'] ?? ''), 0, 30)));
        $comment = htmlspecialchars(trim(mb_substr($input['comment'] ?? $input['text'] ?? '', 0, 2000)));
        
        $rawType = trim($input['type'] ?? 'booking');
        $allowedTypes = ['booking', 'banquet', 'review', 'complaint', 'table'];
        $type = in_array($rawType, $allowedTypes, true) ? $rawType : 'booking';
        
        $rating = !empty($input['rating']) ? max(1, min(5, (int)$input['rating'])) : null;

        if (empty($name) || empty($phone)) {
            jsonResponse(['success' => false, 'error' => 'Заполните имя и контактные данные'], 400);
        }

        // Проверка корректности телефона (не менее 6 цифр)
        $phoneDigits = preg_replace('/[^\d]/', '', $phone);
        if (strlen($phoneDigits) < 6) {
            jsonResponse(['success' => false, 'error' => 'Укажите корректный номер телефона для связи'], 400);
        }

        // Сохраняем в MySQL
        try {
            $stmt = $db->prepare("INSERT INTO bookings 
                (type, name, phone, booking_date, booking_time, guests, comment, rating, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new')");
            $stmt->execute([$type, $name, $phone, $date, $time, $guests, $comment, $rating]);
            $bookingId = $db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Booking insert error: " . $e->getMessage());
            $bookingId = null;
        }

        // Формируем текст уведомления и отправляем в VK (конфиденциальные реквизиты берутся из .env)
        $vkToken = defined('VK_ACCESS_TOKEN') ? VK_ACCESS_TOKEN : '';
        $vkUserId = defined('VK_USER_ID') ? VK_USER_ID : '';

        if ($type === 'complaint' || !empty($input['isComplaint']) || strpos($comment, '[ЖАЛОБА]') !== false) {
            $message = "⚠️ [ЖАЛОБА №{$bookingId}] Обращение гостя\n\n"
                     . "👤 Имя: {$name}\n"
                     . "📞 Телефон/e-mail: {$phone}\n"
                     . "💬 Сообщение: {$comment}";
        } else if ($type === 'review' || !empty($input['isReview']) || strpos($comment, '[ОТЗЫВ]') !== false) {
            $stars = str_repeat('⭐', max(1, min(5, (int)($rating ?: 5))));
            $message = "⭐ [ОТЗЫВ №{$bookingId}] Новый отзыв!\n\n"
                     . "👤 Имя: {$name}\n"
                     . "📞 Контакт: {$phone}\n"
                     . "✨ Оценка: {$rating} из 5 {$stars}\n"
                     . "💬 Отзыв: {$comment}";
        } else {
            $message = "🍽 [БРОНИРОВАНИЕ №{$bookingId}] Новая заявка на бронь!\n\n"
                     . "👤 Имя: {$name}\n"
                     . "📞 Телефон: {$phone}\n"
                     . "📅 Дата: " . ($date ?: 'Не указана') . "\n"
                     . "⏰ Время: " . ($time ?: 'Не указано') . "\n"
                     . "👥 Гостей: " . ($guests ?: 'Не указано') . "\n"
                     . "💬 Комментарий: " . ($comment ?: '—');
        }

        $params = [
            'access_token' => $vkToken,
            'user_id'      => $vkUserId,
            'message'      => $message,
            'random_id'    => rand(100000, 999999999),
            'v'            => '5.131'
        ];

        // Отправка запроса в VK
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.vk.com/method/messages.send?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $vkRes = curl_exec($ch);
        curl_close($ch);

        jsonResponse([
            'success'    => true,
            'booking_id' => $bookingId,
            'message'    => 'Заявка успешно принята'
        ]);
    }

    // Для администратора
    checkAdminAuth();

    if ($action === 'update_status') {
        $id = (int)($input['id'] ?? 0);
        $status = trim($input['status'] ?? 'new');
        if (!$id) jsonResponse(['success' => false, 'error' => 'Не передан ID'], 400);

        $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        jsonResponse(['success' => true, 'message' => 'Статус обновлен']);
    }

    if ($action === 'update_note') {
        $id = (int)($input['id'] ?? 0);
        $note = trim($input['admin_note'] ?? '');
        $stmt = $db->prepare("UPDATE bookings SET admin_note = ? WHERE id = ?");
        $stmt->execute([$note, $id]);
        jsonResponse(['success' => true, 'message' => 'Заметка сохранена']);
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM bookings WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Заявка удалена']);
    }
}

if ($method === 'GET') {
    checkAdminAuth();

    $status = $_GET['status'] ?? null;
    $type = $_GET['type'] ?? null;
    $limit = (int)($_GET['limit'] ?? 100);

    $sql = "SELECT * FROM bookings ";
    $conditions = [];
    $params = [];

    if ($status && $status !== 'all') {
        $conditions[] = "status = ?";
        $params[] = $status;
    }
    if ($type && $type !== 'all') {
        $conditions[] = "type = ?";
        $params[] = $type;
    }

    if (!empty($conditions)) {
        $sql .= "WHERE " . implode(" AND ", $conditions) . " ";
    }
    $sql .= "ORDER BY id DESC LIMIT " . $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $list = $stmt->fetchAll();

    // Считаем количество новых заявок
    $newCount = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status = 'new'")->fetchColumn();

    jsonResponse([
        'success'   => true,
        'new_count' => $newCount,
        'bookings'  => $list
    ]);
}
