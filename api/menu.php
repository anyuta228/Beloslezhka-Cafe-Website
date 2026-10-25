<?php
/**
 * Кафе «Белоснежка» — Управление меню (категории, секции, блюда)
 */
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getJsonInput();
$db = getDB();

if ($method === 'GET') {
    $categoryFilter = $_GET['category'] ?? null;
    $all = !empty($_GET['all']); // если admin запрашивает включая скрытые позиции

    // 1. Получаем категории
    $catSql = "SELECT * FROM categories " . ($all ? "" : "WHERE is_active = 1 ") . "ORDER BY sort_order ASC, id ASC";
    $cats = $db->query($catSql)->fetchAll();

    // 2. Получаем секции
    $sections = [];
    try {
        $secSql = "SELECT * FROM category_sections ORDER BY sort_order ASC, id ASC";
        $sections = $db->query($secSql)->fetchAll();
    } catch (Exception $e) {
        try {
            $secSql = "SELECT * FROM sections ORDER BY sort_order ASC, id ASC";
            $sections = $db->query($secSql)->fetchAll();
        } catch (Exception $e2) {}
    }
    $sectionsByCat = [];
    foreach ($sections as $s) {
        $catRef = $s['category_ref'] ?? $s['category_id'] ?? '';
        if ($catRef) {
            $sectionsByCat[$catRef][] = $s;
        }
    }

    // 3. Получаем блюда
    $dishSql = "SELECT * FROM dishes ";
    $params = [];
    $conditions = [];
    if (!$all) {
        $conditions[] = "is_available = 1";
    }
    if ($categoryFilter) {
        $conditions[] = "category_ref = ?";
        $params[] = $categoryFilter;
    }
    if (!empty($conditions)) {
        $dishSql .= "WHERE " . implode(" AND ", $conditions) . " ";
    }
    $dishSql .= "ORDER BY sort_order ASC, id ASC";

    $stmt = $db->prepare($dishSql);
    $stmt->execute($params);
    $dishes = $stmt->fetchAll();

    // Форматируем для удобного использования
    $dishesByCatAndSec = [];
    foreach ($dishes as $d) {
        $secKey = $d['section_id'] ? (string)$d['section_id'] : 'default';
        $dishesByCatAndSec[$d['category_ref']][$secKey][] = $d;
    }

    jsonResponse([
        'success'           => true,
        'categories'        => $cats,
        'sections'          => $sections,
        'dishes'            => $dishes,
        'dishesByCatAndSec' => $dishesByCatAndSec
    ]);
}

// Все модифицирующие запросы требуют прав админа
checkAdminAuth();

$action = $_GET['action'] ?? $input['action'] ?? '';

if ($method === 'POST') {
    // Добавить блюдо
    if ($action === 'add_dish') {
        $categoryRef = trim($input['category_ref'] ?? '');
        $name = trim($input['name'] ?? '');
        $rawPrice = $input['price'] ?? 0;
        $price = (int)$rawPrice;
        if ($price === 0 && is_string($rawPrice)) {
            preg_match('/\d+/', str_replace(' ', '', $rawPrice), $matches);
            if (!empty($matches[0])) {
                $price = (int)$matches[0];
            }
        }
        $priceText = !empty($input['price_text']) ? trim($input['price_text']) : '';
        if (empty($priceText)) {
            $rawStr = (string)$rawPrice;
            if (mb_stripos($rawStr, 'чел') !== false) {
                $priceText = (mb_stripos($rawStr, 'от') !== false ? 'от ' : '') . $price . ' ₽/чел.';
            } elseif (mb_stripos($rawStr, 'от') !== false) {
                $priceText = 'от ' . $price . ' ₽';
            } else {
                $priceText = $price . ' ₽';
            }
        }
        $price = max(0, min(100000, $price));
        $name = mb_substr($name, 0, 150);
        $weight = mb_substr(trim($input['weight'] ?? ''), 0, 50);
        $sectionId = !empty($input['section_id']) ? (int)$input['section_id'] : null;
        $sectionTitle = mb_substr(trim($input['section_title'] ?? ''), 0, 100);
        $note = mb_substr(trim($input['note'] ?? ''), 0, 500);
        $description = mb_substr(trim($input['description'] ?? ''), 0, 2000);
        $tag = mb_substr(trim($input['tag'] ?? ''), 0, 50);
        $img = mb_substr(trim($input['img'] ?? ''), 0, 255);
        $isAvailable = isset($input['is_available']) ? (int)$input['is_available'] : 1;

        if (empty($categoryRef) || empty($name)) {
            jsonResponse(['success' => false, 'error' => 'Укажите категорию и название блюда'], 400);
        }

        $stmt = $db->prepare("INSERT INTO dishes 
            (category_ref, section_id, section_title, name, weight, price, price_text, note, description, tag, img, is_available) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $categoryRef, $sectionId, $sectionTitle, $name, $weight, $price, $priceText, $note, $description, $tag, $img, $isAvailable
        ]);
        $dishId = $db->lastInsertId();

        jsonResponse(['success' => true, 'id' => $dishId, 'message' => 'Блюдо успешно добавлено']);
    }

    // Редактировать блюдо
    if ($action === 'edit_dish') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'error' => 'Не передан ID блюда'], 400);
        }

        $name = trim($input['name'] ?? '');
        $rawPrice = $input['price'] ?? 0;
        $price = (int)$rawPrice;
        if ($price === 0 && is_string($rawPrice)) {
            preg_match('/\d+/', str_replace(' ', '', $rawPrice), $matches);
            if (!empty($matches[0])) {
                $price = (int)$matches[0];
            }
        }
        $priceText = !empty($input['price_text']) ? trim($input['price_text']) : '';
        if (empty($priceText)) {
            $rawStr = (string)$rawPrice;
            if (mb_stripos($rawStr, 'чел') !== false) {
                $priceText = (mb_stripos($rawStr, 'от') !== false ? 'от ' : '') . $price . ' ₽/чел.';
            } elseif (mb_stripos($rawStr, 'от') !== false) {
                $priceText = 'от ' . $price . ' ₽';
            } else {
                $priceText = $price . ' ₽';
            }
        }
        $price = max(0, min(100000, $price));
        $name = mb_substr($name, 0, 150);
        $weight = mb_substr(trim($input['weight'] ?? ''), 0, 50);
        $categoryRef = mb_substr(trim($input['category_ref'] ?? ''), 0, 50);
        $sectionId = !empty($input['section_id']) ? (int)$input['section_id'] : null;
        $sectionTitle = mb_substr(trim($input['section_title'] ?? ''), 0, 100);
        $note = mb_substr(trim($input['note'] ?? ''), 0, 500);
        $description = mb_substr(trim($input['description'] ?? ''), 0, 2000);
        $tag = mb_substr(trim($input['tag'] ?? ''), 0, 50);
        $img = mb_substr(trim($input['img'] ?? ''), 0, 255);
        $isAvailable = isset($input['is_available']) ? (int)$input['is_available'] : 1;

        $stmt = $db->prepare("UPDATE dishes SET 
            category_ref = ?, section_id = ?, section_title = ?, name = ?, weight = ?, 
            price = ?, price_text = ?, note = ?, description = ?, tag = ?, img = ?, is_available = ? 
            WHERE id = ?");
        $stmt->execute([
            $categoryRef, $sectionId, $sectionTitle, $name, $weight, $price, $priceText, $note, $description, $tag, $img, $isAvailable, $id
        ]);

        jsonResponse(['success' => true, 'message' => 'Блюдо успешно обновлено']);
    }

    // Быстрое переключение наличия (стоп-лист)
    if ($action === 'toggle_available') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'error' => 'Не передан ID'], 400);

        $stmt = $db->prepare("UPDATE dishes SET is_available = IF(is_available = 1, 0, 1) WHERE id = ?");
        $stmt->execute([$id]);

        $check = $db->prepare("SELECT is_available FROM dishes WHERE id = ?");
        $check->execute([$id]);
        $val = (int)$check->fetchColumn();

        jsonResponse(['success' => true, 'is_available' => $val]);
    }

    // Удалить блюдо
    if ($action === 'delete_dish') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'error' => 'Не передан ID'], 400);

        $stmt = $db->prepare("DELETE FROM dishes WHERE id = ?");
        $stmt->execute([$id]);

        jsonResponse(['success' => true, 'message' => 'Блюдо удалено']);
    }

    // Добавить подсекцию
    if ($action === 'add_section') {
        $catRef = trim($input['category_ref'] ?? '');
        $title = trim($input['title'] ?? '');
        if (!$catRef || !$title) jsonResponse(['success' => false, 'error' => 'Заполните поля'], 400);

        $stmt = $db->prepare("INSERT INTO category_sections (category_ref, title) VALUES (?, ?)");
        $stmt->execute([$catRef, $title]);
        jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
    }
}

jsonResponse(['success' => false, 'error' => 'Неизвестное действие'], 400);
