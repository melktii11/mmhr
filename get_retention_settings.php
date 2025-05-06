<?php
require 'config.php'; // adjust path if needed

$response = [
    'retention_days' => 30,
    'enable_auto_delete' => 0
];

$sql = "SELECT retention_days, enable_auto_delete FROM settings WHERE id = 1";
$result = $conn->query($sql);

if ($result && $row = $result->fetch_assoc()) {
    $response['retention_days'] = $row['retention_days'];
    $response['enable_auto_delete'] = $row['enable_auto_delete'];
}

echo json_encode($response);
?>
