<?php
require 'config.php'; // adjust path if needed

$retention_days = isset($_POST['retention_days']) ? intval($_POST['retention_days']) : 30;
$enable_auto_delete = isset($_POST['enable_auto_delete']) ? intval($_POST['enable_auto_delete']) : 0;

$sql = "UPDATE settings SET retention_days = ?, enable_auto_delete = ? WHERE id = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $retention_days, $enable_auto_delete);

if ($stmt->execute()) {
    echo "Retention settings saved successfully.";
} else {
    echo "Error saving retention settings.";
}
?>
