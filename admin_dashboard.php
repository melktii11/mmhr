<?php
session_start();
include 'config.php';
$db_name = 'mmhr_census'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quota'])) {
  $new_quota = (int) $_POST['new_quota'];
  if ($new_quota > 0) {
      $stmt = $conn->prepare("UPDATE storage_settings SET quota_mb = ? LIMIT 1");
      $stmt->bind_param("i", $new_quota);
      $stmt->execute();
  }
}

$quota_query = $conn->query("SELECT quota_mb FROM storage_settings LIMIT 1");
$max_quota_mb = 100;

if ($quota_query && $quota_query->num_rows > 0) {
  $row = $quota_query->fetch_assoc();
  $max_quota_mb = $row['quota_mb'];
}

$sql = "
SELECT 
  table_schema AS db_name, 
  ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size_mb
FROM information_schema.tables 
WHERE table_schema = '$db_name' 
GROUP BY table_schema
";

$result = $conn->query($sql);
$db_size_mb = 0;

if ($row = $result->fetch_assoc()) {
  $db_size_mb = $row['db_size_mb'];
}

$used_percent = min(round(($db_size_mb / $max_quota_mb) * 100, 2), 100);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

if (isset($_POST['add_maintenance'])) {
  $log = $conn->real_escape_string($_POST['maintenance_log']);
  $conn->query("INSERT INTO maintenance_logs (log) VALUES ('$log')");
  header("Location: admin_dashboard.php");
  exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $performed_by = $_SESSION['role'] ?? $_SESSION['username'] ?? 'Unknown';

    if (isset($_POST['reset_system'])) {
     
        $conn->query("TRUNCATE TABLE patient_records");
        $conn->query("TRUNCATE TABLE patient_records_2");
        $conn->query("TRUNCATE TABLE patient_records_3");
        $conn->query("TRUNCATE TABLE leading_causes");
        $conn->query("TRUNCATE TABLE admin_notes");

        $conn->query("INSERT INTO system_logs (action, performed_by) VALUES ('System reset', '$performed_by')");
        echo "<script>alert('System data has been reset.');</script>";
    }

    if (isset($_POST['delete_uploads'])) {
        $upload_dir = 'uploads/';
        $files = glob($upload_dir . '*');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $conn->query("INSERT INTO system_logs (action, performed_by) VALUES ('Deleted all uploads', '$performed_by')");
        echo "<script>alert('All uploaded files have been deleted.');</script>";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'] ?? null;
    $password = $_POST['password'] ?? null;

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];

            if ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: user_dashboard.php");
            }
            exit;
        } else {
            $error = "❌ Incorrect password!";
        }
    } else {
        $error = "❌ Email not found!";
    }
}

if (isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $email, $password, $role);
    $stmt->execute();

    header("Location: admin_dashboard.php#manage-users");
    exit;
}

if (isset($_POST['save_note'])) {
  $note = $_POST['note'];
  $stmt = $conn->prepare("INSERT INTO admin_notes (note) VALUES (?)");
  $stmt->bind_param("s", $note);
  $stmt->execute();
}

$total_space = disk_total_space("C:"); 
$free_space = disk_free_space("C:");
$used_space = $total_space - $free_space;

$used_percent = ($used_space / $total_space) * 100;
$used_percent = round($used_percent, 2);

if (isset($_POST['backup_db'])) {
  $backup_dir = 'backups/';
  if (!is_dir($backup_dir)) {
      mkdir($backup_dir, 0755, true); 
  }

  $backup_file = $backup_dir . 'mmhr_census_backup_' . date('Ymd_His') . '.sql';
  $db_user = 'root';         
  $db_pass = '';             
  $db_name = 'mmhr_census';  

  $command = "\"C:\\xampp\\mysql\\bin\\mysqldump.exe\" -u$db_user ". ($db_pass ? "-p$db_pass " : "") ."$db_name > \"$backup_file\"";
  system($command, $retval);

  if ($retval === 0) {
      $conn->query("INSERT INTO system_logs (action, performed_by) VALUES ('Database backup created', '$performed_by')");
      echo "<script>alert('Backup created successfully!');</script>";
  } else {
      echo "<script>alert('Backup failed. Make sure mysqldump.exe is correctly configured.');</script>";
  }
}

$logs_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $logs_per_page;

$total_logs_result = $conn->query("SELECT COUNT(*) AS total FROM system_logs");
$total_logs = $total_logs_result->fetch_assoc()['total'];
$total_pages = ceil($total_logs / $logs_per_page);

$logs = $conn->query("SELECT * FROM system_logs ORDER BY timestamp DESC LIMIT $logs_per_page OFFSET $offset");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Update General Settings
  if (isset($_POST['update_general_settings'])) {
      $site_title = $_POST['site_title'];
      $logo = $_POST['logo'];
      $timezone = $_POST['timezone'];

      // Update the correct columns for site title, logo, and timezone
      $stmt = $conn->prepare("UPDATE settings SET site_title = ?, logo = ?, timezone = ? WHERE id = 1");
      $stmt->bind_param("sss", $site_title, $logo, $timezone);
      $stmt->execute();
  }

  // Update User Management Settings
  if (isset($_POST['update_user_settings'])) {
      $default_role = $_POST['default_role'];

      // Update the correct column for default role
      $stmt = $conn->prepare("UPDATE settings SET default_role = ? WHERE id = 1");
      $stmt->bind_param("s", $default_role);
      $stmt->execute();
  }

  // Update File Management Settings
  if (isset($_POST['update_file_settings'])) {
      $max_upload_size = $_POST['max_upload_size'];

      // Update the correct column for max file size
      $stmt = $conn->prepare("UPDATE settings SET max_file_size_mb = ? WHERE id = 1");
      $stmt->bind_param("i", $max_upload_size);
      $stmt->execute();
  }

  // Update Email Settings
  if (isset($_POST['update_email_settings'])) {
      $smtp_server = $_POST['smtp_server'];
      $smtp_port = $_POST['smtp_port'];

      // Update the correct columns for SMTP server and port
      $stmt = $conn->prepare("UPDATE settings SET smtp_server = ?, smtp_port = ? WHERE id = 1");
      $stmt->bind_param("si", $smtp_server, $smtp_port);
      $stmt->execute();
  }

  // Update Audit Logs Settings
  if (isset($_POST['update_audit_settings'])) {
      $audit_logging = isset($_POST['audit_logging']) ? 1 : 0;

      // Update the correct column for audit logging
      $stmt = $conn->prepare("UPDATE settings SET audit_logging = ? WHERE id = 1");
      $stmt->bind_param("i", $audit_logging);
      $stmt->execute();
  }
}

$result = $conn->query("SELECT * FROM settings WHERE id = 1");
$settings = $result->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/admin.css">
  <link rel="icon" href="css/download-removebg-preview.png" type="image/png">
</head>
<body>
  <div class="navbar">
    <h1>Admin Dashboard</h1>
    <div>Welcome, Admin</div>
  </div>

  <div class="sidebar">
    <a href="admin_dashboard.php" id="dashboard-link">Dashboard</a>
    <a href="#manage-users" id="manage-users-link">Manage Users</a>
    <a href="settings.php" id="settingsBtn">Settings</a>
    <a href="javascript:void(0);" onclick="confirmLogout()">Logout</a>
  </div>

  <div class="main-content">
    <div class="card">
  <h2>📋 Updates</h2>

  <form action="admin_dashboard.php" method="POST" class="add-update-form">
    <div class="form-field">
      <label for="title">Title</label>
      <input type="text" id="title" name="title" placeholder="Update title" required>
    </div>

    <div class="form-field">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="4" placeholder="Enter update details..." required></textarea>
    </div>

    <button type="submit" name="add_update" class="submit-btn">Add Update</button>
  </form>

<?php
  if (isset($_POST['add_update'])) {
      $title = $_POST['title'];
      $description = $_POST['description'];

      $stmt = $conn->prepare("INSERT INTO updates (title, description) VALUES (?, ?)");
      $stmt->bind_param("ss", $title, $description);
      $stmt->execute();

      echo "<p class='success-message'>✅ Update added successfully!</p>";
  }
  ?>

    <div class="existing-updates">
      <h3>Existing Updates</h3>
      <ul>
        <?php
        $result = $conn->query("SELECT * FROM updates ORDER BY created_at DESC LIMIT 5");
        while ($update = $result->fetch_assoc()) {
          echo "<li>
                  <strong>{$update['title']}</strong><br>
                  {$update['description']}<br>
                  <small>Added on: {$update['created_at']}</small><br>
                  <a href='edit_update.php?id={$update['id']}' class='edit-btn'>Edit</a> | 
                  <a href='delete_update.php?id={$update['id']}' class='delete-btn' onclick='return confirm(\"Are you sure you want to delete this update?\")'>Delete</a>
                </li><hr>";
        }
        ?>
      </ul>

      <div class="pagination">
        <a href="admin_dashboard.php?page=1">1</a>
        <a href="admin_dashboard.php?page=2">2</a>
        <a href="admin_dashboard.php?page=3">3</a>
      </div>
    </div>
  </div>

  <div class="card">
    <h2>🛠️ Maintenance</h2>
    <form action="admin_dashboard.php" method="POST" style="margin-bottom: 15px;">
      <textarea name="maintenance_log" rows="3" placeholder="Add maintenance note..." required style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ccc;"></textarea>
      <button class="btnq" type="submit" name="add_maintenance">Add Log</button>
    </form>

  <div class="table-container">
    <table class="user-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Log</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $result = $conn->query("SELECT * FROM maintenance_logs ORDER BY created_at DESC");
          while ($log = $result->fetch_assoc()) {
            echo "<tr>
                    <td>{$log['id']}</td>
                    <td>{$log['log']}</td>
                    <td>{$log['created_at']}</td>
                    <td>
                      <a href='delete_maintenance.php?id={$log['id']}' class='delete-btn' onclick='return confirm(\"Delete this maintenance log?\")'>Delete</a>
                    </td>
                  </tr>";
          }
        ?>
      </tbody>
    </table>
  </div>
</div>

  <div class="card">
    <h2>📊 Storage Graph</h2>
    <p>Total Quota: <?php echo $max_quota_mb; ?> MB</p>
    <p>Used: <?php echo $db_size_mb; ?> MB (<?php echo $used_percent; ?>%)</p>

    <div class="graph-bar-container">
      <div class="graph-bar-used" style="width: <?php echo $used_percent; ?>%;"></div>
    </div>
    <button class="custom-btn" onclick="toggleQuotaEdit()">✏️ Edit Quota</button>

    <form id="quotaForm" method="POST" class="quota-form">
      <input type="number" name="new_quota" min="1" value="<?php echo $max_quota_mb; ?>" required> MB
      <button type="submit" name="update_quota" class="custom-save-btn">Save</button>
    </form>

  </div>

  <div class="card restricted">
    <h2>🔐 Admin-Only Actions</h2>
    <form method="POST" onsubmit="return confirm('Are you sure? This action cannot be undone.');">
      <button type="submit" name="reset_system" class="admin-btn">🔄 Reset System</button>
      <button type="submit" name="delete_uploads" class="admin-btn">🗑️ Delete Uploads</button>
      <button type="submit" name="backup_db" class="admin-btn">💾 Backup Database</button>
    </form>
  </div>

<div class="card" id="system-logs">
  <h2>📁 System Logs</h2>
  <table class="user-table">
    <thead>
      <tr><th>Timestamp</th><th>Action</th><th>Performed By</th></tr>
    </thead>
      <tbody>
        <?php while ($log = $logs->fetch_assoc()): ?>
          <tr>
            <td><?= $log['timestamp'] ?></td>
            <td><?= $log['action'] ?></td>
            <td><?= $log['performed_by'] ?></td>
          </tr>
        <?php endwhile; ?>
    </tbody>
  </table>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="?page=<?= $page - 1 ?>">⬅️ Previous</a>
    <?php endif; ?>

    <span>Page <?= $page ?> of <?= $total_pages ?></span>

    <?php if ($page < $total_pages): ?>
      <a href="?page=<?= $page + 1 ?>">Next ➡️</a>
    <?php endif; ?>
  </div>
</div>

    <div class="card">
      <h2>📝 Admin Notes / Logs</h2>
      <p>These notes are visible to users for awareness and transparency.</p>
      <form action="admin_dashboard.php" method="POST">
        <textarea name="note" rows="4" style="width: 100%;" required></textarea>
        <br><br>
        <button class="btnq" type="submit" name="save_note">Save Note</button>
      </form>
    </div>

    <div class="card" id="manage-users">
      <h2>👥 Manage Users</h2>

      <h3>Add New User</h3>
      <div class="form-container">
        <form action="admin_dashboard.php" method="POST" class="add-user-form">
          <input type="text" name="username" placeholder="Username" required>
          <input type="email" name="email" placeholder="Email" required>
          <input type="password" name="password" placeholder="Password" required>
          <select name="role">
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </select>
          <button type="submit" name="add_user">➕ Add User</button>
        </form>
      </div>

<h3>Existing Users</h3>
<div class="table-container">
  <table class="user-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Email</th>
        <th>Role</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
  <?php
    $result = $conn->query("SELECT * FROM users");
    while ($user = $result->fetch_assoc()) {
      echo "<tr>
              <td>{$user['id']}</td>
              <td>{$user['username']}</td>
              <td>{$user['email']}</td>
              <td>{$user['role']}</td>
              <td>
                <a href='edit_user.php?id={$user['id']}' class='edit-btn'>Edit</a> | 
                <a href='#' class='delete-btn' onclick='confirmDelete({$user['id']})'>Delete</a>
              </td>
            </tr>";
    }
  ?>
</tbody>
    </table>
    </div>
  </div>

  <div class="floating-settings-card" id="settingsCard" style="display: none;">
    <div class="settings-content">
    <h2>Settings</h2>

    <!-- General Settings -->
<div class="section">
<hr>
  <h3>General Settings</h3>
  <form action="admin_dashboard.php" method="POST">
    <label for="site-title">Site Title:</label>
    <input type="text" id="site-title" name="site_title" value="<?= htmlspecialchars($settings['site_title']) ?>" required>

    <label for="logo">Logo (URL):</label>
    <input type="text" id="logo" name="logo" value="<?= htmlspecialchars($settings['logo']) ?>">

    <label for="timezone">Timezone:</label>
    <select id="timezone" name="timezone">
      <option value="UTC" <?= ($settings['timezone'] === 'UTC') ? 'selected' : '' ?>>UTC</option>
      <option value="GMT" <?= ($settings['timezone'] === 'GMT') ? 'selected' : '' ?>>GMT</option>
      <option value="PST" <?= ($settings['timezone'] === 'PST') ? 'selected' : '' ?>>PST</option>
    </select>

    <button type="submit" name="update_general_settings">Save Settings</button>
    <hr>
  </form>
</div>

<!-- User Management Settings -->
<div class="section">
  <h3>User Management Settings</h3>
  <form action="admin_dashboard.php" method="POST">
    <label for="default-role">Default User Role:</label>
    <select id="default-role" name="default_role">
      <option value="user" <?= ($settings['default_role'] === 'user') ? 'selected' : '' ?>>User</option>
      <option value="admin" <?= ($settings['default_role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
    </select>

    <button type="submit" name="update_user_settings">Save Settings</button>
    <hr>
  </form>
</div>

<!-- File Management Settings -->
<div class="section">
  <h3>File Management</h3>
  <form action="admin_dashboard.php" method="POST">
    <label for="max-upload-size">Max File Upload Size (MB):</label>
    <input type="number" id="max-upload-size" name="max_upload_size" value="<?= htmlspecialchars($settings['max_file_size_mb']) ?>" min="1">

    <button type="submit" name="update_file_settings">Save Settings</button>
    <hr>
  </form>
</div>

<!-- Email Settings -->
<div class="section">
  <h3>Email Settings</h3>
  <form action="admin_dashboard.php" method="POST">
    <label for="smtp-server">SMTP Server:</label>
    <input type="text" id="smtp-server" name="smtp_server" value="<?= htmlspecialchars($settings['smtp_server']) ?>">

    <label for="smtp-port">SMTP Port:</label>
    <input type="number" id="smtp-port" name="smtp_port" value="<?= htmlspecialchars($settings['smtp_port']) ?>">

    <button type="submit" name="update_email_settings">Save Settings</button>
    <hr>
  </form>
</div>
<!-- Audit Logs Settings -->
<div class="section">
  <h3>Audit Logs</h3>
  <form action="admin_dashboard.php" method="POST">
    <label for="audit-logging">Enable Audit Logging:</label>
    <input type="checkbox" id="audit-logging" name="audit_logging" <?= ($settings['audit_logging'] == 1) ? 'checked' : '' ?>>

    <button type="submit" name="update_audit_settings">Save Settings</button>
    <hr>
  </form>
</div>
  </div>
</div>

<!-- Confirmation Popup Modal -->
 <div id="overlay" class="overlay" style="display:none;">
  <div id="deleteModal" class="modal">
    <div id="modalContent" class="modal-content">
      <p>Are you sure you want to delete this user?</p>
      <button id="confirmDeleteBtn" onclick="deleteUser()">Yes, Delete</button>
      <button onclick="closeModal()">No</button>
    </div>
  </div>
</div>

<div id="overlayLogout" class="overlay-logout">
  <div class="modal-logout">
    <p>Are you sure you want to log out?</p>
    <button onclick="logoutUser()">Yes, Logout</button>
    <button onclick="closeLogoutModal()">Cancel</button>
  </div>
</div>

<script>
    let userIdToDelete = null; // This will store the user ID to be deleted

  function confirmDelete(userId) {
    userIdToDelete = userId;  // Set the user ID to be deleted
    document.getElementById("overlay").style.display = "flex"; // Show the overlay and modal
  }

  function closeModal() {
    document.getElementById("overlay").style.display = "none"; // Close the modal and overlay
  }

  function deleteUser() {
    if (userIdToDelete !== null) {
      window.location.href = 'delete_user.php?id=' + userIdToDelete; // Redirect to delete user
    }
  }

  let logoutConfirmed = false;

  // Show the confirmation modal when the admin clicks on the logout link
  function confirmLogout() {
    document.getElementById("overlayLogout").style.display = "flex"; // Show modal
  }

  // Hide the confirmation modal
  function closeLogoutModal() {
    document.getElementById("overlayLogout").style.display = "none"; // Hide modal
  }

  // Proceed with logout if confirmed
  function logoutUser() {
    window.location.href = 'logout.php'; // Redirect to logout page
  }

</script>


<script>
  function toggleQuotaEdit() {
    const form = document.getElementById('quotaForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
  }

  document.addEventListener("DOMContentLoaded", function() {
    if (window.location.hash === "#system-logs") {
      const target = document.getElementById("system-logs");
      if (target) {
        target.scrollIntoView({ behavior: "smooth" });
      }
    }
  });

  document.addEventListener("DOMContentLoaded", function () {
  const dashboardLink = document.getElementById("dashboard-link");
  const manageUsersLink = document.getElementById("manage-users-link");
  const settingsBtn = document.getElementById("settingsBtn");
  const settingsCard = document.getElementById("settingsCard");
  const manageUsersSection = document.getElementById("manage-users");

  dashboardLink.addEventListener("click", function (e) {
    e.preventDefault();
    settingsCard.style.display = "none";
    window.scrollTo({ top: 0, behavior: "smooth" });
  });

  manageUsersLink.addEventListener("click", function (e) {
    e.preventDefault();
    settingsCard.style.display = "none";
    manageUsersSection.scrollIntoView({ behavior: "smooth" });
  });

  settingsBtn.addEventListener("click", function (e) {
    e.preventDefault();
    const isOpen = settingsCard.style.display === "block";
    settingsCard.style.display = isOpen ? "none" : "block";

    if (!isOpen) {
      settingsBtn.classList.add("active");
      dashboardLink.classList.remove("active");
      manageUsersLink.classList.remove("active");
    } else {
      settingsBtn.classList.remove("active");
      dashboardLink.classList.add("active");
    }
  });

  function updateSidebarHighlight() {
    const rect = manageUsersSection.getBoundingClientRect();
    const inView = rect.top < window.innerHeight && rect.bottom >= 100;
    const settingsOpen = settingsCard.style.display === "block";

    if (settingsOpen) {
      settingsBtn.classList.add("active");
      dashboardLink.classList.remove("active");
      manageUsersLink.classList.remove("active");
    } else if (inView) {
      manageUsersLink.classList.add("active");
      dashboardLink.classList.remove("active");
      settingsBtn.classList.remove("active");
    } else {
      dashboardLink.classList.add("active");
      manageUsersLink.classList.remove("active");
      settingsBtn.classList.remove("active");
    }
  }

  window.addEventListener("scroll", updateSidebarHighlight);
  window.addEventListener("resize", updateSidebarHighlight);
  updateSidebarHighlight();
});

</script>
</body>
</html>