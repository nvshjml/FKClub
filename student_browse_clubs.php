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
$message_type = "";

// Handle Leave/Unjoin Club Request (Approved Member leaves)
if (isset($_GET['leave']) && isset($_GET['membership_id'])) {
    $membership_id = $_GET['membership_id'];
    
    // Update status to 'Left' instead of deleting
    $update_sql = "UPDATE CLUB_MEMBERSHIP SET status = 'Left' WHERE membership_id = ? AND user_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("is", $membership_id, $user_id);
    
    if ($stmt->execute()) {
        $message = "You have successfully left the club!";
        $message_type = "success";
    } else {
        $message = "Error leaving club. Please try again.";
        $message_type = "error";
    }
}

// Handle Cancel Club Request (Pending application cancelled)
if (isset($_GET['cancel']) && isset($_GET['membership_id'])) {
    $membership_id = $_GET['membership_id'];
    
    // Update status to 'Cancelled' instead of deleting
    $update_sql = "UPDATE CLUB_MEMBERSHIP SET status = 'Cancelled' WHERE membership_id = ? AND user_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("is", $membership_id, $user_id);
    
    if ($stmt->execute()) {
        $message = "Your application has been cancelled.";
        $message_type = "success";
    } else {
        $message = "Error cancelling application. Please try again.";
        $message_type = "error";
    }
}

// Get user's current active clubs count (Approved only, not Left or Cancelled)
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM CLUB_MEMBERSHIP WHERE user_id = ? AND status = 'Approved'");
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
        cm.membership_id,
        cm.status AS membership_status
    FROM CLUB c
    LEFT JOIN COMMITTEE com ON c.club_id = com.club_id AND com.position = 'President'
    LEFT JOIN `USER` u ON com.user_id = u.user_id
    LEFT JOIN CLUB_MEMBERSHIP cm ON c.club_id = cm.club_id AND cm.user_id = ?
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; color: #333; }
        
        /* Sidebar */
        .sidebar { width: 260px; background-color: #1a202c; color: white; display: flex; flex-direction: column; padding: 30px 20px; position: fixed; height: 100vh; box-shadow: 4px 0 10px rgba(0,0,0,0.1); z-index: 1000; top: 0; left: 0;}
        .sidebar-header { text-align: center; margin-bottom: 35px; }
        .sidebar-logo { max-width: 85px; margin-bottom: 12px; display: inline-block; }
        .sidebar-brand { font-size: 20px; font-weight: 700; color: #ffffff; margin-bottom: 6px; }
        .sidebar-role { font-size: 11px; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 1.5px; background: rgba(255,255,255,0.1); padding: 4px 12px; border-radius: 20px; display: inline-block; }
        .nav-links { display: flex; flex-direction: column; gap: 15px; flex-grow: 1; }
        .nav-links a { text-decoration: none; color: #a0aec0; font-weight: 600; padding: 12px 15px; border-radius: 8px; transition: 0.3s; display: block; }
        .nav-links a:hover, .nav-links a.active { background-color: #2d3748; color: white; }
        .btn-logout { background-color: #e53e3e; color: white; text-align: center; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: bold; margin-top: auto; transition: 0.2s; }
        .btn-logout:hover { background-color: #c53030; }
        
        /* Main Content */
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; max-width: calc(100% - 260px); }
        
        .welcome-card { background-color: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #38a169; }
        .welcome-card h2 { color: #1a202c; margin-bottom: 5px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 13px; color: #718096; text-transform: uppercase; }
        .stat-card .number { font-size: 2.5rem; font-weight: 700; color: #2d3748; margin-top: 10px; }
        
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
        .item-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: transform 0.3s; }
        .item-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
        .item-image { width: 100%; height: 160px; object-fit: cover; }
        .item-content { padding: 20px; }
        .item-title { font-size: 18px; font-weight: bold; color: #2d3748; margin-bottom: 10px; }
        .item-detail { font-size: 14px; color: #718096; margin-bottom: 5px; display: flex; align-items: center; gap: 8px; }
        
        .btn-action { display: block; width: 100%; text-align: center; padding: 10px; margin-top: 15px; background: #3182ce; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; transition: 0.2s; }
        .btn-action:hover { background: #2b6cb0; }
        .btn-pending { background: #ecc94b; color: #744210; cursor: default; }
        .btn-pending:hover { background: #ecc94b; }
        .btn-member { background: #38a169; cursor: default; }
        .btn-member:hover { background: #38a169; }
        .btn-leave { background: #e53e3e; }
        .btn-leave:hover { background: #c53030; }
        .btn-cancel { background: #e53e3e; }
        .btn-cancel:hover { background: #c53030; }
        .btn-left { background: #a0aec0; color: #4a5568; cursor: default; }
        .btn-left:hover { background: #a0aec0; }
        .btn-cancelled { background: #a0aec0; color: #4a5568; cursor: default; }
        .btn-cancelled:hover { background: #a0aec0; }
        
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background-color: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .main-content { margin-left: 200px; width: calc(100% - 200px); }
            .grid-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">
            <img src="image/LogoUMP5.png" alt="UMPSA Logo" class="sidebar-logo">
            <div class="sidebar-brand">FK Club System</div>
            <div class="sidebar-role"><?php echo htmlspecialchars($_SESSION['role']); ?> Dashboard</div>
        </div>
        <div class="nav-links">
            <a href="student_dashboard.php">Dashboard</a>
            <a href="student_profile.php">My Profile</a>
            <a href="student_browse_clubs.php" class="active">Browse Clubs</a>
            <a href="student_event_registration.php">Event Registration</a>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>

    <div class="main-content">
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="welcome-card">
            <h2>🏛️ Browse & Join Clubs</h2>
            <p style="color: #718096;">Join up to <?php echo $max_clubs; ?> clubs. You can leave clubs you've joined or cancel pending applications.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Clubs Available</h3>
                <div class="number"><?php echo $clubs_result->num_rows; ?></div>
            </div>
            <div class="stat-card">
                <h3>Clubs You Joined</h3>
                <div class="number"><?php echo $user_clubs_count; ?> / <?php echo $max_clubs; ?></div>
            </div>
        </div>

        <div class="grid-container">
            <?php while($club = $clubs_result->fetch_assoc()): ?>
                <div class="item-card">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($club['club_name']); ?>&background=random&size=300&bold=true" class="item-image" alt="<?php echo htmlspecialchars($club['club_name']); ?>">
                    <div class="item-content">
                        <div class="item-title"><?php echo htmlspecialchars($club['club_name']); ?></div>
                        <div class="item-detail">
                            <i class="fas fa-crown"></i>
                            <strong>President:</strong> <?php echo htmlspecialchars($club['president_name'] ?? 'TBD'); ?>
                        </div>
                        <?php if (!empty($club['advisor_name'])): ?>
                            <div class="item-detail">
                                <i class="fas fa-chalkboard-user"></i>
                                <strong>Advisor:</strong> <?php echo htmlspecialchars($club['advisor_name']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($club['description'])): ?>
                            <div class="item-detail">
                                <i class="fas fa-align-left"></i>
                                <?php echo htmlspecialchars(substr($club['description'], 0, 100)) . (strlen($club['description']) > 100 ? '...' : ''); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php 
                        // Handle different membership statuses
                        if ($club['membership_status'] == 'Approved'): 
                        ?>
                            <div style="display: flex; gap: 10px;">
                                <button class="btn-action btn-member" disabled style="flex: 1;">
                                    <i class="fas fa-check-circle"></i> Member
                                </button>
                                <a href="?leave=1&membership_id=<?php echo $club['membership_id']; ?>" class="btn-action btn-leave" style="flex: 1;" onclick="return confirm('Are you sure you want to leave this club? This action cannot be undone.')">
                                    <i class="fas fa-sign-out-alt"></i> Leave
                                </a>
                            </div>
                            
                        <?php elseif ($club['membership_status'] == 'Pending'): ?>
                            <div style="display: flex; gap: 10px;">
                                <button class="btn-action btn-pending" disabled style="flex: 1;">
                                    <i class="fas fa-hourglass-half"></i> Pending Approval
                                </button>
                                <a href="?cancel=1&membership_id=<?php echo $club['membership_id']; ?>" class="btn-action btn-cancel" style="flex: 1;" onclick="return confirm('Cancel your application for this club?')">
                                    <i class="fas fa-times-circle"></i> Cancel
                                </a>
                            </div>
                            
                        <?php elseif ($club['membership_status'] == 'Left'): ?>
                            <button class="btn-action btn-left" disabled>
                                <i class="fas fa-sign-out-alt"></i> Left Club
                            </button>
                            
                        <?php elseif ($club['membership_status'] == 'Cancelled'): ?>
                            <button class="btn-action btn-cancelled" disabled>
                                <i class="fas fa-ban"></i> Application Cancelled
                            </button>
                            
                        <?php elseif ($user_clubs_count >= $max_clubs): ?>
                            <button class="btn-action" disabled>
                                <i class="fas fa-lock"></i> Limit Reached (Max <?php echo $max_clubs; ?>)
                            </button>
                            
                        <?php else: ?>
                            <a href="apply_club.php?club_id=<?php echo $club['club_id']; ?>" class="btn-action">
                                <i class="fas fa-hand-peace"></i> Apply to Join
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>