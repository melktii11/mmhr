<?php
include 'config.php';

// Check if ID is set in URL
if (isset($_GET['id'])) {
    $user_id = $_GET['id'];

    // Fetch user data from the database
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if user exists
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
    } else {
        echo "<div class='alert alert-danger'>User not found.</div>";
        exit;
    }

    // Handle form submission for updating user details
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $role = $_POST['role'];

        // Update user information in the database
        $update_stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
        $update_stmt->bind_param("sssi", $username, $email, $role, $user_id);
        $update_stmt->execute();

        // Redirect to the admin dashboard after successful update
        header("Location: admin_dashboard.php#manage-users");
        exit;
    }
} else {
    echo "<div class='alert alert-danger'>Invalid user ID.</div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="icon" href="css/download-removebg-preview.png" type="image/png">
</head>
<body>
    <div class="navbar">
        <h1>Edit User</h1>
    </div>

    <div class="sidebar">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="#">Manage Users</a>
        <a href="#">Settings</a>
        <a href="logout.php">Logout</a>
    </div>

    <div class="main-content">
        <div class="card">
            <h2>👥 Edit User</h2>

            <!-- Edit User Form -->
            <h3>Edit User</h3>
            <div class="form-container">
            <form action="edit_user.php?id=<?php echo $user['id']; ?>" method="POST" class="edit-user-form">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo $user['username']; ?>" placeholder="Username" required>

                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo $user['email']; ?>" placeholder="Email" required>

                <label for="role">Role</label>
                <select name="role" id="role">
                <option value="user" <?php echo ($user['role'] == 'user') ? 'selected' : ''; ?>>User</option>
                <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                </select>

                <button type="submit">✅ Update User</button>
            </form>
            </div>
        </div>
    </div>
</body>
</html>
