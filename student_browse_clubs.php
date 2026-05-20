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

// Get user's current clubs count
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM CLUB_MEMBERSHIP WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user_clubs_count = $stmt->get_result()->fetch_assoc()['total'];
$max_clubs = 3;

// Handle Join Club Request
if (isset($_GET['join']) && isset($_GET['club_id'])) {
    $club_id = $_GET['club_id'];
    
    // Check if already a member
    $check_sql = "SELECT * FROM CLUB_MEMBERSHIP WHERE user_id = ? AND club_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $user_id, $club_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $message = "<div class='alert alert-error' style='background: #fed7d7; color: #822727; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #feb2b2;'>⚠️ You are already a member of this club!</div>";
    } elseif ($user_clubs_count >= $max_clubs) {
        $message = "<div class='alert alert-error' style='background: #fed7d7; color: #822727; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #feb2b2;'>⚠️ You have reached the maximum limit of {$max_clubs} clubs!</div>";
    } else {
        // Add to club membership
        $join_sql = "INSERT INTO CLUB_MEMBERSHIP (user_id, club_id, joined_date) VALUES (?, ?, NOW())";
        $join_stmt = $conn->prepare($join_sql);
        $join_stmt->bind_param("si", $user_id, $club_id);
        
        if ($join_stmt->execute()) {
            $message = "<div class='alert alert-success' style='background: #c6f6d5; color: #22543d; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #9ae6b4;'>✅ Successfully joined the club!</div>";
            // Refresh club count
            $stmt->execute();
            $user_clubs_count = $stmt->get_result()->fetch_assoc()['total'];
        } else {
            $message = "<div class='alert alert-error' style='background: #fed7d7; color: #822727; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #feb2b2;'>❌ Error joining club. Please try again.</div>";
        }
    }
}

// Handle Leave Club Request
if (isset($_GET['leave']) && isset($_GET['club_id'])) {
    $club_id = $_GET['club_id'];
    
    $leave_sql = "DELETE FROM CLUB_MEMBERSHIP WHERE user_id = ? AND club_id = ?";
    $leave_stmt = $conn->prepare($leave_sql);
    $leave_stmt->bind_param("si", $user_id, $club_id);
    
    if ($leave_stmt->execute()) {
        $message = "<div class='alert alert-success' style='background: #c6f6d5; color: #22543d; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #9ae6b4;'>✅ You have left the club.</div>";
        // Refresh club count
        $stmt->execute();
        $user_clubs_count = $stmt->get_result()->fetch_assoc()['total'];
    } else {
        $message = "<div class='alert alert-error' style='background: #fed7d7; color: #822727; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #feb2b2;'>❌ Error leaving club.</div>";
    }
}

// Fetch all available clubs (active only) with president name and user's membership status
$clubs_sql = "
    SELECT 
        c.club_id, 
        c.club_name, 
        c.description, 
        c.advisor_name,
        c.isActive,
        u.name AS president_name,
        CASE WHEN cm.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_member
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
    <title>Browse Clubs - Student Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; color: #333; }
        
        .sidebar { width: 260px; background-color: #1a202c; color: white; display: flex; flex-direction: column; padding: 30px 20px; position: fixed; height: 100vh; box-shadow: 4px 0 10px rgba(0,0,0,0.1); z-index: 1000; top: 0; left: 0;}
        .sidebar-header { text-align: center; margin-bottom: 35px; }
        .sidebar-logo { max-width: 85px; margin-bottom: 12px; display: inline-block; }
        .sidebar-brand { font-size: 20px; font-weight: 700; color: #ffffff; margin-bottom: 6px; }
        .sidebar-role { font-size: 11px; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 1.5px; background: rgba(255,255,255,0.1); padding: 4px 12px; border-radius: 20px; display: inline-block; }
        .nav-links { display: flex; flex-direction: column; gap: 15px; flex-grow: 1; }
        .nav-links a { text-decoration: none; color: #a0aec0; font-weight: 600; padding: 12px 15px; border-radius: 8px; transition: 0.3s; display: block; }
        .nav-links a:hover, .nav-links a.active { background-color: #2d3748; color: white; }
        .btn-logout { background-color: #e53e3e; color: white; text-align: center; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: bold; margin-top: auto; transition: 0.2s; }

        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; max-width: 1200px; }
        .welcome-card { background-color: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #38a169; }
        .welcome-card h2 { color: #1a202c; margin-bottom: 5px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 13px; color: #718096; text-transform: uppercase; }
        .stat-card .number { font-size: 2.5rem; font-weight: 700; color: #2d3748; margin-top: 10px; }

        .section-title { font-size: 22px; color: #1a202c; margin-bottom: 20px; border-bottom: 2px solid #cbd5e0; padding-bottom: 10px; }
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; margin-bottom: 50px; }
        
        .item-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: 0.3s; }
        .item-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
        .item-image { width: 100%; height: 180px; object-fit: cover; background: #edf2f7; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #fff; text-align: center; padding: 20px; }
        .item-content { padding: 20px; }
        .item-title { font-size: 18px; font-weight: bold; color: #2d3748; margin-bottom: 10px; }
        .item-detail { font-size: 14px; color: #718096; margin-bottom: 5px; }
        
        .btn-action { display: block; width: 100%; text-align: center; padding: 10px; margin-top: 15px; background: #3182ce; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; }
        .btn-action:hover { background: #2b6cb0; }
        .btn-action:disabled { background: #a0aec0; cursor: not-allowed; }
        
        .btn-leave { background: #e53e3e; }
        .btn-leave:hover { background: #c53030; }
        .btn-joined { background: #38a169; cursor: default; }
        .btn-joined:hover { background: #38a169; }
        
        /* Club image text overlay style */
        .club-image-text {
            background: linear-gradient(135deg, #1a202c, #2d3748);
            color: white;
            font-size: 24px;
            font-weight: 800;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        .club-image-text small {
            font-size: 14px;
            font-weight: normal;
            opacity: 0.8;
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
            <a href="student_point_recognition.php">Point Recognition</a>
            <a href="student_leaderboard.php">Leaderboard</a>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>

    <div class="main-content">
        
        <?php echo $message; ?>
        
        <div class="welcome-card">
            <h2>🏛️ Browse & Join Clubs</h2>
            <p style="color: #718096;">Discover student clubs that match your interests. Join up to <?php echo $max_clubs; ?> clubs and start your journey!</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card" style="border-bottom: 4px solid #3182ce;">
                <h3>Total Clubs Available</h3>
                <div class="number"><?php echo $clubs_result->num_rows; ?></div>
            </div>
            <div class="stat-card" style="border-bottom: 4px solid #38a169;">
                <h3>Clubs You Joined</h3>
                <div class="number"><?php echo $user_clubs_count; ?> / <?php echo $max_clubs; ?></div>
            </div>
        </div>

        <h2 class="section-title">🏆 Available Clubs</h2>
        
        <div class="grid-container">
            <?php if ($clubs_result && $clubs_result->num_rows > 0): ?>
                <?php while($club = $clubs_result->fetch_assoc()): ?>
                    <div class="item-card">
                        <!-- Club Image - Custom images for specific clubs -->
                        <?php 
                        // Check for specific club names and assign custom images
                        $club_name_lower = strtolower($club['club_name']);
                        
                        if (stripos($club['club_name'], 'AI') !== false || stripos($club['club_name'], 'Robotics') !== false):
                            // AI & Robotics Society
                            ?>
                            <img src="image/roboticclub.jpg" class="item-image" alt="AI & Robotics Society">
                        <?php elseif (stripos($club['club_name'], 'Competitive Programming') !== false || stripos($club['club_name'], 'Programming') !== false):
                            // Competitive Programming Club
                            ?>
                            <img src="image/programmingclub.png" class="item-image" alt="Competitive Programming Club">
                        <?php elseif (stripos($club['club_name'], 'Data Science') !== false || stripos($club['club_name'], 'Analytics') !== false):
                            // Data Science & Analytics Circle
                            ?>
                            <img src="image/datascienceclub.jpg" class="item-image" alt="Data Science & Analytics Circle">
                        <?php elseif (stripos($club['club_name'], 'Mobile App') !== false || stripos($club['club_name'], 'App Development') !== false):
                            // Mobile App Innovation Hub
                            ?>
                            <img src="image/mobileappclub.png" class="item-image" alt="Mobile App Innovation Hub">
                        <?php elseif (stripos($club['club_name'], 'Web Development') !== false || stripos($club['club_name'], 'Web Dev') !== false):
                            // Web Development Club
                            ?>
                            <img src="image/webdevclub.jpg" class="item-image" alt="Web Development Club">
                        <?php elseif (stripos($club['club_name'], 'Cybersecurity') !== false || stripos($club['club_name'], 'Security') !== false):
                            // Cybersecurity Club
                            ?>
                            <img src="image/cybersecurityclub.jpg" class="item-image" alt="Cybersecurity Club">
                        <?php else: ?>
                            <!-- Default avatar for other clubs -->
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($club['club_name']); ?>&background=random&size=300&bold=true" class="item-image" alt="Club Logo">
                        <?php endif; ?>
                        
                        <div class="item-content">
                            <div class="item-title"><?php echo htmlspecialchars($club['club_name']); ?></div>
                            <div class="item-detail">👑 <strong>President:</strong> <?php echo $club['president_name'] ? htmlspecialchars($club['president_name']) : 'TBD'; ?></div>
                            <?php if (!empty($club['advisor_name'])): ?>
                                <div class="item-detail">👨‍🏫 <strong>Advisor:</strong> <?php echo htmlspecialchars($club['advisor_name']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($club['description'])): ?>
                                <div class="item-detail">📝 <?php echo htmlspecialchars(substr($club['description'], 0, 80)) . (strlen($club['description']) > 80 ? '...' : ''); ?></div>
                            <?php endif; ?>
                            
                            <?php if ($club['is_member'] == 1): ?>
                                <a href="?leave=1&club_id=<?php echo $club['club_id']; ?>" class="btn-action btn-leave" onclick="return confirm('Are you sure you want to leave this club?')">
                                    ➖ Leave Club
                                </a>
                            <?php elseif ($user_clubs_count >= $max_clubs): ?>
                                <button class="btn-action" disabled>
                                    🔒 Limit Reached (Max <?php echo $max_clubs; ?>)
                                </button>
                            <?php else: ?>
                                <a href="?join=1&club_id=<?php echo $club['club_id']; ?>" class="btn-action">
                                    Join Club
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No clubs available right now.</p>
            <?php endif; ?>
        </div>
        
    </div>

</body>
</html>