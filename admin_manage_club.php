<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

$club_id = isset($_GET['club_id']) ? $_GET['club_id'] : '';
$message = "";

if (empty($club_id)) {
    header("Location: admin_club_analytics.php");
    exit();
}

$club_stmt = $conn->prepare("SELECT club_name FROM club WHERE club_id = ?");
$club_stmt->bind_param("s", $club_id);
$club_stmt->execute();
$club = $club_stmt->get_result()->fetch_assoc();

if (!$club) {
    header("Location: admin_club_analytics.php");
    exit();
}

if (isset($_POST['remove_member'])) {
    $remove_id = $_POST['user_id'];
    $stmt = $conn->prepare("DELETE FROM committee WHERE user_id = ? AND club_id = ?");
    $stmt->bind_param("ss", $remove_id, $club_id);
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>✅ Member removed successfully.</div>";
    } else {
        $message = "<div class='alert alert-error'>❌ Error removing member.</div>";
    }
}

$members_stmt = $conn->prepare("
    SELECT u.user_id, u.name, c.position 
    FROM committee c 
    JOIN `user` u ON c.user_id = u.user_id 
    WHERE c.club_id = ? 
    ORDER BY c.position ASC, u.name ASC
");
$members_stmt->bind_param("s", $club_id);
$members_stmt->execute();
$members = $members_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Club Members</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: #f4f7f6; display: flex; min-height: 100vh; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; margin-top: 10px; }
        .page-header h2 { color: #1a202c; font-size: 24px; margin: 0; }
        .btn-back { background: #718096; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 14px; transition: 0.2s; }
        .btn-back:hover { background: #4a5568; }
        .table-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 15px 20px; font-size: 13px; color: #4a5568; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; font-weight: 600; }
        td { padding: 15px 20px; color: #2d3748; border-bottom: 1px solid #edf2f7; font-size: 14px; }
        tr:hover { background-color: #f8fafc; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #e2e8f0; color: #4a5568; }
        .action-links { display: flex; gap: 8px; }
        .btn-sm { padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; }
        .btn-edit { background: #fefcbf; color: #744210; border: 1px solid #fbd38d; }
        .btn-danger { background: #fff5f5; color: #c53030; border: 1px solid #fed7d7; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2>👥 Members of: <?php echo htmlspecialchars($club['club_name']); ?></h2>
            <a href="admin_club_analytics.php" class="btn-back">← Back</a>
        </div>

        <?php if($message) echo $message; ?>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Matrix ID</th>
                        <th>Name</th>
                        <th>Role / Position</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($members && $members->num_rows > 0): ?>
                        <?php while($m = $members->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($m['user_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($m['name']); ?></td>
                                <td><span class="badge"><?php echo htmlspecialchars($m['position']); ?></span></td>
                                <td>
                                    <div class="action-links">
                                        <a href="admin_edit_membership.php?user_id=<?php echo urlencode($m['user_id']); ?>&club_id=<?php echo htmlspecialchars($club_id); ?>" class="btn-sm btn-edit">Edit</a>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to remove this member from the club?');" style="margin:0;">
                                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($m['user_id']); ?>">
                                            <button type="submit" name="remove_member" class="btn-sm btn-danger">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center; color: #718096; padding: 30px;">No members found in this club.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>