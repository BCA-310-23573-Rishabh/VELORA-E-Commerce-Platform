<?php
require_once __DIR__ . '/../config.php';
session_start();

$pageTitle = 'VELORA | Cart';
$pageDescription = 'VELORA | Cart';
$adminDisplayName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="refresh" content="0; url=../user/cart.php">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
</head>
<body>
  <p>Redirecting to <a href="../user/cart.php">the requested page</a>.</p>
</body>
</html>