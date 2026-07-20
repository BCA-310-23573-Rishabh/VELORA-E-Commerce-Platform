<?php
require_once 'config.php';

$adminEmail = envValue('VELORA_ADMIN_EMAIL', 'admin@example.com');
$adminPassword = envValue('VELORA_ADMIN_PASSWORD', '');

if ($adminPassword === '') {
    echo 'Set VELORA_ADMIN_PASSWORD before running this script.';
    exit;
}

$db = getDB();

$stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
$stmt->bind_param('s', $adminEmail);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $password = password_hash($adminPassword, PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT INTO users (first_name, last_name, email, password, is_admin) VALUES (?, ?, ?, ?, 1)');
    $first = 'Admin';
    $last = 'User';
    $stmt->bind_param('ssss', $first, $last, $adminEmail, $password);
    $stmt->execute();
    echo 'Admin user created: ' . htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8');
} else {
    echo 'Admin user already exists';
}
$stmt->close();
$db->close();
?>