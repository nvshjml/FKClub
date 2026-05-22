<?php
session_start();
require 'db_connect.php';

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
    header("Location: admin_manage_club.php");
    exit();
}

// 3. HANDLE UPDATE SUBMISSION
if (isset($_POST['save_user'])) {
    $stmt = $conn->prepare("UPDATE `USER` SET name = ?, email = ?, phone = ?, role = ?, account_status = ? WHERE user_id = ?");
    $stmt->bind_param("ssssss", $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['role'], $_POST['status'], $_POST['user_id']);
    
    if ($stmt->execute()) {
        header("Location: admin_manage_club.php?msg=success");
        exit();
    } else {
        $error = "Update failed: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #e2e8f0; padding: 40px; }
        .card { background: white; max-width: 500px; margin: auto; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 5px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px; }
        .btn { background: #3182ce; color: white; border: none; padding: 12px; border-radius: 6px; cursor: pointer; width: 100%; font-weight: 600; margin-top: 10px; }
        .btn:hover { background: #2b6cb0; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Edit User: <?php echo htmlspecialchars($user['user_id']); ?></h2>
        <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
        
        <form method="POST">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
            
            <div class="form-group"><label>Name</label><input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
            <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>"></div>
            
            <div class="form-group">
                <label>Role</label>
                <select name="role">
                    <option value="Student" <?php if($user['role']=='Student') echo 'selected'; ?>>Student</option>
                    <option value="Committee" <?php if($user['role']=='Committee') echo 'selected'; ?>>Committee</option>
                    <option value="Admin" <?php if($user['role']=='Admin') echo 'selected'; ?>>Admin</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="Approved" <?php if($user['account_status']=='Approved') echo 'selected'; ?>>Approved</option>
                    <option value="Pending" <?php if($user['account_status']=='Pending') echo 'selected'; ?>>Pending</option>
                    <option value="Rejected" <?php if($user['account_status']=='Rejected') echo 'selected'; ?>>Rejected</option>
                </select>
            </div>
            
            <button type="submit" name="save_user" class="btn">Update Profile</button>
            <a href="admin_manage_club.php" style="display:block; text-align:center; margin-top:15px; color:#718096; text-decoration:none; font-size:14px;">Cancel</a>
        </form>
    </div>
</body>
</html>