<?php
$mysqli = new mysqli("localhost", "root", "", "mmhr");

// Only update relevant keys
$max_upload_files = $_POST['max_upload_files'];
$max_file_size_mb = $_POST['max_file_size_mb'];
$allowed_file_extensions = $_POST['allowed_file_extensions'] ?? 'xlsx,xls';

$stmt = $mysqli->prepare("UPDATE settings SET max_upload_files = ?, max_file_size_mb = ?, allowed_file_extensions = ? WHERE id = 1");
$stmt->bind_param("iis", $max_upload_files, $max_file_size_mb, $allowed_file_extensions);
$stmt->execute();

echo "Upload settings saved successfully.";
