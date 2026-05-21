<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php"); exit();
}

$attendance_sql = "
    SELECT u.user_id, u.name AS student_name, e.event_name, c.club_name, e.date, a.start_time, a.attend_status, a.point_awarded
    FROM ATTENDANCE a JOIN EVENT_REGISTRATION er ON a.register_id = er.register_id
    JOIN `USER` u ON er.user_id = u.user_id JOIN EVENT e ON er.event_id = e.event_id
    JOIN CLUB c ON e.club_id = c.club_id ORDER BY e.date DESC, a.start_time DESC
";
$attendance_result = $conn->query($attendance_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Master Attendance</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; width: calc(100% - 260px); }
        .card { background: white; padding: 35px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border-top: 6px solid #3182ce; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .card-header h2 { font-size: 22px; margin: 0; }
        .btn-export { background: #edf2f7; color: #4a5568; border: 1px solid #cbd5e0; padding: 8px 15px; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 16px; font-size: 13px; color: #4a5568; font-weight: 600; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        td { padding: 16px; color: #2d3748; border-bottom: 1px solid #edf2f7; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; text-align: center; min-width: 80px; display: inline-block; }
        .status-present { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .status-late { background: #fefcbf; color: #744210; border: 1px solid #fbd38d; }
        .status-absent { background: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        .points-awarded { font-weight: bold; color: #3182ce; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="card">
            <div class="card-header">
                <h2>📋 Master Attendance Records</h2>
                <button class="btn-export" onclick="window.print()">🖨️ Print Report</button>
            </div>
            <div class="table-container">
                <?php if ($attendance_result && $attendance_result->num_rows > 0): ?>
                    <table>
                        <thead><tr><th>Matrix ID</th><th>Name</th><th>Event</th><th>Club</th><th>Date</th><th>Status</th><th>Points</th></tr></thead>
                        <tbody>
                            <?php while($row = $attendance_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['user_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['event_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['club_name']); ?></td>
                                    <td><?php echo date("d M Y", strtotime($row['date'])); ?></td>
                                    <td>
                                        <?php 
                                        if ($row['attend_status'] == 'Present') echo '<span class="status-badge status-present">Present</span>';
                                        elseif ($row['attend_status'] == 'Late') echo '<span class="status-badge status-late">Late</span>';
                                        else echo '<span class="status-badge status-absent">Absent</span>';
                                        ?>
                                    </td>
                                    <td class="points-awarded">+<?php echo $row['point_awarded']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; padding: 20px; color: #718096;">No records found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>