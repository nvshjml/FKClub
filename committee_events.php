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
$current_page = basename($_SERVER['PHP_SELF']);

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
    
    $insert_stmt = $conn->prepare("
        INSERT INTO `event` (event_name, club_id, date, time, venue, max_cap, description, qr_token) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert_stmt->bind_param("sisssiss", $event_name, $club_id, $date, $time, $venue, $max_cap, $description, $qr_token);
    
    if ($insert_stmt->execute()) {
        $message = "<div style='background:#c6f6d5; color:#22543d; padding:12px; border-radius:8px; margin-bottom:20px;'>✅ Event created successfully!</div>";
    } else {
        $message = "<div style='background:#fed7d7; color:#822727; padding:12px; border-radius:8px; margin-bottom:20px;'>❌ Error: " . $conn->error . "</div>";
    }
}

// ==================== UPDATE EVENT ====================
if (isset($_POST['update_event'])) {
    $event_id = intval($_POST['event_id']);
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
    $update_stmt->bind_param("sssssiii", $event_name, $date, $time, $venue, $max_cap, $description, $event_id, $club_id);
    
    if ($update_stmt->execute()) {
        $message = "<div style='background:#c6f6d5; color:#22543d; padding:12px; border-radius:8px; margin-bottom:20px;'>✏️ Event updated successfully!</div>";
        $edit_event = null; // Close modal
    } else {
        $message = "<div style='background:#fed7d7; color:#822727; padding:12px; border-radius:8px; margin-bottom:20px;'>❌ Update failed: " . $conn->error . "</div>";
    }
}

// ==================== DELETE EVENT ====================
if (isset($_POST['delete_event'])) {
    $event_id = intval($_POST['event_id']);
    
    $check_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM `event_registration` WHERE event_id = ?");
    $check_stmt->bind_param("i", $event_id);
    $check_stmt->execute();
    $has_registrations = $check_stmt->get_result()->fetch_assoc()['cnt'];
    
    if ($has_registrations > 0) {
        $message = "<div style='background:#fed7d7; color:#822727; padding:12px; border-radius:8px; margin-bottom:20px;'>⚠️ Cannot delete: This event has $has_registrations registration(s).</div>";
    } else {
        $del_stmt = $conn->prepare("DELETE FROM `event` WHERE event_id = ? AND club_id = ?");
        $del_stmt->bind_param("ii", $event_id, $club_id);
        if ($del_stmt->execute()) {
            $message = "<div style='background:#c6f6d5; color:#22543d; padding:12px; border-radius:8px; margin-bottom:20px;'>🗑️ Event deleted successfully!</div>";
        }
    }
}

// ==================== GET EVENT FOR EDIT ====================
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_stmt = $conn->prepare("SELECT * FROM `event` WHERE event_id = ? AND club_id = ?");
    $edit_stmt->bind_param("ii", $edit_id, $club_id);
    $edit_stmt->execute();
    $edit_event = $edit_stmt->get_result()->fetch_assoc();
}

// ==================== GET ALL EVENTS ====================
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
        body { display: flex; background: #e2e8f0; min-height: 100vh; }
        
        .main-content { margin-left: 260px; padding: 40px; width: calc(100% - 260px); }
        .welcome-card { background: white; padding: 25px 30px; border-radius: 12px; margin-bottom: 30px; border-left: 6px solid #38a169; }
        .btn-create { background: #3182ce; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; margin-bottom: 25px; }
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; }
        .event-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .event-image { height: 120px; background: linear-gradient(135deg, #3182ce, #2b6cb0); display: flex; align-items: center; justify-content: center; color: white; font-size: 40px; }
        .event-content { padding: 20px; }
        .event-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .event-detail { font-size: 14px; color: #718096; margin-bottom: 5px; }
        .capacity-bar { margin-top: 12px; background: #e2e8f0; border-radius: 10px; height: 8px; }
        .capacity-fill { background: #38a169; height: 100%; border-radius: 10px; }
        .event-actions { margin-top: 15px; display: flex; gap: 10px; }
        .btn-edit { background: #fefcbf; color: #744210; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 13px; }
        .btn-delete { background: #fff5f5; color: #c53030; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; }
        .btn-attend { background: #ebf8ff; color: #2b6cb0; border: none; padding: 8px 12px; border-radius: 6px; text-decoration: none; display: inline-block; font-size: 13px; }
        .badge-upcoming { background: #c6f6d5; color: #22543d; padding: 4px 8px; border-radius: 20px; font-size: 11px; }
        .badge-past { background: #e2e8f0; color: #4a5568; padding: 4px 8px; border-radius: 20px; font-size: 11px; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 2000; }
        .modal-content { background: white; border-radius: 16px; width: 90%; max-width: 500px; padding: 30px; }
        .modal-content input, .modal-content textarea { width: 100%; padding: 12px; margin-bottom: 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-family: 'Inter', sans-serif; }
        
        .btn-submit { background: #3182ce; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-cancel { background: #e2e8f0; color: #4a5568; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; margin-left: 10px; text-decoration: none; display: inline-block; }
        
        .description-text { background: #f7fafc; padding: 8px 12px; border-radius: 8px; margin-top: 8px; font-size: 13px; color: #4a5568; border-left: 3px solid #3182ce; }
        .modal-content textarea { resize: vertical; min-height: 100px; }
        .btn-submit { background: #3182ce; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-cancel { background: #e2e8f0; color: #4a5568; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <?php echo $message; ?>
    
    <div class="welcome-card">
        <h2>📅 Manage Events</h2>
        <p><?php echo htmlspecialchars($club_name); ?> • Create, edit, and delete events</p>
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
                        
                        <!-- SHOW DESCRIPTION IF EXISTS -->
                        <?php if (!empty($event['description'])): ?>
                            <div class="description-text">📝 <?php echo htmlspecialchars($event['description']); ?></div>
                        <?php endif; ?>
                        
                        <div class="capacity-bar"><div class="capacity-fill" style="width: <?php echo $percentage; ?>%;"></div></div>
                        <div class="event-detail">👥 <?php echo $event['registered_count']; ?> / <?php echo $event['max_cap']; ?> registered</div>
                        
                        <?php if (!empty($event['description'])): ?>
                            <div class="event-detail">📝 <?php echo htmlspecialchars(substr($event['description'], 0, 80)) . (strlen($event['description']) > 80 ? '...' : ''); ?></div>
                        <?php endif; ?>
                        <div class="capacity-bar"><div class="capacity-fill" style="width: <?php echo $percentage; ?>%;"></div></div>
                        <div class="event-detail">👥 <?php echo $event['registered_count']; ?> / <?php echo $event['max_cap']; ?> registered</div>
                        <div class="event-actions">
                            <a href="committee_attendance.php?event_id=<?php echo $event['event_id']; ?>" class="btn-attend">📝 Attendance</a>
                            <a href="?edit_id=<?php echo $event['event_id']; ?>" class="btn-edit">✏️ Edit</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this event?');">
                                <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                <button type="submit" name="delete_event" class="btn-delete">🗑️ Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="background:#f7fafc; padding:60px; text-align:center; border-radius:12px; color:#718096; grid-column: 1 / -1;">
                No events yet. Click "Create New Event" to get started!
            </div>
        <?php endif; ?>
    </div>
</div>

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
            <textarea name="description" rows="3" placeholder="Event description (optional)"></textarea>

            <button type="submit" name="create_event" class="btn-submit">Create Event</button>
            <button type="button" class="btn-cancel" onclick="closeCreateModal()">Cancel</button>
        </form>
    </div>
</div>

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
            <button type="submit" name="update_event" class="btn-submit">Save Changes</button>

            <textarea name="description" rows="4" placeholder="Event description..."><?php echo htmlspecialchars($edit_event['description'] ?? ''); ?></textarea>
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
