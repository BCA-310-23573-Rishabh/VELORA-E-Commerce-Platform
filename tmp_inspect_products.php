<?php
$db = new mysqli('localhost', 'root', '', 'velora_db');
if ($db->connect_error) {
    echo 'DBERR ' . $db->connect_error . PHP_EOL;
    exit(1);
}
$res = $db->query("SELECT id, name, category, subcategory, is_active, price, image, hover_image FROM products ORDER BY id ASC");
echo 'rows=' . $res->num_rows . PHP_EOL;
while ($row = $res->fetch_assoc()) {
    echo $row['id'] . ' | ' . $row['name'] . ' | cat=' . $row['category'] . ' | sub=' . $row['subcategory'] . ' | active=' . $row['is_active'] . ' | price=' . $row['price'] . PHP_EOL;
}
$db->close();
