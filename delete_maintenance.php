<?php
include 'config.php'; // adjust if needed

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM maintenance_logs WHERE id = $id");
}
header("Location: admin_dashboard.php");
exit();
?>
