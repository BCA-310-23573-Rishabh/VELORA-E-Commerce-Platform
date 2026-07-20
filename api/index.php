<?php

$url = $_SERVER['REQUEST_URI'];

if (strpos($url, "upload") !== false) {

    require "controllers/UploadController.php";

}
elseif (strpos($url, "product") !== false) {

    require "controllers/ProductController.php";
}
?>