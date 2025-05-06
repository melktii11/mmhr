<?php
include 'config.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM updates WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $update = $result->fetch_assoc();
}
?>

<div class="card">
  <h2>📝 Edit Update</h2>
  <form action="edit_update.php?id=<?php echo $update['id']; ?>" method="POST" class="add-update-form">
    <div class="form-field">
      <label for="title">Title</label>
      <input type="text" id="title" name="title" value="<?php echo $update['title']; ?>" required>
    </div>

    <div class="form-field">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="4" required><?php echo $update['description']; ?></textarea>
    </div>

    <button type="submit" name="update_update" class="submit-btn">Update</button>
  </form>

  <?php
  // Handle Update Form Submission
  if (isset($_POST['update_update'])) {
      $title = $_POST['title'];
      $description = $_POST['description'];

      $stmt = $conn->prepare("UPDATE updates SET title = ?, description = ? WHERE id = ?");
      $stmt->bind_param("ssi", $title, $description, $id);
      $stmt->execute();

      echo "<p class='success-message'>✅ Update successfully updated!</p>";
  }
  ?>
</div>
