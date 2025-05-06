<?php
$conn = new mysqli("localhost", "root", "", "mmhr");

$stmt = $conn->prepare("SELECT max_upload_files, max_file_size_mb, allowed_file_extensions FROM settings WHERE id = 1");
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

header('Content-Type: application/json');
echo json_encode($data);
