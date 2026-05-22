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
    $status = 'Approved';


    $check_sql = "SELECT * FROM `USER` WHERE user_id = ? OR email = ?";


    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("ss", $new_id, $email);
    $stmt_check->execute();
    
    if ($stmt_check->get_result()->num_rows > 0) {
        $message = "<div class='alert alert-error'>❌ Error: Matrix ID or Email already exists.</div>";
    } else {
        $insert_sql = "INSERT INTO `USER` (user_id, role, name, email, pass_hash, phone, account_status) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($insert_sql);
        $stmt_insert->bind_param("sssssss", $new_id, $role, $name, $email, $password, $phone, $status);
        
        if ($stmt_insert->execute()) {
            $message = "<div class='alert alert-success'>✅ User registered successfully.</div>";
        }
    }
}


if (isset($_POST['delete_user_btn'])) {
    $target_id = $_POST['target_id'];
    
    // Prevent admin from deleting themselves
    if ($target_id === $_SESSION['user_id']) {
        $message = "<div class='alert alert-error'>❌ You cannot delete your own Admin account.</div>";
    } else {
        $del_sql = "DELETE FROM `USER` WHERE user_id = ?";
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

$users_sql = "SELECT user_id, name, email, phone, role, account_status FROM `USER` ORDER BY role ASC, name ASC";
$users_result = $conn->query($users_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; margin-top: 10px; }
        .btn-primary { background: #3182ce; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-edit { background: #fefcbf; color: #744210; border: 1px solid #fbd38d; padding: 6px 10px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; }
        .btn-assign { background: #feebc8; color: #744210; border: 1px solid #f6e05e; padding: 6px 10px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; }
        .btn-danger { background: #fff5f5; color: #c53030; border: 1px solid #fed7d7; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; }
        .form-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; display: none; max-width: 800px; }
        .table-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 15px 20px; font-size: 13px; color: #4a5568; text-transform: uppercase; }
        td { padding: 15px 20px; border-bottom: 1px solid #edf2f7; font-size: 14px; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #e2e8f0; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background: #c6f6d5; color: #22543d; }
        .alert-error { background: #fed7d7; color: #822727; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2>👥 Manage Users</h2>
            <button class="btn-primary" onclick="toggleForm()">+ Register New User</button>
        </div>

        <?php if($message) echo $message; ?>

        <div class="form-card" id="addUserForm">
            <form action="admin_manage_users.php" method="POST">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <input type="text" name="new_id" placeholder="Matrix ID" required style="padding:10px; border-radius:6px; border:1px solid #ccc;">
                    <input type="text" name="name" placeholder="Full Name" required style="padding:10px; border-radius:6px; border:1px solid #ccc;">
                    <input type="email" name="email" placeholder="Email" required style="padding:10px; border-radius:6px; border:1px solid #ccc;">
                    <input type="text" name="phone" placeholder="Phone" required style="padding:10px; border-radius:6px; border:1px solid #ccc;">
                    <select name="role" required style="padding:10px; border-radius:6px; border:1px solid #ccc;">
                        <option value="Student">Student</option>
                        <option value="Committee">Committee</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <button type="submit" name="add_user_btn" class="btn-primary" style="margin-top:20px; width:100%;">Submit</button>
            </form>
        </div>

        <div class="table-card">
            <table>
                <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php while($u = $users_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['user_id']); ?></td>
                            <td><?php echo htmlspecialchars($u['name']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><span class="badge"><?php echo $u['role']; ?></span></td>
                            <td><?php echo $u['account_status']; ?></td>
                            <td style="display:flex; gap:8px;">
    <a href="admin_edit_user.php?user_id=<?php echo urlencode($u['user_id']); ?>" class="btn-edit">Edit</a>

    <?php if ($u['role'] == 'Committee'): ?>
        <a href="admin_assign_committee.php?user_id=<?php echo urlencode($u['user_id']); ?>" class="btn-assign">Assign Club</a>
    <?php elseif ($u['role'] == 'Student'): ?>
        <a href="admin_assign_student.php?user_id=<?php echo urlencode($u['user_id']); ?>" class="btn-assign">Assign Club</a>
    <?php endif; ?>

    <form method="POST" onsubmit="return confirm('Delete this user?');">
        <input type="hidden" name="target_id" value="<?php echo $u['user_id']; ?>">
        <button type="submit" name="delete_user_btn" class="btn-danger">Delete</button>
    </form>
</td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function toggleForm() {
            var form = document.getElementById("addUserForm");
            form.style.display = (form.style.display === "none" || form.style.display === "") ? "block" : "none";
        }
    </script>
</body>
</html>