<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

// SECURITY CHECK - Only Committee members can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Committee') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// 1. GET COMMITTEE MEMBER'S CLUB INFORMATION
$stmt_club = $conn->prepare("
    SELECT c.club_id, c.club_name, c.description, c.advisor_name, com.position 
    FROM `committee` com
    JOIN `club` c ON com.club_id = c.club_id
    WHERE com.user_id = ?
");
$stmt_club->bind_param("s", $user_id);
$stmt_club->execute();
$club_result = $stmt_club->get_result();

if ($club_result->num_rows == 0) {
    $club_id = null;
    $club_name = "No Club Assigned";
    $position = "N/A";
    $club_description = "You are not assigned to any club yet. Please contact administrator.";
    $advisor_name = "N/A";
} else {
    $club_data = $club_result->fetch_assoc();
    $club_id = $club_data['club_id'];
    $club_name = $club_data['club_name'];
    $position = $club_data['position'];
    $club_description = $club_data['description'];
    $advisor_name = $club_data['advisor_name'];
}

// 2. FETCH STATISTICS FOR THIS CLUB
if ($club_id) {
    // Total events created by this club
    $stmt_events = $conn->prepare("SELECT COUNT(*) AS total FROM `event` WHERE club_id = ?");
    $stmt_events->bind_param("i", $club_id);
    $stmt_events->execute();
    $total_events = $stmt_events->get_result()->fetch_assoc()['total'];
    
    // Total registrations for this club's events
    $stmt_registrations = $conn->prepare("
        SELECT COUNT(*) AS total 
        FROM `event_registration` er
        JOIN `event` e ON er.event_id = e.event_id
        WHERE e.club_id = ?
    ");
    $stmt_registrations->bind_param("i", $club_id);
    $stmt_registrations->execute();
    $total_registrations = $stmt_registrations->get_result()->fetch_assoc()['total'];
    
    // Total attendance for this club's events
    $stmt_attendance = $conn->prepare("
        SELECT COUNT(*) AS total_attended
        FROM `attendance` a
        JOIN `event_registration` er ON a.register_id = er.register_id
        JOIN `event` e ON er.event_id = e.event_id
        WHERE e.club_id = ? AND a.attend_status IN ('Present', 'Late')
    ");
    $stmt_attendance->bind_param("i", $club_id);
    $stmt_attendance->execute();
    $attended_count = $stmt_attendance->get_result()->fetch_assoc()['total_attended'];
    
    $attendance_rate = ($total_registrations > 0) ? round(($attended_count / $total_registrations) * 100) : 0;
    
    // 3. FETCH UPCOMING EVENTS FOR THIS CLUB
    $stmt_upcoming = $conn->prepare("
        SELECT e.event_id, e.event_name, e.date, e.time, e.venue, e.max_cap,
            (SELECT COUNT(*) FROM `event_registration` WHERE event_id = e.event_id) AS registered_count
        FROM `event` e
        WHERE e.club_id = ? AND e.date >= CURDATE()
        ORDER BY e.date ASC
        LIMIT 4
    ");
    $stmt_upcoming->bind_param("i", $club_id);
    $stmt_upcoming->execute();
    $upcoming_events = $stmt_upcoming->get_result();
    
    // 4. FETCH CLUB MEMBERS COUNT
    $stmt_members = $conn->prepare("
        SELECT COUNT(*) AS total 
        FROM `club_membership` 
        WHERE club_id = ?
    ");
    $stmt_members->bind_param("i", $club_id);
    $stmt_members->execute();
    $total_members = $stmt_members->get_result()->fetch_assoc()['total'];
    
    // 5. FETCH PENDING REGISTRATIONS COUNT
    $stmt_pending = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM `event_registration` er
        JOIN `event` e ON er.event_id = e.event_id
        WHERE e.club_id = ? AND er.status = 'Pending'
    ");
    $stmt_pending->bind_param("i", $club_id);
    $stmt_pending->execute();
    $pending_count = $stmt_pending->get_result()->fetch_assoc()['total'];
    
} else {
    $total_events = 0;
    $total_registrations = 0;
    $attendance_rate = 0;
    $attended_count = 0;
    $total_members = 0;
    $pending_count = 0;
    $upcoming_events = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Committee Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; color: #333; }
        
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; max-width: 1200px; }
        .welcome-card { background-color: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #38a169; }
        .welcome-card h2 { color: #1a202c; margin-bottom: 5px; }
        .welcome-card p { color: #718096; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 13px; color: #718096; text-transform: uppercase; letter-spacing: 1px; }
        .stat-card .number { font-size: 2.5rem; font-weight: 700; color: #2d3748; margin-top: 10px; }

        .section-title { font-size: 22px; color: #1a202c; margin-bottom: 20px; border-bottom: 2px solid #cbd5e0; padding-bottom: 10px; }
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; margin-bottom: 50px; }
        
        .item-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: 0.3s; }
        .item-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
        .item-image { width: 100%; height: 160px; object-fit: cover; background: #edf2f7; }
        .item-content { padding: 20px; }
        .item-title { font-size: 18px; font-weight: bold; color: #2d3748; margin-bottom: 10px; }
        .item-detail { font-size: 14px; color: #718096; margin-bottom: 5px; display: flex; align-items: center; gap: 8px; }
        .capacity { font-size: 13px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #e2e8f0; }
        
        .btn-action { display: inline-block; width: auto; text-align: center; padding: 8px 16px; margin-top: 12px; margin-right: 8px; background: #3182ce; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 13px; text-decoration: none; }
        .btn-action:hover { background: #2b6cb0; }
        .btn-success { background: #38a169; }
        .btn-success:hover { background: #2f855a; }
        
        .recent-table { width: 100%; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border-collapse: collapse; }
        .recent-table th, .recent-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .recent-table th { background: #f7fafc; font-weight: 600; color: #4a5568; }
        .recent-table tr:hover { background: #f7fafc; }
        
        .badge-pending { background: #fefcbf; color: #744210; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 20px; max-width: 100%; }
            body { flex-direction: column; }
        }
    </style>
</head>
<body>

    <?php include 'committee_sidebar.php'; ?>

    <div class="main-content">
        
        <div class="welcome-card">
            <h2>Welcome, <?php echo htmlspecialchars($user_name); ?>! 🎉</h2>
            <p><?php echo htmlspecialchars($position); ?> of <?php echo htmlspecialchars($club_name); ?> • Manage your club, create events, track attendance, and monitor member participation.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card" style="border-bottom: 4px solid #3182ce;">
                <h3>Total Events</h3>
                <div class="number"><?php echo $total_events; ?></div>
            </div>
            <div class="stat-card" style="border-bottom: 4px solid #38a169;">
                <h3>Total Registrations</h3>
                <div class="number"><?php echo $total_registrations; ?></div>
            </div>
            <div class="stat-card" style="border-bottom: 4px solid #805ad5;">
                <h3>Attendance Rate</h3>
                <div class="number"><?php echo $attendance_rate; ?>%</div>
            </div>
            <div class="stat-card" style="border-bottom: 4px solid #ecc94b;">
                <h3>Club Members</h3>
                <div class="number"><?php echo $total_members; ?></div>
            </div>
            <div class="stat-card" style="border-bottom: 4px solid #ed8936;">
                <h3>Pending Approvals</h3>
                <div class="number"><?php echo $pending_count; ?></div>
            </div>
        </div>

        <h2 class="section-title">📅 Upcoming Events</h2>
        <div class="grid-container">
            <?php if ($club_id && $upcoming_events && $upcoming_events->num_rows > 0): ?>
                <?php while($event = $upcoming_events->fetch_assoc()): 
                    $remaining = $event['max_cap'] - $event['registered_count'];
                    $is_full = ($event['registered_count'] >= $event['max_cap']);
                ?>
                    <div class="item-card">
                        <img src="https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=500&q=80" class="item-image" alt="Event Poster">
                        <div class="item-content">
                            <div class="item-title"><?php echo htmlspecialchars($event['event_name']); ?></div>
                            <div class="item-detail">📅 <?php echo date("d M Y", strtotime($event['date'])); ?></div>
                            <div class="item-detail">⏰ <?php echo date("h:i A", strtotime($event['time'])); ?></div>
                            <div class="item-detail">📍 <?php echo htmlspecialchars($event['venue']); ?></div>
                            <div class="capacity">
                                👥 Registered: <?php echo $event['registered_count']; ?> / <?php echo $event['max_cap']; ?>
                                <?php if (!$is_full && $remaining > 0): ?>
                                    <span style="color: #38a169;"> (<?php echo $remaining; ?> slots left)</span>
                                <?php elseif ($is_full): ?>
                                    <span style="color: #e53e3e;"> (Full)</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <a href="committee_registrations.php?event_id=<?php echo $event['event_id']; ?>" class="btn-action">View Registrations</a>
                                <a href="committee_attendance.php?event_id=<?php echo $event['event_id']; ?>" class="btn-action btn-success">Record Attendance</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="item-card" style="padding: 40px; text-align: center; color: #718096;">
                    <?php if (!$club_id): ?>
                        You are not assigned to any club yet. Please contact the administrator.
                    <?php else: ?>
                        No upcoming events. <a href="committee_events.php" style="color: #3182ce;">Create your first event →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <h2 class="section-title">📋 Recent Registrations</h2>
        
        <?php
        // Fetch recent registrations
        if ($club_id) {
            $stmt_recent = $conn->prepare("
                SELECT er.register_id, u.name AS student_name, u.user_id, e.event_name, er.register_date, er.status
                FROM `event_registration` er
                JOIN `user` u ON er.user_id = u.user_id
                JOIN `event` e ON er.event_id = e.event_id
                WHERE e.club_id = ?
                ORDER BY er.register_date DESC
                LIMIT 5
            ");
            $stmt_recent->bind_param("i", $club_id);
            $stmt_recent->execute();
            $recent_registrations = $stmt_recent->get_result();
        } else {
            $recent_registrations = null;
        }
        ?>
        
        <?php if ($club_id && $recent_registrations && $recent_registrations->num_rows > 0): ?>
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Student ID</th>
                        <th>Event</th>
                        <th>Registration Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($reg = $recent_registrations->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($reg['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($reg['user_id']); ?></td>
                            <td><?php echo htmlspecialchars($reg['event_name']); ?></td>
                            <td><?php echo date("d M Y, h:i A", strtotime($reg['register_date'])); ?></td>
                            <td>
                                <?php if ($reg['status'] == 'Pending'): ?>
                                    <span class="badge-pending">Pending</span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($reg['status']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="background: #f7fafc; padding: 20px; text-align: center; border-radius: 12px; color: #718096; margin-bottom: 30px;">
                No registrations yet.
            </div>
        <?php endif; ?>

        <h2 class="section-title">⚡ Quick Actions</h2>
        <div class="grid-container" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
            <div class="item-card">
                <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=500&q=80" class="item-image" alt="Reports">
                <div class="item-content">
                    <div class="item-title">Generate Reports</div>
                    <div class="item-detail">📊 View attendance statistics</div>
                    <div class="item-detail">📈 Track participation rates</div>
                    <a href="committee_reports.php" class="btn-action" style="display: block;">View Reports →</a>
                </div>
            </div>
            
            <div class="item-card">
                <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=500&q=80" class="item-image" alt="Members">
                <div class="item-content">
                    <div class="item-title">Committee Members</div>
                    <div class="item-detail">👥 View your club's committee</div>
                    <div class="item-detail">🏢 Manage team roles</div>
                    <a href="committee_manage_committee.php" class="btn-action" style="display: block;">View Members →</a>
                </div>
            </div>
        </div>

    </div>

</body>
</html>