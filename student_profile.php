<?php
session_start();
require 'db_connect.php';

// SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'Admin') {
    header("Location: index.php");
    exit();
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
        // Ensure directory exists
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
        
        $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        // Add a timestamp to the filename so the browser doesn't cache old photos
        $new_filename = $user_id . "_" . time() . "." . $file_extension; 
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
            $photo_path = $new_filename;
        }
    }

    // Dynamic Update Query (Hashes the password securely if provided)
    if (!empty($new_password) && $photo_path) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE `USER` SET phone = ?, pass_hash = ?, profile_photo = ? WHERE user_id = ?");
        $stmt->bind_param("ssss", $new_phone, $hashed_password, $photo_path, $user_id);
    } elseif ($photo_path) {
        $stmt = $conn->prepare("UPDATE `USER` SET phone = ?, profile_photo = ? WHERE user_id = ?");
        $stmt->bind_param("sss", $new_phone, $photo_path, $user_id);
    } elseif (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE `USER` SET phone = ?, pass_hash = ? WHERE user_id = ?");
        $stmt->bind_param("sss", $new_phone, $hashed_password, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE `USER` SET phone = ? WHERE user_id = ?");
        $stmt->bind_param("ss", $new_phone, $user_id);
    }

    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>✅ Profile updated successfully!</div>";
        $show_form = false; 
    } else {
        $message = "<div class='alert alert-error'>❌ Error updating profile.</div>";
        $show_form = true; 
    }
}

// 2. FETCH CURRENT PROFILE DATA
$stmt = $conn->prepare("SELECT * FROM `USER` WHERE user_id = ?");
$stmt->bind_param("s", $user_id); 
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// Set the photo path, or default to a UI Avatar if they haven't uploaded one
$photo = !empty($user_data['profile_photo']) ? "uploads/" . $user_data['profile_photo'] : "https://ui-avatars.com/api/?name=" . urlencode($user_data['name']) . "&background=random";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { 
            display: flex; 
            background: #e2e8f0; /* Restored the original grey background */
            min-height: 100vh; 
        }
        .main-content { margin-left: 260px; width: calc(100% - 260px); padding: 40px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .profile-container { width: 100%; max-width: 600px; display: flex; flex-direction: column; gap: 20px; }
        
        .card { background: white; padding: 35px; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); width: 100%; }
        .card h2 { margin-bottom: 20px; color: #1a202c; font-size: 22px; display: flex; align-items: center; gap: 10px; justify-content: center; border-bottom: 2px solid #edf2f7; padding-bottom: 15px;}
        
        /* Avatar Styling */
        .avatar-container { text-align: center; margin-bottom: 25px; }
        .avatar { width: 130px; height: 130px; border-radius: 50%; object-fit: cover; border: 4px solid #edf2f7; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        
        .info-list { list-style: none; margin-bottom: 25px; }
        .info-list li { display: flex; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid #edf2f7; font-size: 15px; }
        .info-list li:last-child { border-bottom: none; }
        .info-label { color: #4a5568; font-weight: 500; }
        .info-value { font-weight: 700; color: #1a202c; text-align: right; }
        
        .form-group { margin-bottom: 20px; text-align: left; }
        label { display: block; margin-bottom: 8px; color: #4a5568; font-weight: 600; font-size: 14px; }
        input[type="text"], input[type="password"], input[type="file"] { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; transition: 0.3s; }
        input[type="text"]:focus, input[type="password"]:focus { border-color: #3182ce; box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.2); }
        
        /* File input specific styling */
        input[type="file"] { background: #f8fafc; color: #4a5568; cursor: pointer; }
        input[type="file"]::file-selector-button { background: #e2e8f0; border: none; padding: 8px 12px; border-radius: 6px; color: #4a5568; font-weight: 600; cursor: pointer; margin-right: 10px; transition: 0.2s; }
        input[type="file"]::file-selector-button:hover { background: #cbd5e0; }

        .btn-toggle { background: #edf2f7; color: #4a5568; border: none; padding: 12px 20px; border-radius: 8px; width: 100%; font-weight: bold; cursor: pointer; transition: 0.2s; font-size: 15px; }
        .btn-toggle:hover { background: #e2e8f0; color: #2d3748; }
        .btn-save { background: #3182ce; color: white; border: none; padding: 12px 20px; border-radius: 8px; width: 100%; font-weight: bold; cursor: pointer; font-size: 15px; margin-bottom: 10px; transition: 0.2s;}
        .btn-save:hover { background: #2b6cb0; }
        .btn-cancel { background: white; color: #e53e3e; border: 2px solid #fed7d7; padding: 10px 20px; border-radius: 8px; width: 100%; font-weight: bold; cursor: pointer; font-size: 15px; transition: 0.2s;}
        .btn-cancel:hover { background: #fff5f5; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; text-align: center; }
        .alert-success { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background-color: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        
        #editCard { display: <?php echo $show_form ? 'block' : 'none'; ?>; animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="profile-container">
            <?php echo $message; ?>
            
            <div class="card" id="viewCard">
                <h2>👤 My Profile</h2>
                
                <div class="avatar-container">
                    <img src="<?php echo $photo; ?>" class="avatar" alt="Profile Photo">
                </div>

                <ul class="info-list">
                    <li><span class="info-label">Name</span><span class="info-value"><?php echo htmlspecialchars($user_data['name']); ?></span></li>
                    <li><span class="info-label">Matrix ID</span><span class="info-value"><?php echo htmlspecialchars($user_data['user_id']); ?></span></li>
                    <li><span class="info-label">Email</span><span class="info-value"><?php echo htmlspecialchars($user_data['email']); ?></span></li>
                    <li><span class="info-label">Phone</span><span class="info-value"><?php echo htmlspecialchars($user_data['phone'] ?? '-'); ?></span></li>
                    <?php if (isset($user_data['total_point'])): ?>
                        <li><span class="info-label">Total Points</span><span class="info-value" style="color: #38a169; font-size: 18px;"><?php echo htmlspecialchars($user_data['total_point']); ?></span></li>
                    <?php endif; ?>
                </ul>
                <button class="btn-toggle" onclick="toggleEdit()">✏️ Edit Profile</button>
            </div>

            <div class="card" id="editCard">
                <h2 style="justify-content: flex-start; border: none; padding-bottom: 0;">⚙️ Update Information</h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Update Profile Photo</label>
                        <input type="file" name="profile_photo" accept="image/png, image/jpeg, image/jpg">
                    </div>
                    <div class="form-group">
                        <label>Update Phone</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="password" placeholder="(Leave blank to keep current)">
                    </div>
                    <button type="submit" name="update_btn" class="btn-save">💾 Save Changes</button>
                    <button type="button" class="btn-cancel" onclick="toggleEdit()">Cancel</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleEdit() {
            var editCard = document.getElementById("editCard");
            var editBtn = document.querySelector(".btn-toggle");
            if (editCard.style.display === "none" || editCard.style.display === "") {
                editCard.style.display = "block";
                editBtn.style.display = "none"; 
                editCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                editCard.style.display = "none";
                editBtn.style.display = "block"; 
            }
        }
    </script>
</body>
</html>