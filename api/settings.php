<?php
/**
 * Кафе «Белоснежка» — Настройки сайта
 */
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getJsonInput();
$db = getDB();

if ($method === 'GET') {
    $rows = $db->query("SELECT setting_key, setting_value, description FROM site_settings")->fetchAll();
    $settings = [];
    foreach ($rows as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
    jsonResponse(['success' => true, 'settings' => $settings]);
}

checkAdminAuth();

if ($method === 'POST') {
    $action = $_GET['action'] ?? $input['action'] ?? 'update';

    if ($action === 'update') {
        $data = $input['settings'] ?? $input;
        if (is_array($data)) {
            $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            foreach ($data as $key => $val) {
                if ($key !== 'action' && is_string($key)) {
                    $stmt->execute([$key, (string)$val]);
                }
            }
        }
        jsonResponse(['success' => true, 'message' => 'Настройки сохранены']);
    }
}

jsonResponse(['success' => false, 'error' => 'Неизвестное действие'], 400);
