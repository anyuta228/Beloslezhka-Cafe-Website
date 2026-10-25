<?php
/**
 * Кафе «Белоснежка» — Управление фотогалереей
 */
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getJsonInput();
$db = getDB();

if ($method === 'GET') {
    $category = $_GET['category'] ?? null;
    $all = !empty($_GET['all']);

    $sql = "SELECT * FROM gallery ";
    $params = [];
    $conds = [];

    if (!$all) {
        $conds[] = "is_active = 1";
    }
    if ($category && $category !== 'Все') {
        $conds[] = "category = ?";
        $params[] = $category;
    }
    if (!empty($conds)) {
        $sql .= "WHERE " . implode(" AND ", $conds) . " ";
    }
    $sql .= "ORDER BY sort_order ASC, id ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $list = $stmt->fetchAll();

    jsonResponse(['success' => true, 'gallery' => $list]);
}

checkAdminAuth();

if ($method === 'POST') {
    $action = $_GET['action'] ?? $input['action'] ?? '';

    if ($action === 'add') {
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $category = trim($input['category'] ?? 'Интерьер');
        $img = trim($input['img'] ?? '');
        if (!$img) jsonResponse(['success' => false, 'error' => 'Укажите фото'], 400);

        try {
            $stmt = $db->prepare("INSERT INTO gallery (title, description, category, img) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $description, $category, $img]);
        } catch (PDOException $e) {
            $stmt = $db->prepare("INSERT INTO gallery (title, category, img) VALUES (?, ?, ?)");
            $stmt->execute([$title, $category, $img]);
        }

        jsonResponse(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Фото добавлено в галерею']);
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM gallery WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Фото удалено']);
    }

    if ($action === 'edit') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'error' => 'Не передан ID'], 400);

        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $category = trim($input['category'] ?? 'Интерьер');
        $img = trim($input['img'] ?? '');

        try {
            if ($img) {
                $stmt = $db->prepare("UPDATE gallery SET title = ?, description = ?, category = ?, img = ? WHERE id = ?");
                $stmt->execute([$title, $description, $category, $img, $id]);
            } else {
                $stmt = $db->prepare("UPDATE gallery SET title = ?, description = ?, category = ? WHERE id = ?");
                $stmt->execute([$title, $description, $category, $id]);
            }
        } catch (PDOException $e) {
            if ($img) {
                $stmt = $db->prepare("UPDATE gallery SET title = ?, category = ?, img = ? WHERE id = ?");
                $stmt->execute([$title, $category, $img, $id]);
            } else {
                $stmt = $db->prepare("UPDATE gallery SET title = ?, category = ? WHERE id = ?");
                $stmt->execute([$title, $category, $id]);
            }
        }

        jsonResponse(['success' => true, 'message' => 'Информация обновлена']);
    }
}

jsonResponse(['success' => false, 'error' => 'Неизвестное действие'], 400);
