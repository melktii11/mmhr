<?php
// delete_backup.php

if (isset($_GET['file'])) {
    $file = basename($_GET['file']);
    if (file_exists($file)) {
        unlink($file);
    }
}
?>
