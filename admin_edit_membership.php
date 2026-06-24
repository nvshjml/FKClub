<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

$user_id = $_GET['user_id'] ?? '';
$club_id = $_GET['club_id'] ?? '';
$message = "";

if (isset($_POST['update_membership'])) {
    $new_position = $_POST['position'];
    $can_update = true;

    if ($new_position === 'President') {
        $check_stmt = $conn->prepare("SELECT user_id FROM committee WHERE club_id = ? AND position = 'President' AND user_id != ?");
        $check_stmt->bind_param("ss", $club_id, $user_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $message = "<div class='alert alert-error'>❌ Error: This club already has a President. You cannot have two.</div>";
            $can_update = false;
        }
    }

    if ($can_update) {
        $stmt = $conn->prepare("UPDATE committee SET position = ? WHERE user_id = ? AND club_id = ?");
        $stmt->bind_param("sss", $new_position, $user_id, $club_id);
        
        if ($stmt->execute()) {
            header("Location: admin_manage_club.php?club_id=$club_id");
            exit();
        } else {
            $message = "<div class='alert alert-error'>❌ Error updating position.</div>";
        }
    }
}

$member_stmt = $conn->prepare("SELECT position FROM committee WHERE user_id = ? AND club_id = ?");
$member_stmt->bind_param("ss", $user_id, $club_id);
$member_stmt->execute();
$member = $member_stmt->get_result()->fetch_assoc();

$user_stmt = $conn->prepare("SELECT name FROM `user` WHERE user_id = ?");
$user_stmt->bind_param("s", $user_id);
$user_stmt->execute();
$user_info = $user_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Member Position</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: #e2e8f0; display: flex; min-height: 100vh; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h2 { color: #1a202c; font-size: 24px; margin: 0; }
        .btn-primary { background: #3182ce; color: white; border: none; padding: 12px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 14px; width: 100%; margin-top: 10px; }
        .btn-primary:hover { background: #2b6cb0; }
        .btn-back { display: inline-block; background: #718096; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 14px; transition: 0.2s; }
        .btn-back:hover { background: #4a5568; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 500px; border-top: 4px solid #3182ce; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 8px; color: #4a5568; }
        select { width: 100%; padding: 12px 15px; border-radius: 8px; border: 1px solid #cbd5e0; outline: none; font-size: 14px; transition: border 0.2s; color: #2d3748; background-color: #fff; }
        select:focus { border-color: #3182ce; box-shadow: 0 0 0 3px rgba(49,130,206,0.1); }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .alert-error { background: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h2>✏️ Edit Member Position</h2>
            <a href="admin_manage_club.php?club_id=<?php echo htmlspecialchars($club_id); ?>" class="btn-back">← Back</a>
        </div>
        <div class="card">
            <?php echo $message; ?>
            <p style="margin-bottom: 20px; color: #4a5568;">Updating role for: <strong><?php echo htmlspecialchars($user_info['name'] ?? 'Unknown User'); ?></strong> (<?php echo htmlspecialchars($user_id); ?>)</p>
            
            <form method="POST">
                <div class="form-group">
                    <label>New Position/Role:</label>
                    <select name="position">
                        <option value="President" <?php if(isset($member['position']) && $member['position']=='President') echo 'selected'; ?>>President</option>
                        <option value="Vice President" <?php if(isset($member['position']) && $member['position']=='Vice President') echo 'selected'; ?>>Vice President</option>
                        <option value="Secretary" <?php if(isset($member['position']) && $member['position']=='Secretary') echo 'selected'; ?>>Secretary</option>
                        <option value="Treasurer" <?php if(isset($member['position']) && $member['position']=='Treasurer') echo 'selected'; ?>>Treasurer</option>
                        <option value="Committee Member" <?php if(isset($member['position']) && $member['position']=='Committee Member') echo 'selected'; ?>>Committee Member</option>
                    </select>
                </div>
                <button type="submit" name="update_membership" class="btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
</body>
</html>