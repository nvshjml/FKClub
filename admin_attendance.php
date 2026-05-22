<?php
session_start();
require 'db_connect.php';

// SECURITY CHECK: Only Admins can access this page!
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

$message = "";
$edit_record = null;

// ============================================================
// USING CORRECT PRIMARY KEY: attend_id (from your database)
// ============================================================
$pk_column = 'attend_id';

// ============================================================
// HANDLE DELETE ATTENDANCE RECORD
// ============================================================
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // First get the register_id and points to update user's total points
    $get_sql = "SELECT register_id, point_awarded FROM ATTENDANCE WHERE $pk_column = ?";
    $get_stmt = $conn->prepare($get_sql);
    $get_stmt->bind_param("i", $delete_id);
    $get_stmt->execute();
    $att_record = $get_stmt->get_result()->fetch_assoc();
    
    if ($att_record) {
        // Get the user_id from event_registration
        $user_sql = "SELECT user_id FROM EVENT_REGISTRATION WHERE register_id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("i", $att_record['register_id']);
        $user_stmt->execute();
        $user = $user_stmt->get_result()->fetch_assoc();
        
        if ($user) {
            // Subtract the points from user's total
            $update_user = "UPDATE `USER` SET total_point = total_point - ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_user);
            $update_stmt->bind_param("is", $att_record['point_awarded'], $user['user_id']);
            $update_stmt->execute();
        }
        
        // Delete the attendance record
        $delete_sql = "DELETE FROM ATTENDANCE WHERE $pk_column = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $message = "<div class='alert alert-success'>✅ Attendance record deleted successfully! Student points have been updated.</div>";
        } else {
            $message = "<div class='alert alert-error'>❌ Error deleting record.</div>";
        }
    }
}

// ============================================================
// HANDLE EDIT ATTENDANCE RECORD
// ============================================================
if (isset($_POST['update_attendance'])) {
    $attendance_id = intval($_POST['attendance_id']);
    $attend_status = $_POST['attend_status'];
    $new_points = intval($_POST['point_awarded']);
    
    // Get old points before update
    $old_sql = "SELECT point_awarded, register_id FROM ATTENDANCE WHERE $pk_column = ?";
    $old_stmt = $conn->prepare($old_sql);
    $old_stmt->bind_param("i", $attendance_id);
    $old_stmt->execute();
    $old_data = $old_stmt->get_result()->fetch_assoc();
    
    if ($old_data) {
        $old_points = $old_data['point_awarded'];
        $register_id = $old_data['register_id'];
        
        // Update attendance record
        $update_sql = "UPDATE ATTENDANCE SET attend_status = ?, point_awarded = ? WHERE $pk_column = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sii", $attend_status, $new_points, $attendance_id);
        
        if ($update_stmt->execute()) {
            // Get the user_id from event_registration
            $user_sql = "SELECT user_id FROM EVENT_REGISTRATION WHERE register_id = ?";
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param("i", $register_id);
            $user_stmt->execute();
            $user = $user_stmt->get_result()->fetch_assoc();
            
            if ($user) {
                // Update user's total points (subtract old, add new)
                $points_diff = $new_points - $old_points;
                $update_user = "UPDATE `USER` SET total_point = total_point + ? WHERE user_id = ?";
                $update_user_stmt = $conn->prepare($update_user);
                $update_user_stmt->bind_param("is", $points_diff, $user['user_id']);
                $update_user_stmt->execute();
            }
            
            $message = "<div class='alert alert-success'>✏️ Attendance record updated successfully! Student points have been adjusted.</div>";
            $edit_record = null;
        } else {
            $message = "<div class='alert alert-error'>❌ Error updating record.</div>";
        }
    }
}

// ============================================================
// GET EDIT RECORD
// ============================================================
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_sql = "
        SELECT a.$pk_column as attendance_id, a.attend_status, a.point_awarded, a.register_id,
               u.user_id, u.name AS student_name, e.event_name, e.date, a.start_time
        FROM ATTENDANCE a
        JOIN EVENT_REGISTRATION er ON a.register_id = er.register_id
        JOIN `USER` u ON er.user_id = u.user_id
        JOIN EVENT e ON er.event_id = e.event_id
        WHERE a.$pk_column = ?
    ";
    $edit_stmt = $conn->prepare($edit_sql);
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $edit_record = $edit_stmt->get_result()->fetch_assoc();
}

// ============================================================
// FETCH MASTER ATTENDANCE LIST
// ============================================================
$attendance_sql = "
    SELECT 
        a.$pk_column as attendance_id,
        u.user_id, 
        u.name AS student_name, 
        e.event_name, 
        c.club_name, 
        e.date, 
        a.start_time,
        a.attend_status, 
        a.point_awarded
    FROM ATTENDANCE a
    JOIN EVENT_REGISTRATION er ON a.register_id = er.register_id
    JOIN `USER` u ON er.user_id = u.user_id
    JOIN EVENT e ON er.event_id = e.event_id
    JOIN CLUB c ON e.club_id = c.club_id
    ORDER BY e.date DESC, a.start_time DESC
";
$attendance_result = $conn->query($attendance_sql);

if (!$attendance_result) {
    die("Query Error: " . $conn->error);
}

// Fetch pending count for sidebar badge
$sidebar_pending_count = 0;
$sidebar_stmt = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE account_status = 'Pending'");
if($sidebar_stmt) {
    $sidebar_pending_count = $sidebar_stmt->fetch_assoc()['total'];
}

$current_page = basename($_SERVER['PHP_SELF']); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Attendance List - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; margin: 0; }

        .sidebar { 
            width: 260px; background-color: #1a202c; color: white; display: flex; flex-direction: column; 
            padding: 30px 20px; position: fixed; height: 100vh; box-shadow: 4px 0 10px rgba(0,0,0,0.1); 
            z-index: 1000; top: 0; left: 0; box-sizing: border-box; 
        }
        .sidebar-header { text-align: center; margin-bottom: 35px; }
        .sidebar-logo { max-width: 85px; margin-bottom: 12px; display: inline-block; }
        .sidebar-brand { font-size: 20px; font-weight: 700; color: #ffffff; margin-bottom: 6px; }
        .sidebar-role { font-size: 11px; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 1.5px; background: rgba(255,255,255,0.1); padding: 4px 12px; border-radius: 20px; display: inline-block; }

        .nav-links-sidebar { display: flex; flex-direction: column; gap: 15px; flex-grow: 1; }
        .nav-links-sidebar a { 
            text-decoration: none; color: #a0aec0; font-weight: 600; padding: 12px 15px; 
            border-radius: 8px; transition: 0.3s; display: flex; align-items: center; justify-content: space-between; font-size: 15px; 
        }
        .nav-links-sidebar a:hover, .nav-links-sidebar a.active { background-color: #2d3748; color: white; }
        .badge-sidebar { background: #e53e3e; color: white; border-radius: 12px; padding: 2px 8px; font-size: 12px; font-weight: bold; }
        .btn-logout-sidebar { background-color: #e53e3e; color: white; text-align: center; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: bold; margin-top: auto; transition: 0.2s; }
        .btn-logout-sidebar:hover { background-color: #c53030; }

        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; width: calc(100% - 260px); box-sizing: border-box; }
        .content-wrapper { display: flex; justify-content: center; width: 100%; align-items: flex-start; }
        
        .card { background-color: rgba(255, 255, 255, 0.95); padding: 35px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 1300px; backdrop-filter: blur(10px); border-top: 6px solid #3182ce; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .card-header h2 { color: #1a202c; font-size: 22px; margin: 0; }
        .btn-export { background: #edf2f7; color: #4a5568; border: 1px solid #cbd5e0; padding: 8px 15px; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 14px; transition: 0.2s; }
        .btn-export:hover { background: #e2e8f0; color: #2d3748; }

        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        thead { background-color: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        th { padding: 16px 15px; font-size: 13px; color: #4a5568; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        td { padding: 16px 15px; font-size: 14px; color: #2d3748; border-bottom: 1px solid #edf2f7; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #f8fafc; }

        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; text-align: center; min-width: 80px; }
        .status-present { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .status-late { background-color: #fefcbf; color: #744210; border: 1px solid #fbd38d; }
        .status-absent { background-color: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        
        .points-awarded { font-weight: bold; color: #3182ce; }
        
        .action-buttons { display: flex; gap: 8px; }
        .btn-edit { background-color: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-edit:hover { background-color: #bee3f8; }
        .btn-delete { background-color: #fff5f5; color: #c53030; border: 1px solid #fed7d7; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-delete:hover { background-color: #fed7d7; }
        
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background-color: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 2000; }
        .modal-content { background: white; border-radius: 16px; width: 90%; max-width: 500px; padding: 30px; }
        .modal-content h3 { margin-bottom: 20px; color: #1a202c; }
        .modal-content select, .modal-content input { width: 100%; padding: 12px; margin-bottom: 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-family: 'Inter', sans-serif; }
        .btn-submit { background: #3182ce; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-cancel { background: #e2e8f0; color: #4a5568; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; margin-left: 10px; font-weight: 600; text-decoration: none; display: inline-block; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">
            <img src="image/LogoUMP5.png" alt="UMPSA Logo" class="sidebar-logo">
            <div class="sidebar-brand">FK Club Admin</div>
            <div class="sidebar-role"><?php echo htmlspecialchars($_SESSION['role']); ?> Dashboard</div>
        </div>
        <div class="nav-links-sidebar">
            <a href="admin_dashboard.php" class="<?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
            <a href="admin_attendance.php" class="<?php echo ($current_page == 'admin_attendance.php') ? 'active' : ''; ?>">Attendance</a>
            <a href="pending_approvals.php">
                <span>Approvals</span>
                <?php if($sidebar_pending_count > 0): ?>
                    <span class="badge-sidebar"><?php echo $sidebar_pending_count; ?></span>
                <?php endif; ?>
            </a>
        </div>
        <a href="logout.php" class="btn-logout-sidebar">Logout</a>
    </div>

    <div class="main-content">
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h2>📋 Master Attendance Records</h2>
                    <button class="btn-export" onclick="window.print()">🖨️ Print Report</button>
                </div>
                
                <?php echo $message; ?>

                <div class="table-container">
                    <?php if ($attendance_result && $attendance_result->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Matrix ID</th>
                                    <th>Student Name</th>
                                    <th>Event</th>
                                    <th>Club</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Points</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $attendance_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['user_id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['event_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['club_name']); ?></td>
                                        <td>
                                            <?php echo date("d M Y", strtotime($row['date'])); ?><br>
                                            <span style="color: #718096; font-size: 12px;">
                                                <?php echo $row['start_time'] ? date("h:i A", strtotime($row['start_time'])) : '--:--'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($row['attend_status'] == 'Present') {
                                                echo '<span class="status-badge status-present">Present</span>';
                                            } elseif ($row['attend_status'] == 'Late') {
                                                echo '<span class="status-badge status-late">Late</span>';
                                            } else {
                                                echo '<span class="status-badge status-absent">Absent</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="points-awarded">
                                            <?php 
                                            $points = $row['point_awarded'];
                                            if ($points > 0) {
                                                echo '+' . $points;
                                            } else {
                                                echo $points;  // show negative or zero correctly
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?edit_id=<?php echo $row['attendance_id']; ?>" class="btn-edit">✏️ Edit</a>
                                                <a href="?delete_id=<?php echo $row['attendance_id']; ?>" class="btn-delete" onclick="return confirm('⚠️ Are you sure you want to DELETE this attendance record?\n\nStudent: <?php echo addslashes($row['student_name']); ?>\nEvent: <?php echo addslashes($row['event_name']); ?>\nPoints: +<?php echo $row['point_awarded']; ?>\n\nThis will also update the student\'s total points!')">🗑️ Delete</a>
                                            </div>
                                         </span>
                                        </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #718096; font-style: italic; padding: 20px 0; text-align: center; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e0;">
                            No attendance records found in the system yet.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <?php if ($edit_record): ?>
    <div id="editModal" class="modal" style="display: flex;">
        <div class="modal-content">
            <h3>✏️ Edit Attendance Record</h3>
            <p style="margin-bottom: 15px; color: #718096;">
                <strong>Student:</strong> <?php echo htmlspecialchars($edit_record['student_name']); ?><br>
                <strong>Event:</strong> <?php echo htmlspecialchars($edit_record['event_name']); ?>
            </p>
            <form method="POST" action="">
                <input type="hidden" name="attendance_id" value="<?php echo $edit_record['attendance_id']; ?>">
                
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Attendance Status</label>
                <select name="attend_status" required>
                    <option value="Present" <?php echo $edit_record['attend_status'] == 'Present' ? 'selected' : ''; ?>>Present (+10 points)</option>
                    <option value="Late" <?php echo $edit_record['attend_status'] == 'Late' ? 'selected' : ''; ?>>Late (+5 points)</option>
                    <option value="Absent" <?php echo $edit_record['attend_status'] == 'Absent' ? 'selected' : ''; ?>>Absent (-10 points)</option>
                </select>
                
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Points to Award</label>
                <input type="number" name="point_awarded" value="<?php echo $edit_record['point_awarded']; ?>" required>
                <small style="color: #718096;">Present: +10 | Late: +5 | Absent: -10</small>
                
                <div style="margin-top: 20px;">
                    <button type="submit" name="update_attendance" class="btn-submit">💾 Save Changes</button>
                    <a href="admin_attendance.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        window.onclick = function(event) {
            var modal = document.getElementById('editModal');
            if (event.target == modal) {
                window.location.href = 'admin_attendance.php';
            }
        }
    </script>
</body>
</html>