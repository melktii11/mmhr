<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tools - MMHR Census</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="sige/download-removebg-preview.png" type="image/png">

    <style>
        body {
            background-image: url('sige/bgg.png');
            background-size: cover;
            background-repeat: no-repeat;
            min-height: 100vh;
        }
        .navb {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #fff;
            padding: 10px 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .nav-text h1 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        .nav-text p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }
        .nav-links {
            display: flex;
            gap: 15px;
        }
        .logout {
            margin-left: auto;
        }
    </style>

</head>
<body>

<!-- Navbar -->
<nav class="navb">
    <div style="display: flex; align-items: center;">
        <img src="sige/download-removebg-preview.png" alt="icon" style="height: 50px;">
        <div class="nav-text ms-3">
            <h1>BicutanMed</h1>
            <p>Caring For Life</p>
        </div>
    </div>
    <div class="nav-links d-none d-md-flex">
        <!-- Future links can be placed here -->
    </div>
    <a href="dashboard.php" class="btn btn-primary me-2">Dashboard</a>
    <a href="logout.php" class="btn btn-danger">Logout</a>
</nav>

<!-- Main Content -->
<div class="container mt-5">
    <div class="text-center mb-4">
        <h2 class="text-white">Tools Menu</h2>
    </div>

    <div class="d-flex justify-content-center">
        <div class="dropdown">
            <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                Select Tool
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export.php">Export Data to Excel</a></li>
                <li><a class="dropdown-item" href="upload.php">Import New Data</a></li>
                <li><a class="dropdown-item" href="backup.php">Download Backup</a></li>
                <li><a class="dropdown-item" href="clear_data.php">Clear Old Data</a></li>
                <li><a class="dropdown-item" href="view_logs.php">View Logs</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Bootstrap JS (for dropdown functionality) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
