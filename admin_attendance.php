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
$pk_column = 'attend_id';

// ============================================================
// HANDLE DELETE ATTENDANCE RECORD
// ============================================================
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    $get_sql = "SELECT register_id, point_awarded FROM ATTENDANCE WHERE $pk_column = ?";
    $get_stmt = $conn->prepare($get_sql);
    $get_stmt->bind_param("i", $delete_id);
    $get_stmt->execute();
    $att_record = $get_stmt->get_result()->fetch_assoc();
    
    if ($att_record) {
        $user_sql = "SELECT user_id FROM EVENT_REGISTRATION WHERE register_id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("i", $att_record['register_id']);
        $user_stmt->execute();
        $user = $user_stmt->get_result()->fetch_assoc();
        
        if ($user) {
            $update_user = "UPDATE `USER` SET total_point = GREATEST(0, total_point - ?) WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_user);
            $update_stmt->bind_param("is", $att_record['point_awarded'], $user['user_id']);
            $update_stmt->execute();
        }
        
        $delete_sql = "DELETE FROM ATTENDANCE WHERE $pk_column = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $message = "<div class='alert alert-success'>✅ Attendance record deleted successfully! Student points updated.</div>";
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
    
    $old_sql = "SELECT point_awarded, register_id FROM ATTENDANCE WHERE $pk_column = ?";
    $old_stmt = $conn->prepare($old_sql);
    $old_stmt->bind_param("i", $attendance_id);
    $old_stmt->execute();
    $old_data = $old_stmt->get_result()->fetch_assoc();
    
    if ($old_data) {
        $old_points = $old_data['point_awarded'];
        $register_id = $old_data['register_id'];
        
        $update_sql = "UPDATE ATTENDANCE SET attend_status = ?, point_awarded = ? WHERE $pk_column = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sii", $attend_status, $new_points, $attendance_id);
        
        if ($update_stmt->execute()) {
            $user_sql = "SELECT user_id FROM EVENT_REGISTRATION WHERE register_id = ?";
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param("i", $register_id);
            $user_stmt->execute();
            $user = $user_stmt->get_result()->fetch_assoc();
            
            if ($user) {
                $points_diff = $new_points - $old_points;
                $update_user = "UPDATE `USER` SET total_point = GREATEST(0, total_point + ?) WHERE user_id = ?";
                $update_user_stmt = $conn->prepare($update_user);
                $update_user_stmt->bind_param("is", $points_diff, $user['user_id']);
                $update_user_stmt->execute();
            }
            
            $message = "<div class='alert alert-success'>✏️ Attendance record updated successfully!</div>";
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
        a.$pk_column as attendance_id, u.user_id, u.name AS student_name, 
        e.event_name, c.club_name, e.date, a.start_time, a.attend_status, a.point_awarded
    FROM ATTENDANCE a
    JOIN EVENT_REGISTRATION er ON a.register_id = er.register_id
    JOIN `USER` u ON er.user_id = u.user_id
    JOIN EVENT e ON er.event_id = e.event_id
    JOIN CLUB c ON e.club_id = c.club_id
    ORDER BY e.date DESC, a.start_time DESC
";
$attendance_result = $conn->query($attendance_sql);
$current_page = basename($_SERVER['PHP_SELF']); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Master Attendance List - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; margin: 0; }

        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; width: calc(100% - 260px); box-sizing: border-box; }
        .content-wrapper { display: flex; justify-content: center; width: 100%; align-items: flex-start; }
        
        .card { background-color: rgba(255, 255, 255, 0.95); padding: 35px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 1300px; border-top: 6px solid #3182ce; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .card-header h2 { color: #1a202c; font-size: 22px; margin: 0; }
        .btn-export { background: #edf2f7; color: #4a5568; border: 1px solid #cbd5e0; padding: 8px 15px; border-radius: 6px; font-weight: 600; cursor: pointer; }

        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        thead { background-color: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        th { padding: 16px 15px; font-size: 13px; color: #4a5568; font-weight: 600; text-transform: uppercase; }
        td { padding: 16px 15px; font-size: 14px; color: #2d3748; border-bottom: 1px solid #edf2f7; vertical-align: middle; }
        tr:hover { background-color: #f8fafc; }

        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; text-align: center; min-width: 80px; }
        .status-present { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .status-late { background-color: #fefcbf; color: #744210; border: 1px solid #fbd38d; }
        /* ADDED VOLUNTEER CSS */
        .status-volunteer { background-color: #ebf8ff; color: #2b6cb0; border: 1px solid #90cdf4; }
        .status-absent { background-color: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        
        .points-awarded { font-weight: bold; color: #3182ce; }
        
        .action-buttons { display: flex; gap: 8px; }
        .btn-edit { background-color: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600; }
        .btn-delete { background-color: #fff5f5; color: #c53030; border: 1px solid #fed7d7; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600; }
        
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background-color: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 2000; }
        .modal-content { background: white; border-radius: 16px; width: 90%; max-width: 500px; padding: 30px; }
        .modal-content input, .modal-content select { width: 100%; padding: 12px; margin-bottom: 15px; border: 2px solid #e2e8f0; border-radius: 8px; }
        .btn-submit { background: #3182ce; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-cancel { background: #e2e8f0; color: #4a5568; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; margin-left: 10px; text-decoration: none; display: inline-block; font-weight: 600; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

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
                                            // ADDED VOLUNTEER DISPLAY LOGIC
                                            if ($row['attend_status'] == 'Present') {
                                                echo '<span class="status-badge status-present">Present</span>';
                                            } elseif ($row['attend_status'] == 'Late') {
                                                echo '<span class="status-badge status-late">Late</span>';
                                            } elseif ($row['attend_status'] == 'Volunteer') {
                                                echo '<span class="status-badge status-volunteer">Volunteer</span>';
                                            } else {
                                                echo '<span class="status-badge status-absent">Absent</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="points-awarded">
                                            <?php 
                                            $points = $row['point_awarded'];
                                            echo ($points > 0) ? '+' . $points : $points;
                                            ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?edit_id=<?php echo $row['attendance_id']; ?>" class="btn-edit">✏️ Edit</a>
                                                <a href="?delete_id=<?php echo $row['attendance_id']; ?>" class="btn-delete" onclick="return confirm('Delete this record? Student points will be updated.')">🗑️ Delete</a>
                                            </div>
                                         </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #718096; padding: 20px;">No attendance records found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($edit_record): ?>
    <div id="editModal" class="modal" style="display: flex;">
        <div class="modal-content">
            <h3 style="color:#1a202c;">✏️ Edit Attendance</h3>
            <p style="margin-bottom: 15px; color: #718096;">
                <strong>Student:</strong> <?php echo htmlspecialchars($edit_record['student_name']); ?><br>
                <strong>Event:</strong> <?php echo htmlspecialchars($edit_record['event_name']); ?>
            </p>
            <form method="POST">
                <input type="hidden" name="attendance_id" value="<?php echo $edit_record['attendance_id']; ?>">
                
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Status</label>
                <select name="attend_status" required>
                    <option value="Present" <?php echo $edit_record['attend_status'] == 'Present' ? 'selected' : ''; ?>>Present (+10)</option>
                    <option value="Late" <?php echo $edit_record['attend_status'] == 'Late' ? 'selected' : ''; ?>>Late (+5)</option>
                    <option value="Volunteer" <?php echo $edit_record['attend_status'] == 'Volunteer' ? 'selected' : ''; ?>>Volunteer (+15)</option>
                    <option value="Absent" <?php echo $edit_record['attend_status'] == 'Absent' ? 'selected' : ''; ?>>Absent (-10)</option>
                </select>
                
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Points</label>
                <input type="number" name="point_awarded" value="<?php echo $edit_record['point_awarded']; ?>" required>
                
                <div style="margin-top: 20px;">
                    <button type="submit" name="update_attendance" class="btn-submit">Save</button>
                    <a href="admin_attendance.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</body>
</html>