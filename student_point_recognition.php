<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

// SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'Admin') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);
$message = "";

// Fetch user's total points and details
$user_sql = "SELECT name, email, total_point, user_id FROM `USER` WHERE user_id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

$total_points = $user_data ? $user_data['total_point'] : 0;

// ============================================================
// FIXED: Recognition levels now EXACTLY match PDF Table B (Page 6)
// ============================================================
function getRecognitionLevel($points) {
    if ($points >= 80) {
        return [
            'level' => 'Outstanding Participant', 
            'badge' => '🏆', 
            'color' => '#ffd700', 
            'bg' => '#fef3c7', 
            'benefits' => 'Eligible for leadership award / priority in event registration',
            'next' => null
        ];
    } elseif ($points >= 50) {
        return [
            'level' => 'Highly Active', 
            'badge' => '⭐', 
            'color' => '#38a169', 
            'bg' => '#c6f6d5', 
            'benefits' => 'Eligible for active student award / bonus points',
            'next' => 80
        ];
    } elseif ($points >= 20) {
        return [
            'level' => 'Active Participant', 
            'badge' => '📜', 
            'color' => '#3182ce', 
            'bg' => '#ebf8ff', 
            'benefits' => 'Eligible for participation certificate',
            'next' => 50
        ];
    } else {
        return [
            'level' => 'Needs Improvement', 
            'badge' => '⚠️', 
            'color' => '#e53e3e', 
            'bg' => '#fed7d7', 
            'benefits' => 'Warning / Reminder to participate more',
            'next' => 20
        ];
    }
}

$recognition = getRecognitionLevel($total_points);

// Fetch registration history
$history_sql = "
    SELECT er.*, e.event_name, e.date, e.time, e.venue
    FROM EVENT_REGISTRATION er
    JOIN EVENT e ON er.event_id = e.event_id
    WHERE er.user_id = ?
    ORDER BY e.date DESC
";
$stmt = $conn->prepare($history_sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$registration_history = $stmt->get_result();

// Calculate statistics from registrations
$total_registrations = 0;
$total_active = 0;
$total_cancelled = 0;
$history_data = [];

while ($row = $registration_history->fetch_assoc()) {
    $total_registrations++;
    if ($row['status'] == 'Registered') {
        $total_active++;
    } elseif ($row['status'] == 'Cancelled') {
        $total_cancelled++;
    }
    $history_data[] = $row;
}
$registration_history->data_seek(0); // Reset pointer

// Count completed events (past events that were registered)
$completed_sql = "
    SELECT COUNT(*) as total
    FROM EVENT_REGISTRATION er
    JOIN EVENT e ON er.event_id = e.event_id
    WHERE er.user_id = ? AND e.date < CURDATE() AND er.status = 'Registered'
";
$stmt = $conn->prepare($completed_sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$completed_count = $stmt->get_result()->fetch_assoc()['total'];

// Fetch club membership count
$club_count_sql = "SELECT COUNT(*) as total FROM CLUB_MEMBERSHIP WHERE user_id = ?";
$stmt = $conn->prepare($club_count_sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$club_count = $stmt->get_result()->fetch_assoc()['total'];

// Fetch upcoming events registered
$upcoming_sql = "
    SELECT e.event_name, e.date, e.time, e.venue
    FROM EVENT_REGISTRATION er
    JOIN EVENT e ON er.event_id = e.event_id
    WHERE er.user_id = ? AND e.date >= CURDATE() AND er.status = 'Registered'
    ORDER BY e.date ASC
    LIMIT 5
";
$stmt = $conn->prepare($upcoming_sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$upcoming_events = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point Recognition - Student Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; color: #333; }
        
        .sidebar { width: 260px; background-color: #1a202c; color: white; display: flex; flex-direction: column; padding: 30px 20px; position: fixed; height: 100vh; box-shadow: 4px 0 10px rgba(0,0,0,0.1); z-index: 1000; top: 0; left: 0;}
        .sidebar-header { text-align: center; margin-bottom: 35px; }
        .sidebar-logo { max-width: 85px; margin-bottom: 12px; display: inline-block; }
        .sidebar-brand { font-size: 20px; font-weight: 700; color: #ffffff; margin-bottom: 6px; }
        .sidebar-role { font-size: 11px; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 1.5px; background: rgba(255,255,255,0.1); padding: 4px 12px; border-radius: 20px; display: inline-block; }
        .nav-links { display: flex; flex-direction: column; gap: 15px; flex-grow: 1; }
        .nav-links a { text-decoration: none; color: #a0aec0; font-weight: 600; padding: 12px 15px; border-radius: 8px; transition: 0.3s; display: block; }
        .nav-links a:hover, .nav-links a.active { background-color: #2d3748; color: white; }
        .btn-logout { background-color: #e53e3e; color: white; text-align: center; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: bold; margin-top: auto; transition: 0.2s; }

        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; max-width: calc(100% - 260px); }
        .welcome-card { background-color: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #38a169; }
        .welcome-card h2 { color: #1a202c; margin-bottom: 5px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 13px; color: #718096; text-transform: uppercase; }
        .stat-card .number { font-size: 2.5rem; font-weight: 700; color: #2d3748; margin-top: 10px; }

        .section-title { font-size: 22px; color: #1a202c; margin-bottom: 20px; border-bottom: 2px solid #cbd5e0; padding-bottom: 10px; margin-top: 30px; }
        
        /* Points Card */
        .points-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
            text-align: center;
        }
        .points-card h1 {
            font-size: 64px;
            font-weight: 800;
            margin: 15px 0;
        }
        .points-card .recognition-badge {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .points-card .recognition-level {
            font-size: 18px;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            display: inline-block;
            padding: 8px 20px;
            border-radius: 30px;
        }
        .points-card .benefits {
            margin-top: 20px;
            font-size: 14px;
            background: rgba(255,255,255,0.15);
            padding: 10px 20px;
            border-radius: 30px;
            display: inline-block;
        }
        
        /* Progress Bar */
        .progress-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
            color: #64748b;
        }
        .progress-bar-container {
            background: #e2e8f0;
            border-radius: 12px;
            height: 12px;
            overflow: hidden;
        }
        .progress-bar-fill {
            background: linear-gradient(90deg, #38a169, #2b6cb0);
            height: 100%;
            border-radius: 12px;
            transition: width 0.5s ease;
        }
        .next-milestone {
            margin-top: 12px;
            font-size: 13px;
            color: #38a169;
            text-align: right;
        }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .info-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .info-card .icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        .info-card .value {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
        }
        .info-card .label {
            font-size: 12px;
            color: #718096;
            margin-top: 5px;
        }
        
        /* Table Styles */
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .data-table th { background: #f8fafc; padding: 15px; text-align: left; font-weight: 600; color: #4a5568; border-bottom: 2px solid #e2e8f0; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #edf2f7; color: #2d3748; }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover { background: #f8fafc; }
        
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-registered { background: #c6f6d5; color: #22543d; }
        .status-cancelled { background: #fed7d7; color: #822727; }
        
        .empty-state { text-align: center; padding: 40px; background: white; border-radius: 12px; color: #718096; }
        
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .main-content { margin-left: 200px; width: calc(100% - 200px); }
        }
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
            <a href="student_dashboard.php">Dashboard</a>
            <a href="student_profile.php">My Profile</a>
            <a href="student_browse_clubs.php">Browse Clubs</a>
            <a href="student_event_registration.php">Event Registration</a>
            <a href="student_point_recognition.php" class="active">Point Recognition</a>
            <a href="student_leaderboard.php">Leaderboard</a>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>

    <div class="main-content">
        
        <?php echo $message; ?>
        
        <div class="welcome-card">
            <h2>🏆 Point Recognition System</h2>
            <p style="color: #718096;">Track your participation points, see your recognition level, and monitor your progress.</p>
        </div>

        <!-- Main Points Card -->
        <div class="points-card">
            <div class="recognition-badge"><?php echo $recognition['badge']; ?></div>
            <h1><?php echo $total_points; ?></h1>
            <div class="recognition-level" style="background: <?php echo $recognition['bg']; ?>; color: <?php echo $recognition['color']; ?>;">
                <?php echo $recognition['level']; ?>
            </div>
            <div class="benefits">
                🎁 <?php echo $recognition['benefits']; ?>
            </div>
        </div>

        <!-- Progress Bar (if not at max) -->
        <?php if ($recognition['next'] !== null): ?>
        <div class="progress-card">
            <div class="progress-label">
                <span>Progress to next level: <?php echo $recognition['level']; ?> → Next Level</span>
                <span><?php echo $total_points; ?> / <?php echo $recognition['next']; ?> points</span>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" style="width: <?php echo min(($total_points / $recognition['next']) * 100, 100); ?>%"></div>
            </div>
            <div class="next-milestone">
                🎯 Need <?php echo max(0, $recognition['next'] - $total_points); ?> more points to reach next level
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="info-grid">
            <div class="info-card">
                <div class="icon">👥</div>
                <div class="value"><?php echo $club_count; ?></div>
                <div class="label">Clubs Joined</div>
            </div>
            <div class="info-card">
                <div class="icon">✅</div>
                <div class="value"><?php echo $total_active; ?></div>
                <div class="label">Active Registrations</div>
            </div>
            <div class="info-card">
                <div class="icon">📅</div>
                <div class="value"><?php echo $completed_count; ?></div>
                <div class="label">Completed Events</div>
            </div>
            <div class="info-card">
                <div class="icon">❌</div>
                <div class="value"><?php echo $total_cancelled; ?></div>
                <div class="label">Cancelled Registrations</div>
            </div>
        </div>

        <!-- Registration History Table -->
        <h2 class="section-title">📊 My Event Registration History</h2>
        
        <div class="table-container">
            <?php if (!empty($history_data)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Venue</th>
                            <th>Registration Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history_data as $record): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($record['event_name']); ?></strong></td>
                                <td><?php echo date("d M Y", strtotime($record['date'])); ?></td>
                                <td><?php echo date("h:i A", strtotime($record['time'])); ?></td>
                                <td><?php echo htmlspecialchars($record['venue']); ?></td>
                                <td><?php echo date("d M Y", strtotime($record['register_date'])); ?></td>
                                <td>
                                    <?php if ($record['status'] == 'Registered'): ?>
                                        <span class="status-badge status-registered">✅ Registered</span>
                                    <?php elseif ($record['status'] == 'Cancelled'): ?>
                                        <span class="status-badge status-cancelled">❌ Cancelled</span>
                                    <?php else: ?>
                                        <span class="status-badge"><?php echo $record['status']; ?></span>
                                    <?php endif; ?>
                                 </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No registration history yet. Register for events to earn points!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Upcoming Registered Events -->
        <?php if ($upcoming_events->num_rows > 0): ?>
        <h2 class="section-title">📅 Upcoming Events You're Registered For</h2>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Event Name</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Venue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($event = $upcoming_events->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($event['event_name']); ?></strong></td>
                            <td><?php echo date("d M Y", strtotime($event['date'])); ?></td>
                            <td><?php echo date("h:i A", strtotime($event['time'])); ?></td>
                            <td><?php echo htmlspecialchars($event['venue']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Point System Reference (Table A from PDF) -->
        <h2 class="section-title">📖 Point System Reference</h2>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Points</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>✅ Present on time</td><td style="color: #38a169; font-weight: 700;">+10 points</td></tr>
                    <tr><td>⏰ Late arrival</td><td style="color: #38a169; font-weight: 700;">+5 points</td></tr>
                    <tr><td>❌ Absent without notice</td><td style="color: #e53e3e; font-weight: 700;">-10 points</td></tr>
                    <tr><td>🤝 Volunteer/Helper in event</td><td style="color: #38a169; font-weight: 700;">+5 points</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Recognition Levels Reference (Table B from PDF) -->
        <h2 class="section-title">🏅 Recognition Levels</h2>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Total Points</th>
                        <th>Recognition Level</th>
                        <th>Benefits</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>80 and above</td><td>🏆 Outstanding Participant</td><td>Eligible for leadership award / priority in event registration</td></tr>
                    <tr><td>50 – 79</td><td>⭐ Highly Active</td><td>Eligible for active student award / bonus points</td></tr>
                    <tr><td>20 – 49</td><td>📜 Active Participant</td><td>Eligible for participation certificate</td></tr>
                    <tr><td>Less than 20</td><td>⚠️ Needs Improvement</td><td>Warning / Reminder to participate more</td></tr>
                </tbody>
            </table>
        </div>
        
    </div>

</body>
</html>