<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

$msg = "";

if (isset($_POST['add_club_btn'])) {
    $club_name = trim($_POST['club_name']);
    $isActive = $_POST['isActive'];

    $check_stmt = $conn->prepare("SELECT club_id FROM CLUB WHERE club_name = ?");
    $check_stmt->bind_param("s", $club_name);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $msg = "<div class='alert alert-error'>❌ Error: A club with this name already exists.</div>";
    } else {
        $insert_stmt = $conn->prepare("INSERT INTO CLUB (club_name, isActive) VALUES (?, ?)");
        $insert_stmt->bind_param("si", $club_name, $isActive);
        
        if ($insert_stmt->execute()) {
            $msg = "<div class='alert alert-success'>✅ New club added successfully!</div>";
        } else {
            $msg = "<div class='alert alert-error'>❌ Database Error: " . $conn->error . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Club</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: #f4f7f6; display: flex; min-height: 100vh; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h2 { color: #1a202c; font-size: 24px; margin: 0; }

        .btn-primary { background: #3182ce; color: white; border: none; padding: 12px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 14px; width: 100%; margin-top: 10px; }
        .btn-primary:hover { background: #2b6cb0; }
        
        .btn-back { display: inline-block; background: #718096; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 14px; transition: 0.2s; }
        .btn-back:hover { background: #4a5568; }

        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 600px; border-top: 4px solid #3182ce; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 8px; color: #4a5568; }
        
        input[type="text"], select { width: 100%; padding: 12px 15px; border-radius: 8px; border: 1px solid #cbd5e0; outline: none; font-size: 14px; transition: border 0.2s; color: #2d3748; background-color: #fff; }
        input:focus, select:focus { border-color: #3182ce; box-shadow: 0 0 0 3px rgba(49,130,206,0.1); }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .alert-success { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2>🏆 Add New Club</h2>
            <a href="admin_club_analytics.php" class="btn-back">← Back</a>
        </div>

        <div class="card">
            <?php echo $msg; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Club Name</label>
                    <input type="text" name="club_name" placeholder="e.g. AI & Robotics Society" required>
                </div>
                
                <div class="form-group">
                    <label>Club Status</label>
                    <select name="isActive" required>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                
                <button type="submit" name="add_club_btn" class="btn-primary">Create Club</button>
            </form>
        </div>
    </div>

</body>
</html>