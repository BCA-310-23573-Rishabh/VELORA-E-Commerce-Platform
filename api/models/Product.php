<?php

class Product {

    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($name, $price, $images) {

        $imagesJson = json_encode($images);

        $stmt = $this->conn->prepare(
            "INSERT INTO products (name, price, images)
             VALUES (?, ?, ?)"
        );

        $stmt->bind_param("sss", $name, $price, $imagesJson);

        return $stmt->execute();
    }
}
?>