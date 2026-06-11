<?php
session_start();
require 'db_connect.php';

// SECURITY CHECK - Ensure user is logged in and not an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'Admin') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['club_id'])) {
    header("Location: student_browse_clubs.php");
    exit();
}

// FIX: Do NOT use intval() here since your IDs are alphanumeric strings (e.g., 'CLB001')
$club_id = trim($_GET['club_id']);

// Fetch Club Details
$club_sql = "SELECT * FROM CLUB WHERE club_id = ? AND isActive = 1";
$stmt = $conn->prepare($club_sql);
$stmt->bind_param("s", $club_id); // Changed bind type from "i" to "s"
$stmt->execute();
$club = $stmt->get_result()->fetch_assoc();

if (!$club) {
    echo "<script>alert('Club not found or inactive.'); window.location.href='student_browse_clubs.php';</script>";
    exit();
}

// Fetch Committee Members
$committee_sql = "
    SELECT u.name, c.position 
    FROM committee c 
    JOIN `USER` u ON c.user_id = u.user_id 
    WHERE c.club_id = ? 
    ORDER BY FIELD(c.position, 'President', 'Vice President', 'Secretary', 'Treasurer', 'Committee Member')
";
$stmt = $conn->prepare($committee_sql);
$stmt->bind_param("s", $club_id); // Changed bind type from "i" to "s"
$stmt->execute();
$committee_members = $stmt->get_result();

// Fetch Upcoming Events
$upcoming_sql = "SELECT * FROM EVENT WHERE club_id = ? AND date >= CURDATE() ORDER BY date ASC";
$stmt = $conn->prepare($upcoming_sql);
$stmt->bind_param("s", $club_id); // Changed bind type from "i" to "s"
$stmt->execute();
$upcoming_events = $stmt->get_result();

// Fetch Past Events
$past_sql = "SELECT * FROM EVENT WHERE club_id = ? AND date < CURDATE() ORDER BY date DESC LIMIT 5";
$stmt = $conn->prepare($past_sql);
$stmt->bind_param("s", $club_id); // Changed bind type from "i" to "s"
$stmt->execute();
$past_events = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($club['club_name']); ?> - Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; color: #333; }
        
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; max-width: calc(100% - 260px); }
        
        .header-card { background: linear-gradient(135deg, #3182ce, #2b6cb0); color: white; padding: 40px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; position: relative;}
        .header-card h1 { font-size: 32px; margin-bottom: 10px; }
        .header-card p { opacity: 0.9; font-size: 16px; max-width: 800px; line-height: 1.6;}
        .btn-back { position: absolute; top: 20px; right: 30px; background: rgba(255,255,255,0.2); color: white; border: none; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 13px; transition: 0.2s; }
        .btn-back:hover { background: rgba(255,255,255,0.3); }

        .layout-grid { display: grid; grid-template-columns: 1fr 350px; gap: 30px; }
        
        .section-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .section-title { font-size: 18px; color: #1a202c; border-bottom: 2px solid #edf2f7; padding-bottom: 12px; margin-bottom: 20px; font-weight: 700; }
        
        .event-list { display: flex; flex-direction: column; gap: 15px; }
        .event-item { padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; border-left: 4px solid #3182ce; }
        .event-item.past { border-left-color: #a0aec0; background: #f8fafc; }
        .event-title { font-weight: 600; font-size: 16px; color: #2d3748; margin-bottom: 5px;}
        .event-meta { font-size: 13px; color: #718096; display: flex; gap: 15px; }

        .committee-list { list-style: none; }
        .committee-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #edf2f7; }
        .committee-item:last-child { border-bottom: none; }
        .c-name { font-weight: 600; color: #2d3748; font-size: 14px; }
        .c-role { font-size: 12px; background: #edf2f7; color: #4a5568; padding: 4px 10px; border-radius: 12px; font-weight: 600;}

        @media (max-width: 1024px) {
            .layout-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .main-content { margin-left: 200px; width: calc(100% - 200px); padding: 20px; }
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        
        <div class="header-card">
            <a href="student_dashboard.php" class="btn-back">← Back to Clubs</a>
            <h1><?php echo htmlspecialchars($club['club_name']); ?></h1>
            <p><?php echo nl2br(htmlspecialchars($club['description'] ?? 'No description available for this club yet.')); ?></p>
            <?php if (!empty($club['advisor_name'])): ?>
                <div style="margin-top: 15px; font-size: 14px; opacity: 0.9;">
                    <strong>Advisor:</strong> <?php echo htmlspecialchars($club['advisor_name']); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="layout-grid">
            <div class="left-col">
                <div class="section-card">
                    <div class="section-title">📅 Upcoming Events</div>
                    <div class="event-list">
                        <?php if ($upcoming_events && $upcoming_events->num_rows > 0): ?>
                            <?php while($event = $upcoming_events->fetch_assoc()): ?>
                                <div class="event-item">
                                    <div class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></div>
                                    <div class="event-meta">
                                        <span>🗓️ <?php echo date("d M Y", strtotime($event['date'])); ?></span>
                                        <span>⏰ <?php echo date("h:i A", strtotime($event['time'])); ?></span>
                                        <span>📍 <?php echo htmlspecialchars($event['venue']); ?></span>
                                    </div>
                                    <?php if (!empty($event['description'])): ?>
                                        <div style="margin-top: 8px; font-size: 13px; color: #4a5568;"><?php echo htmlspecialchars($event['description']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="color: #718096; font-size: 14px;">No upcoming events scheduled.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-title">🕰️ Past Events</div>
                    <div class="event-list">
                        <?php if ($past_events && $past_events->num_rows > 0): ?>
                            <?php while($event = $past_events->fetch_assoc()): ?>
                                <div class="event-item past">
                                    <div class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></div>
                                    <div class="event-meta">
                                        <span>🗓️ <?php echo date("d M Y", strtotime($event['date'])); ?></span>
                                        <span>📍 <?php echo htmlspecialchars($event['venue']); ?></span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="color: #718096; font-size: 14px;">No past events recorded.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="right-col">
                <div class="section-card">
                    <div class="section-title">👥 Club Committee</div>
                    <ul class="committee-list">
                        <?php if ($committee_members && $committee_members->num_rows > 0): ?>
                            <?php while($member = $committee_members->fetch_assoc()): ?>
                                <li class="committee-item">
                                    <span class="c-name"><?php echo htmlspecialchars($member['name']); ?></span>
                                    <span class="c-role"><?php echo htmlspecialchars($member['position']); ?></span>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="color: #718096; font-size: 14px;">No committee members assigned yet.</p>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

    </div>

</body>
</html>