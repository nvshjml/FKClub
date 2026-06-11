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
$max_clubs = 3;

// --- 1. HANDLE JOIN CLUB REQUEST ---
if (isset($_GET['join']) && isset($_GET['club_id'])) {
    $club_id = $_GET['club_id'];
    
    // Check how many active/pending clubs the user currently has
    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM club_membership WHERE user_id = ? AND status IN ('Approved', 'Pending')");
    $count_stmt->bind_param("s", $user_id);
    $count_stmt->execute();
    $current_count = $count_stmt->get_result()->fetch_assoc()['total'];
    
    if ($current_count < $max_clubs) {
        // Check if a record already exists (e.g., they left previously)
        $check = $conn->prepare("SELECT status FROM club_membership WHERE user_id = ? AND club_id = ?");
        $check->bind_param("ss", $user_id, $club_id);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            // Update existing record back to Pending
            $stmt = $conn->prepare("UPDATE club_membership SET status = 'Pending', join_date = CURDATE() WHERE user_id = ? AND club_id = ?");
            $stmt->bind_param("ss", $user_id, $club_id);
            $stmt->execute();
        } else {
            // Insert brand new record
            $stmt = $conn->prepare("INSERT INTO club_membership (user_id, club_id, join_date, status) VALUES (?, ?, CURDATE(), 'Pending')");
            $stmt->bind_param("ss", $user_id, $club_id);
            $stmt->execute();
        }
        $message = "Application submitted! Please wait for admin approval.";
    } else {
        $message = "You have reached the maximum limit of $max_clubs clubs.";
    }
}

// --- 2. HANDLE LEAVE CLUB REQUEST ---
if (isset($_GET['leave']) && isset($_GET['club_id'])) {
    $club_id = $_GET['club_id'];
    $stmt = $conn->prepare("UPDATE club_membership SET status = 'Left' WHERE club_id = ? AND user_id = ?");
    $stmt->bind_param("ss", $club_id, $user_id);
    if ($stmt->execute()) { $message = "You have successfully left the club!"; }
}

// --- 3. HANDLE CANCEL CLUB REQUEST ---
if (isset($_GET['cancel']) && isset($_GET['club_id'])) {
    $club_id = $_GET['club_id'];
    $stmt = $conn->prepare("UPDATE club_membership SET status = 'Cancelled' WHERE club_id = ? AND user_id = ?");
    $stmt->bind_param("ss", $club_id, $user_id);
    if ($stmt->execute()) { $message = "Your application has been cancelled."; }
}

// --- FETCH LIMITS & STATS ---
// 1. Stat count (Only approved clubs for the dashboard)
$stmt_stat = $conn->prepare("SELECT COUNT(*) AS total FROM club_membership WHERE user_id = ? AND status = 'Approved'");
$stmt_stat->bind_param("s", $user_id); 
$stmt_stat->execute();
$stat_clubs_count = $stmt_stat->get_result()->fetch_assoc()['total'];

// 2. Limit count (Approved + Pending clubs to prevent spamming applications)
$stmt_limit = $conn->prepare("SELECT COUNT(*) AS total FROM club_membership WHERE user_id = ? AND status IN ('Approved', 'Pending')");
$stmt_limit->bind_param("s", $user_id); 
$stmt_limit->execute();
$limit_clubs_count = $stmt_limit->get_result()->fetch_assoc()['total'];

// Fetch clubs with subqueries for membership status and president name
$clubs_sql = "
    SELECT 
        c.club_id, 
        c.club_name, 
        c.description, 
        c.advisor_name,
        (SELECT status FROM club_membership cm WHERE cm.club_id = c.club_id AND cm.user_id = ?) as membership_status,
        (SELECT u.name FROM committee com JOIN user u ON com.user_id = u.user_id WHERE com.club_id = c.club_id AND com.position = 'President' LIMIT 1) as president_name
    FROM club c
    WHERE c.isActive = 1
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
    <title>Browse Clubs</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; color: #333; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; width: calc(100% - 260px); }
        .welcome-card { background-color: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #38a169; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 13px; color: #718096; text-transform: uppercase; }
        .stat-card .number { font-size: 2.5rem; font-weight: 700; color: #2d3748; margin-top: 10px; }
        
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
        .item-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: 0.3s; display: flex; flex-direction: column;}
        .item-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
        
        .item-image { width: 100%; height: 180px; object-fit: cover; background: #edf2f7; display: block; }
        
        .item-content { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .item-title { font-size: 18px; font-weight: bold; color: #2d3748; margin-bottom: 10px; }
        .item-detail { font-size: 14px; color: #718096; margin-bottom: 5px; }
        
        .btn-action { display: block; width: 100%; text-align: center; padding: 10px; margin-top: auto; background: #3182ce; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; transition: 0.2s; }
        .btn-action:hover { background: #2b6cb0; }
        .btn-pending { background: #ecc94b; color: #744210; cursor: default; }
        .btn-member { background: #38a169; cursor: default; }
        .btn-leave { background: #e53e3e; }
        .btn-cancel { background: #e53e3e; }
        .btn-left, .btn-cancelled { background: #a0aec0; color: #4a5568; cursor: default; }
        
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; text-align: center;}
        .alert-success { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="welcome-card">
            <h2>🏛️ Browse & Join Clubs</h2>
            <p style="color: #718096;">Join up to <?php echo $max_clubs; ?> clubs. You can leave clubs you've joined or cancel pending applications.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card" style="border-bottom: 4px solid #3182ce;">
                <h3>Total Clubs Available</h3>
                <div class="number"><?php echo $clubs_result->num_rows; ?></div>
            </div>
            <div class="stat-card" style="border-bottom: 4px solid #38a169;">
                <h3>Clubs You Joined</h3>
                <div class="number"><?php echo $stat_clubs_count; ?> / <?php echo $max_clubs; ?></div>
            </div>
        </div>

        <div class="grid-container">
            <?php while($club = $clubs_result->fetch_assoc()): ?>
                <div class="item-card">
                    <?php 
                    $club_name_lower = strtolower($club['club_name']);
                    if (stripos($club_name_lower, 'ai') !== false || stripos($club_name_lower, 'robotics') !== false) {
                        echo '<img src="image/roboticclub.jpg" class="item-image" alt="Club Logo">';
                    } elseif (stripos($club_name_lower, 'programming') !== false) {
                        echo '<img src="image/programmingclub.png" class="item-image" alt="Club Logo">';
                    } elseif (stripos($club_name_lower, 'data') !== false || stripos($club_name_lower, 'analytics') !== false) {
                        echo '<img src="image/datascienceclub.jpg" class="item-image" alt="Club Logo">';
                    } elseif (stripos($club_name_lower, 'mobile') !== false) {
                        echo '<img src="image/mobileappclub.png" class="item-image" alt="Club Logo">';
                    } elseif (stripos($club_name_lower, 'web') !== false) {
                        echo '<img src="image/webdevclub.jpg" class="item-image" alt="Club Logo">';
                    } elseif (stripos($club_name_lower, 'cyber') !== false || stripos($club_name_lower, 'security') !== false) {
                        echo '<img src="image/cybersecurityclub.jpg" class="item-image" alt="Club Logo">';
                    } else {
                        echo '<img src="https://ui-avatars.com/api/?name='.urlencode($club['club_name']).'&background=random&size=300&bold=true" class="item-image" alt="Club Logo">';
                    }
                    ?>
                    
                    <div class="item-content">
                        <div class="item-title"><?php echo htmlspecialchars($club['club_name']); ?></div>
                        <div class="item-detail">👑 <strong>President:</strong> <?php echo htmlspecialchars($club['president_name'] ?? 'TBD'); ?></div>
                        <?php if (!empty($club['advisor_name'])): ?>
                            <div class="item-detail">👨‍🏫 <strong>Advisor:</strong> <?php echo htmlspecialchars($club['advisor_name']); ?></div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 15px;">
                            
                            <a href="student_club_details.php?club_id=<?php echo $club['club_id']; ?>" class="btn-action" style="background: #edf2f7; color: #4a5568; margin-bottom: 10px; margin-top: 0;">ℹ️ View Club Details</a>

                        <?php if ($club['membership_status'] == 'Approved'): ?>
                            <div style="display: flex; gap: 10px;">
                                <button class="btn-action btn-member" disabled style="flex: 1;">✅ Member</button>
                                <a href="?leave=1&club_id=<?php echo $club['club_id']; ?>" class="btn-action btn-leave" style="flex: 1;" onclick="return confirm('Leave this club?')">Leave</a>
                            </div>
                        <?php elseif ($club['membership_status'] == 'Pending'): ?>
                            <div style="display: flex; gap: 10px;">
                                <button class="btn-action btn-pending" disabled style="flex: 1;">⏳ Pending</button>
                                <a href="?cancel=1&club_id=<?php echo $club['club_id']; ?>" class="btn-action btn-cancel" style="flex: 1;" onclick="return confirm('Cancel application?')">Cancel</a>
                            </div>
                        <?php elseif ($club['membership_status'] == 'Left'): ?>
                            <button class="btn-action btn-left" disabled>Left Club</button>
                        <?php elseif ($limit_clubs_count >= $max_clubs): ?>
                            <button class="btn-action" disabled>🔒 Limit Reached</button>
                        
                        <?php else: ?>
                            <a href="?join=1&club_id=<?php echo $club['club_id']; ?>" class="btn-action" style="background:#553c9a;">Apply to Join</a>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>