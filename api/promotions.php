<?php
/**
 * Кафе «Белоснежка» — Управление акциями и спецпредложениями
 */
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getJsonInput();
$db = getDB();

if ($method === 'GET') {
    $all = !empty($_GET['all']);
    $sql = "SELECT * FROM promotions " . ($all ? "" : "WHERE is_active = 1 ") . "ORDER BY sort_order ASC, id ASC";
    $list = $db->query($sql)->fetchAll();
    jsonResponse(['success' => true, 'promotions' => $list]);
}

checkAdminAuth();

if ($method === 'POST') {
    $action = $_GET['action'] ?? $input['action'] ?? '';

    if ($action === 'add') {
        $category = trim($input['category'] ?? 'birthday');
        $tag = trim($input['tag'] ?? '');
        $badge = trim($input['badge'] ?? ($input['discount'] ?? ''));
        $title = trim($input['title'] ?? '');
        $subtitle = trim($input['subtitle'] ?? ($input['short_desc'] ?? ''));
        $description = trim($input['description'] ?? ($input['full_desc'] ?? ''));
        $bonusLabel = trim($input['bonus_label'] ?? 'Условие акции:');
        $bonus = trim($input['bonus'] ?? ($input['conditions'] ?? ''));
        $actionLabel = trim($input['action_label'] ?? 'Забронировать');
        $actionType = trim($input['action_type'] ?? 'table');
        $img = !empty($input['img']) ? trim($input['img']) : null;
        $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;
        $sortOrder = isset($input['sort_order']) ? (int)$input['sort_order'] : 0;

        if (empty($title)) {
            jsonResponse(['success' => false, 'error' => 'Укажите название акции'], 400);
        }

        $stmt = $db->prepare("INSERT INTO promotions 
            (category, tag, badge, title, subtitle, description, bonus_label, bonus, action_label, action_type, img, is_active, sort_order) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $category, $tag, $badge, $title, $subtitle, $description, $bonusLabel, $bonus, $actionLabel, $actionType, $img, $isActive, $sortOrder
        ]);

        jsonResponse(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Акция добавлена']);
    }

    if ($action === 'edit') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'error' => 'Не передан ID'], 400);

        $category = trim($input['category'] ?? 'birthday');
        $tag = trim($input['tag'] ?? '');
        $badge = trim($input['badge'] ?? ($input['discount'] ?? ''));
        $title = trim($input['title'] ?? '');
        $subtitle = trim($input['subtitle'] ?? ($input['short_desc'] ?? ''));
        $description = trim($input['description'] ?? ($input['full_desc'] ?? ''));
        $bonusLabel = trim($input['bonus_label'] ?? 'Условие акции:');
        $bonus = trim($input['bonus'] ?? ($input['conditions'] ?? ''));
        $actionLabel = trim($input['action_label'] ?? 'Забронировать');
        $actionType = trim($input['action_type'] ?? 'table');
        $img = !empty($input['img']) ? trim($input['img']) : null;
        $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        $stmt = $db->prepare("UPDATE promotions SET 
            category = ?, tag = ?, badge = ?, title = ?, subtitle = ?, 
            description = ?, bonus_label = ?, bonus = ?, action_label = ?, action_type = ?, 
            img = ?, is_active = ? WHERE id = ?");
        $stmt->execute([
            $category, $tag, $badge, $title, $subtitle, $description, $bonusLabel, $bonus, $actionLabel, $actionType, $img, $isActive, $id
        ]);

        jsonResponse(['success' => true, 'message' => 'Акция обновлена']);
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM promotions WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Акция удалена']);
    }
}

jsonResponse(['success' => false, 'error' => 'Неизвестное действие'], 400);
