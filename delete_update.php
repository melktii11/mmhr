<?php
include 'config.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Handle Deletion
    $stmt = $conn->prepare("DELETE FROM updates WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    echo "<p class='success-message'>✅ Update deleted successfully!</p>";
    // Redirect back to the dashboard after deletion
    header("Location: admin_dashboard.php");
    exit();
}
?>
