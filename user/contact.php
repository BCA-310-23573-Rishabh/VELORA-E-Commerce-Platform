<?php
require_once __DIR__ . '/../config.php';
session_start();

$pageTitle = 'VELORA | Contact';
$pageDescription = 'VELORA | Contact';
$adminDisplayName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Admin';
?>
<!DOCTYPE html>
<html lang=\"en\">
<head>
  <meta charset=\"UTF-8\">
  <meta http-equiv=\"refresh\" content=\"0; url=shop.php\">
  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <style>body{font-family:Arial,sans-serif;padding:2rem;line-height:1.6;}a{color:#8b6f4c;text-decoration:none;}</style>
</head>
<body>
  <h1>Contact</h1>
  <p>Contact details are available from the shop page for now.</p>
  <p><a href=\"shop.php\">Continue to the collection</a></p>
</body>
</html>