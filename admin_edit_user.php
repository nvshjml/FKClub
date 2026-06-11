<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

// 1. SECURITY & REDIRECT
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

// 2. FETCH USER DATA
$user = null;
if (isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    $stmt = $conn->prepare("SELECT * FROM `USER` WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}

// If no user found, redirect back
if (!$user) {
    header("Location: admin_manage_users.php");
    exit();
}

// 3. HANDLE UPDATE SUBMISSION
if (isset($_POST['save_user'])) {
    $user_id_post = $_POST['user_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $role = $_POST['role'];
    $status = $_POST['status'];

    // A. Update Base Profile Information
    $stmt = $conn->prepare("UPDATE `USER` SET name = ?, email = ?, phone = ?, role = ?, account_status = ? WHERE user_id = ?");
    $stmt->bind_param("ssssss", $name, $email, $phone, $role, $status, $user_id_post);
    
    if ($stmt->execute()) {
        
        // B. Handle Optional Password Reset
        if (!empty($_POST['new_password'])) {
            $new_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt_pw = $conn->prepare("UPDATE `USER` SET pass_hash = ? WHERE user_id = ?");
            $stmt_pw->bind_param("ss", $new_hash, $user_id_post);
            $stmt_pw->execute();
        }

        // C. Handle Optional Profile Photo Upload
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == UPLOAD_ERR_OK) {
            // Ensure uploads directory exists
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Generate unique filename to prevent overwriting
            $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $new_filename = $user_id_post . "_" . time() . "." . $file_extension;
            $target_file = $upload_dir . $new_filename;

            // Only allow specific image formats
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array(strtolower($file_extension), $allowed_types)) {
                if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
                    $stmt_photo = $conn->prepare("UPDATE `USER` SET profile_photo = ? WHERE user_id = ?");
                    $stmt_photo->bind_param("ss", $new_filename, $user_id_post);
                    $stmt_photo->execute();
                }
            } else {
                $error = "Invalid file type. Only JPG, PNG, and GIF allowed.";
            }
        }

        if (!isset($error)) {
            header("Location: admin_manage_users.php?msg=success");
            exit();
        }
    } else {
        $error = "Update failed: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Essential Layout Safety Net */
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: #e2e8f0; display: flex; min-height: 100vh; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; }
        
        /* Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h2 { color: #1a202c; font-size: 24px; margin: 0; }

        /* Buttons */
        .btn-primary { background: #3182ce; color: white; border: none; padding: 12px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 14px; width: 100%; margin-top: 10px; }
        .btn-primary:hover { background: #2b6cb0; }
        
        .btn-back { display: inline-block; background: #718096; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 14px; transition: 0.2s; }
        .btn-back:hover { background: #4a5568; }

        /* Form Card */
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 600px; border-top: 4px solid #3182ce; margin: auto; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 8px; color: #4a5568; }
        
        input[type="text"], input[type="email"], input[type="password"], input[type="file"], select { 
            width: 100%; 
            padding: 12px 15px; 
            border-radius: 8px; 
            border: 1px solid #cbd5e0; 
            outline: none; 
            font-size: 14px; 
            transition: border 0.2s; 
            color: #2d3748; 
            background-color: #fff; 
        }
        input:focus, select:focus { border-color: #3182ce; box-shadow: 0 0 0 3px rgba(49,130,206,0.1); }

        /* Profile Photo Preview */
        .photo-preview { display: flex; align-items: center; gap: 20px; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #edf2f7; }
        .photo-preview img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #e2e8f0; }

        /* Alerts */
        .alert-error { background: #fed7d7; color: #822727; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 14px; border: 1px solid #feb2b2; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2>✏️ Edit User Profile</h2>
            <a href="admin_manage_users.php" class="btn-back">← Back to Manage Users</a>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 20px; color: #2d3748; font-size: 18px; border-bottom: 2px solid #edf2f7; padding-bottom: 10px;">
                Matrix ID: <?php echo htmlspecialchars($user['user_id']); ?>
            </h3>
            
            <?php if(isset($error)) echo "<div class='alert-error'>❌ $error</div>"; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                
                <div class="photo-preview">
                    <?php 
                        $photo_path = !empty($user['profile_photo']) ? 'uploads/' . $user['profile_photo'] : 'uploads/default.png';
                        // Fallback in case file doesn't actually exist on disk
                        if (!file_exists($photo_path)) { $photo_path = 'uploads/default.png'; }
                    ?>
                    <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="Profile Photo">
                    <div style="flex-grow: 1;">
                        <label>Update Profile Photo</label>
                        <input type="file" name="profile_photo" accept="image/png, image/jpeg, image/gif">
                        <small style="color: #718096; display: block; margin-top: 5px;">Leave blank to keep current photo. Max 2MB.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                </div>

                <div class="form-group">
                    <label>Reset Password</label>
                    <input type="password" name="new_password" placeholder="Enter new password to reset">
                    <small style="color: #718096; display: block; margin-top: 5px;">Leave this blank if you do not want to change the user's password.</small>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>System Role</label>
                        <select name="role">
                            <option value="Student" <?php if($user['role']=='Student') echo 'selected'; ?>>Student</option>
                            <option value="Committee" <?php if($user['role']=='Committee') echo 'selected'; ?>>Committee</option>
                            <option value="Admin" <?php if($user['role']=='Admin') echo 'selected'; ?>>Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Account Status</label>
                        <select name="status">
                            <option value="Active" <?php if($user['account_status']=='Active') echo 'selected'; ?>>Active</option>
                            <option value="Inactive" <?php if($user['account_status']=='Inactive') echo 'selected'; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="save_user" class="btn-primary">Update Profile</button>
            </form>
        </div>
    </div>

</body>
</html>