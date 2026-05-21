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

// Fetch current user's rank and points
$user_sql = "SELECT name, total_point, user_id FROM `USER` WHERE user_id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$current_user_points = $current_user ? $current_user['total_point'] : 0;
$current_user_name = $current_user ? $current_user['name'] : '';

// Fetch top 10 students by points (Students only, not Admin/Committee)
$leaderboard_sql = "
    SELECT user_id, name, total_point
    FROM `USER`
    WHERE role = 'Student'
    ORDER BY total_point DESC
    LIMIT 10
";
$leaderboard_result = $conn->query($leaderboard_sql);

// Fetch all students for ranking calculation
$all_students_sql = "
    SELECT user_id, total_point
    FROM `USER`
    WHERE role = 'Student'
    ORDER BY total_point DESC
";
$all_students = $conn->query($all_students_sql);

// Calculate current user's rank
$rank = 1;
$current_user_rank = null;
if ($all_students) {
    while ($student = $all_students->fetch_assoc()) {
        if ($student['user_id'] == $user_id) {
            $current_user_rank = $rank;
            break;
        }
        $rank++;
    }
}

// Get total number of students
$total_students_sql = "SELECT COUNT(*) as total FROM `USER` WHERE role = 'Student'";
$total_result = $conn->query($total_students_sql);
$total_students = $total_result->fetch_assoc()['total'];

// Get top 3 for podium display
$top3_sql = "
    SELECT user_id, name, total_point
    FROM `USER`
    WHERE role = 'Student'
    ORDER BY total_point DESC
    LIMIT 3
";
$top3_result = $conn->query($top3_sql);
$top3 = [];
while ($row = $top3_result->fetch_assoc()) {
    $top3[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - Student Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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

        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; max-width: calc(100% - 260px); }
        .welcome-card { background-color: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #38a169; }
        .welcome-card h2 { color: #1a202c; margin-bottom: 5px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 13px; color: #718096; text-transform: uppercase; }
        .stat-card .number { font-size: 2.5rem; font-weight: 700; color: #2d3748; margin-top: 10px; }

        .section-title { font-size: 22px; color: #1a202c; margin-bottom: 20px; border-bottom: 2px solid #cbd5e0; padding-bottom: 10px; margin-top: 30px; }
        
        /* Podium Styles */
        .podium-container {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 20px;
            margin-bottom: 50px;
            flex-wrap: wrap;
        }
        .podium-card {
            text-align: center;
            padding: 20px;
            border-radius: 16px;
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .podium-card:hover {
            transform: translateY(-5px);
        }
        .podium-card .rank-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        .podium-card .avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            font-weight: bold;
            color: white;
        }
        .podium-card .name {
            font-weight: 700;
            font-size: 16px;
            color: #2d3748;
            margin-bottom: 5px;
        }
        .podium-card .points {
            font-size: 20px;
            font-weight: 800;
            color: #38a169;
        }
        .podium-card.rank-1 .avatar {
            width: 100px;
            height: 100px;
            font-size: 40px;
            background: linear-gradient(135deg, #ffd700, #ffb347);
        }
        .podium-card.rank-1 {
            min-width: 180px;
        }
        .podium-card.rank-2 .avatar {
            background: linear-gradient(135deg, #c0c0c0, #a8a8a8);
        }
        .podium-card.rank-3 .avatar {
            background: linear-gradient(135deg, #cd7f32, #b8860b);
        }
        
        /* Leaderboard Table */
        .leaderboard-table-container {
            overflow-x: auto;
        }
        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .leaderboard-table th {
            background: #f8fafc;
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
        }
        .leaderboard-table td {
            padding: 14px 20px;
            border-bottom: 1px solid #edf2f7;
            color: #2d3748;
        }
        .leaderboard-table tr:last-child td {
            border-bottom: none;
        }
        .leaderboard-table tr:hover {
            background: #f8fafc;
        }
        .leaderboard-table .current-user {
            background: #ebf8ff;
            border-left: 4px solid #3182ce;
        }
        
        .rank-badge {
            display: inline-block;
            width: 32px;
            height: 32px;
            background: #e2e8f0;
            border-radius: 50%;
            text-align: center;
            line-height: 32px;
            font-weight: 700;
            font-size: 14px;
            color: #4a5568;
        }
        .rank-badge.top-1 { background: #ffd700; color: #744210; }
        .rank-badge.top-2 { background: #c0c0c0; color: #2d3748; }
        .rank-badge.top-3 { background: #cd7f32; color: white; }
        
        .points-display {
            font-weight: 700;
            color: #38a169;
            font-size: 18px;
        }
        
        .your-rank-card {
            background: linear-gradient(135deg, #3182ce, #2b6cb0);
            border-radius: 16px;
            padding: 25px;
            margin-top: 30px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .your-rank-card .rank-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .your-rank-card .rank-number {
            font-size: 48px;
            font-weight: 800;
        }
        .your-rank-card .rank-label {
            font-size: 14px;
            opacity: 0.8;
        }
        .your-rank-card .points-info {
            text-align: right;
        }
        .your-rank-card .points-value {
            font-size: 32px;
            font-weight: 800;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .main-content { margin-left: 200px; width: calc(100% - 200px); }
            .podium-container { gap: 10px; }
            .podium-card.rank-1 { min-width: 140px; }
            .podium-card .avatar { width: 60px; height: 60px; font-size: 24px; }
            .podium-card.rank-1 .avatar { width: 75px; height: 75px; font-size: 32px; }
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
            <a href="student_browse_clubs.php">Browse Clubs</a>
            <a href="student_event_registration.php">Event Registration</a>
            <a href="student_point_recognition.php">Point Recognition</a>
            <a href="student_leaderboard.php" class="active">Leaderboard</a>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>

    <div class="main-content">
        
        <?php echo $message; ?>
        
        <div class="welcome-card">
            <h2>🏆 Student Leaderboard</h2>
            <p style="color: #718096;">See how you rank among all students based on participation points.</p>
        </div>

        <!-- Stats Summary -->
        <div class="stats-grid">
            <div class="stat-card" style="border-bottom: 4px solid #3182ce;">
                <h3>Total Students</h3>
                <div class="number"><?php echo $total_students; ?></div>
            </div>
            <div class="stat-card" style="border-bottom: 4px solid #38a169;">
                <h3>Your Points</h3>
                <div class="number"><?php echo $current_user_points; ?></div>
            </div>
            <?php if ($current_user_rank): ?>
            <div class="stat-card" style="border-bottom: 4px solid #805ad5;">
                <h3>Your Rank</h3>
                <div class="number">#<?php echo $current_user_rank; ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Podium (Top 3) -->
        <?php if (count($top3) >= 1): ?>
        <h2 class="section-title">🥇 Top Performers</h2>
        <div class="podium-container">
            <?php 
            $positions = [
                1 => ['icon' => '🥇', 'class' => 'rank-1'],
                2 => ['icon' => '🥈', 'class' => 'rank-2'],
                3 => ['icon' => '🥉', 'class' => 'rank-3']
            ];
            foreach ($top3 as $index => $student):
                $rank_num = $index + 1;
                $avatar_text = strtoupper(substr($student['name'], 0, 1));
            ?>
                <div class="podium-card <?php echo $positions[$rank_num]['class']; ?>">
                    <div class="rank-icon"><?php echo $positions[$rank_num]['icon']; ?></div>
                    <div class="avatar"><?php echo $avatar_text; ?></div>
                    <div class="name"><?php echo htmlspecialchars($student['name']); ?></div>
                    <div class="points"><?php echo $student['total_point']; ?> pts</div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Full Leaderboard Table -->
        <h2 class="section-title">📊 Full Leaderboard</h2>
        
        <div class="leaderboard-table-container">
            <?php if ($leaderboard_result && $leaderboard_result->num_rows > 0): ?>
                <table class="leaderboard-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Rank</th>
                            <th>Student Name</th>
                            <th style="width: 120px; text-align: center;">Points</th>
                            <th style="width: 120px; text-align: center;">Badge</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank_counter = 1;
                        while($student = $leaderboard_result->fetch_assoc()): 
                            $is_current_user = ($student['user_id'] == $user_id);
                            $badge = '';
                            if ($rank_counter == 1) $badge = '👑 Champion';
                            elseif ($rank_counter == 2) $badge = '🥈 Runner Up';
                            elseif ($rank_counter == 3) $badge = '🥉 Bronze';
                            elseif ($student['total_point'] >= 80) $badge = '🏆 Outstanding';
                            elseif ($student['total_point'] >= 50) $badge = '⭐ Active';
                            elseif ($student['total_point'] >= 20) $badge = '📜 Participant';
                            else $badge = '⚠️ Beginner';
                        ?>
                            <tr class="<?php echo $is_current_user ? 'current-user' : ''; ?>">
                                <td>
                                    <span class="rank-badge <?php echo $rank_counter <= 3 ? 'top-' . $rank_counter : ''; ?>">
                                        <?php echo $rank_counter; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($student['name']); ?>
                                    <?php if ($is_current_user): ?>
                                        <span style="background: #3182ce; color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; margin-left: 8px;">You</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="points-display"><?php echo $student['total_point']; ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <?php echo $badge; ?>
                                </td>
                            </tr>
                        <?php 
                            $rank_counter++;
                        endwhile; 
                        ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No students found on the leaderboard yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Your Rank Card -->
        <?php if ($current_user_rank): ?>
        <div class="your-rank-card">
            <div class="rank-info">
                <div class="rank-number">#<?php echo $current_user_rank; ?></div>
                <div>
                    <div class="rank-label">Your Rank</div>
                    <div style="font-size: 14px; opacity: 0.9;">out of <?php echo $total_students; ?> students</div>
                </div>
            </div>
            <div class="points-info">
                <div class="points-value"><?php echo $current_user_points; ?> pts</div>
                <div class="rank-label">Total Points</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Motivation Section -->
        <h2 class="section-title">💪 Keep Going!</h2>
        <div style="background: white; border-radius: 16px; padding: 25px; margin-top: 20px; text-align: center;">
            <?php if ($current_user_points < 20): ?>
                <p style="font-size: 16px; color: #e53e3e;">⚠️ You need <strong><?php echo 20 - $current_user_points; ?></strong> more points to reach the Participation Certificate level!</p>
                <p style="margin-top: 10px; color: #718096;">Attend more events and be on time to earn points!</p>
            <?php elseif ($current_user_points < 50): ?>
                <p style="font-size: 16px; color: #3182ce;">📜 You need <strong><?php echo 50 - $current_user_points; ?></strong> more points to reach the Active Student Award level!</p>
                <p style="margin-top: 10px; color: #718096;">Keep participating in club activities!</p>
            <?php elseif ($current_user_points < 80): ?>
                <p style="font-size: 16px; color: #38a169;">⭐ You need <strong><?php echo 80 - $current_user_points; ?></strong> more points to become an Outstanding Participant!</p>
                <p style="margin-top: 10px; color: #718096;">You're almost there! Continue your excellent participation!</p>
            <?php else: ?>
                <p style="font-size: 16px; color: #ffd700;">🏆 Congratulations! You are an <strong>Outstanding Participant</strong>!</p>
                <p style="margin-top: 10px; color: #718096;">You are eligible for leadership awards and priority in event registration!</p>
            <?php endif; ?>
            
            <div style="margin-top: 20px;">
                <a href="student_event_registration.php" class="btn-action" style="display: inline-block; background: #3182ce; color: white; padding: 10px 24px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                    📅 Register for More Events
                </a>
            </div>
        </div>

    </div>

</body>
</html>