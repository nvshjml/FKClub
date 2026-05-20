<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'Admin') {
    header("Location: index.php"); exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

if (isset($_POST['update_btn'])) {
    $new_phone = $_POST['phone'];
    $new_password = $_POST['password'];
    
    if (!empty($new_password)) {
        $stmt = $conn->prepare("UPDATE `USER` SET phone = ?, pass_hash = ? WHERE user_id = ?");
        $stmt->bind_param("sss", $new_phone, $new_password, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE `USER` SET phone = ? WHERE user_id = ?");
        $stmt->bind_param("ss", $new_phone, $user_id);
    }
    
    if ($stmt->execute()) {
        $message = "<div style='background:#c6f6d5;color:#22543d;padding:12px;border-radius:8px;margin-bottom:20px;'>✅ Profile updated!</div>";
    }
}

$stmt = $conn->prepare("SELECT * FROM `USER` WHERE user_id = ?");
$stmt->bind_param("s", $user_id); $stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('https://images.unsplash.com/photo-1541339907198-e08756dedf3f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') no-repeat center center/cover; min-height: 100vh; backdrop-filter: blur(5px); }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; display: flex; justify-content: center; align-items: flex-start;}
        .card { background: rgba(255, 255, 255, 0.95); padding: 35px; border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.05); width: 100%; max-width: 600px; }
        .card h2 { margin-bottom: 20px; }
        .info-list { list-style: none; margin-bottom: 25px; }
        .info-list li { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #edf2f7; font-size: 14px; }
        input { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; margin-bottom: 15px; }
        .btn-save { background: #3182ce; color: white; border: none; padding: 12px 20px; border-radius: 8px; width: 100%; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="card">
            <h2>👤 My Profile</h2>
            <?php echo $message; ?>
            
            <ul class="info-list">
                <li><span>Name</span><strong><?php echo htmlspecialchars($user_data['name']); ?></strong></li>
                <li><span>Matrix ID</span><strong><?php echo htmlspecialchars($user_data['user_id']); ?></strong></li>
                <li><span>Email</span><strong><?php echo htmlspecialchars($user_data['email']); ?></strong></li>
                <?php if ($user_data['role'] == 'Student'): ?>
                    <li><span>Total Points</span><strong style="color: #38a169;"><?php echo htmlspecialchars($user_data['total_point']); ?></strong></li>
                <?php endif; ?>
            </ul>

            <form method="POST">
                <label>Update Phone</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($user_data['phone']); ?>" required>
                
                <label>New Password</label>
                <input type="password" name="password" placeholder="(Leave blank to keep current)">
                
                <button type="submit" name="update_btn" class="btn-save">Save Changes</button>
            </form>
        </div>
    </div>
</body>
</html>