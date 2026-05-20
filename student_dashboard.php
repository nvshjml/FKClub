<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'Admin') {
    header("Location: index.php"); exit();
}

$user_id = $_SESSION['user_id'];

$stmt1 = $conn->prepare("SELECT total_point FROM `USER` WHERE user_id = ?");
$stmt1->bind_param("s", $user_id); $stmt1->execute();
$total_points = $stmt1->get_result()->fetch_assoc()['total_point'] ?? 0;

$stmt2 = $conn->prepare("SELECT COUNT(*) AS total FROM CLUB_MEMBERSHIP WHERE user_id = ?");
$stmt2->bind_param("s", $user_id); $stmt2->execute();
$total_clubs = $stmt2->get_result()->fetch_assoc()['total'];

$stmt3 = $conn->prepare("SELECT COUNT(*) AS total FROM EVENT_REGISTRATION WHERE user_id = ?");
$stmt3->bind_param("s", $user_id); $stmt3->execute();
$total_events = $stmt3->get_result()->fetch_assoc()['total'];

$events_sql = "SELECT event_id, event_name, date, time, venue FROM EVENT WHERE date >= CURDATE() ORDER BY date ASC";
$events_result = $conn->query($events_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
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
        .section-title { font-size: 22px; margin-bottom: 20px; margin-top: 40px; border-bottom: 2px solid #cbd5e0; padding-bottom: 10px; }
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; }
        .item-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="welcome-card">
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>! 🎓</h2>
            <p>Track your points, manage your clubs, and register for upcoming events.</p>
        </div>

        <div class="stats-grid">
            <?php if ($_SESSION['role'] == 'Student'): ?>
                <div class="stat-card" style="border-bottom: 4px solid #3182ce;"><h3>Total Points</h3><div class="number"><?php echo $total_points; ?></div></div>
            <?php endif; ?>
            <div class="stat-card" style="border-bottom: 4px solid #38a169;"><h3>Clubs Joined</h3><div class="number"><?php echo $total_clubs; ?> / 3</div></div>
            <div class="stat-card" style="border-bottom: 4px solid #805ad5;"><h3>Events Registered</h3><div class="number"><?php echo $total_events; ?></div></div>
        </div>

        <h2 class="section-title">📅 Upcoming Events</h2>
        <div class="grid-container">
            <?php while($event = $events_result->fetch_assoc()): ?>
                <div class="item-card">
                    <h3><?php echo htmlspecialchars($event['event_name']); ?></h3>
                    <p>📅 <?php echo date("d M Y", strtotime($event['date'])); ?> | 📍 <?php echo htmlspecialchars($event['venue']); ?></p>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>