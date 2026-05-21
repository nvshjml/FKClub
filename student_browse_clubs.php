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

// Get user's current clubs count (Count only approved or pending to prevent spamming applications)
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM CLUB_MEMBERSHIP WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user_clubs_count = $stmt->get_result()->fetch_assoc()['total'];
$max_clubs = 3;

// Fetch all available clubs with user's membership status
$clubs_sql = "
    SELECT 
        c.club_id, 
        c.club_name, 
        c.description, 
        c.advisor_name,
        c.isActive,
        u.name AS president_name,
        (SELECT status FROM CLUB_MEMBERSHIP WHERE club_id = c.club_id AND user_id = ? LIMIT 1) AS membership_status
    FROM CLUB c
    LEFT JOIN COMMITTEE com ON c.club_id = com.club_id AND com.position = 'President'
    LEFT JOIN `USER` u ON com.user_id = u.user_id
    WHERE c.isActive = 1
    ORDER BY c.club_name ASC
";
$stmt = $conn->prepare($clubs_sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$clubs_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Clubs</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; color: #333; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; max-width: 1200px; width: calc(100% - 260px); }
        .welcome-card { background-color: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #38a169; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .number { font-size: 2.5rem; font-weight: 700; color: #2d3748; margin-top: 10px; }
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; }
        .item-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .item-image { width: 100%; height: 180px; object-fit: cover; }
        .item-content { padding: 20px; }
        .btn-action { display: block; width: 100%; text-align: center; padding: 10px; margin-top: 15px; background: #3182ce; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; }
        .btn-pending { background: #ecc94b; color: #744210; cursor: default; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="welcome-card">
            <h2>🏛️ Browse & Join Clubs</h2>
            <p>Apply to join up to <?php echo $max_clubs; ?> clubs. Your application will be reviewed by the Admin.</p>
        </div>

        <div class="grid-container">
            <?php while($club = $clubs_result->fetch_assoc()): ?>
                <div class="item-card">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($club['club_name']); ?>&background=random&size=300" class="item-image">
                    <div class="item-content">
                        <h3><?php echo htmlspecialchars($club['club_name']); ?></h3>
                        <p style="font-size: 13px; color: #718096; margin: 10px 0;">👑 President: <?php echo htmlspecialchars($club['president_name'] ?? 'TBD'); ?></p>
                        
                        <?php if ($club['membership_status'] == 'Approved'): ?>
                            <button class="btn-action" disabled style="background:#38a169;">✅ Member</button>
                        <?php elseif ($club['membership_status'] == 'Pending'): ?>
                            <button class="btn-action btn-pending" disabled>⏳ Pending Approval</button>
                        <?php elseif ($user_clubs_count >= $max_clubs): ?>
                            <button class="btn-action" disabled>🔒 Limit Reached</button>
                        <?php else: ?>
                            <a href="apply_club.php?club_id=<?php echo $club['club_id']; ?>" class="btn-action" style="background:#553c9a;">Apply to Join</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>