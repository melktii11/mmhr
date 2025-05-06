<?php
include 'config.php';
header('Content-Type: application/json');

$result = $conn->query("SELECT note, created_at FROM admin_notes ORDER BY created_at DESC");

$notes = [];
while ($row = $result->fetch_assoc()) {
    $notes[] = $row;
}

echo json_encode($notes);
