<?php
require_once 'config.php';
startSession();

echo "<h1>Session Check</h1>";
echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
echo "<p>Is Admin: " . (isset($_SESSION['is_admin']) ? ($_SESSION['is_admin'] ? 'Yes' : 'No') : 'Not set') . "</p>";
echo "<p>Email: " . ($_SESSION['email'] ?? 'Not set') . "</p>";
?>