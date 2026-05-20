<?php
session_start();
require 'db_connect.php';

// SECURITY CHECK: Only Admins can access this page!
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

// Fetch pending count for sidebar badge
$sidebar_pending_count = 0;
$sidebar_stmt = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE account_status = 'Pending'");
if($sidebar_stmt) {
    $sidebar_pending_count = $sidebar_stmt->fetch_assoc()['total'];
}

// ---------------------------------------------------------
// FETCH MASTER ATTENDANCE LIST
// ---------------------------------------------------------
// This query links 5 tables together to get the full picture!
$attendance_sql = "
    SELECT 
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

$current_page = basename($_SERVER['PHP_SELF']); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Attendance List</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Global Reset */
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; margin: 0; }

        /* Sidebar Styles */
        .sidebar { 
            width: 260px; background-color: #1a202c; color: white; display: flex; flex-direction: column; 
            padding: 30px 20px; position: fixed; height: 100vh; box-shadow: 4px 0 10px rgba(0,0,0,0.1); 
            z-index: 1000; top: 0; left: 0; box-sizing: border-box; 
        }
        
        /* NEW Sidebar Header Styles */
        .sidebar-header { text-align: center; margin-bottom: 35px; }
        .sidebar-logo { max-width: 85px; margin-bottom: 12px; display: inline-block; }
        .sidebar-brand { font-size: 20px; font-weight: 700; color: #ffffff; margin-bottom: 6px; }
        .sidebar-role { 
            font-size: 11px; font-weight: 700; color: #a0aec0; text-transform: uppercase; 
            letter-spacing: 1.5px; background: rgba(255,255,255,0.1); 
            padding: 4px 12px; border-radius: 20px; display: inline-block; 
        }

        .nav-links-sidebar { display: flex; flex-direction: column; gap: 15px; flex-grow: 1; }
        .nav-links-sidebar a { 
            text-decoration: none; color: #a0aec0; font-weight: 600; padding: 12px 15px; 
            border-radius: 8px; transition: 0.3s; display: flex; align-items: center; justify-content: space-between; font-size: 15px; 
        }
        .nav-links-sidebar a:hover, .nav-links-sidebar a.active { background-color: #2d3748; color: white; }
        .badge-sidebar { background: #e53e3e; color: white; border-radius: 12px; padding: 2px 8px; font-size: 12px; font-weight: bold; }
        .btn-logout-sidebar { background-color: #e53e3e; color: white; text-align: center; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: bold; margin-top: auto; transition: 0.2s; }
        .btn-logout-sidebar:hover { background-color: #c53030; }

        /* Main Content Layout */
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; width: calc(100% - 260px); box-sizing: border-box; }
        .content-wrapper { display: flex; justify-content: center; width: 100%; align-items: flex-start; }
        
        /* Card Styling */
        .card { background-color: rgba(255, 255, 255, 0.95); padding: 35px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 1100px; backdrop-filter: blur(10px); border-top: 6px solid #3182ce; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .card-header h2 { color: #1a202c; font-size: 22px; margin: 0; }
        .btn-export { background: #edf2f7; color: #4a5568; border: 1px solid #cbd5e0; padding: 8px 15px; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 14px; transition: 0.2s; }
        .btn-export:hover { background: #e2e8f0; color: #2d3748; }

        /* Modern Table */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        thead { background-color: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        th { padding: 16px 15px; font-size: 13px; color: #4a5568; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        td { padding: 16px 15px; font-size: 14px; color: #2d3748; border-bottom: 1px solid #edf2f7; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #f8fafc; }

        /* Status Badges */
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; text-align: center; min-width: 80px; }
        .status-present { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .status-late { background-color: #fefcbf; color: #744210; border: 1px solid #fbd38d; }
        .status-absent { background-color: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        
        .points-awarded { font-weight: bold; color: #3182ce; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-brand">FK Club Admin</div>
        <div class="nav-links-sidebar">
            <a href="admin_dashboard.php" class="<?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
            
            <a href="admin_attendance.php" class="<?php echo ($current_page == 'admin_attendance.php') ? 'active' : ''; ?>">Attendance</a>
            
            <a href="pending_approvals.php" class="<?php echo ($current_page == 'pending_approvals.php') ? 'active' : ''; ?>">
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
                                            // Apply the correct color badge based on status
                                            if ($row['attend_status'] == 'Present') {
                                                echo '<span class="status-badge status-present">Present</span>';
                                            } elseif ($row['attend_status'] == 'Late') {
                                                echo '<span class="status-badge status-late">Late</span>';
                                            } else {
                                                echo '<span class="status-badge status-absent">Absent</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="points-awarded">+<?php echo $row['point_awarded']; ?></td>
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

</body>
</html>