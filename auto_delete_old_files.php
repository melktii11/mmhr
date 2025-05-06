<?php
require 'config.php'; // adjust if your DB connection file has a different name

// 1. Get retention settings
$stmt = $pdo->query("SELECT retention_days, enable_auto_delete FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Exit if auto delete is disabled
if (!$settings || $settings['enable_auto_delete'] != 1) {
    exit("Auto-deletion is disabled.");
}

$retentionDays = (int)$settings['retention_days'];
if ($retentionDays <= 0) {
    exit("Invalid retention period.");
}

// 2. Calculate the cutoff date
$cutoffDate = date('Y-m-d H:i:s', strtotime("-$retentionDays days"));

// 3. Get files older than the cutoff
$stmt = $pdo->prepare("SELECT id, file_path FROM uploaded_files WHERE uploaded_at < ?");
$stmt->execute([$cutoffDate]);
$oldFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Loop through and delete each file
$deletedCount = 0;
foreach ($oldFiles as $file) {
    $filePath = $file['file_path'];

    // Delete from server
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Delete from database
    $deleteStmt = $pdo->prepare("DELETE FROM uploaded_files WHERE id = ?");
    $deleteStmt->execute([$file['id']]);

    $deletedCount++;
}

echo "Deleted $deletedCount file(s) older than $retentionDays day(s).";
