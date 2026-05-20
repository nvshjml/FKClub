<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Committee') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$selected_event_id = isset($_GET['event_id']) ? $_GET['event_id'] : (isset($_POST['event_id']) ? $_POST['event_id'] : null);

// Get club info
$stmt = $conn->prepare("
    SELECT c.club_id, c.club_name 
    FROM `committee` com 
    JOIN `club` c ON com.club_id = c.club_id 
    WHERE com.user_id = ?
");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$club = $stmt->get_result()->fetch_assoc();
$club_id = $club ? $club['club_id'] : null;
$club_name = $club ? $club['club_name'] : 'No Club Assigned';

// Get all events for this club
$events = [];
if ($club_id) {
    $events_stmt = $conn->prepare("SELECT event_id, event_name, date FROM `event` WHERE club_id = ? ORDER BY date DESC");
    $events_stmt->bind_param("i", $club_id);
    $events_stmt->execute();
    $events = $events_stmt->get_result();
}

// Get registered students for selected event
$registered_students = [];
$event_info = null;
if ($selected_event_id && $club_id) {
    $event_stmt = $conn->prepare("SELECT * FROM `event` WHERE event_id = ? AND club_id = ?");
    $event_stmt->bind_param("ii", $selected_event_id, $club_id);
    $event_stmt->execute();
    $event_info = $event_stmt->get_result()->fetch_assoc();
    
    $students_stmt = $conn->prepare("
        SELECT er.register_id, u.user_id, u.name, u.email, a.attend_status
        FROM `event_registration` er
        JOIN `user` u ON er.user_id = u.user_id
        LEFT JOIN `attendance` a ON er.register_id = a.register_id
        WHERE er.event_id = ? AND er.status = 'Approved'
        ORDER BY u.name ASC
    ");
    $students_stmt->bind_param("i", $selected_event_id);
    $students_stmt->execute();
    $registered_students = $students_stmt->get_result();
}

// Handle attendance submission
if (isset($_POST['save_attendance'])) {
    foreach ($_POST['attendance'] as $register_id => $status) {
        $points = ($status == 'Present') ? 5 : (($status == 'Late') ? 3 : 0);
        
        $check_stmt = $conn->prepare("SELECT attendance_id FROM `attendance` WHERE register_id = ?");
        $check_stmt->bind_param("i", $register_id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        
        if ($existing) {
            $update_stmt = $conn->prepare("UPDATE `attendance` SET attend_status = ?, point_awarded = ? WHERE register_id = ?");
            $update_stmt->bind_param("sii", $status, $points, $register_id);
            $update_stmt->execute();
        } else {
            $insert_stmt = $conn->prepare("INSERT INTO `attendance` (register_id, attend_status, point_awarded) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("isi", $register_id, $status, $points);
            $insert_stmt->execute();
            
            if ($points > 0) {
                $update_points = $conn->prepare("UPDATE `user` SET total_point = total_point + ? WHERE user_id = (SELECT user_id FROM event_registration WHERE register_id = ?)");
                $update_points->bind_param("ii", $points, $register_id);
                $update_points->execute();
            }
        }
    }
    $message = "<div style='background:#c6f6d5; color:#22543d; padding:12px; border-radius:8px; margin-bottom:20px;'>✅ Attendance saved!</div>";
    
    // Refresh data
    $students_stmt = $conn->prepare("
        SELECT er.register_id, u.user_id, u.name, u.email, a.attend_status
        FROM `event_registration` er
        JOIN `user` u ON er.user_id = u.user_id
        LEFT JOIN `attendance` a ON er.register_id = a.register_id
        WHERE er.event_id = ? AND er.status = 'Approved'
        ORDER BY u.name ASC
    ");
    $students_stmt->bind_param("i", $selected_event_id);
    $students_stmt->execute();
    $registered_students = $students_stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Record Attendance</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; }
        
        .sidebar { width: 260px; background-color: #1a202c; color: white; display: flex; flex-direction: column; padding: 30px 20px; position: fixed; height: 100vh; box-shadow: 4px 0 10px rgba(0,0,0,0.1); z-index: 1000; top: 0; left: 0;}
        .sidebar-header { text-align: center; margin-bottom: 35px; }
        .sidebar-logo { max-width: 85px; margin-bottom: 12px; display: inline-block; }
        .sidebar-brand { font-size: 20px; font-weight: 700; color: #ffffff; margin-bottom: 6px; }
        .sidebar-role { font-size: 11px; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 1.5px; background: rgba(255,255,255,0.1); padding: 4px 12px; border-radius: 20px; display: inline-block; }
        .nav-links { display: flex; flex-direction: column; gap: 15px; flex-grow: 1; }
        .nav-links a { text-decoration: none; color: #a0aec0; font-weight: 600; padding: 12px 15px; border-radius: 8px; display: block; }
        .nav-links a:hover, .nav-links a.active { background-color: #2d3748; color: white; }
        .btn-logout { background-color: #e53e3e; text-align: center; padding: 12px; border-radius: 8px; margin-top: auto; color: white; text-decoration: none; display: block; }
        
        .main-content { margin-left: 260px; padding: 40px; width: calc(100% - 260px); }
        .welcome-card { background: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; border-left: 6px solid #38a169; }
        .form-card { background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
        .form-card select, .form-card button { padding: 12px; border-radius: 8px; border: 2px solid #e2e8f0; }
        .form-card select { width: 300px; margin-right: 10px; }
        .btn-select { background: #3182ce; color: white; border: none; cursor: pointer; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; }
        .stat-card .number { font-size: 32px; font-weight: bold; }
        
        table { width: 100%; background: white; border-radius: 12px; overflow: hidden; border-collapse: collapse; }
        th, td { padding: 14px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f7fafc; }
        
        .status-select { padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e0; }
        .status-present { background: #c6f6d5; color: #22543d; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .status-late { background: #fefcbf; color: #744210; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .status-absent { background: #fed7d7; color: #822727; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
        
        .btn-save { background: #38a169; color: white; border: none; padding: 14px 28px; border-radius: 8px; cursor: pointer; margin-top: 20px; font-size: 16px; }
        .btn-bulk { background: #edf2f7; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; margin-right: 10px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
            <img src="image/LogoUMP5.png" alt="UMPSA Logo" class="sidebar-logo">
            <div class="sidebar-brand">FK Club System</div>
            <div class="sidebar-role"><?php echo htmlspecialchars($_SESSION['role']); ?> Dashboard</div>
    </div>
    <div class="nav-links">
        <a href="committee_dashboard.php">Dashboard</a>
        <a href="committee_profile.php">My Profile</a>
        <a href="committee_club_details.php">Club Details</a>
        <a href="committee_events.php">Manage Events</a>
        <a href="committee_attendance.php" class="active">Record Attendance</a>
    </div>
    <a href="logout.php" class="btn-logout">Logout</a>
</div>

<div class="main-content">
    <?php echo $message; ?>
    
    <div class="welcome-card">
        <h2>📝 Record Attendance</h2>
        <p><?php echo htmlspecialchars($club_name); ?></p>
    </div>

    <div class="form-card">
        <form method="GET">
            <select name="event_id" required>
                <option value="">-- Select Event --</option>
                <?php while($event = $events->fetch_assoc()): ?>
                    <option value="<?php echo $event['event_id']; ?>" <?php echo ($selected_event_id == $event['event_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($event['event_name']); ?> - <?php echo date("d M Y", strtotime($event['date'])); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <button type="submit" class="btn-select">Load Event</button>
        </form>
    </div>

    <?php if ($event_info && $registered_students && $registered_students->num_rows > 0): 
        $present = $late = $absent = 0;
        $students_array = [];
        foreach($registered_students as $s) {
            $status = $s['attend_status'] ?? 'Absent';
            if ($status == 'Present') $present++;
            elseif ($status == 'Late') $late++;
            else $absent++;
            $students_array[] = $s;
        }
    ?>
        <div class="stats-grid">
            <div class="stat-card" style="border-bottom: 3px solid #38a169;"><div class="number"><?php echo $present; ?></div><div>Present</div></div>
            <div class="stat-card" style="border-bottom: 3px solid #ecc94b;"><div class="number"><?php echo $late; ?></div><div>Late</div></div>
            <div class="stat-card" style="border-bottom: 3px solid #e53e3e;"><div class="number"><?php echo $absent; ?></div><div>Absent</div></div>
            <div class="stat-card" style="border-bottom: 3px solid #3182ce;"><div class="number"><?php echo $registered_students->num_rows; ?></div><div>Total</div></div>
        </div>

        <form method="POST">
            <input type="hidden" name="event_id" value="<?php echo $selected_event_id; ?>">
            <div style="margin-bottom: 15px;">
                <button type="button" class="btn-bulk" onclick="markAll('Present')">✅ All Present</button>
                <button type="button" class="btn-bulk" onclick="markAll('Late')">🕐 All Late</button>
                <button type="button" class="btn-bulk" onclick="markAll('Absent')">❌ All Absent</button>
            </div>
            <table>
                <thead><tr><th>Matrix ID</th><th>Name</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach($students_array as $student): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['user_id']); ?></td>
                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                        <td>
                            <select name="attendance[<?php echo $student['register_id']; ?>]" class="status-select">
                                <option value="Present" <?php echo ($student['attend_status'] ?? '') == 'Present' ? 'selected' : ''; ?>>✅ Present</option>
                                <option value="Late" <?php echo ($student['attend_status'] ?? '') == 'Late' ? 'selected' : ''; ?>>🕐 Late</option>
                                <option value="Absent" <?php echo ($student['attend_status'] ?? '') == 'Absent' || !$student['attend_status'] ? 'selected' : ''; ?>>❌ Absent</option>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" name="save_attendance" class="btn-save">💾 Save Attendance</button>
        </form>
    <?php elseif ($selected_event_id): ?>
        <div style="background:#f7fafc; padding:40px; text-align:center; border-radius:12px;">
            No approved registrations for this event yet.
        </div>
    <?php endif; ?>
</div>

<script>
    function markAll(status) {
        var selects = document.querySelectorAll('.status-select');
        for(var i = 0; i < selects.length; i++) {
            selects[i].value = status;
        }
    }
</script>
</body>
</html>