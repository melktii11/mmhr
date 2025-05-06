<?php
require 'config.php'; // adjust as needed

$formats = isset($_POST['formats']) ? implode(',', $_POST['formats']) : '';

$query = $pdo->prepare("UPDATE settings SET export_formats = ? LIMIT 1");
$query->execute([$formats]);

echo "Export format preferences saved successfully!";
?>
