<?php
require_once '../../config.php';

header('Content-Type: application/json');
startSession();
requireAdmin();

$uploadDir = realpath(__DIR__ . '/../../Images');
if ($uploadDir === false) {
    $uploadDir = __DIR__ . '/../../Images';
    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to create upload directory']);
        exit;
    }
}

$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];
$maxSize = 2 * 1024 * 1024;
$imagePaths = [];

if (!empty($_FILES['images']['name'])) {
    foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
        if (!isset($_FILES['images']['error'][$key]) || $_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) {
            continue;
        }
        if (!is_uploaded_file($tmpName)) {
            continue;
        }

        $size = @filesize($tmpName);
        if ($size === false || $size > $maxSize) {
            continue;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $tmpName) : null;
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!in_array($mimeType, $allowedTypes, true)) {
            continue;
        }

        $originalName = basename($_FILES['images']['name'][$key]);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $safeName = bin2hex(random_bytes(8)) . '_' . time() . ($extension ? '.' . $extension : '');
        $targetFile = $uploadDir . DIRECTORY_SEPARATOR . $safeName;

        if (move_uploaded_file($tmpName, $targetFile)) {
            $imagePaths[] = 'Images/' . $safeName;
        }
    }
}

echo json_encode([
    'success' => true,
    'images' => $imagePaths
]);
?>