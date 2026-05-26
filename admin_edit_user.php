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

// If no user found, redirect back to the correct users management page
if (!$user) {
    header("Location: admin_manage_users.php");
    exit();
}

// 3. HANDLE UPDATE SUBMISSION
if (isset($_POST['save_user'])) {
    $stmt = $conn->prepare("UPDATE `USER` SET name = ?, email = ?, phone = ?, role = ?, account_status = ? WHERE user_id = ?");
    $stmt->bind_param("ssssss", $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['role'], $_POST['status'], $_POST['user_id']);
    
    if ($stmt->execute()) {
        header("Location: admin_manage_users.php?msg=success");
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
    <title>Edit User Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Global Reset & Layout */
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: #f4f7f6; display: flex; min-height: 100vh; }
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
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 600px; border-top: 4px solid #3182ce; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 8px; color: #4a5568; }
        
        input[type="text"], input[type="email"], select { 
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
            
            <form method="POST">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                
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
                            <option value="Approved" <?php if($user['account_status']=='Approved') echo 'selected'; ?>>Approved</option>
                            <option value="Pending" <?php if($user['account_status']=='Pending') echo 'selected'; ?>>Pending</option>
                            <option value="Rejected" <?php if($user['account_status']=='Rejected') echo 'selected'; ?>>Rejected</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="save_user" class="btn-primary">Update Profile</button>
            </form>
        </div>
    </div>

</body>
</html>