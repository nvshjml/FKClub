<?php
session_start();
require 'db_connect.php';

// Security check - only committee members can access this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Committee') {
    header("Location: index.php");
    exit();
}

$event_id = isset($_GET['event_id']) ? $_GET['event_id'] : null;

if (!$event_id) {
    die("Event ID required");
}

// Verify committee has access to this event
$user_id = $_SESSION['user_id'];
$verify = $conn->prepare("
    SELECT e.*, c.club_name 
    FROM event e 
    JOIN club c ON e.club_id = c.club_id 
    JOIN committee com ON c.club_id = com.club_id 
    WHERE e.event_id = ? AND com.user_id = ?
");
$verify->bind_param("ss", $event_id, $user_id);
$verify->execute();
$event = $verify->get_result()->fetch_assoc();

if (!$event) {
    die("You don't have permission to access this event");
}

// Generate QR code URL for student attendance
$qr_content = "http://" . $_SERVER['HTTP_HOST'] . "/FKClub/student_scan_attendance.php?event_id=" . $event_id . "&token=" . urlencode($event['qr_token']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QR Code - <?php echo htmlspecialchars($event['event_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .qr-container {
            background: white;
            border-radius: 24px;
            padding: 40px;
            text-align: center;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 35px rgba(0,0,0,0.2);
        }
        .qr-container h1 {
            color: #1a202c;
            margin-bottom: 10px;
            font-size: 24px;
        }
        .qr-container p {
            color: #718096;
            margin-bottom: 20px;
        }
        #qrcode {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
        #qrcode img {
            border: 10px solid white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .event-info {
            background: #f7fafc;
            padding: 15px;
            border-radius: 12px;
            margin-top: 20px;
        }
        .event-info p {
            margin: 5px 0;
            color: #2d3748;
        }
        .btn-print {
            background: #3182ce;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            width: 100%;
        }
        .btn-print:hover {
            background: #2b6cb0;
        }
        .btn-back {
            background: #e2e8f0;
            color: #4a5568;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            width: 100%;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-back:hover {
            background: #cbd5e0;
        }
        .instruction {
            background: #ebf8ff;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            color: #2b6cb0;
            margin-top: 15px;
        }
        @media print {
            body { background: white; padding: 0; }
            .btn-print, .btn-back, .instruction { display: none; }
            .qr-container { box-shadow: none; padding: 0; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
</head>
<body>
    <div class="qr-container">
        <h1>🎫 Event Check-in QR Code</h1>
        <p><?php echo htmlspecialchars($event['event_name']); ?></p>
        
        <div id="qrcode"></div>
        
        <div class="event-info">
            <p><strong>📅 Date:</strong> <?php echo date("d M Y", strtotime($event['date'])); ?></p>
            <p><strong>⏰ Time:</strong> <?php echo date("h:i A", strtotime($event['time'])); ?></p>
            <p><strong>📍 Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?></p>
            <p><strong>🏢 Club:</strong> <?php echo htmlspecialchars($event['club_name']); ?></p>
        </div>
        
        <div class="instruction">
            📱 Students: Scan this QR code with your phone camera to check in.<br>
            ✅ Present on time: +10 points | ⏰ Late: +5 points
        </div>
        
        <button class="btn-print" onclick="window.print()">🖨️ Print QR Code</button>
        <a href="committee_events.php" class="btn-back">← Back to Events</a>
    </div>

    <script>
        new QRCode(document.getElementById("qrcode"), {
            text: "<?php echo $qr_content; ?>",
            width: 250,
            height: 250,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    </script>
</body>
</html>