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

$stmt1 = $conn->prepare("SELECT total_point FROM `USER` WHERE user_id = ?");
$stmt1->bind_param("s", $user_id);
$stmt1->execute();
$points_result = $stmt1->get_result()->fetch_assoc();
$total_points = $points_result ? $points_result['total_point'] : 0;

$stmt2 = $conn->prepare("SELECT COUNT(*) AS total FROM CLUB_MEMBERSHIP WHERE user_id = ?");
$stmt2->bind_param("s", $user_id); $stmt2->execute();
$total_clubs = $stmt2->get_result()->fetch_assoc()['total'];

$stmt3 = $conn->prepare("SELECT COUNT(*) AS total FROM EVENT_REGISTRATION WHERE user_id = ?");
$stmt3->bind_param("s", $user_id); $stmt3->execute();
$total_events = $stmt3->get_result()->fetch_assoc()['total'];

$events_sql = "SELECT event_id, event_name, date, time, venue, qr_token FROM EVENT WHERE date >= CURDATE() ORDER BY date ASC";
$events_result = $conn->query($events_sql);

$clubs_sql = "SELECT c.club_id, c.club_name, u.name AS president_name FROM CLUB c LEFT JOIN COMMITTEE com ON c.club_id = com.club_id AND com.position = 'President' LEFT JOIN `USER` u ON com.user_id = u.user_id WHERE c.isActive = 1";
$clubs_result = $conn->query($clubs_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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

        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; max-width: 1200px; }
        .welcome-card { background-color: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #38a169; }
        .welcome-card h2 { color: #1a202c; margin-bottom: 5px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 13px; color: #718096; text-transform: uppercase; }
        .stat-card .number { font-size: 2.5rem; font-weight: 700; color: #2d3748; margin-top: 10px; }

        .section-title { font-size: 22px; color: #1a202c; margin-bottom: 20px; border-bottom: 2px solid #cbd5e0; padding-bottom: 10px; }
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; margin-bottom: 50px; }
        .item-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: 0.3s; }
        .item-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
        .item-image { width: 100%; height: 160px; object-fit: cover; background: #edf2f7; }
        .item-content { padding: 20px; }
        .item-title { font-size: 18px; font-weight: bold; color: #2d3748; margin-bottom: 10px; }
        .item-detail { font-size: 14px; color: #718096; margin-bottom: 5px; }
        .btn-action { display: block; width: 100%; text-align: center; padding: 10px; margin-top: 15px; background: #3182ce; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; text-align: center; width: 300px; }
        .modal-content img { width: 200px; height: 200px; margin: 20px 0; }
        .close-btn { background: #e53e3e; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; width: 100%; font-weight: bold; }
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
            <a href="student_point_recognition.php">Point Recognition</a>
            <a href="student_leaderboard.php">Leaderboard</a>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>

    <div class="main-content">
        <div class="welcome-card">
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>! 🎓</h2>
            <p style="color: #718096;">This is your student portal. Track your points, manage your clubs, and register for upcoming events.</p>
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
            <?php if ($events_result && $events_result->num_rows > 0): ?>
                <?php while($event = $events_result->fetch_assoc()): ?>
                    <div class="item-card">
                        <img src="https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=500&q=80" class="item-image" alt="Event Poster">
                        <div class="item-content">
                            <div class="item-title"><?php echo htmlspecialchars($event['event_name']); ?></div>
                            <div class="item-detail">📅 <?php echo date("d M Y", strtotime($event['date'])); ?></div>
                            <div class="item-detail">⏰ <?php echo date("h:i A", strtotime($event['time'])); ?></div>
                            <div class="item-detail">📍 <?php echo htmlspecialchars($event['venue']); ?></div>
                            <button class="btn-action" onclick="showQR('<?php echo $event['qr_token']; ?>', '<?php echo htmlspecialchars($event['event_name'], ENT_QUOTES); ?>')">Show Registration QR</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No upcoming events at the moment.</p>
            <?php endif; ?>
        </div>

        <h2 class="section-title">🏆 Available Clubs</h2>
        <div class="grid-container">
            <?php if ($clubs_result && $clubs_result->num_rows > 0): ?>
                <?php while($club = $clubs_result->fetch_assoc()): ?>
                    <div class="item-card">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($club['club_name']); ?>&background=random&size=300" class="item-image" alt="Club Logo">
                        <div class="item-content">
                            <div class="item-title"><?php echo htmlspecialchars($club['club_name']); ?></div>
                            <div class="item-detail">👤 <strong>President:</strong> <?php echo $club['president_name'] ? htmlspecialchars($club['president_name']) : 'TBD'; ?></div>
                            <button class="btn-action" <?php echo ($total_clubs >= 3) ? 'disabled' : ''; ?>><?php echo ($total_clubs >= 3) ? 'Limit Reached' : 'Join Club'; ?></button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No clubs available right now.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal" id="qrModal">
        <div class="modal-content">
            <h3 id="modalEventTitle">Event Name</h3>
            <p style="font-size: 13px; color: #718096;">Scan to register attendance</p>
            <img id="qrImage" src="" alt="QR Code">
            <button class="close-btn" onclick="closeQR()">Close</button>
        </div>
    </div>

    <script>
        function showQR(qrToken, eventName) {
            document.getElementById('modalEventTitle').innerText = eventName;
            document.getElementById('qrImage').src = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" + encodeURIComponent(qrToken);
            document.getElementById('qrModal').style.display = 'flex';
        }
        function closeQR() { document.getElementById('qrModal').style.display = 'none'; }
    </script>
</body>
</html>