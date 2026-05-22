<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'Admin') {
    header("Location: index.php"); exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$show_form = false;

// 1. HANDLE PROFILE & PHOTO UPDATE
if (isset($_POST['update_btn'])) {
    $new_phone = $_POST['phone'];
    $new_password = $_POST['password'];
    $photo_path = null;

    // Handle File Upload
    if (!empty($_FILES['profile_photo']['name'])) {
        $target_dir = "uploads/";
        $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $new_filename = $user_id . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
            $photo_path = $new_filename;
        }
    }

    // Update Query
    if (!empty($new_password) && $photo_path) {
        $stmt = $conn->prepare("UPDATE `USER` SET phone = ?, pass_hash = ?, profile_photo = ? WHERE user_id = ?");
        $stmt->bind_param("ssss", $new_phone, $new_password, $photo_path, $user_id);
    } elseif ($photo_path) {
        $stmt = $conn->prepare("UPDATE `USER` SET phone = ?, profile_photo = ? WHERE user_id = ?");
        $stmt->bind_param("sss", $new_phone, $photo_path, $user_id);
    } elseif (!empty($new_password)) {
        $stmt = $conn->prepare("UPDATE `USER` SET phone = ?, pass_hash = ? WHERE user_id = ?");
        $stmt->bind_param("sss", $new_phone, $new_password, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE `USER` SET phone = ? WHERE user_id = ?");
        $stmt->bind_param("ss", $new_phone, $user_id);
    }

    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>✅ Profile updated successfully!</div>";
    } else {
        $message = "<div class='alert alert-error'>❌ Error updating profile.</div>";
        $show_form = true;
    }
}

// 2. FETCH CURRENT DATA
$stmt = $conn->prepare("SELECT * FROM `USER` WHERE user_id = ?");
$stmt->bind_param("s", $user_id); $stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$photo = !empty($user_data['profile_photo']) ? "uploads/" . $user_data['profile_photo'] : "https://ui-avatars.com/api/?name=" . urlencode($user_data['name']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .profile-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: center; }
        .avatar { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin-bottom: 15px; border: 4px solid #e2e8f0; }
        .info-list { text-align: left; margin-top: 20px; }
        .info-list li { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #edf2f7; }
        .btn-toggle { background: #3182ce; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; margin-top: 20px; }
        #editCard { display: none; margin-top: 20px; text-align: left; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <?php echo $message; ?>
        <div class="profile-card">
            <img src="<?php echo $photo; ?>" class="avatar" alt="Profile Photo">
            <h2><?php echo htmlspecialchars($user_data['name']); ?></h2>
            <ul class="info-list">
                <li><span class="info-label">Matrix ID:</span> <strong><?php echo htmlspecialchars($user_data['user_id']); ?></strong></li>
                <li><span class="info-label">Email:</span> <strong><?php echo htmlspecialchars($user_data['email']); ?></strong></li>
                <li><span class="info-label">Phone:</span> <strong><?php echo htmlspecialchars($user_data['phone'] ?? '-'); ?></strong></li>
            </ul>
            <button class="btn-toggle" onclick="toggleEdit()">Edit Profile</button>

            <div id="editCard">
                <form method="POST" enctype="multipart/form-data">
                    <label>Profile Photo</label>
                    <input type="file" name="profile_photo" accept="image/*">
                    <label style="margin-top:10px;">Phone</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" required style="width:100%; padding:8px;">
                    <label style="margin-top:10px;">New Password</label>
                    <input type="password" name="password" placeholder="Leave blank to keep current">
                    <button type="submit" name="update_btn" class="btn-toggle" style="background:#38a169;">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
    <script>
        function toggleEdit() {
            document.getElementById('editCard').style.display = 'block';
        }
    </script>
</body>
</html>