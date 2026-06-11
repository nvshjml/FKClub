<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'Admin') {
    header("Location: index.php"); exit();
}

$user_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);

$user_sql = "SELECT name, total_point, user_id FROM `USER` WHERE user_id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("s", $user_id); $stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$current_user_points = $current_user ? $current_user['total_point'] : 0;
$current_user_name = $current_user ? $current_user['name'] : '';

$leaderboard_sql = "SELECT user_id, name, total_point FROM `USER` WHERE role = 'Student' ORDER BY total_point DESC LIMIT 10";
$leaderboard_result = $conn->query($leaderboard_sql);

$all_students_sql = "SELECT user_id, total_point FROM `USER` WHERE role = 'Student' ORDER BY total_point DESC";
$all_students = $conn->query($all_students_sql);

$rank = 1; $current_user_rank = null;
if ($all_students) {
    while ($student = $all_students->fetch_assoc()) {
        if ($student['user_id'] == $user_id) { $current_user_rank = $rank; break; }
        $rank++;
    }
}

$total_result = $conn->query("SELECT COUNT(*) as total FROM `USER` WHERE role = 'Student'");
$total_students = $total_result->fetch_assoc()['total'];

$top3_result = $conn->query("SELECT user_id, name, total_point FROM `USER` WHERE role = 'Student' ORDER BY total_point DESC LIMIT 3");
$top3 = [];
while ($row = $top3_result->fetch_assoc()) { $top3[] = $row; }

function getStudentBadge($points, $rank) {
    if ($rank == 1) return '👑 Champion';
    if ($rank == 2) return '🥈 Runner Up';
    if ($rank == 3) return '🥉 Bronze';
    if ($points >= 80) return '🏆 Outstanding Participant';
    if ($points >= 50) return '⭐ Highly Active';
    if ($points >= 20) return '📜 Active Participant';
    return '⚠️ Needs Improvement';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leaderboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; color: #333; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; max-width: calc(100% - 260px); }
        .welcome-card { background-color: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #38a169; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 13px; color: #718096; text-transform: uppercase; }
        .stat-card .number { font-size: 2.5rem; font-weight: 700; color: #2d3748; margin-top: 10px; }
        .section-title { font-size: 22px; color: #1a202c; margin-bottom: 20px; border-bottom: 2px solid #cbd5e0; padding-bottom: 10px; margin-top: 30px; }
        .podium-container { display: flex; justify-content: center; align-items: flex-end; gap: 20px; margin-bottom: 50px; flex-wrap: wrap; }
        .podium-card { text-align: center; padding: 20px; border-radius: 16px; background: white; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .podium-card:hover { transform: translateY(-5px); }
        .podium-card .rank-icon { font-size: 40px; margin-bottom: 10px; }
        .podium-card .avatar { width: 80px; height: 80px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 32px; font-weight: bold; color: white; }
        .podium-card .name { font-weight: 700; font-size: 16px; color: #2d3748; margin-bottom: 5px; }
        .podium-card .points { font-size: 20px; font-weight: 800; color: #38a169; }
        .podium-card.rank-1 .avatar { width: 100px; height: 100px; font-size: 40px; background: linear-gradient(135deg, #ffd700, #ffb347); }
        .podium-card.rank-1 { min-width: 180px; }
        .podium-card.rank-2 .avatar { background: linear-gradient(135deg, #c0c0c0, #a8a8a8); }
        .podium-card.rank-3 .avatar { background: linear-gradient(135deg, #cd7f32, #b8860b); }
        .leaderboard-table-container { overflow-x: auto; }
        .leaderboard-table { width: 100%; border-collapse: collapse; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .leaderboard-table th { background: #f8fafc; padding: 16px 20px; text-align: left; font-weight: 600; color: #4a5568; border-bottom: 2px solid #e2e8f0; }
        .leaderboard-table td { padding: 14px 20px; border-bottom: 1px solid #edf2f7; color: #2d3748; }
        .leaderboard-table .current-user { background: #ebf8ff; border-left: 4px solid #3182ce; }
        .rank-badge { display: inline-block; width: 32px; height: 32px; background: #e2e8f0; border-radius: 50%; text-align: center; line-height: 32px; font-weight: 700; font-size: 14px; color: #4a5568; }
        .rank-badge.top-1 { background: #ffd700; color: #744210; }
        .rank-badge.top-2 { background: #c0c0c0; color: #2d3748; }
        .rank-badge.top-3 { background: #cd7f32; color: white; }
        .points-display { font-weight: 700; color: #38a169; font-size: 18px; }
        .your-rank-card { background: linear-gradient(135deg, #3182ce, #2b6cb0); border-radius: 16px; padding: 25px; margin-top: 30px; color: white; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .your-rank-card .rank-info { display: flex; align-items: center; gap: 20px; }
        .your-rank-card .rank-number { font-size: 48px; font-weight: 800; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="welcome-card">
            <h2>🏆 Student Leaderboard</h2>
            <p style="color: #718096;">See how you rank among all students based on participation points.</p>
        </div>
        <div class="stats-grid">
            <div class="stat-card" style="border-bottom: 4px solid #3182ce;"><h3>Total Students</h3><div class="number"><?php echo $total_students; ?></div></div>
            <div class="stat-card" style="border-bottom: 4px solid #38a169;"><h3>Your Points</h3><div class="number"><?php echo $current_user_points; ?></div></div>
            <?php if ($current_user_rank): ?><div class="stat-card" style="border-bottom: 4px solid #805ad5;"><h3>Your Rank</h3><div class="number">#<?php echo $current_user_rank; ?></div></div><?php endif; ?>
        </div>
        
        <?php if (count($top3) >= 1): ?>
        <h2 class="section-title">🥇 Top Performers</h2>
        <div class="podium-container">
            <?php 
            $positions = [ 1 => ['icon' => '🥇', 'class' => 'rank-1'], 2 => ['icon' => '🥈', 'class' => 'rank-2'], 3 => ['icon' => '🥉', 'class' => 'rank-3'] ];
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

        <h2 class="section-title">📊 Full Leaderboard</h2>
        <div class="leaderboard-table-container">
            <?php if ($leaderboard_result && $leaderboard_result->num_rows > 0): ?>
                <table class="leaderboard-table">
                    <thead><tr><th style="width: 80px;">Rank</th><th>Student Name</th><th style="width: 120px; text-align: center;">Points</th><th style="width: 180px; text-align: center;">Recognition Level</th></tr></thead>
                    <tbody>
                        <?php 
                        $rank_counter = 1;
                        while($student = $leaderboard_result->fetch_assoc()): 
                            $is_current_user = ($student['user_id'] == $user_id);
                            $badge = getStudentBadge($student['total_point'], $rank_counter);
                        ?>
                            <tr class="<?php echo $is_current_user ? 'current-user' : ''; ?>">
                                <td><span class="rank-badge <?php echo $rank_counter <= 3 ? 'top-' . $rank_counter : ''; ?>"><?php echo $rank_counter; ?></span></td>
                                <td><?php echo htmlspecialchars($student['name']); ?><?php if ($is_current_user): ?><span style="background: #3182ce; color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; margin-left: 8px;">You</span><?php endif; ?></td>
                                <td style="text-align: center;"><span class="points-display"><?php echo $student['total_point']; ?></span></td>
                                <td style="text-align: center;"><?php echo $badge; ?></td>
                            </tr>
                        <?php $rank_counter++; endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>