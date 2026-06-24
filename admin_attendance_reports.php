<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

$message = "";
$edit_record = null;
$current_page = basename($_SERVER['PHP_SELF']);
$pk_column = 'attend_id';
$selected_event_id = isset($_GET['filter_event_id']) ? $conn->real_escape_string($_GET['filter_event_id']) : '';

// Delete attendance record
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    $get_sql = "SELECT register_id, point_awarded FROM attendance WHERE $pk_column = ?";
    $get_stmt = $conn->prepare($get_sql);
    $get_stmt->bind_param("i", $delete_id);
    $get_stmt->execute();
    $att_record = $get_stmt->get_result()->fetch_assoc();
    
    if ($att_record) {
        $user_sql = "SELECT user_id FROM event_registration WHERE register_id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("i", $att_record['register_id']);
        $user_stmt->execute();
        $user = $user_stmt->get_result()->fetch_assoc();
        
        if ($user) {
            $update_user = "UPDATE `user` SET total_point = GREATEST(0, total_point - ?) WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_user);
            $update_stmt->bind_param("is", $att_record['point_awarded'], $user['user_id']);
            $update_stmt->execute();
        }
        
        $delete_sql = "DELETE FROM attendance WHERE $pk_column = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $message = "<div class='alert alert-success'>✅ Attendance record deleted successfully! Student points have been updated.</div>";
        } else {
            $message = "<div class='alert alert-error'>❌ Error deleting record.</div>";
        }
    }
}

// Edit attendance record
if (isset($_POST['update_attendance'])) {
    $attendance_id = intval($_POST['attendance_id']);
    $attend_status = $_POST['attend_status'];
    $new_points = intval($_POST['point_awarded']);
    
    $old_sql = "SELECT point_awarded, register_id FROM attendance WHERE $pk_column = ?";
    $old_stmt = $conn->prepare($old_sql);
    $old_stmt->bind_param("i", $attendance_id);
    $old_stmt->execute();
    $old_data = $old_stmt->get_result()->fetch_assoc();
    
    if ($old_data) {
        $old_points = $old_data['point_awarded'];
        $register_id = $old_data['register_id'];
        
        $update_sql = "UPDATE attendance SET attend_status = ?, point_awarded = ? WHERE $pk_column = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sii", $attend_status, $new_points, $attendance_id);
        
        if ($update_stmt->execute()) {
            $user_sql = "SELECT user_id FROM event_registration WHERE register_id = ?";
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param("i", $register_id);
            $user_stmt->execute();
            $user = $user_stmt->get_result()->fetch_assoc();
            
            if ($user) {
                $points_diff = $new_points - $old_points;
                $update_user = "UPDATE `user` SET total_point = GREATEST(0, total_point + ?) WHERE user_id = ?";
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

// Fetch record for editing
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_sql = "
        SELECT a.$pk_column as attendance_id, a.attend_status, a.point_awarded, a.register_id,
               u.user_id, u.name AS student_name, e.event_name, e.date, a.start_time
        FROM attendance a
        JOIN event_registration er ON a.register_id = er.register_id
        JOIN `user` u ON er.user_id = u.user_id
        JOIN event e ON er.event_id = e.event_id
        WHERE a.$pk_column = ?
    ";
    $edit_stmt = $conn->prepare($edit_sql);
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $edit_record = $edit_stmt->get_result()->fetch_assoc();
}

// Fetch master attendance list
$attendance_sql = "
    SELECT 
        a.$pk_column as attendance_id, u.user_id, u.name AS student_name, e.event_name, 
        c.club_name, e.date, a.start_time, a.attend_status, a.point_awarded, e.event_id
    FROM attendance a
    JOIN event_registration er ON a.register_id = er.register_id
    JOIN `user` u ON er.user_id = u.user_id
    JOIN event e ON er.event_id = e.event_id
    JOIN club c ON e.club_id = c.club_id
";

if (!empty($selected_event_id)) {
    $attendance_sql .= " WHERE e.event_id = '$selected_event_id'";
}

$attendance_sql .= " ORDER BY e.date DESC, a.start_time DESC";
$attendance_result = $conn->query($attendance_sql);

if (!$attendance_result) {
    die("Query Error: " . $conn->error);
}

$all_events_sql = "SELECT event_id, event_name, date, club_id FROM EVENT ORDER BY date DESC";
$all_events_result = $conn->query($all_events_sql);

// Handle Filters
$filter_club = isset($_GET['club_id']) ? $conn->real_escape_string($_GET['club_id']) : '';
$filter_semester = isset($_GET['semester']) ? $conn->real_escape_string($_GET['semester']) : '';

$event_where = "1=1";
if ($filter_club) {
    $event_where .= " AND e.club_id = '$filter_club'";
}
if ($filter_semester) {
    if ($filter_semester == '2026-1') $event_where .= " AND e.date BETWEEN '2026-01-01' AND '2026-06-30'";
    if ($filter_semester == '2026-2') $event_where .= " AND e.date BETWEEN '2026-07-01' AND '2026-12-31'";
}

// Fetch Summary Statistics
$events_conducted_q = $conn->query("SELECT COUNT(*) AS total FROM EVENT e WHERE e.date <= CURDATE() AND $event_where");
$events_conducted = $events_conducted_q->fetch_assoc()['total'];

$participation_q = $conn->query("
    SELECT COUNT(*) AS total 
    FROM attendance a 
    JOIN event_registration er ON a.register_id = er.register_id 
    JOIN event e ON er.event_id = e.event_id 
    WHERE a.attend_status IN ('Present', 'Late', 'Volunteer') AND $event_where
");
$total_participation = $participation_q->fetch_assoc()['total'];

$total_registered_q = $conn->query("
    SELECT COUNT(*) AS total 
    FROM event_registration er 
    JOIN event e ON er.event_id = e.event_id 
    WHERE er.status = 'Registered' AND e.date <= CURDATE() AND $event_where
");
$total_registered = $total_registered_q->fetch_assoc()['total'];
$overall_rate = ($total_registered > 0) ? round(($total_participation / $total_registered) * 100, 1) : 0;

// Chart 1: Point Distribution
$point_dist_q = $conn->query("
    SELECT 
        SUM(CASE WHEN total_point < 20 THEN 1 ELSE 0 END) as tier1,
        SUM(CASE WHEN total_point BETWEEN 20 AND 49 THEN 1 ELSE 0 END) as tier2,
        SUM(CASE WHEN total_point BETWEEN 50 AND 79 THEN 1 ELSE 0 END) as tier3,
        SUM(CASE WHEN total_point >= 80 THEN 1 ELSE 0 END) as tier4
    FROM `user` WHERE role IN ('Student', 'Committee') AND account_status = 'Approved'
");
$point_dist = $point_dist_q->fetch_assoc();

// Chart 2: Participation Trends
$trends_q = $conn->query("
    SELECT DATE_FORMAT(e.date, '%b %Y') as month_year, COUNT(a.attend_id) as participants
    FROM event e
    JOIN event_registration er ON e.event_id = er.event_id
    JOIN attendance a ON er.register_id = a.register_id
    WHERE a.attend_status IN ('Present', 'Late', 'Volunteer') AND $event_where
    GROUP BY DATE_FORMAT(e.date, '%Y-%m'), month_year
    ORDER BY e.date ASC LIMIT 6
");
$trend_labels = []; 
$trend_data = [];
while($row = $trends_q->fetch_assoc()) {
    $trend_labels[] = $row['month_year']; 
    $trend_data[] = $row['participants'];
}

// Attendance Rate Per Event
$event_rates_sql = "
    SELECT e.event_name, c.club_name, e.date,
           (SELECT COUNT(*) FROM event_registration er WHERE er.event_id = e.event_id AND er.status='Registered') as registered,
           (SELECT COUNT(*) FROM attendance a JOIN event_registration er2 ON a.register_id = er2.register_id WHERE er2.event_id = e.event_id AND a.attend_status IN ('Present', 'Late', 'Volunteer')) as attended
    FROM event e
    JOIN club c ON e.club_id = c.club_id
    WHERE e.date <= CURDATE() AND $event_where
    ORDER BY e.date DESC
";
$event_rates = $conn->query($event_rates_sql);

// Most Active Students
$active_students = $conn->query("
    SELECT user_id, name, total_point, role
    FROM `user`
    WHERE role IN ('Student', 'Committee') AND account_status = 'Approved'
    ORDER BY total_point DESC
    LIMIT 10
");

$clubs = $conn->query("SELECT club_id, club_name FROM club WHERE isActive = 1 ORDER BY club_name ASC");

$sidebar_pending_count = 0;
$sidebar_stmt = $conn->query("SELECT COUNT(*) AS total FROM `user` WHERE account_status = 'Pending'");
if($sidebar_stmt) {
    $sidebar_pending_count = $sidebar_stmt->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - QR & Attendance | FK Club System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; margin: 0; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; width: calc(100% - 260px); box-sizing: border-box; }
        .card { background-color: rgba(255, 255, 255, 0.95); padding: 35px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; backdrop-filter: blur(10px); border-top: 6px solid #3182ce; margin-bottom: 30px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .card-header h2 { color: #1a202c; font-size: 22px; margin: 0; }
        .btn-export { background: #edf2f7; color: #4a5568; border: 1px solid #cbd5e0; padding: 8px 15px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px; }
        .btn-export:hover { background: #e2e8f0; }
        .filter-bar { background: #f8fafc; padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap; border: 1px solid #e2e8f0; }
        .filter-bar label { font-weight: 600; color: #4a5568; }
        .filter-bar select { padding: 10px 15px; border-radius: 8px; border: 1px solid #cbd5e0; outline: none; font-size: 14px; background: white; min-width: 250px; }
        .filter-bar .btn-clear-filter { background: #e2e8f0; color: #4a5568; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; }
        .filter-bar .btn-clear-filter:hover { background: #cbd5e0; }
        .filter-info { background: #ebf8ff; padding: 8px 15px; border-radius: 20px; font-size: 13px; color: #2b6cb0; }
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
        .status-volunteer { background-color: #ebf8ff; color: #2b6cb0; border: 1px solid #90cdf4; }
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
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;}
        .page-header h1 { color: #1a202c; font-size: 28px; font-weight: 700; }
        .filter-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap;}
        .filter-card select { padding: 10px 15px; border-radius: 8px; border: 1px solid #cbd5e0; outline: none; font-size: 14px; background: white; min-width: 200px;}
        .btn-filter { background: #3182ce; color: white; padding: 10px 20px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; transition: 0.2s;}
        .btn-filter:hover { background: #2b6cb0; }
        .btn-clear { background: #e2e8f0; color: #4a5568; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px;}
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: center; border-bottom: 4px solid #3182ce; }
        .stat-card h3 { color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        .stat-card .number { font-size: 32px; font-weight: 800; color: #2d3748; }
        .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .chart-card h3 { color: #2d3748; font-size: 16px; margin-bottom: 20px; border-bottom: 2px solid #edf2f7; padding-bottom: 10px;}
        .chart-container { position: relative; height: 250px; width: 100%; display: flex; justify-content: center;}
        .section-header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 20px; border-radius: 8px 8px 0 0; border: 1px solid #cbd5e0; border-bottom: 1px solid #e2e8f0; }
        .section-header h2 { color: #2d3748; font-size: 15px; font-weight: 700; margin: 0; }
        .table-card { background: white; border-radius: 0 0 8px 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 30px; border: 1px solid #cbd5e0; border-top: none; }
        .table-responsive { width: 100%; overflow-x: auto; }
        .progress-bar-bg { background: #e2e8f0; border-radius: 10px; height: 8px; width: 100%; margin-top: 5px; overflow: hidden;}
        .progress-bar-fill { background: #38a169; height: 100%; border-radius: 10px; }
        .badge-tier { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .tier-1 { background: #fed7d7; color: #822727; }
        .tier-2 { background: #e2e8f0; color: #4a5568; }
        .tier-3 { background: #c6f6d5; color: #22543d; }
        .tier-4 { background: #fefcbf; color: #744210; }

        @media (max-width: 1024px) { .charts-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .main-content { margin-left: 200px; width: calc(100% - 200px); padding: 20px; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>📊 Attendance & Participation Reports</h1>
            <span style="color: #718096; font-weight: 500; background: white; padding: 8px 15px; border-radius: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">Admin Analytics Dashboard</span>
        </div>

        <form method="GET" class="filter-card">
            <strong style="color:#4a5568;">🔍 Filter Reports By:</strong>
            <?php if($selected_event_id): ?>
                <input type="hidden" name="filter_event_id" value="<?php echo htmlspecialchars($selected_event_id); ?>">
            <?php endif; ?>
            <select name="club_id">
                <option value="">All Clubs</option>
                <?php 
                $clubs->data_seek(0);
                while($c = $clubs->fetch_assoc()): ?>
                    <option value="<?php echo $c['club_id']; ?>" <?php if($filter_club == $c['club_id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($c['club_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <select name="semester">
                <option value="">All Semesters</option>
                <option value="2026-1" <?php if($filter_semester == '2026-1') echo 'selected'; ?>>Semester 1, 2026 (Jan - Jun)</option>
                <option value="2026-2" <?php if($filter_semester == '2026-2') echo 'selected'; ?>>Semester 2, 2026 (Jul - Dec)</option>
            </select>
            <button type="submit" class="btn-filter">Apply Filters</button>
            <?php if($filter_club || $filter_semester): ?>
                <a href="<?php echo $current_page; ?><?php echo $selected_event_id ? '?filter_event_id='.$selected_event_id : ''; ?>" class="btn-clear">Clear All Filters</a>
            <?php endif; ?>
        </form>

        <div class="stats-grid">
            <div class="stat-card"><h3>Events Conducted</h3><div class="number"><?php echo $events_conducted; ?></div></div>
            <div class="stat-card" style="border-bottom-color: #38a169;"><h3>Total Participants</h3><div class="number"><?php echo $total_participation; ?></div></div>
            <div class="stat-card" style="border-bottom-color: #805ad5;"><h3>Overall Attendance Rate</h3><div class="number"><?php echo $overall_rate; ?>%</div></div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h3>🏆 Student Recognition Distribution</h3>
                <div class="chart-container"><canvas id="distributionChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h3>📈 Participation Trends Over Time</h3>
                <div class="chart-container"><canvas id="trendChart"></canvas></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>📋 Master Attendance Records</h2>
                <button class="btn-export" onclick="window.print()">🖨️ Print Report</button>
            </div>
            <?php echo $message; ?>

            <div class="filter-bar">
                <label>🎯 Filter by Event:</label>
                <select id="eventFilterSelect" onchange="window.location.href='<?php echo $current_page; ?>?filter_event_id=' + this.value + '<?php echo ($filter_club ? '&club_id='.$filter_club : '') . ($filter_semester ? '&semester='.$filter_semester : ''); ?>'">
                    <option value="">-- All Events --</option>
                    <?php 
                    $all_events_result->data_seek(0);
                    while($event = $all_events_result->fetch_assoc()): ?>
                        <option value="<?php echo $event['event_id']; ?>" <?php if($selected_event_id == $event['event_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($event['event_name']); ?> (<?php echo date("d M Y", strtotime($event['date'])); ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
                <?php if (!empty($selected_event_id)): 
                    $event_name_sql = "SELECT event_name FROM event WHERE event_id = '$selected_event_id'";
                    $event_name_result = $conn->query($event_name_sql);
                    $selected_event = $event_name_result->fetch_assoc();
                ?>
                    <span class="filter-info">
                        📌 Showing attendance for: <strong><?php echo htmlspecialchars($selected_event['event_name']); ?></strong>
                    </span>
                    <a href="<?php echo $current_page; ?><?php echo ($filter_club ? '?club_id='.$filter_club : '') . ($filter_semester ? (($filter_club ? '&' : '?') . 'semester='.$filter_semester) : ''); ?>" class="btn-clear-filter">✖️ Clear Event Filter</a>
                <?php endif; ?>
            </div>

            <div class="table-container">
                <?php if ($attendance_result && $attendance_result->num_rows > 0): ?>
                    <div style="margin-bottom: 15px; padding: 10px; background: #edf2f7; border-radius: 8px;">
                        <strong>📊 Total Records Found:</strong> <?php echo $attendance_result->num_rows; ?> attendance record(s)
                    </div>
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
                                        if ($row['attend_status'] == 'Present') echo '<span class="status-badge status-present">Present</span>';
                                        elseif ($row['attend_status'] == 'Late') echo '<span class="status-badge status-late">Late</span>';
                                        elseif ($row['attend_status'] == 'Volunteer') echo '<span class="status-badge status-volunteer">Volunteer</span>';
                                        else echo '<span class="status-badge status-absent">Absent</span>';
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
                                            <a href="?edit_id=<?php echo $row['attendance_id']; ?><?php echo $selected_event_id ? '&filter_event_id='.$selected_event_id : ''; ?>" class="btn-edit">✏️ Edit</a>
                                            <a href="?delete_id=<?php echo $row['attendance_id']; ?><?php echo $selected_event_id ? '&filter_event_id='.$selected_event_id : ''; ?>" class="btn-delete" onclick="return confirm('⚠️ Are you sure you want to DELETE this attendance record?\n\nStudent: <?php echo addslashes($row['student_name']); ?>\nEvent: <?php echo addslashes($row['event_name']); ?>\nPoints: <?php echo $row['point_awarded']; ?>\n\nThis will also update the student\'s total points!')">🗑️ Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #718096; font-style: italic; padding: 20px 0; text-align: center; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e0;">
                        <?php echo !empty($selected_event_id) ? 'No attendance records found for the selected event.' : 'No attendance records found in the system yet.'; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="section-header">
            <h2>📊 Attendance Rate per Event</h2>
        </div>
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Club</th>
                            <th>Date</th>
                            <th>Attended / Registered</th>
                            <th>Attendance Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($event_rates && $event_rates->num_rows > 0): ?>
                            <?php while($e = $event_rates->fetch_assoc()): 
                                $rate = ($e['registered'] > 0) ? round(($e['attended'] / $e['registered']) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($e['event_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($e['club_name']); ?></td>
                                    <td><?php echo date("d M Y", strtotime($e['date'])); ?></td>
                                    <td><?php echo $e['attended']; ?> / <?php echo $e['registered']; ?> students</td>
                                    <td>
                                        <strong><?php echo $rate; ?>%</strong>
                                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: <?php echo $rate; ?>%;"></div></div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; padding: 30px;">No events match the selected filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="section-header">
            <h2>🔥 Engagement Rankings: Most Active Students</h2>
            <a href="admin_manage_users.php" class="btn-clear" style="font-size:12px; padding:6px 12px;">Manage Users</a>
        </div>
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Matrix ID</th>
                            <th>Student Name</th>
                            <th>Total Points</th>
                            <th>Real-Time Recognition Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($active_students && $active_students->num_rows > 0): ?>
                            <?php $rank = 1; while($s = $active_students->fetch_assoc()): 
                                $pts = $s['total_point'];
                                if ($pts < 20) { $tier_class = 'tier-1'; $tier_text = 'Needs Improvement'; }
                                elseif ($pts >= 20 && $pts <= 49) { $tier_class = 'tier-2'; $tier_text = 'Certified Participant'; }
                                elseif ($pts >= 50 && $pts <= 79) { $tier_class = 'tier-3'; $tier_text = 'Active Student'; }
                                else { $tier_class = 'tier-4'; $tier_text = 'Outstanding Participant'; }
                            ?>
                                <tr>
                                    <td><strong style="color: #a0aec0;">#<?php echo $rank++; ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($s['user_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($s['name']); ?></td>
                                    <td><strong style="color: #3182ce; font-size: 16px;"><?php echo $pts; ?> pts</strong></td>
                                    <td><span class="badge-tier <?php echo $tier_class; ?>"><?php echo $tier_text; ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; padding: 30px;">No active students found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

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
                <?php if ($selected_event_id): ?>
                    <input type="hidden" name="filter_event_id" value="<?php echo $selected_event_id; ?>">
                <?php endif; ?>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Attendance Status</label>
                <select name="attend_status" required>
                    <option value="Present" <?php echo $edit_record['attend_status'] == 'Present' ? 'selected' : ''; ?>>Present (+10 points)</option>
                    <option value="Late" <?php echo $edit_record['attend_status'] == 'Late' ? 'selected' : ''; ?>>Late (+5 points)</option>
                    <option value="Volunteer" <?php echo $edit_record['attend_status'] == 'Volunteer' ? 'selected' : ''; ?>>Volunteer (+15 points)</option>
                    <option value="Absent" <?php echo $edit_record['attend_status'] == 'Absent' ? 'selected' : ''; ?>>Absent (-10 points)</option>
                </select>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Points to Award</label>
                <input type="number" name="point_awarded" value="<?php echo $edit_record['point_awarded']; ?>" required>
                <small style="color: #718096;">Volunteer: +15 | Present: +10 | Late: +5 | Absent: -10</small>
                <div style="margin-top: 20px;">
                    <button type="submit" name="update_attendance" class="btn-submit">💾 Save Changes</button>
                    <a href="<?php echo $current_page; ?><?php echo $selected_event_id ? '?filter_event_id='.$selected_event_id : ''; ?>" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        window.onclick = function(event) {
            var modal = document.getElementById('editModal');
            if (event.target == modal) {
                window.location.href = '<?php echo $current_page; ?><?php echo $selected_event_id ? '?filter_event_id='.$selected_event_id : ''; ?>';
            }
        }

        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = "#718096";

        new Chart(document.getElementById('distributionChart'), { 
            type: 'doughnut', 
            data: { 
                labels: ['Needs Improvement (<20)', 'Certified (20-49)', 'Active (50-79)', 'Outstanding (80+)'], 
                datasets: [{ 
                    data: [
                        <?php echo $point_dist['tier1'] ?: 0; ?>, 
                        <?php echo $point_dist['tier2'] ?: 0; ?>, 
                        <?php echo $point_dist['tier3'] ?: 0; ?>, 
                        <?php echo $point_dist['tier4'] ?: 0; ?>
                    ], 
                    backgroundColor: ['#fed7d7', '#cbd5e0', '#68d391', '#f6e05e'], 
                    borderWidth: 1,
                    borderColor: '#ffffff'
                }] 
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } }, cutout: '65%' }
        });

        new Chart(document.getElementById('trendChart'), { 
            type: 'line', 
            data: { 
                labels: <?php echo json_encode($trend_labels); ?>, 
                datasets: [{ 
                    label: 'Participants Attended', 
                    data: <?php echo json_encode($trend_data); ?>, 
                    borderColor: '#805ad5', 
                    backgroundColor: 'rgba(128, 90, 213, 0.1)', 
                    borderWidth: 2, 
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#805ad5',
                    pointRadius: 4
                }] 
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    </script>
</body>
</html>