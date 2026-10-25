<?php
/**
 * Кафе «Белоснежка» — Полный пакет данных для публичного сайта
 * Возвращает актуальные настройки, меню, акции, залы и галерею
 */
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Защита от DoS: не более 120 запросов в минуту с одного IP
checkRateLimit('site_data', 120, 60);

$db = getDB();

try {
    // 1. Settings
    $settings = [];
    $rows = $db->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
    foreach ($rows as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }

    // 2. Categories
    $categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();

    // 3. Sections
    $sections = [];
    try {
        $sections = $db->query("SELECT * FROM category_sections ORDER BY sort_order ASC, id ASC")->fetchAll();
    } catch (Exception $e) {
        try {
            $sections = $db->query("SELECT * FROM sections ORDER BY sort_order ASC, id ASC")->fetchAll();
        } catch (Exception $e2) {}
    }

    // 4. Dishes
    $dishes = $db->query("SELECT * FROM dishes ORDER BY sort_order ASC, id ASC")->fetchAll();

    // 5. Promotions
    $promotions = $db->query("SELECT * FROM promotions WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();

    // 6. Halls
    $halls = $db->query("SELECT * FROM halls WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
    foreach ($halls as &$h) {
        $rawFeatures = $h['features'] ?? $h['features_json'] ?? '[]';
        $rawPhotos = $h['photos'] ?? $h['photos_json'] ?? '[]';
        $h['features'] = is_array($rawFeatures) ? $rawFeatures : (json_decode($rawFeatures, true) ?: []);
        $h['photos'] = is_array($rawPhotos) ? $rawPhotos : (json_decode($rawPhotos, true) ?: []);
    }
    unset($h);

    // 7. Gallery
    $gallery = $db->query("SELECT * FROM gallery WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();

    jsonResponse([
        'success' => true,
        'settings' => $settings,
        'categories' => $categories,
        'sections' => $sections,
        'dishes' => $dishes,
        'promotions' => $promotions,
        'halls' => $halls,
        'gallery' => $gallery
    ]);
} catch (Exception $e) {
    error_log("site_data.php error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Не удалось загрузить данные сайта. Пожалуйста, повторите попытку позже.'], 500);
}
