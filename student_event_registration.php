<?php
session_start();
require 'db_connect.php';

// SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'Admin') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);
$message = "";

// Handle Event Registration
if (isset($_POST['register_btn']) && isset($_POST['event_id'])) {
    $event_id = $_POST['event_id'];
    
    // Check if already registered
    $check_sql = "SELECT * FROM EVENT_REGISTRATION WHERE user_id = ? AND event_id = ? AND status != 'Cancelled'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $user_id, $event_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $message = "<div class='alert alert-error'>⚠️ You are already registered for this event!</div>";
    } else {
        // Check event capacity
        $capacity_sql = "SELECT e.*, 
                                (SELECT COUNT(*) FROM EVENT_REGISTRATION er WHERE er.event_id = e.event_id AND er.status != 'Cancelled') as registered_count
                         FROM EVENT e WHERE e.event_id = ?";
        $capacity_stmt = $conn->prepare($capacity_sql);
        $capacity_stmt->bind_param("i", $event_id);
        $capacity_stmt->execute();
        $capacity_result = $capacity_stmt->get_result()->fetch_assoc();
        
        // FIXED: Using max_cap instead of max_participants
        $max_cap = isset($capacity_result['max_cap']) && $capacity_result['max_cap'] ? $capacity_result['max_cap'] : 100;
        $registered_count = $capacity_result['registered_count'] ?: 0;
        
        if ($registered_count >= $max_cap) {
            $message = "<div class='alert alert-error'>❌ Sorry! This event is already full. (Max: {$max_cap} participants)</div>";
        } else {
            $register_sql = "INSERT INTO EVENT_REGISTRATION (user_id, event_id, registration_date, status) VALUES (?, ?, NOW(), 'Registered')";
            $register_stmt = $conn->prepare($register_sql);
            $register_stmt->bind_param("si", $user_id, $event_id);
            
            if ($register_stmt->execute()) {
                $message = "<div class='alert alert-success'>✅ Successfully registered for the event!</div>";
            } else {
                $message = "<div class='alert alert-error'>❌ Error registering for event. Please try again.</div>";
            }
        }
    }
}

// Handle Cancel Registration
if (isset($_GET['cancel']) && isset($_GET['event_id'])) {
    $event_id = $_GET['event_id'];
    
    $cancel_sql = "UPDATE EVENT_REGISTRATION SET status = 'Cancelled', cancelled_date = NOW() WHERE user_id = ? AND event_id = ? AND status = 'Registered'";
    $cancel_stmt = $conn->prepare($cancel_sql);
    $cancel_stmt->bind_param("si", $user_id, $event_id);
    
    if ($cancel_stmt->execute() && $cancel_stmt->affected_rows > 0) {
        $message = "<div class='alert alert-success'>✅ Registration cancelled successfully!</div>";
    } else {
        $message = "<div class='alert alert-error'>❌ Error cancelling registration.</div>";
    }
}

// Fetch available events (upcoming)
$available_events_sql = "
    SELECT e.*, 
           (SELECT COUNT(*) FROM EVENT_REGISTRATION er WHERE er.event_id = e.event_id AND er.status != 'Cancelled') as registered_count,
           CASE WHEN er2.user_id IS NOT NULL THEN 1 ELSE 0 END as is_registered
    FROM EVENT e
    LEFT JOIN EVENT_REGISTRATION er2 ON e.event_id = er2.event_id AND er2.user_id = ? AND er2.status != 'Cancelled'
    WHERE e.date >= CURDATE()
    ORDER BY e.date ASC
";
$stmt = $conn->prepare($available_events_sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$available_events = $stmt->get_result();

// Fetch user's registration history
// FIXED: Using max_cap instead of max_participants here too!
$history_sql = "
    SELECT er.*, e.event_name, e.date, e.time, e.venue, e.max_cap, c.club_name,
           DATEDIFF(e.date, CURDATE()) as days_until
    FROM EVENT_REGISTRATION er
    JOIN EVENT e ON er.event_id = e.event_id
    JOIN CLUB c ON e.club_id = c.club_id
    WHERE er.user_id = ?
    ORDER BY e.date DESC
";
$stmt = $conn->prepare($history_sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$registration_history = $stmt->get_result();

$registered_count = $registration_history->num_rows;
$available_count = $available_events->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registration</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; color: #333; }
        
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; max-width: calc(100% - 260px); width: calc(100% - 260px); }
        .welcome-card { background-color: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #38a169; }
        .welcome-card h2 { color: #1a202c; margin-bottom: 5px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 13px; color: #718096; text-transform: uppercase; }
        .stat-card .number { font-size: 2.5rem; font-weight: 700; color: #2d3748; margin-top: 10px; }

        .section-title { font-size: 22px; color: #1a202c; margin-bottom: 20px; border-bottom: 2px solid #cbd5e0; padding-bottom: 10px; margin-top: 30px; }
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; margin-bottom: 50px; }
        
        .item-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: 0.3s; }
        .item-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
        .item-image { width: 100%; height: 140px; object-fit: cover; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; font-size: 48px; }
        .item-content { padding: 20px; }
        .item-title { font-size: 18px; font-weight: bold; color: #2d3748; margin-bottom: 10px; }
        .item-detail { font-size: 14px; color: #718096; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .capacity-bar { margin: 12px 0; }
        .capacity-bar .bar { background: #e2e8f0; border-radius: 10px; height: 8px; overflow: hidden; }
        .capacity-bar .fill { background: #38a169; height: 100%; border-radius: 10px; }
        .capacity-text { font-size: 12px; color: #64748b; margin-top: 5px; }
        
        .btn-action { display: block; width: 100%; text-align: center; padding: 10px; margin-top: 15px; background: #3182ce; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; }
        .btn-action:hover { background: #2b6cb0; }
        .btn-action:disabled, .btn-full { background: #a0aec0; cursor: not-allowed; }
        .btn-cancel { background: #e53e3e; }
        .btn-cancel:hover { background: #c53030; }
        
        /* Table Styles */
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .data-table th { background: #f8fafc; padding: 15px; text-align: left; font-weight: 600; color: #4a5568; border-bottom: 2px solid #e2e8f0; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #edf2f7; color: #2d3748; }
        .data-table tr:hover { background: #f8fafc; }
        
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-registered { background: #c6f6d5; color: #22543d; }
        .status-cancelled { background: #fed7d7; color: #822727; }
        .status-completed { background: #bee3f8; color: #2b6cb0; }
        
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background-color: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        
        .empty-state { text-align: center; padding: 40px; background: white; border-radius: 12px; color: #718096; }
        .btn-sm { padding: 6px 12px; font-size: 12px; margin-top: 0; display: inline-block; width: auto; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        
        <?php echo $message; ?>
        
        <div class="welcome-card">
            <h2>📅 Event Registration</h2>
            <p style="color: #718096;">Browse upcoming events, register to participate, and track your registration history.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card" style="border-bottom: 4px solid #3182ce;">
                <h3>Available Events</h3>
                <div class="number"><?php echo $available_count; ?></div>
            </div>
            <div class="stat-card" style="border-bottom: 4px solid #38a169;">
                <h3>My Registrations</h3>
                <div class="number"><?php echo $registered_count; ?></div>
            </div>
        </div>

        <h2 class="section-title">🎯 Available Events for Registration</h2>
        
        <div class="grid-container">
            <?php if ($available_events && $available_events->num_rows > 0): ?>
                <?php while($event = $available_events->fetch_assoc()): 
                    // FIXED: Using max_cap instead of max_participants
                    $max_cap = isset($event['max_cap']) && $event['max_cap'] ? $event['max_cap'] : 100;
                    $registered_count_event = isset($event['registered_count']) ? $event['registered_count'] : 0;
                    $percentage = ($registered_count_event / $max_cap) * 100;
                    $is_full = $registered_count_event >= $max_cap;
                    $is_registered = isset($event['is_registered']) ? $event['is_registered'] : 0;
                ?>
                    <div class="item-card">
                        <div class="item-image">🎉</div>
                        <div class="item-content">
                            <div class="item-title"><?php echo htmlspecialchars($event['event_name']); ?></div>
                            <div class="item-detail">📅 <?php echo date("d M Y", strtotime($event['date'])); ?></div>
                            <div class="item-detail">⏰ <?php echo date("h:i A", strtotime($event['time'])); ?></div>
                            <div class="item-detail">📍 <?php echo htmlspecialchars($event['venue']); ?></div>
                            
                            <div class="capacity-bar">
                                <div class="bar">
                                    <div class="fill" style="width: <?php echo min($percentage, 100); ?>%"></div>
                                </div>
                                <div class="capacity-text">
                                    👥 <strong><?php echo $registered_count_event; ?></strong> / <?php echo $max_cap; ?> registered
                                    <?php echo $is_full ? ' (FULL)' : ' (' . ($max_cap - $registered_count_event) . ' slots left)'; ?>
                                </div>
                            </div>
                            
                            <?php if ($is_registered): ?>
                                <button class="btn-action" disabled style="background: #38a169;">✅ Already Registered</button>
                            <?php elseif ($is_full): ?>
                                <button class="btn-action btn-full" disabled>❌ Event Full</button>
                            <?php else: ?>
                                <form method="POST" action="" onsubmit="return confirm('Are you sure you want to register for this event?')">
                                    <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                    <button type="submit" name="register_btn" class="btn-action">➕ Register for Event</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state" style="grid-column: 1/-1;">
                    <p>No upcoming events available for registration at the moment.</p>
                </div>
            <?php endif; ?>
        </div>

        <h2 class="section-title">📋 My Registration History</h2>
        
        <div class="table-container">
            <?php if ($registration_history && $registration_history->num_rows > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Club</th>
                            <th>Date</th>
                            <th>Venue</th>
                            <th>Registration Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($reg = $registration_history->fetch_assoc()): 
                            $can_cancel = ($reg['status'] == 'Registered' && $reg['days_until'] > 0);
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($reg['event_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($reg['club_name']); ?></td>
                                <td><?php echo date("d M Y", strtotime($reg['date'])); ?></td>
                                <td><?php echo htmlspecialchars($reg['venue']); ?></td>
                                <td><?php echo date("d M Y", strtotime($reg['registration_date'])); ?></td>
                                <td>
                                    <?php if ($reg['status'] == 'Registered'): ?>
                                        <span class="status-badge status-registered">✅ Registered</span>
                                    <?php elseif ($reg['status'] == 'Cancelled'): ?>
                                        <span class="status-badge status-cancelled">❌ Cancelled</span>
                                    <?php else: ?>
                                        <span class="status-badge status-completed">📌 Completed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($can_cancel): ?>
                                        <a href="?cancel=1&event_id=<?php echo $reg['event_id']; ?>" class="btn-action btn-cancel btn-sm" onclick="return confirm('Are you sure you want to cancel your registration for <?php echo addslashes($reg['event_name']); ?>?')">
                                            Cancel
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #a0aec0; font-size: 12px;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>You haven't registered for any events yet.</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>

</body>
</html>