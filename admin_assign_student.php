<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

// SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

$msg = "";
// Handle Assignment Submission
if (isset($_POST['assign_btn'])) {
    $stmt = $conn->prepare("INSERT INTO committee (user_id, club_id, position) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $_POST['user_id'], $_POST['club_id'], $_POST['position']);
    
    if($stmt->execute()) {
        $msg = "<div class='alert alert-success'>✅ Student assigned to club successfully!</div>";
    } else {
        $msg = "<div class='alert alert-error'>❌ Error: " . $conn->error . "</div>";
    }
}

// Get user from URL
$selected_user_id = $_GET['user_id'] ?? '';

// Fetch Students and Clubs
$students = $conn->query("SELECT user_id, name FROM `USER` WHERE role = 'Student'");
$clubs = $conn->query("SELECT club_id, club_name FROM CLUB");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Student to Club</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: #e2e8f0; padding: 40px; }
        .card { background: white; max-width: 500px; margin: auto; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 5px; }
        select { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px; }
        .btn { background: #3182ce; color: white; border: none; padding: 12px; border-radius: 6px; cursor: pointer; width: 100%; font-weight: 600; }
        .alert-success { background: #c6f6d5; color: #22543d; padding: 10px; border-radius: 6px; margin-bottom: 15px; }
        .alert-error { background: #fed7d7; color: #822727; padding: 10px; border-radius: 6px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Assign Student to Club</h2>
        <?php echo $msg; ?>
        <form method="POST">
            <div class="form-group">
                <label>Select Student</label>
                <select name="user_id" required>
                    <?php while($s = $students->fetch_assoc()): ?>
                        <option value="<?php echo $s['user_id']; ?>" <?php if($selected_user_id == $s['user_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($s['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Select Club</label>
                <select name="club_id" required>
                    <?php while($c = $clubs->fetch_assoc()): ?>
                        <option value="<?php echo $c['club_id']; ?>"><?php echo htmlspecialchars($c['club_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Position / Role</label>
                <select name="position" required>
                    <option value="Member">Member</option>
                    <option value="General Representative">General Representative</option>
                </select>
            </div>
            <button type="submit" name="assign_btn" class="btn">Assign Student</button>
            <a href="admin_manage_users.php" style="display:block; text-align:center; margin-top:15px; color:#718096; text-decoration:none; font-size:14px;">Back to Users</a>
        </form>
    </div>
</body>
</html>