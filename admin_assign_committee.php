<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') { header("Location: index.php"); exit(); }

$msg = "";

if (isset($_POST['assign_btn'])) {
    $user_id = $_POST['user_id'];
    $club_id = $_POST['club_id'];
    $position = $_POST['position'];
    
    $can_assign = true;

    // 1. Check 3-Club Maximum Limit
    $check_existing_club = $conn->prepare("SELECT status FROM club_membership WHERE user_id = ? AND club_id = ?");
    $check_existing_club->bind_param("ss", $user_id, $club_id);
    $check_existing_club->execute();
    $is_already_in_club = $check_existing_club->get_result()->num_rows > 0;

    if (!$is_already_in_club) {
        $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM club_membership WHERE user_id = ? AND status IN ('Approved', 'Pending')");
        $count_stmt->bind_param("s", $user_id);
        $count_stmt->execute();
        $current_count = $count_stmt->get_result()->fetch_assoc()['total'];

        if ($current_count >= 3) {
            $msg = "<div class='alert alert-error'>❌ Error: This student has already reached the maximum limit of joining 3 clubs.</div>";
            $can_assign = false;
        }
    }

    // 2. Check if a President already exists for this club
    if ($can_assign && $position === 'President') {
        $check_stmt = $conn->prepare("SELECT user_id FROM committee WHERE club_id = ? AND position = 'President'");
        $check_stmt->bind_param("s", $club_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $msg = "<div class='alert alert-error'>❌ Error: This club already has a President. Please demote or remove the current President first.</div>";
            $can_assign = false;
        }
    }

    // 3. Proceed with assignment & Auto-Sync
    if ($can_assign) {
        $stmt = $conn->prepare("INSERT INTO committee (user_id, club_id, position) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $user_id, $club_id, $position);
        
        if($stmt->execute()) {
            
            // Auto-Sync to club_membership
            $check_mem = $conn->prepare("SELECT status FROM club_membership WHERE user_id = ? AND club_id = ?");
            $check_mem->bind_param("ss", $user_id, $club_id); 
            $check_mem->execute();
            $mem_result = $check_mem->get_result();
            
            if ($mem_result->num_rows == 0) {
                $ins_mem = $conn->prepare("INSERT INTO club_membership (user_id, club_id, join_date, status) VALUES (?, ?, CURDATE(), 'Approved')");
                $ins_mem->bind_param("ss", $user_id, $club_id);
                $ins_mem->execute();
            } else {
                $upd_mem = $conn->prepare("UPDATE club_membership SET status = 'Approved' WHERE user_id = ? AND club_id = ?");
                $upd_mem->bind_param("ss", $user_id, $club_id);
                $upd_mem->execute();
            }

            $msg = "<div class='alert alert-success'>✅ Role assigned and added to club membership successfully!</div>";
        } else {
            if ($conn->errno == 1062) {
                $msg = "<div class='alert alert-error'>❌ Error: This student is already in this club's committee. Edit their role from the 'Manage Club' page.</div>";
            } else {
                $msg = "<div class='alert alert-error'>❌ Error: " . $stmt->error . "</div>";
            }
        }
    }
}

$selected_user = $_GET['user_id'] ?? '';
$users = $conn->query("SELECT user_id, name FROM `USER` WHERE role = 'Committee'");
$clubs = $conn->query("SELECT club_id, club_name FROM CLUB");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Assign Committee</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; margin: 0; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; width: calc(100% - 260px); }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h2 { color: #1a202c; font-size: 24px; margin: 0; }

        .btn-back {
            display: inline-flex;
            align-items: center;
            background: #718096;
            color: #ffffff;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: 0.2s;
            border: none;
        }
        .btn-back:hover { background: #4a5568; }

        .card { background: white; max-width: 600px; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-top: 4px solid #3182ce; }
        .form-group { margin-bottom: 20px; }
        label { font-size: 14px; font-weight: 600; color: #4a5568; display: block; margin-bottom: 8px; }
        select { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e0; border-radius: 8px; outline: none; font-size: 14px; transition: border 0.2s; color: #2d3748; }
        select:focus { border-color: #3182ce; box-shadow: 0 0 0 3px rgba(49,130,206,0.1); }
        
        .btn { background: #3182ce; color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; width: 100%; font-weight: 600; font-size: 15px; transition: 0.2s; margin-top: 10px; }
        .btn:hover { background: #2b6cb0; }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 14px; max-width: 600px; }
        .alert-success { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h2>Assign Committee Role</h2>
            <a href="admin_manage_users.php" class="btn-back">&larr; Back</a>
        </div>
        
        <?php if($msg) echo $msg; ?>
        
        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label>Committee Member</label>
                    <select name="user_id" required>
                        <?php while($u = $users->fetch_assoc()): ?>
                            <option value="<?php echo $u['user_id']; ?>" <?php if($selected_user == $u['user_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($u['name']) . " (" . htmlspecialchars($u['user_id']) . ")"; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Target Club</label>
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
                        <option value="Vice President">Vice President</option>
                        <option value="Secretary">Secretary</option>
                        <option value="Treasurer">Treasurer</option>
                        <option value="Committee Member">Committee Member</option>
                    </select>
                </div>
                
                <button type="submit" name="assign_btn" class="btn">Assign Role</button>
            </form>
        </div>
    </div>
</body>
</html>