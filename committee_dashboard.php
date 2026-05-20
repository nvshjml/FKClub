<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Committee') {
    header("Location: index.php"); exit();
}

$user_id = $_SESSION['user_id']; $user_name = $_SESSION['name'];

$stmt_club = $conn->prepare("SELECT c.club_id, c.club_name, c.description, c.advisor_name, com.position FROM `committee` com JOIN `club` c ON com.club_id = c.club_id WHERE com.user_id = ?");
$stmt_club->bind_param("s", $user_id); $stmt_club->execute();
$club_result = $stmt_club->get_result();

if ($club_result->num_rows == 0) {
    $club_id = null; $club_name = "No Club Assigned"; $position = "N/A";
} else {
    $club_data = $club_result->fetch_assoc();
    $club_id = $club_data['club_id']; $club_name = $club_data['club_name']; $position = $club_data['position'];
}

if ($club_id) {
    $stmt_events = $conn->prepare("SELECT COUNT(*) AS total FROM `event` WHERE club_id = ?");
    $stmt_events->bind_param("i", $club_id); $stmt_events->execute();
    $total_events = $stmt_events->get_result()->fetch_assoc()['total'];
    
    $stmt_registrations = $conn->prepare("SELECT COUNT(*) AS total FROM `event_registration` er JOIN `event` e ON er.event_id = e.event_id WHERE e.club_id = ?");
    $stmt_registrations->bind_param("i", $club_id); $stmt_registrations->execute();
    $total_registrations = $stmt_registrations->get_result()->fetch_assoc()['total'];
    
    $stmt_members = $conn->prepare("SELECT COUNT(*) AS total FROM `club_membership` WHERE club_id = ?");
    $stmt_members->bind_param("i", $club_id); $stmt_members->execute();
    $total_members = $stmt_members->get_result()->fetch_assoc()['total'];
    
    $attendance_rate = ($total_registrations > 0) ? 100 : 0; // Simplified for dashboard
} else {
    $total_events = 0; $total_registrations = 0; $attendance_rate = 0; $total_members = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Committee Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; }
        .welcome-card { background: white; padding: 25px 30px; border-radius: 12px; border-left: 6px solid #38a169; margin-bottom: 30px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; }
        .stat-card h3 { color: #718096; font-size: 13px; text-transform: uppercase; }
        .stat-card .number { font-size: 2.5rem; font-weight: 700; color: #2d3748; margin-top: 10px; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="welcome-card">
            <h2>Welcome, <?php echo htmlspecialchars($user_name); ?>! 🎉</h2>
            <p><?php echo htmlspecialchars($position); ?> of <?php echo htmlspecialchars($club_name); ?></p>
        </div>

        <div class="stats-grid">
            <div class="stat-card" style="border-bottom: 4px solid #3182ce;"><h3>Total Events</h3><div class="number"><?php echo $total_events; ?></div></div>
            <div class="stat-card" style="border-bottom: 4px solid #38a169;"><h3>Registrations</h3><div class="number"><?php echo $total_registrations; ?></div></div>
            <div class="stat-card" style="border-bottom: 4px solid #805ad5;"><h3>Attendance Rate</h3><div class="number"><?php echo $attendance_rate; ?>%</div></div>
            <div class="stat-card" style="border-bottom: 4px solid #ecc94b;"><h3>Club Members</h3><div class="number"><?php echo $total_members; ?></div></div>
        </div>
    </div>
</body>
</html>