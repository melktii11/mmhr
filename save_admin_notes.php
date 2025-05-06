<?php
require 'config.php';

$admin_notes = $_POST['admin_notes'] ?? '';

$stmt = $pdo->prepare("UPDATE settings SET admin_notes = ? WHERE id = 1");
$stmt->execute([$admin_notes]);

echo "Admin notes updated successfully.";
