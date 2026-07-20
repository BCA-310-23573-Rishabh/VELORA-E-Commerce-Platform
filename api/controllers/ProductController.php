<?php

header('Content-Type: application/json');

require_once "../config/db.php";
require_once "../models/Product.php";

$product = new Product($conn);

$name = $_POST['name'];
$price = $_POST['price'];

$images = $_POST['images'];

if ($product->create($name, $price, $images)) {

    echo json_encode([
        "success" => true
    ]);

} else {

    echo json_encode([
        "success" => false
    ]);
}
?>