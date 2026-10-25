<?php
/**
 * Кафе «Белоснежка» — Безопасная загрузка изображений
 */
require_once __DIR__ . '/config.php';

checkAdminAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Метод не поддерживается'], 405);
}

if (empty($_FILES['file'])) {
    jsonResponse(['success' => false, 'error' => 'Файл не передан'], 400);
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'error' => 'Ошибка при загрузке файла (код ' . $file['error'] . ')'], 400);
}

// Проверка размера (макс 12 МБ)
if ($file['size'] > 12 * 1024 * 1024) {
    jsonResponse(['success' => false, 'error' => 'Размер файла превышает 12 МБ'], 400);
}

// Проверка типа файла
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/avif' => 'avif'
];

if (!isset($allowedMimes[$mime])) {
    jsonResponse(['success' => false, 'error' => 'Недопустимый формат файла. Разрешены только JPG, PNG, WEBP, AVIF.'], 400);
}

$ext = $allowedMimes[$mime];
$uploadDir = __DIR__ . '/../uploads/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$targetPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    jsonResponse(['success' => false, 'error' => 'Не удалось сохранить файл на сервере'], 500);
}

$publicUrl = '/uploads/' . $filename;

jsonResponse([
    'success'  => true,
    'url'      => $publicUrl,
    'filename' => $filename,
    'message'  => 'Файл успешно загружен'
]);
