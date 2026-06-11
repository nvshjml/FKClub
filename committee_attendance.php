<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

// SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Committee') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$current_page = basename($_SERVER['PHP_SELF']);

// ============================================================
// FIXED: Use STRING binding ("s") because club_id is VARCHAR
// ============================================================

// 1. Get the Club ID for this Committee member
$stmt = $conn->prepare("SELECT c.club_id, c.club_name FROM `committee` com JOIN `club` c ON com.club_id = c.club_id WHERE com.user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$club = $stmt->get_result()->fetch_assoc();
$club_id = $club ? $club['club_id'] : null;
$club_name = $club ? $club['club_name'] : 'No Club Assigned';

// 2. Fetch all events for this club (using STRING binding "s")
$events = [];
if ($club_id) {
    $event_query = $conn->prepare("SELECT event_id, event_name, date FROM `event` WHERE club_id = ? ORDER BY date DESC");
    $event_query->bind_param("s", $club_id);  // ← Changed from "i" to "s"
    $event_query->execute();
    $events = $event_query->get_result();
}

// 3. Handle Attendance Submission (The Point Engine)
if (isset($_POST['mark_attendance'])) {
    $register_id = intval($_POST['register_id']);
    $student_id = $_POST['student_id'];
    $status = $_POST['status']; 
    
    // Check if attendance is already recorded for this registration
    $check = $conn->prepare("SELECT * FROM ATTENDANCE WHERE register_id = ?");
    $check->bind_param("i", $register_id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $message = "<div class='alert alert-error'>⚠️ Attendance already recorded for this student!</div>";
    } else {
        // Determine Points based on Module 4 Requirement 1d
        $points = 0;
        if ($status == 'Present') $points = 10;
        elseif ($status == 'Late') $points = 5;
        elseif ($status == 'Volunteer') $points = 15; // 10 for present + 5 bonus
        elseif ($status == 'Absent') $points = -10;   // Penalty
        
        // Insert into ATTENDANCE table
        $ins = $conn->prepare("INSERT INTO ATTENDANCE (register_id, start_time, attend_status, point_awarded) VALUES (?, NOW(), ?, ?)");
        $ins->bind_param("isi", $register_id, $status, $points);
        
        if ($ins->execute()) {
            // Update Student's Total Points in USER table
            $upd = $conn->prepare("UPDATE `USER` SET total_point = GREATEST(0, total_point + ?) WHERE user_id = ?");
            $upd->bind_param("is", $points, $student_id);
            $upd->execute();
            
            $point_text = ($points > 0) ? "+$points" : "$points";
            $message = "<div class='alert alert-success'>✅ Attendance marked as <strong>$status</strong>. Points awarded: $point_text</div>";
        } else {
            $message = "<div class='alert alert-error'>❌ Error recording attendance: " . $conn->error . "</div>";
        }
    }
}

// 4. Fetch registered students if an event is selected
$selected_event_id = isset($_GET['event_id']) ? $_GET['event_id'] : null;  // Keep as string
$students = null;

if ($selected_event_id) {
    $student_sql = "
        SELECT er.register_id, u.user_id, u.name, er.register_type, a.attend_status, a.point_awarded
        FROM EVENT_REGISTRATION er
        JOIN `USER` u ON er.user_id = u.user_id
        LEFT JOIN ATTENDANCE a ON er.register_id = a.register_id
        WHERE er.event_id = ? AND er.status = 'Registered'
        ORDER BY u.name ASC
    ";
    $student_stmt = $conn->prepare($student_sql);
    $student_stmt->bind_param("s", $selected_event_id);  // ← Changed from "i" to "s"
    $student_stmt->execute();
    $students = $student_stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Attendance - Committee</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; }
        
        .main-content { margin-left: 260px; padding: 40px; width: calc(100% - 260px); }
        .welcome-card { background: white; padding: 25px 30px; border-radius: 12px; margin-bottom: 30px; border-left: 6px solid #3182ce; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .welcome-card h2 { color: #1a202c; margin-bottom: 5px; }
        
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .card h3 { margin-bottom: 15px; color: #2d3748; }
        
        select { width: 100%; max-width: 400px; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; cursor: pointer; font-family: 'Inter', sans-serif; }
        select:focus { border-color: #3182ce; outline: none; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f8fafc; padding: 15px; text-align: left; font-weight: 600; color: #4a5568; border-bottom: 2px solid #e2e8f0; }
        td { padding: 15px; border-bottom: 1px solid #edf2f7; vertical-align: middle; color: #2d3748; }
        
        .btn-attendance { padding: 8px 12px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 12px; margin-right: 4px; transition: 0.2s; }
        .btn-present { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .btn-present:hover { background: #9ae6b4; }
        .btn-late { background: #fefcbf; color: #744210; border: 1px solid #fbd38d; }
        .btn-late:hover { background: #fbd38d; }
        .btn-volunteer { background: #ebf8ff; color: #2b6cb0; border: 1px solid #90cdf4; }
        .btn-volunteer:hover { background: #90cdf4; }
        .btn-absent { background: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        .btn-absent:hover { background: #feb2b2; }
        
        .status-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background-color: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        .alert-info { background-color: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; }
        
        .club-badge { background: #3182ce; color: white; padding: 4px 12px; border-radius: 20px; display: inline-block; font-size: 13px; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <?php echo $message; ?>
        
        <div class="welcome-card">
            <h2>📝 Record Event Attendance</h2>
            <p style="color: #718096; margin-top: 5px;">Select an event from the dropdown below to view registered students and mark their attendance. Points will be automatically calculated!</p>
            <p style="margin-top: 10px;"><span class="club-badge">🏆 Your Club: <?php echo htmlspecialchars($club_name); ?></span></p>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 15px; color: #2d3748;">1. Select an Event</h3>
            <form method="GET" action="committee_attendance.php">
                <select name="event_id" onchange="this.form.submit()">
                    <option value="">-- Choose an upcoming or past event --</option>
                    <?php if ($events && $events->num_rows > 0): ?>
                        <?php while($e = $events->fetch_assoc()): ?>
                            <option value="<?php echo $e['event_id']; ?>" <?php echo ($selected_event_id == $e['event_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($e['event_name']); ?> (<?php echo date("d M Y", strtotime($e['date'])); ?>)
                            </option>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <option value="" disabled>-- No events found for your club --</option>
                    <?php endif; ?>
                </select>
            </form>
            
            <?php if ($events && $events->num_rows == 0 && $club_id): ?>
                <div class="alert alert-info" style="margin-top: 15px;">
                    ℹ️ No events found for <strong><?php echo htmlspecialchars($club_name); ?></strong>. 
                    Please go to <strong>Manage Events</strong> to create an event first.
                </div>
            <?php endif; ?>
            
            <?php if ($club_id && $events && $events->num_rows > 0): ?>
                <div class="alert alert-success" style="margin-top: 15px; padding: 10px;">
                    ✅ <strong><?php echo $events->num_rows; ?> event(s)</strong> found for your club. Select one from the dropdown above.
                </div>
            <?php endif; ?>
        </div>

        <?php if ($selected_event_id): ?>
            <div class="card">
                <h3 style="margin-bottom: 15px; color: #2d3748;">2. Registered Students</h3>
                
                <?php if ($students && $students->num_rows > 0): ?>
                    <div style="margin-bottom: 15px; padding: 10px; background: #f8fafc; border-radius: 8px;">
                        📊 Total registered: <strong><?php echo $students->num_rows; ?> students</strong>
                    </div>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Matrix ID</th>
                                    <th>Student Name</th>
                                    <th>Role</th>
                                    <th>Actions / Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($student = $students->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($student['user_id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><span style="background: #e2e8f0; padding: 4px 8px; border-radius: 6px; font-size: 12px; color: #4a5568; font-weight: bold;"><?php echo htmlspecialchars($student['register_type']); ?></span></td>
                                        
                                        <td>
                                            <?php if ($student['attend_status'] == null): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="register_id" value="<?php echo $student['register_id']; ?>">
                                                    <input type="hidden" name="student_id" value="<?php echo $student['user_id']; ?>">
                                                    
                                                    <button type="submit" name="status" value="Present" class="btn-attendance btn-present">Present (+10)</button>
                                                    <button type="submit" name="status" value="Late" class="btn-attendance btn-late">Late (+5)</button>
                                                    <button type="submit" name="status" value="Volunteer" class="btn-attendance btn-volunteer">Volunteer (+15)</button>
                                                    <button type="submit" name="status" value="Absent" class="btn-attendance btn-absent">Absent (-10)</button>
                                                    <input type="hidden" name="mark_attendance" value="1">
                                                </form>
                                            <?php else: ?>
                                                <?php 
                                                    if ($student['attend_status'] == 'Present') {
                                                        echo "<span class='status-badge btn-present'>✅ Present (+{$student['point_awarded']} pts)</span>";
                                                    } elseif ($student['attend_status'] == 'Late') {
                                                        echo "<span class='status-badge btn-late'>⚠️ Late (+{$student['point_awarded']} pts)</span>";
                                                    } elseif ($student['attend_status'] == 'Volunteer') {
                                                        echo "<span class='status-badge btn-volunteer'>🤝 Volunteer (+{$student['point_awarded']} pts)</span>";
                                                    } else {
                                                        echo "<span class='status-badge btn-absent'>❌ Absent ({$student['point_awarded']} pts)</span>";
                                                    }
                                                ?>
                                            <?php endif; ?>
                                         </span>
                                        </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: #718096; font-style: italic; padding: 20px 0; background: #f8fafc; text-align: center; border-radius: 8px;">No students are registered for this event yet.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>