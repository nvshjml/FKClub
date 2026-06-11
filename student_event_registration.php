<?php
session_start();
require 'db_connect.php';

// SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'Admin') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$message_type = "";

// ---------------------------------------------------------
// 1. HANDLE REGISTRATION (With Committee Blocker)
// ---------------------------------------------------------
if (isset($_GET['register']) && isset($_GET['event_id'])) {
    $event_id = $_GET['event_id']; // Treat as string (e.g., EVT260001)

    // --- NEW LOGIC: Block Committee Members from registering for their own events ---
    
    // Find which club is hosting this event
    $club_check_stmt = $conn->prepare("SELECT club_id FROM event WHERE event_id = ?");
    $club_check_stmt->bind_param("s", $event_id);
    $club_check_stmt->execute();
    $event_data = $club_check_stmt->get_result()->fetch_assoc();
    $host_club_id = $event_data['club_id'];

    // Check if the logged-in user is a committee member for THAT club
    $committee_check_stmt = $conn->prepare("SELECT * FROM committee WHERE user_id = ? AND club_id = ?");
    $committee_check_stmt->bind_param("ss", $user_id, $host_club_id);
    $committee_check_stmt->execute();
    $is_committee = $committee_check_stmt->get_result()->num_rows > 0;

    if ($is_committee) {
        // BLOCK THEM: Show error message
        $message = "As a committee member of this club, you cannot register as a participant for your own events.";
        $message_type = "error";
    } else {
        // ALLOW THEM: Proceed with normal registration logic
        
        // Check if already registered
        $check_stmt = $conn->prepare("SELECT status FROM event_registration WHERE user_id = ? AND event_id = ?");
        $check_stmt->bind_param("ss", $user_id, $event_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['status'] == 'Cancelled') {
                // Re-activate registration
                $upd = $conn->prepare("UPDATE event_registration SET status = 'Registered', register_date = CURRENT_TIMESTAMP WHERE user_id = ? AND event_id = ?");
                $upd->bind_param("ss", $user_id, $event_id);
                $upd->execute();
                $message = "You have successfully re-registered for the event!";
                $message_type = "success";
            } else {
                $message = "You are already registered or waitlisted for this event.";
                $message_type = "error";
            }
        } else {
            // Check capacity for waitlisting
            $cap_stmt = $conn->prepare("
                SELECT max_cap, 
                (SELECT COUNT(*) FROM event_registration WHERE event_id = e.event_id AND status = 'Registered') as current_reg 
                FROM event e WHERE e.event_id = ?
            ");
            $cap_stmt->bind_param("s", $event_id);
            $cap_stmt->execute();
            $cap_data = $cap_stmt->get_result()->fetch_assoc();

            $status = 'Registered';
            if ($cap_data['max_cap'] > 0 && $cap_data['current_reg'] >= $cap_data['max_cap']) {
                $status = 'Waitlisted';
                $message = "Event is full. You have been added to the Waitlist.";
                $message_type = "warning";
            } else {
                $message = "Successfully registered for the event!";
                $message_type = "success";
            }

            // Insert new registration
            $ins = $conn->prepare("INSERT INTO event_registration (user_id, event_id, register_type, status) VALUES (?, ?, 'Participant', ?)");
            $ins->bind_param("sss", $user_id, $event_id, $status);
            $ins->execute();
        }
    }
}

// ---------------------------------------------------------
// 2. HANDLE CANCELLATION 
// ---------------------------------------------------------
if (isset($_GET['cancel']) && isset($_GET['event_id'])) {
    $event_id = $_GET['event_id'];
    $cancel_stmt = $conn->prepare("UPDATE event_registration SET status = 'Cancelled' WHERE user_id = ? AND event_id = ?");
    $cancel_stmt->bind_param("ss", $user_id, $event_id);
    if ($cancel_stmt->execute()) {
        $message = "Registration cancelled successfully.";
        $message_type = "success";
    }
}

// ---------------------------------------------------------
// FETCH STATS FOR DASHBOARD
// ---------------------------------------------------------
$total_available = $conn->query("SELECT COUNT(*) as t FROM event WHERE date >= CURDATE()")->fetch_assoc()['t'];

$stmt = $conn->prepare("SELECT COUNT(*) as t FROM event_registration WHERE user_id = ? AND status = 'Registered'");
$stmt->bind_param("s", $user_id); $stmt->execute();
$total_registered = $stmt->get_result()->fetch_assoc()['t'];

$stmt = $conn->prepare("SELECT COUNT(*) as t FROM event_registration WHERE user_id = ? AND status = 'Waitlisted'");
$stmt->bind_param("s", $user_id); $stmt->execute();
$total_waitlisted = $stmt->get_result()->fetch_assoc()['t'];

// ---------------------------------------------------------
// FETCH UPCOMING EVENTS
// ---------------------------------------------------------
$events_sql = "
    SELECT 
        e.*, 
        c.club_name,
        (SELECT COUNT(*) FROM event_registration er WHERE er.event_id = e.event_id AND er.status = 'Registered') as registered_count,
        (SELECT status FROM event_registration er2 WHERE er2.event_id = e.event_id AND er2.user_id = ?) as user_status
    FROM event e
    JOIN club c ON e.club_id = c.club_id
    WHERE e.date >= CURDATE()
    ORDER BY e.date ASC
";
$stmt = $conn->prepare($events_sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$events_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Registration</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css"> 
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; color: #333; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; max-width: 1200px; }
        .welcome-card { background-color: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #38a169; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 13px; color: #718096; text-transform: uppercase; }
        .stat-card .number { font-size: 2.5rem; font-weight: 700; color: #2d3748; margin-top: 10px; }
        
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
        .item-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); display: flex; flex-direction: column; transition: 0.3s;}
        .item-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
        
        .item-image { width: 100%; height: 160px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; font-size: 40px;}
        
        .item-content { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .item-title { font-size: 18px; font-weight: bold; color: #2d3748; margin-bottom: 12px; }
        .item-detail { font-size: 13px; color: #718096; margin-bottom: 6px; }
        
        .capacity-bar-container { background: #edf2f7; height: 6px; border-radius: 6px; overflow: hidden; margin: 15px 0 5px 0; }
        .capacity-bar-fill { background: #38a169; height: 100%; border-radius: 6px; }
        .capacity-bar-fill.full { background: #e53e3e; }
        .capacity-text { font-size: 12px; color: #4a5568; font-weight: 600; margin-bottom: 15px;}

        .btn-action { display: block; width: 100%; text-align: center; padding: 10px; margin-top: auto; background: #3182ce; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; transition: 0.2s; }
        .btn-action:hover { background: #2b6cb0; }
        .btn-cancel { background: #e53e3e; }
        .btn-waitlist { background: #ed8936; cursor: default;}
        .btn-registered { background: #38a169; cursor: default;}
        
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; text-align: center;}
        .alert-success { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background-color: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        .alert-warning { background-color: #feebc8; color: #c05621; border: 1px solid #fbd38d; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="welcome-card">
            <h2>📅 Event Registration</h2>
            <p style="color: #718096;">Browse upcoming events, register to participate, and track your registration history.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card" style="border-bottom: 4px solid #3182ce;">
                <h3>Available Events</h3>
                <div class="number"><?php echo $total_available; ?></div>
            </div>
            <div class="stat-card" style="border-bottom: 4px solid #38a169;">
                <h3>My Registrations</h3>
                <div class="number"><?php echo $total_registered; ?></div>
            </div>
            <div class="stat-card" style="border-bottom: 4px solid #ed8936;">
                <h3>Waitlisted</h3>
                <div class="number"><?php echo $total_waitlisted; ?></div>
            </div>
        </div>

        <h3 style="margin-bottom: 20px; color: #2d3748;">🎯 Available Events for Registration</h3>

        <div class="grid-container">
            <?php while($event = $events_result->fetch_assoc()): 
                $reg_count = $event['registered_count'];
                $max_cap = $event['max_cap'] ?: 100;
                $percent = min(($reg_count / $max_cap) * 100, 100);
                $is_full = ($reg_count >= $max_cap);
            ?>
                <div class="item-card">
                    <div class="item-image">🎉</div>
                    
                    <div class="item-content">
                        <div class="item-title"><?php echo htmlspecialchars($event['event_name']); ?></div>
                        <div class="item-detail">🗓️ <?php echo date("d M Y", strtotime($event['date'])); ?></div>
                        <div class="item-detail">⏰ <?php echo date("h:i A", strtotime($event['time'])); ?></div>
                        <div class="item-detail">📍 <?php echo htmlspecialchars($event['venue']); ?></div>
                        
                        <div class="capacity-bar-container">
                            <div class="capacity-bar-fill <?php echo $is_full ? 'full' : ''; ?>" style="width: <?php echo $percent; ?>%;"></div>
                        </div>
                        <div class="capacity-text">
                            👥 <?php echo $reg_count; ?> / <?php echo $max_cap; ?> registered 
                            <span style="color: #718096; font-weight:normal;">(<?php echo max(0, $max_cap - $reg_count); ?> slots left)</span>
                        </div>
                        
                        <div style="margin-top: 10px;">
                        <?php if ($event['user_status'] == 'Registered'): ?>
                            <div style="display: flex; gap: 10px;">
                                <button class="btn-action btn-registered" disabled style="flex: 1;">✅ Registered</button>
                                <a href="?cancel=1&event_id=<?php echo $event['event_id']; ?>" class="btn-action btn-cancel" style="flex: 1;" onclick="return confirm('Cancel registration?')">Cancel</a>
                            </div>
                        <?php elseif ($event['user_status'] == 'Waitlisted'): ?>
                            <div style="display: flex; gap: 10px;">
                                <button class="btn-action btn-waitlist" disabled style="flex: 1;">⏳ Waitlisted</button>
                                <a href="?cancel=1&event_id=<?php echo $event['event_id']; ?>" class="btn-action btn-cancel" style="flex: 1;" onclick="return confirm('Remove from waitlist?')">Cancel</a>
                            </div>
                        <?php else: ?>
                            <a href="?register=1&event_id=<?php echo $event['event_id']; ?>" class="btn-action">Register</a>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>