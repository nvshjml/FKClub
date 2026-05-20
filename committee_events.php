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
$show_form = false;

// Get club info
$stmt = $conn->prepare("
    SELECT c.club_id, c.club_name, com.position 
    FROM `committee` com 
    JOIN `club` c ON com.club_id = c.club_id 
    WHERE com.user_id = ?
");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$club = $stmt->get_result()->fetch_assoc();

if (!$club) {
    $club_id = null;
    $club_name = "No Club Assigned";
} else {
    $club_id = $club['club_id'];
    $club_name = $club['club_name'];
}

// Handle Create Event
if (isset($_POST['create_event'])) {
    $event_name = $_POST['event_name'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $venue = $_POST['venue'];
    $max_cap = $_POST['max_cap'];
    $description = $_POST['description'];
    $qr_token = bin2hex(random_bytes(16));
    
    $insert_stmt = $conn->prepare("
        INSERT INTO `event` (event_name, club_id, date, time, venue, max_cap, description, qr_token) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert_stmt->bind_param("sisssiss", $event_name, $club_id, $date, $time, $venue, $max_cap, $description, $qr_token);
    
    if ($insert_stmt->execute()) {
        $message = "<div style='background:#c6f6d5; color:#22543d; padding:12px; border-radius:8px; margin-bottom:20px;'>✅ Event created successfully!</div>";
        $show_form = false;
    } else {
        $message = "<div style='background:#fed7d7; color:#822727; padding:12px; border-radius:8px; margin-bottom:20px;'>❌ Error creating event: " . $conn->error . "</div>";
    }
}

// Handle Delete Event
if (isset($_POST['delete_event'])) {
    $event_id = $_POST['event_id'];
    $del_stmt = $conn->prepare("DELETE FROM `event` WHERE event_id = ? AND club_id = ?");
    $del_stmt->bind_param("ii", $event_id, $club_id);
    if ($del_stmt->execute()) {
        $message = "<div style='background:#c6f6d5; color:#22543d; padding:12px; border-radius:8px; margin-bottom:20px;'>✅ Event deleted successfully!</div>";
    }
}

// Get all events for this club
$events = [];
if ($club_id) {
    $events_stmt = $conn->prepare("
        SELECT e.*, 
            (SELECT COUNT(*) FROM `event_registration` WHERE event_id = e.event_id) AS registered_count 
        FROM `event` e 
        WHERE e.club_id = ? 
        ORDER BY e.date ASC
    ");
    $events_stmt->bind_param("i", $club_id);
    $events_stmt->execute();
    $events = $events_stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events</title>
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
        .btn-logout { background-color: #e53e3e; color: white; text-align: center; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: bold; margin-top: auto; transition: 0.2s; display: block; }

        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; width: calc(100% - 260px); }
        .welcome-card { background-color: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #38a169; }
        .welcome-card h2 { color: #1a202c; margin-bottom: 5px; }
        
        .btn-create { background: #3182ce; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-bottom: 25px; font-size: 14px; }
        .btn-create:hover { background: #2b6cb0; }
        
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .event-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: 0.3s; }
        .event-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
        .event-image { width: 100%; height: 140px; background: linear-gradient(135deg, #3182ce 0%, #2b6cb0 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 48px; }
        .event-content { padding: 20px; }
        .event-title { font-size: 18px; font-weight: bold; color: #2d3748; margin-bottom: 10px; }
        .event-detail { font-size: 14px; color: #718096; margin-bottom: 5px; display: flex; align-items: center; gap: 8px; }
        .capacity-bar { margin-top: 12px; background: #e2e8f0; border-radius: 10px; height: 8px; overflow: hidden; }
        .capacity-fill { background: #38a169; height: 100%; border-radius: 10px; }
        .capacity-text { font-size: 12px; margin-top: 6px; color: #4a5568; }
        .event-actions { margin-top: 15px; display: flex; gap: 10px; padding-top: 15px; border-top: 1px solid #e2e8f0; }
        .btn-delete { background: #fff5f5; color: #c53030; border: 1px solid #fed7d7; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; }
        .btn-delete:hover { background: #fed7d7; }
        .btn-view { background: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; padding: 8px 12px; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 13px; display: inline-block; }
        .btn-view:hover { background: #bee3f8; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 2000; }
        .modal-content { background: white; border-radius: 16px; width: 90%; max-width: 500px; padding: 30px; }
        .modal-content h3 { margin-bottom: 20px; color: #1a202c; }
        .modal-content input, .modal-content textarea, .modal-content select { width: 100%; padding: 12px; margin-bottom: 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-family: 'Inter', sans-serif; }
        .modal-content button { padding: 12px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; }
        .btn-submit { background: #3182ce; color: white; margin-right: 10px; }
        .btn-cancel { background: #e2e8f0; color: #4a5568; }
        
        .badge-upcoming { background: #c6f6d5; color: #22543d; padding: 4px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; margin-left: 10px; }
        .badge-past { background: #e2e8f0; color: #4a5568; padding: 4px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; margin-left: 10px; }
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
            <a href="committee_events.php" class="active">Manage Events</a>
            <a href="committee_attendance.php">Record Attendance</a>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>

    <div class="main-content">
        <?php echo $message; ?>
        
        <div class="welcome-card">
            <h2>📅 Manage Events</h2>
            <p><?php echo htmlspecialchars($club_name); ?> • Create, edit, and manage your club's events.</p>
        </div>

        <button class="btn-create" onclick="openModal()">+ Create New Event</button>

        <div class="grid-container">
            <?php if ($events && $events->num_rows > 0): ?>
                <?php while($event = $events->fetch_assoc()): 
                    $remaining = $event['max_cap'] - $event['registered_count'];
                    $percentage = ($event['registered_count'] / $event['max_cap']) * 100;
                    $is_past = strtotime($event['date']) < strtotime(date('Y-m-d'));
                ?>
                    <div class="event-card">
                        <div class="event-image">🎯</div>
                        <div class="event-content">
                            <div class="event-title">
                                <?php echo htmlspecialchars($event['event_name']); ?>
                                <?php if (!$is_past): ?>
                                    <span class="badge-upcoming">Upcoming</span>
                                <?php else: ?>
                                    <span class="badge-past">Past</span>
                                <?php endif; ?>
                            </div>
                            <div class="event-detail">📅 <?php echo date("d M Y", strtotime($event['date'])); ?></div>
                            <div class="event-detail">⏰ <?php echo date("h:i A", strtotime($event['time'])); ?></div>
                            <div class="event-detail">📍 <?php echo htmlspecialchars($event['venue']); ?></div>
                            <div class="capacity-bar">
                                <div class="capacity-fill" style="width: <?php echo $percentage; ?>%;"></div>
                            </div>
                            <div class="capacity-text">
                                👥 Registered: <?php echo $event['registered_count']; ?> / <?php echo $event['max_cap']; ?>
                                <?php if (!$is_past && $remaining > 0): ?>
                                    <span style="color: #38a169;"> (<?php echo $remaining; ?> slots left)</span>
                                <?php endif; ?>
                            </div>
                            <div class="event-actions">
                                <a href="committee_attendance.php?event_id=<?php echo $event['event_id']; ?>" class="btn-view">Record Attendance</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this event?');">
                                    <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                    <button type="submit" name="delete_event" class="btn-delete">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="background: #f7fafc; padding: 40px; text-align: center; border-radius: 12px; color: #718096; grid-column: 1/-1;">
                    No events created yet. Click "Create New Event" to get started!
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for creating event -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <h3>Create New Event</h3>
            <form method="POST" action="">
                <input type="text" name="event_name" placeholder="Event Name" required>
                <input type="date" name="date" required>
                <input type="time" name="time" required>
                <input type="text" name="venue" placeholder="Venue" required>
                <input type="number" name="max_cap" placeholder="Maximum Capacity" required>
                <textarea name="description" rows="3" placeholder="Event Description"></textarea>
                <button type="submit" name="create_event" class="btn-submit">Create Event</button>
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('eventModal').style.display = 'flex';
        }
        function closeModal() {
            document.getElementById('eventModal').style.display = 'none';
        }
        window.onclick = function(event) {
            if (event.target == document.getElementById('eventModal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>