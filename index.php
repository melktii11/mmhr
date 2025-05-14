<?php
session_start(); 
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $username, $hashed_password, $db_role);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            $_SESSION["user_id"] = $id; 
            $_SESSION["username"] = $username;
            $_SESSION["role"] = $db_role;

            if ($db_role === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;
        } else {
            echo "<div class='alert alert-danger' id='alert-box'>Invalid password.</div>";
        }
    } else {
        echo "<div class='alert alert-danger' id='alert-box'>No user found with that email.</div>";

    }
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="login-container">
            <div class="logo-container">
                <img src="css/download-removebg-preview.png" alt="Logo" class="logo">
                <h2>BICUTAN MEDICAL CENTER INC.</h2>
            </div>
        <form method="POST" method="GET">
            <label for="email">👨‍💻 Email:</label>
            <input type="email" id="email" name="email" required placeholder="Enter your email">

            <label for="password">🔑 Password:</label>
            <input type="password" id="password" name="password" required placeholder="Enter your password">

            <button type="submit">Login</button>
        </form>
        <br>
        <div class="footer">
            <small>&copy; Bicutan Medical Center Inc. All rights reserved.</small>
        </div>
    </div>
</body>

<script>
    // Automatically hide alert after 3 seconds
    window.addEventListener("DOMContentLoaded", function () {
        const alertBox = document.getElementById("alert-box");
        if (alertBox) {
            setTimeout(() => {
                alertBox.style.transition = "opacity 0.5s ease";
                alertBox.style.opacity = "0";
                setTimeout(() => alertBox.remove(), 500); // Remove from DOM
            }, 3000); // 3 seconds
        }
    });
</script>

</html>
