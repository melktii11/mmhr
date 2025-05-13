<?php
  if (isset($_GET['id'])) {
    $userId = $_GET['id'];
    
    // Proceed with deletion (you'll need your database connection here)
    $conn->query("DELETE FROM users WHERE id = $userId");

    // Redirect back to the dashboard after deletion
    header("Location: admin_dashboard.php");
    exit;
  }
?>
