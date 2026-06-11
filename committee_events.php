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
$edit_event = null;

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

// ==================== CREATE EVENT ====================
if (isset($_POST['create_event'])) {
    $event_name = trim($_POST['event_name']);
    $date = $_POST['date'];
    $time = $_POST['time'];
    $venue = trim($_POST['venue']);
    $max_cap = intval($_POST['max_cap']);
    $description = trim($_POST['description']);
    $qr_token = bin2hex(random_bytes(16));
    
    // Generate event ID (EVT + timestamp)
    $event_id = 'EVT' . date('ymd') . rand(100, 999);
    
    $insert_stmt = $conn->prepare("
        INSERT INTO `event` (event_id, event_name, club_id, date, time, venue, max_cap, description, qr_token) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert_stmt->bind_param("ssssssiss", $event_id, $event_name, $club_id, $date, $time, $venue, $max_cap, $description, $qr_token);
    
    if ($insert_stmt->execute()) {
        $message = "<div style='background:#c6f6d5; color:#22543d; padding:12px; border-radius:8px; margin-bottom:20px;'>✅ Event created successfully! QR code has been generated.</div>";
    } else {
        $message = "<div style='background:#fed7d7; color:#822727; padding:12px; border-radius:8px; margin-bottom:20px;'>❌ Error: " . $conn->error . "</div>";
    }
}

// ==================== UPDATE EVENT ====================
if (isset($_POST['update_event'])) {
    $event_id = $_POST['event_id'];
    $event_name = trim($_POST['event_name']);
    $date = $_POST['date'];
    $time = $_POST['time'];
    $venue = trim($_POST['venue']);
    $max_cap = intval($_POST['max_cap']);
    $description = trim($_POST['description']);
    
    $update_stmt = $conn->prepare("
        UPDATE `event` 
        SET event_name = ?, date = ?, time = ?, venue = ?, max_cap = ?, description = ? 
        WHERE event_id = ? AND club_id = ?
    ");
    $update_stmt->bind_param("sssssis", $event_name, $date, $time, $venue, $max_cap, $description, $event_id, $club_id);
    
    if ($update_stmt->execute()) {
        $message = "<div style='background:#c6f6d5; color:#22543d; padding:12px; border-radius:8px; margin-bottom:20px;'>✏️ Event updated successfully!</div>";
    } else {
        $message = "<div style='background:#fed7d7; color:#822727; padding:12px; border-radius:8px; margin-bottom:20px;'>❌ Update failed: " . $conn->error . "</div>";
    }
}

// ==================== DELETE EVENT ====================
if (isset($_POST['delete_event'])) {
    $event_id = $_POST['event_id'];
    
    // Check if event has registrations
    $check_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM `event_registration` WHERE event_id = ?");
    $check_stmt->bind_param("s", $event_id);
    $check_stmt->execute();
    $has_registrations = $check_stmt->get_result()->fetch_assoc()['cnt'];
    
    if ($has_registrations > 0) {
        $message = "<div style='background:#fed7d7; color:#822727; padding:12px; border-radius:8px; margin-bottom:20px;'>⚠️ Cannot delete: This event has $has_registrations registration(s).</div>";
    } else {
        $del_stmt = $conn->prepare("DELETE FROM `event` WHERE event_id = ? AND club_id = ?");
        $del_stmt->bind_param("ss", $event_id, $club_id);
        if ($del_stmt->execute()) {
            $message = "<div style='background:#c6f6d5; color:#22543d; padding:12px; border-radius:8px; margin-bottom:20px;'>🗑️ Event deleted successfully!</div>";
        }
    }
}

// ==================== GET EVENT FOR EDIT ====================
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $edit_stmt = $conn->prepare("SELECT * FROM `event` WHERE event_id = ? AND club_id = ?");
    $edit_stmt->bind_param("ss", $edit_id, $club_id);
    $edit_stmt->execute();
    $edit_event = $edit_stmt->get_result()->fetch_assoc();
}

// ==================== GET ALL EVENTS ====================
$events = [];
if ($club_id) {
    $events_stmt = $conn->prepare("
        SELECT e.*, 
            (SELECT COUNT(*) FROM `event_registration` WHERE event_id = e.event_id AND status = 'Registered') AS registered_count 
        FROM `event` e 
        WHERE e.club_id = ? 
        ORDER BY e.date ASC
    ");
    $events_stmt->bind_param("s", $club_id);
    $events_stmt->execute();
    $events = $events_stmt->get_result();
}

// Fetch pending count for sidebar (not used for committee but for consistency)
$sidebar_pending_count = 0;
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - Committee</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; }
        
        .sidebar { width: 260px; background-color: #1a202c; color: white; display: flex; flex-direction: column; padding: 30px 20px; position: fixed; height: 100vh; }
        .sidebar-header { text-align: center; margin-bottom: 35px; }
        .sidebar-logo { max-width: 85px; margin-bottom: 12px; }
        .sidebar-brand { font-size: 20px; font-weight: 700; margin-bottom: 6px; }
        .sidebar-role { font-size: 11px; background: rgba(255,255,255,0.1); padding: 4px 12px; border-radius: 20px; display: inline-block; }
        .nav-links { display: flex; flex-direction: column; gap: 15px; flex-grow: 1; }
        .nav-links a { text-decoration: none; color: #a0aec0; font-weight: 600; padding: 12px 15px; border-radius: 8px; display: block; }
        .nav-links a:hover, .nav-links a.active { background-color: #2d3748; color: white; }
        .btn-logout { background-color: #e53e3e; text-align: center; padding: 12px; border-radius: 8px; margin-top: auto; color: white; text-decoration: none; display: block; }
        
        .main-content { margin-left: 260px; padding: 40px; width: calc(100% - 260px); }
        .welcome-card { background: white; padding: 25px 30px; border-radius: 12px; margin-bottom: 30px; border-left: 6px solid #38a169; }
        .btn-create { background: #3182ce; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; margin-bottom: 25px; font-weight: 600; }
        .btn-create:hover { background: #2b6cb0; }
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; }
        .event-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .event-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
        .event-image { height: 120px; background: linear-gradient(135deg, #3182ce, #2b6cb0); display: flex; align-items: center; justify-content: center; color: white; font-size: 40px; }
        .event-content { padding: 20px; }
        .event-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .event-detail { font-size: 14px; color: #718096; margin-bottom: 5px; }
        .capacity-bar { margin-top: 12px; background: #e2e8f0; border-radius: 10px; height: 8px; }
        .capacity-fill { background: #38a169; height: 100%; border-radius: 10px; }
        .event-actions { margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap; }
        .btn-edit { background: #fefcbf; color: #744210; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 13px; font-weight: 600; }
        .btn-delete { background: #fff5f5; color: #c53030; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .btn-attend { background: #ebf8ff; color: #2b6cb0; border: none; padding: 8px 12px; border-radius: 6px; text-decoration: none; display: inline-block; font-size: 13px; font-weight: 600; }
        .btn-qr { background: #805ad5; color: white; border: none; padding: 8px 12px; border-radius: 6px; text-decoration: none; display: inline-block; font-size: 13px; font-weight: 600; }
        .btn-qr:hover { background: #6b46c0; }
        .badge-upcoming { background: #c6f6d5; color: #22543d; padding: 4px 8px; border-radius: 20px; font-size: 11px; }
        .badge-past { background: #e2e8f0; color: #4a5568; padding: 4px 8px; border-radius: 20px; font-size: 11px; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 2000; }
        .modal-content { background: white; border-radius: 16px; width: 90%; max-width: 500px; padding: 30px; }
        .modal-content input, .modal-content textarea { width: 100%; padding: 12px; margin-bottom: 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-family: 'Inter', sans-serif; }
        .modal-content textarea { resize: vertical; min-height: 100px; }
        .btn-submit { background: #3182ce; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-cancel { background: #e2e8f0; color: #4a5568; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; margin-left: 10px; font-weight: 600; }
        .description-text { background: #f7fafc; padding: 8px 12px; border-radius: 8px; margin-top: 8px; font-size: 13px; color: #4a5568; border-left: 3px solid #3182ce; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <img src="image/LogoUMP5.png" alt="Logo" class="sidebar-logo">
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
        <p><?php echo htmlspecialchars($club_name); ?> • Create, edit, and delete events. Generate QR codes for student check-in.</p>
    </div>

    <button class="btn-create" onclick="openCreateModal()">+ Create New Event</button>

    <div class="grid-container">
        <?php if ($events && $events->num_rows > 0): ?>
            <?php while($event = $events->fetch_assoc()): 
                $percentage = ($event['max_cap'] > 0) ? ($event['registered_count'] / $event['max_cap']) * 100 : 0;
                $is_past = strtotime($event['date']) < strtotime(date('Y-m-d'));
            ?>
                <div class="event-card">
                    <div class="event-image">🎯</div>
                    <div class="event-content">
                        <div class="event-title">
                            <?php echo htmlspecialchars($event['event_name']); ?>
                            <?php if ($is_past): ?>
                                <span class="badge-past">Past</span>
                            <?php else: ?>
                                <span class="badge-upcoming">Upcoming</span>
                            <?php endif; ?>
                        </div>
                        <div class="event-detail">📅 <?php echo date("d M Y", strtotime($event['date'])); ?></div>
                        <div class="event-detail">⏰ <?php echo date("h:i A", strtotime($event['time'])); ?></div>
                        <div class="event-detail">📍 <?php echo htmlspecialchars($event['venue']); ?></div>
                        
                        <?php if (!empty($event['description'])): ?>
                            <div class="description-text">📝 <?php echo htmlspecialchars($event['description']); ?></div>
                        <?php endif; ?>
                        
                        <div class="capacity-bar"><div class="capacity-fill" style="width: <?php echo $percentage; ?>%;"></div></div>
                        <div class="event-detail">👥 <?php echo $event['registered_count']; ?> / <?php echo $event['max_cap']; ?> registered</div>
                        
                        <div class="event-actions">
                            <a href="committee_attendance.php?event_id=<?php echo $event['event_id']; ?>" class="btn-attend">📝 Attendance</a>
                            <a href="generate_qr.php?event_id=<?php echo $event['event_id']; ?>" class="btn-qr" target="_blank">📱 QR Code</a>
                            <a href="?edit_id=<?php echo $event['event_id']; ?>" class="btn-edit">✏️ Edit</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this event? This action cannot be undone.');">
                                <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                <button type="submit" name="delete_event" class="btn-delete">🗑️ Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="background:#f7fafc; padding:60px; text-align:center; border-radius:12px; color:#718096;">
                No events yet. Click "Create New Event" to get started!
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Modal -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <h3>➕ Create New Event</h3>
        <form method="POST">
            <input type="text" name="event_name" placeholder="Event Name" required>
            <input type="date" name="date" required>
            <input type="time" name="time" required>
            <input type="text" name="venue" placeholder="Venue" required>
            <input type="number" name="max_cap" placeholder="Max Capacity" required>
            <textarea name="description" rows="4" placeholder="Event Description (optional)"></textarea>
            <button type="submit" name="create_event" class="btn-submit">Create Event</button>
            <button type="button" class="btn-cancel" onclick="closeCreateModal()">Cancel</button>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<?php if ($edit_event): ?>
<div id="editModal" class="modal" style="display: flex;">
    <div class="modal-content">
        <h3>✏️ Edit Event</h3>
        <form method="POST">
            <input type="hidden" name="event_id" value="<?php echo $edit_event['event_id']; ?>">
            <input type="text" name="event_name" value="<?php echo htmlspecialchars($edit_event['event_name']); ?>" required>
            <input type="date" name="date" value="<?php echo $edit_event['date']; ?>" required>
            <input type="time" name="time" value="<?php echo $edit_event['time']; ?>" required>
            <input type="text" name="venue" value="<?php echo htmlspecialchars($edit_event['venue']); ?>" required>
            <input type="number" name="max_cap" value="<?php echo $edit_event['max_cap']; ?>" required>
            <textarea name="description" rows="4" placeholder="Event Description"><?php echo htmlspecialchars($edit_event['description'] ?? ''); ?></textarea>
            <button type="submit" name="update_event" class="btn-submit">💾 Save Changes</button>
            <a href="committee_events.php" class="btn-cancel">Cancel</a>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    function openCreateModal() {
        document.getElementById('createModal').style.display = 'flex';
    }
    function closeCreateModal() {
        document.getElementById('createModal').style.display = 'none';
    }
    window.onclick = function(event) {
        if (event.target == document.getElementById('createModal')) {
            closeCreateModal();
        }
        if (event.target == document.getElementById('editModal')) {
            window.location.href = 'committee_events.php';
        }
    }
</script>
</body>
</html>