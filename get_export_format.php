<?php
require 'config.php'; // adjust as needed

// Assume only one row in settings table
$query = $pdo->query("SELECT export_formats FROM settings LIMIT 1");
$row = $query->fetch(PDO::FETCH_ASSOC);

$formats = $row ? explode(',', $row['export_formats']) : [];

echo json_encode(['formats' => $formats]);
?>
