<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'Admin') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);

$user_sql = "SELECT name, email, total_point, user_id FROM `USER` WHERE user_id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

$total_points = $user_data ? $user_data['total_point'] : 0;

function getRecognitionLevel($points) {
    if ($points >= 80) return ['level' => 'Outstanding Participant', 'badge' => '🏆', 'color' => '#ffd700', 'bg' => '#fef3c7', 'benefits' => 'Eligible for leadership award / priority in event registration', 'next' => null];
    if ($points >= 50) return ['level' => 'Highly Active', 'badge' => '⭐', 'color' => '#38a169', 'bg' => '#c6f6d5', 'benefits' => 'Eligible for active student award / bonus points', 'next' => 80];
    if ($points >= 20) return ['level' => 'Active Participant', 'badge' => '📜', 'color' => '#3182ce', 'bg' => '#ebf8ff', 'benefits' => 'Eligible for participation certificate', 'next' => 50];
    return ['level' => 'Needs Improvement', 'badge' => '⚠️', 'color' => '#e53e3e', 'bg' => '#fed7d7', 'benefits' => 'Warning / Reminder to participate more', 'next' => 20];
}

$recognition = getRecognitionLevel($total_points);

$history_sql = "SELECT er.*, e.event_name, e.date, e.time, e.venue FROM EVENT_REGISTRATION er JOIN EVENT e ON er.event_id = e.event_id WHERE er.user_id = ? ORDER BY e.date DESC";
$stmt = $conn->prepare($history_sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$registration_history = $stmt->get_result();

$total_registrations = 0; $total_active = 0; $total_cancelled = 0; $history_data = [];
while ($row = $registration_history->fetch_assoc()) {
    $total_registrations++;
    if ($row['status'] == 'Registered') $total_active++;
    elseif ($row['status'] == 'Cancelled') $total_cancelled++;
    $history_data[] = $row;
}

$completed_sql = "SELECT COUNT(*) as total FROM EVENT_REGISTRATION er JOIN EVENT e ON er.event_id = e.event_id WHERE er.user_id = ? AND e.date < CURDATE() AND er.status = 'Registered'";
$stmt = $conn->prepare($completed_sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$completed_count = $stmt->get_result()->fetch_assoc()['total'];

$club_count_sql = "SELECT COUNT(*) as total FROM CLUB_MEMBERSHIP WHERE user_id = ?";
$stmt = $conn->prepare($club_count_sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$club_count = $stmt->get_result()->fetch_assoc()['total'];

$upcoming_sql = "SELECT e.event_name, e.date, e.time, e.venue FROM EVENT_REGISTRATION er JOIN EVENT e ON er.event_id = e.event_id WHERE er.user_id = ? AND e.date >= CURDATE() AND er.status = 'Registered' ORDER BY e.date ASC LIMIT 5";
$stmt = $conn->prepare($upcoming_sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$upcoming_events = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Point Recognition</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; color: #333; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; max-width: calc(100% - 260px); }
        .welcome-card { background-color: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #38a169; }
        .section-title { font-size: 22px; color: #1a202c; margin-bottom: 20px; border-bottom: 2px solid #cbd5e0; padding-bottom: 10px; margin-top: 30px; }
        .points-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; padding: 30px; color: white; margin-bottom: 30px; text-align: center; }
        .points-card h1 { font-size: 64px; font-weight: 800; margin: 15px 0; }
        .points-card .recognition-badge { font-size: 48px; margin-bottom: 10px; }
        .points-card .recognition-level { font-size: 18px; font-weight: 600; background: rgba(255,255,255,0.2); display: inline-block; padding: 8px 20px; border-radius: 30px; }
        .points-card .benefits { margin-top: 20px; font-size: 14px; background: rgba(255,255,255,0.15); padding: 10px 20px; border-radius: 30px; display: inline-block; }
        .progress-card { background: white; border-radius: 16px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .progress-label { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: #64748b; }
        .progress-bar-container { background: #e2e8f0; border-radius: 12px; height: 12px; overflow: hidden; }
        .progress-bar-fill { background: linear-gradient(90deg, #38a169, #2b6cb0); height: 100%; border-radius: 12px; transition: width 0.5s ease; }
        .next-milestone { margin-top: 12px; font-size: 13px; color: #38a169; text-align: right; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .info-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .info-card .icon { font-size: 32px; margin-bottom: 10px; }
        .info-card .value { font-size: 24px; font-weight: 700; color: #2d3748; }
        .info-card .label { font-size: 12px; color: #718096; margin-top: 5px; }
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .data-table th { background: #f8fafc; padding: 15px; text-align: left; font-weight: 600; color: #4a5568; border-bottom: 2px solid #e2e8f0; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #edf2f7; color: #2d3748; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-registered { background: #c6f6d5; color: #22543d; }
        .status-cancelled { background: #fed7d7; color: #822727; }
        .empty-state { text-align: center; padding: 40px; background: white; border-radius: 12px; color: #718096; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="welcome-card">
            <h2>🏆 Point Recognition System</h2>
            <p style="color: #718096;">Track your participation points, see your recognition level, and monitor your progress.</p>
        </div>
        <div class="points-card">
            <div class="recognition-badge"><?php echo $recognition['badge']; ?></div>
            <h1><?php echo $total_points; ?></h1>
            <div class="recognition-level" style="background: <?php echo $recognition['bg']; ?>; color: <?php echo $recognition['color']; ?>;"><?php echo $recognition['level']; ?></div>
            <div class="benefits">🎁 <?php echo $recognition['benefits']; ?></div>
        </div>
        <?php if ($recognition['next'] !== null): ?>
        <div class="progress-card">
            <div class="progress-label"><span>Progress to next level</span><span><?php echo $total_points; ?> / <?php echo $recognition['next']; ?> points</span></div>
            <div class="progress-bar-container"><div class="progress-bar-fill" style="width: <?php echo min(($total_points / $recognition['next']) * 100, 100); ?>%"></div></div>
            <div class="next-milestone">🎯 Need <?php echo max(0, $recognition['next'] - $total_points); ?> more points</div>
        </div>
        <?php endif; ?>
        
        <div class="info-grid">
            <div class="info-card"><div class="icon">👥</div><div class="value"><?php echo $club_count; ?></div><div class="label">Clubs Joined</div></div>
            <div class="info-card"><div class="icon">✅</div><div class="value"><?php echo $total_active; ?></div><div class="label">Active Registrations</div></div>
            <div class="info-card"><div class="icon">📅</div><div class="value"><?php echo $completed_count; ?></div><div class="label">Completed Events</div></div>
            <div class="info-card"><div class="icon">❌</div><div class="value"><?php echo $total_cancelled; ?></div><div class="label">Cancelled Registrations</div></div>
        </div>
        
        <h2 class="section-title">📊 My Event Registration History</h2>
        <div class="table-container">
            <?php if (!empty($history_data)): ?>
                <table class="data-table">
                    <thead><tr><th>Event Name</th><th>Date</th><th>Time</th><th>Venue</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($history_data as $record): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($record['event_name']); ?></strong></td>
                                <td><?php echo date("d M Y", strtotime($record['date'])); ?></td>
                                <td><?php echo date("h:i A", strtotime($record['time'])); ?></td>
                                <td><?php echo htmlspecialchars($record['venue']); ?></td>
                                <td>
                                    <?php if ($record['status'] == 'Registered'): ?><span class="status-badge status-registered">✅ Registered</span>
                                    <?php elseif ($record['status'] == 'Cancelled'): ?><span class="status-badge status-cancelled">❌ Cancelled</span>
                                    <?php else: ?><span class="status-badge"><?php echo $record['status']; ?></span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state"><p>No registration history yet.</p></div>
            <?php endif; ?>
        </div>
        
        </div>
</body>
</html>