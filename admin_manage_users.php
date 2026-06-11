<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

// SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

$message = "";

// ---------------------------------------------------------
// 1. HANDLE ADD NEW USER
// ---------------------------------------------------------
if (isset($_POST['add_user_btn'])) {
    $new_id = $_POST['new_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $role = $_POST['role'];
    $password = password_hash("12345", PASSWORD_DEFAULT);
    
    // THE FIX 1: Set default status to 'Active' instead of 'Approved'
    $status = 'Active';

    // Set points to strictly integer 0 to prevent MySQL Type Errors
    $starting_points = 0;

    $check_sql = "SELECT * FROM `user` WHERE user_id = ? OR email = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("ss", $new_id, $email);
    $stmt_check->execute();
    
    if ($stmt_check->get_result()->num_rows > 0) {
        $message = "<div class='alert alert-error'>❌ Error: Matrix ID or Email already exists.</div>";
    } else {
        $insert_sql = "INSERT INTO `user` (user_id, role, name, email, pass_hash, phone, account_status, total_point) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($insert_sql);
        
        $stmt_insert->bind_param("sssssssi", $new_id, $role, $name, $email, $password, $phone, $status, $starting_points);
        
        if ($stmt_insert->execute()) {
            $message = "<div class='alert alert-success'>✅ User registered successfully. Default password is '12345'.</div>";
        } else {
            $message = "<div class='alert alert-error'>❌ Database Error: " . htmlspecialchars($stmt_insert->error) . "</div>";
        }
    }
}

// ---------------------------------------------------------
// 2. HANDLE DELETE USER
// ---------------------------------------------------------
if (isset($_POST['delete_user_btn'])) {
    $target_id = $_POST['target_id'];
    
    if ($target_id === $_SESSION['user_id']) {
        $message = "<div class='alert alert-error'>❌ You cannot delete your own Admin account.</div>";
    } else {
        $del_sql = "DELETE FROM `user` WHERE user_id = ?";
        $stmt_del = $conn->prepare($del_sql);
        
        if ($stmt_del) {
            $stmt_del->bind_param("s", $target_id);
            if ($stmt_del->execute()) {
                $message = "<div class='alert alert-success'>🗑️ User has been permanently deleted.</div>";
            } else {
                $message = "<div class='alert alert-error'>❌ Database Error: " . $stmt_del->error . "</div>";
            }
        } else {
            $message = "<div class='alert alert-error'>❌ Prepare Failed: " . $conn->error . "</div>";
        }
    }
}

$users_sql = "SELECT user_id, name, email, phone, role, account_status FROM `user` ORDER BY role ASC, name ASC";
$users_result = $conn->query($users_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css"> <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; color: #333; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; max-width: 1200px; }
        /* Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h2 { color: #1a202c; font-size: 24px; margin: 0; }
        .header-actions { display: flex; gap: 15px; align-items: center; }
        
        /* Buttons */
        .btn-primary { background: #3182ce; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 14px; }
        .btn-primary:hover { background: #2b6cb0; }
        
        .action-links { display: flex; gap: 8px; align-items: center; }
        .btn-edit { background: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.2s; }
        .btn-edit:hover { background: #bee3f8; }
        
        .btn-assign { background: #feebc8; color: #744210; border: 1px solid #f6e05e; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.2s; }
        .btn-assign:hover { background: #fbd38d; }
        
        .btn-danger { background: #fff5f5; color: #c53030; border: 1px solid #fed7d7; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; transition: 0.2s; }
        .btn-danger:hover { background: #fed7d7; }

        .btn-back { display: inline-block; background: #718096; color: #ffffff; padding: 10px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; transition: 0.2s; border: none;}
        .btn-back:hover { background: #4a5568; color: #ffffff;}

        /* Form Card */
        .form-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; display: none; max-width: 800px; border-top: 4px solid #3182ce; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-input { width: 100%; padding: 12px 15px; border-radius: 8px; border: 1px solid #cbd5e0; outline: none; font-size: 14px; transition: border 0.2s; color: #2d3748; }
        .form-input:focus { border-color: #3182ce; box-shadow: 0 0 0 3px rgba(49,130,206,0.1); }

        /* Table Card */
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; text-align: left; min-width: 800px;}
        th { background: #f8fafc; padding: 15px 20px; font-size: 13px; color: #4a5568; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; font-weight: 600; }
        td { padding: 15px 20px; border-bottom: 1px solid #edf2f7; font-size: 14px; color: #2d3748; }
        tr:hover { background-color: #f8fafc; }
        
        /* Badges & Alerts */
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #edf2f7; color: #4a5568; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .alert-success { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2>👥 Manage Users</h2>
            <button class="btn-primary" onclick="toggleForm()">+ Register New User</button>
            <a href="admin_dashboard.php" class="btn-back">&larr; Back</a>
        </div>

        <?php if($message) echo $message; ?>

        <div class="form-card" id="addUserForm">
            <h3 style="margin-bottom: 20px; color: #2d3748; font-size: 18px;">Direct Registration</h3>
            <form action="admin_manage_users.php" method="POST">
                <div class="form-grid">
                    <input type="text" name="new_id" placeholder="Matrix ID" required class="form-input">
                    <input type="text" name="name" placeholder="Full Name" required class="form-input">
                    <input type="email" name="email" placeholder="Email Address" required class="form-input">
                    <input type="text" name="phone" placeholder="Phone Number" required class="form-input">
                    <select name="role" required class="form-input" style="grid-column: span 2;">
                        <option value="Student">Student</option>
                        <option value="Committee">Committee</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <button type="submit" name="add_user_btn" class="btn-primary" style="margin-top:20px; width:100%; padding: 12px;">Submit Registration</button>
            </form>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($u = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($u['user_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($u['name']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><span class="badge"><?php echo htmlspecialchars($u['role']); ?></span></td>
                                <td>
                                    <span style="color: <?php echo $u['account_status'] == 'Active' ? '#38a169' : '#e53e3e'; ?>; font-weight: 600; font-size: 13px;">
                                        <?php echo htmlspecialchars($u['account_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-links">
                                        <a href="admin_edit_user.php?user_id=<?php echo urlencode($u['user_id']); ?>" class="btn-edit">Edit</a>
                                        
                                        <?php if ($u['role'] == 'Committee'): ?>
                                            <a href="admin_assign_committee.php?user_id=<?php echo urlencode($u['user_id']); ?>" class="btn-assign">Assign Club</a>
                                        <?php elseif ($u['role'] == 'Student'): ?>
                                            <a href="admin_assign_student.php?user_id=<?php echo urlencode($u['user_id']); ?>" class="btn-assign">Assign Club</a>
                                        <?php endif; ?>
                                        
                                        <form method="POST" onsubmit="return confirm('Delete this user?');" style="margin: 0;">
                                            <input type="hidden" name="target_id" value="<?php echo $u['user_id']; ?>">
                                            <button type="submit" name="delete_user_btn" class="btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function toggleForm() {
            var form = document.getElementById("addUserForm");
            if (form.style.display === "none" || form.style.display === "") {
                form.style.display = "block";
            } else {
                form.style.display = "none";
            }
        }
    </script>
</body>
</html>