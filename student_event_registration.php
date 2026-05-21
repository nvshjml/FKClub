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

// Handle Event Registration
if (isset($_POST['register_btn']) && isset($_POST['event_id'])) {
    $event_id = intval($_POST['event_id']);
    
    // Check if already registered
    $check_stmt = $conn->prepare("SELECT * FROM EVENT_REGISTRATION WHERE user_id = ? AND event_id = ? AND status != 'Cancelled'");
    $check_stmt->bind_param("si", $user_id, $event_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $message = "<div class='alert alert-error'>⚠️ You are already registered for this event!</div>";
    } else {
        // Check capacity using max_cap
        $capacity_stmt = $conn->prepare("SELECT e.*, (SELECT COUNT(*) FROM EVENT_REGISTRATION er WHERE er.event_id = e.event_id AND er.status != 'Cancelled') as registered_count FROM EVENT e WHERE e.event_id = ?");
        $capacity_stmt->bind_param("i", $event_id);
        $capacity_stmt->execute();
        $cap = $capacity_stmt->get_result()->fetch_assoc();
        
        $max_cap = isset($cap['max_cap']) ? $cap['max_cap'] : 100;
        $registered_count = $cap['registered_count'] ?: 0;
        
        if ($registered_count >= $max_cap) {
            $message = "<div class='alert alert-error'>❌ Sorry! This event is already full.</div>";
        } else {
            $reg_stmt = $conn->prepare("INSERT INTO EVENT_REGISTRATION (user_id, event_id, register_type, status, register_date) VALUES (?, ?, 'Participant', 'Registered', NOW())");
            $reg_stmt->bind_param("si", $user_id, $event_id);
            if ($reg_stmt->execute()) {
                $message = "<div class='alert alert-success'>✅ Successfully registered!</div>";
            } else {
                $message = "<div class='alert alert-error'>❌ Registration failed.</div>";
            }
        }
    }
}

// Handle Cancel
if (isset($_GET['cancel']) && isset($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);
    $cancel_stmt = $conn->prepare("UPDATE EVENT_REGISTRATION SET status = 'Cancelled' WHERE user_id = ? AND event_id = ? AND status = 'Registered'");
    $cancel_stmt->bind_param("si", $user_id, $event_id);
    if ($cancel_stmt->execute()) {
        $message = "<div class='alert alert-success'>✅ Registration cancelled!</div>";
    }
}

// Fetch events
$available_events = $conn->prepare("SELECT e.*, (SELECT COUNT(*) FROM EVENT_REGISTRATION er WHERE er.event_id = e.event_id AND er.status != 'Cancelled') as registered_count, CASE WHEN er2.user_id IS NOT NULL THEN 1 ELSE 0 END as is_registered FROM EVENT e LEFT JOIN EVENT_REGISTRATION er2 ON e.event_id = er2.event_id AND er2.user_id = ? AND er2.status != 'Cancelled' WHERE e.date >= CURDATE() ORDER BY e.date ASC");
$available_events->bind_param("s", $user_id);
$available_events->execute();
$events_res = $available_events->get_result();

$history_res = $conn->prepare("SELECT er.*, e.event_name, e.date, e.venue, c.club_name FROM EVENT_REGISTRATION er JOIN EVENT e ON er.event_id = e.event_id JOIN CLUB c ON e.club_id = c.club_id WHERE er.user_id = ? ORDER BY e.date DESC");
$history_res->bind_param("s", $user_id);
$history_res->execute();
$history_res = $history_res->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Registration</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; margin: 0; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .card h3 { color: #1a202c; margin-top: 0; margin-bottom: 15px; font-size: 18px; }
        .card p { color: #4a5568; font-size: 14px; margin-bottom: 8px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; text-align: center; }
        .alert-success { background: #c6f6d5; color: #22543d; }
        .alert-error { background: #fed7d7; color: #822727; }
        .btn { display: block; width: 100%; padding: 12px; background: #3182ce; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; text-align: center; margin-top: 15px; font-size: 14px; transition: 0.2s; }
        .btn:hover:not(:disabled) { background: #2b6cb0; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; text-align: center; width: 340px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .modal-content h3 { margin-top: 0; color: #2d3748; }
        .qr-wrapper { margin: 20px 0; padding: 10px; border: 2px dashed #cbd5e0; border-radius: 12px; display: inline-block; cursor: pointer; transition: 0.2s; }
        .qr-wrapper:hover { transform: scale(1.05); border-color: #3182ce; background: #ebf8ff; }
        .modal-content img { width: 200px; height: 200px; display: block; }
        .btn-cancel { background: #e2e8f0; color: #4a5568; }
        .btn-cancel:hover { background: #cbd5e0; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <?php echo $message; ?>
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 30px;">
            <h2 style="margin: 0; color: #1a202c;">📅 Event Registration</h2>
        </div>
        
        <div class="grid">
            <?php while($e = $events_res->fetch_assoc()): ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($e['event_name']); ?></h3>
                    <p>📅 <?php echo date("d M Y", strtotime($e['date'])); ?></p>
                    
                    <?php if ($e['is_registered']): ?>
                        <button class="btn" style="background:#38a169; cursor: default;" disabled>✅ Registered</button>
                    <?php else: ?>
                        <button type="button" class="btn" onclick="openQrModal(<?php echo $e['event_id']; ?>, '<?php echo addslashes(htmlspecialchars($e['event_name'])); ?>', '<?php echo htmlspecialchars($e['qr_token'] ?? 'default_qr'); ?>')">Register</button>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <div id="qrModal" class="modal">
        <div class="modal-content">
            <h3 id="modalEventTitle">Event Name</h3>
            <p style="font-size: 14px; color: #718096; margin-bottom: 5px;">Tap the QR code below to confirm your registration</p>
            
            <form id="qrRegForm" method="POST">
                <input type="hidden" name="event_id" id="modalEventId" value="">
                <input type="hidden" name="register_btn" value="1">
                
                <div class="qr-wrapper" onclick="submitRegistration()">
                    <img id="qrImage" src="" alt="QR Code">
                </div>
            </form>
            
            <button type="button" class="btn btn-cancel" onclick="closeQrModal()">Cancel</button>
        </div>
    </div>

    <script>
        function openQrModal(eventId, eventName, qrToken) {
            document.getElementById('modalEventTitle').innerText = eventName;
            document.getElementById('modalEventId').value = eventId;
            // Generate QR code on the fly
            document.getElementById('qrImage').src = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" + encodeURIComponent(qrToken);
            document.getElementById('qrModal').style.display = 'flex';
        }

        function closeQrModal() {
            document.getElementById('qrModal').style.display = 'none';
        }

        function submitRegistration() {
            // When the QR wrapper is tapped, submit the hidden form!
            document.getElementById('qrRegForm').submit();
        }

        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            var modal = document.getElementById('qrModal');
            if (event.target == modal) {
                closeQrModal();
            }
        }
    </script>
</body>
</html>