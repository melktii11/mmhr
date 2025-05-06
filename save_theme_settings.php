<?php
require 'config.php';

$theme_mode = $_POST['theme_mode'] ?? 'light';
$font_size = $_POST['font_size'] ?? 'medium';
$layout_spacing = $_POST['layout_spacing'] ?? 'comfortable';

$stmt = $pdo->prepare("UPDATE settings SET theme_mode = ?, font_size = ?, layout_spacing = ? WHERE id = 1");
$stmt->execute([$theme_mode, $font_size, $layout_spacing]);

echo "Theme settings saved successfully.";
