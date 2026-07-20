<?php
$conn = new mysqli("localhost", "root", "", "velora");

if ($conn->connect_error) {
    die("Connection Failed");
}
?>