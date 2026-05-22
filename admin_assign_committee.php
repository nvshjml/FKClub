<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

if ($_SESSION['role'] != 'Admin') { header("Location: index.php"); exit(); }

$msg = "";
if (isset($_POST['assign_btn'])) {
    $stmt = $conn->prepare("INSERT INTO committee (user_id, club_id, position) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $_POST['user_id'], $_POST['club_id'], $_POST['position']);
    if($stmt->execute()) $msg = "<div class='alert alert-success'>✅ Role assigned successfully!</div>";
}

$selected_user = $_GET['user_id'] ?? '';
$users = $conn->query("SELECT user_id, name FROM `USER` WHERE role = 'Committee'");
$clubs = $conn->query("SELECT club_id, club_name FROM CLUB");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Assign Committee</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; }
        .card { background: white; max-width: 500px; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        select { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #cbd5e0; border-radius: 6px; }
        .btn { background: #3182ce; color: white; border: none; padding: 12px; border-radius: 6px; cursor: pointer; width: 100%; font-weight: 600; }
        .alert-success { background: #c6f6d5; color: #22543d; padding: 10px; border-radius: 6px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="card">
            <h2>Assign Committee Role</h2>
            <?php echo $msg; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Committee Member</label>
                    <select name="user_id" required>
                        <?php while($u = $users->fetch_assoc()): ?>
                            <option value="<?php echo $u['user_id']; ?>" <?php if($selected_user == $u['user_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($u['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Club</label>
                    <select name="club_id" required>
                        <?php while($c = $clubs->fetch_assoc()): ?>
                            <option value="<?php echo $c['club_id']; ?>"><?php echo htmlspecialchars($c['club_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <select name="position" required>
                        <option value="President">President</option>
                        <option value="Secretary">Secretary</option>
                        <option value="Treasurer">Treasurer</option>
                        <option value="Committee Member">Committee Member</option>
                    </select>
                </div>
                <button type="submit" name="assign_btn" class="btn">Assign Role</button>
                <a href="admin_manage_users.php" style="display:block; text-align:center; margin-top:15px; color:#718096; text-decoration:none; font-size:14px;">Back to Users</a>
            </form>
        </div>
    </div>
</body>
</html>