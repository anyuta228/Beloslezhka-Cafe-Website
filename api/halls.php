<?php
/**
 * Кафе «Белоснежка» — Управление залами
 */
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getJsonInput();
$db = getDB();

if ($method === 'GET') {
    $all = !empty($_GET['all']);
    $sql = "SELECT * FROM halls " . ($all ? "" : "WHERE is_active = 1 ") . "ORDER BY sort_order ASC, id ASC";
    $list = $db->query($sql)->fetchAll();
    
    // Декодируем JSON поля для фронтенда
    foreach ($list as &$h) {
        $rawFeatures = $h['features'] ?? $h['features_json'] ?? '[]';
        $rawPhotos = $h['photos'] ?? $h['photos_json'] ?? '[]';
        $h['features'] = is_array($rawFeatures) ? $rawFeatures : (json_decode($rawFeatures, true) ?: []);
        $h['photos'] = is_array($rawPhotos) ? $rawPhotos : (json_decode($rawPhotos, true) ?: []);
    }
    unset($h);

    jsonResponse(['success' => true, 'halls' => $list]);
}

checkAdminAuth();

if ($method === 'POST') {
    $action = $_GET['action'] ?? $input['action'] ?? 'edit';

    if ($action === 'edit') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'error' => 'Не передан ID зала'], 400);

        $name = trim($input['name'] ?? '');
        $capacity = trim($input['capacity'] ?? '');
        $capacityBadge = trim($input['capacity_badge'] ?? '');
        $typeBadge = trim($input['type_badge'] ?? '');
        $description = trim($input['description'] ?? '');
        $img = trim($input['img'] ?? '');
        
        $features = is_array($input['features'] ?? null) ? $input['features'] : (json_decode($input['features'] ?? '[]', true) ?: []);
        $featuresJson = json_encode($features, JSON_UNESCAPED_UNICODE);

        $photos = is_array($input['photos'] ?? null) ? $input['photos'] : (json_decode($input['photos'] ?? '[]', true) ?: []);
        $photosJson = json_encode($photos, JSON_UNESCAPED_UNICODE);

        $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        // Определяем имена колонок в таблице
        try {
            $stmt = $db->prepare("UPDATE halls SET 
                name = ?, capacity = ?, capacity_badge = ?, type_badge = ?, description = ?, img = ?, features = ?, photos = ?, is_active = ? 
                WHERE id = ?");
            $stmt->execute([$name, $capacity, $capacityBadge, $typeBadge, $description, $img, $featuresJson, $photosJson, $isActive, $id]);
        } catch (PDOException $e) {
            // Фолбэк если в существующей таблице нет колонок badges
            try {
                $stmt = $db->prepare("UPDATE halls SET 
                    name = ?, capacity = ?, description = ?, img = ?, features = ?, photos = ?, is_active = ? 
                    WHERE id = ?");
                $stmt->execute([$name, $capacity, $description, $img, $featuresJson, $photosJson, $isActive, $id]);
            } catch (PDOException $e2) {
                $stmt = $db->prepare("UPDATE halls SET 
                    name = ?, capacity = ?, description = ?, features_json = ?, photos_json = ?, is_active = ? 
                    WHERE id = ?");
                $stmt->execute([$name, $capacity, $description, $featuresJson, $photosJson, $isActive, $id]);
            }
        }

        jsonResponse(['success' => true, 'message' => 'Данные зала обновлены']);
    }
}

jsonResponse(['success' => false, 'error' => 'Неизвестное действие'], 400);
