<?php
session_start();
require 'db_connect.php';

$message = "";
$event_id = isset($_GET['event_id']) ? $_GET['event_id'] : null;
$token = isset($_GET['token']) ? $_GET['token'] : null;

// If student is not logged in, store redirect and show login page
if (!isset($_SESSION['user_id'])) {
    $_SESSION['qr_redirect_event'] = $event_id;
    $_SESSION['qr_redirect_token'] = $token;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Login Required</title>
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
            .login-card {
                background: white;
                border-radius: 24px;
                padding: 40px;
                text-align: center;
                max-width: 400px;
                width: 100%;
                box-shadow: 0 20px 35px rgba(0,0,0,0.2);
            }
            .btn-login {
                display: inline-block;
                background: #3182ce;
                color: white;
                padding: 12px 30px;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 600;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="login-card">
            <h2>🔐 Login Required</h2>
            <p style="margin-top: 15px;">Please login to your student account to check in.</p>
            <a href="index.php" class="btn-login">Go to Login</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$user_id = $_SESSION['user_id'];

// Verify event exists and QR token matches
$event_check = $conn->prepare("
    SELECT e.*, c.club_name 
    FROM event e 
    JOIN club c ON e.club_id = c.club_id 
    WHERE e.event_id = ? AND e.qr_token = ?
");
$event_check->bind_param("ss", $event_id, $token);
$event_check->execute();
$event = $event_check->get_result()->fetch_assoc();

if (!$event) {
    die("Invalid QR Code. Please contact the event organizer.");
}

// Check if student is registered for this event
$check_reg = $conn->prepare("
    SELECT er.*, a.attend_status 
    FROM event_registration er
    LEFT JOIN attendance a ON er.register_id = a.register_id
    WHERE er.event_id = ? AND er.user_id = ? AND er.status = 'Registered'
");
$check_reg->bind_param("ss", $event_id, $user_id);
$check_reg->execute();
$registration = $check_reg->get_result()->fetch_assoc();

if (!$registration) {
    die("You are not registered for this event. Please register first.");
}

if ($registration['attend_status']) {
    die("Your attendance has already been recorded for this event.");
}

// Determine if student is on time or late
$event_time = strtotime($event['date'] . ' ' . $event['time']);
$current_time = time();
$minutes_diff = round(($current_time - $event_time) / 60);

if ($current_time <= $event_time) {
    $status = 'Present';
    $points = 10;
} elseif ($minutes_diff <= 15) {
    $status = 'Late';
    $points = 5;
} else {
    $status = 'Absent';
    $points = -10;
}

// Record attendance
$record = $conn->prepare("
    INSERT INTO attendance (register_id, start_time, attend_status, point_awarded) 
    VALUES (?, NOW(), ?, ?)
");
$record->bind_param("isi", $registration['register_id'], $status, $points);

if ($record->execute()) {
    // Update student's total points
    $update = $conn->prepare("UPDATE `user` SET total_point = total_point + ? WHERE user_id = ?");
    $update->bind_param("is", $points, $user_id);
    $update->execute();
    
    $message = "
        <div class='success-card'>
            <div class='checkmark'>✅</div>
            <h2>Attendance Recorded!</h2>
            <p><strong>Event:</strong> " . htmlspecialchars($event['event_name']) . "</p>
            <p><strong>Status:</strong> $status</p>
            <p><strong>Points:</strong> " . ($points > 0 ? "+$points" : $points) . "</p>
            <p><strong>Time:</strong> " . date("h:i A") . "</p>
            <a href='student_dashboard.php' class='btn-dashboard'>Go to Dashboard</a>
        </div>
    ";
} else {
    $message = "<div class='error-card'>❌ Error recording attendance. Please try again.</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Check-in</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
        .success-card {
            background: white;
            border-radius: 24px;
            padding: 40px;
            text-align: center;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 20px 35px rgba(0,0,0,0.2);
        }
        .checkmark {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .success-card h2 {
            color: #22543d;
            margin-bottom: 20px;
        }
        .success-card p {
            color: #4a5568;
            margin: 10px 0;
        }
        .error-card {
            background: white;
            border-radius: 24px;
            padding: 40px;
            text-align: center;
            max-width: 450px;
            color: #e53e3e;
        }
        .btn-dashboard {
            display: inline-block;
            margin-top: 25px;
            padding: 12px 24px;
            background: #3182ce;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }
        .btn-dashboard:hover {
            background: #2b6cb0;
        }
    </style>
</head>
<body>
    <?php echo $message; ?>
</body>
</html>