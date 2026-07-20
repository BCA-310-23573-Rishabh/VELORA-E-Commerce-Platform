<?php
$db = new mysqli('localhost', 'root', '', 'velora_db');
if ($db->connect_error) {
    die('Connection failed: ' . $db->connect_error);
}
$res = $db->query('SELECT id,name,image,hover_image FROM products ORDER BY id DESC LIMIT 10');
while ($row = $res->fetch_assoc()) {
    echo 'ID ' . $row['id'] . ' ' . addslashes($row['name']) . "\n";
    echo 'image=' . addslashes($row['image']) . "\n";
    echo 'hover=' . addslashes($row['hover_image']) . "\n";
    echo "---\n";
}
$db->close();
