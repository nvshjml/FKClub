<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
$message = "";

// ---------------------------------------------------------
// 1. HANDLE CLUB MEMBERSHIP APPROVALS 
// ---------------------------------------------------------
if (isset($_POST['approve_club'])) {
    $m_user_id = $_POST['member_user_id'];
    $m_club_id = $_POST['member_club_id'];
    
    $stmt = $conn->prepare("UPDATE club_membership SET status = 'Approved' WHERE user_id = ? AND club_id = ?");
    $stmt->bind_param("ss", $m_user_id, $m_club_id);
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>✅ Club membership approved for <strong>$m_user_id</strong>!</div>";
    }
}

if (isset($_POST['reject_club'])) {
    $m_user_id = $_POST['member_user_id'];
    $m_club_id = $_POST['member_club_id'];
    
    $stmt = $conn->prepare("DELETE FROM club_membership WHERE user_id = ? AND club_id = ?");
    $stmt->bind_param("ss", $m_user_id, $m_club_id);
    if ($stmt->execute()) {
        $message = "<div class='alert alert-error'>❌ Club membership rejected and removed from database for <strong>$m_user_id</strong>.</div>";
    }
}

// ---------------------------------------------------------
// 2. FETCH PENDING DATA
// ---------------------------------------------------------
$pending_clubs_sql = "
    SELECT cm.user_id, cm.club_id, u.name, c.club_name, cm.join_date 
    FROM club_membership cm 
    JOIN `user` u ON cm.user_id = u.user_id 
    JOIN club c ON cm.club_id = c.club_id 
    WHERE cm.status = 'Pending'
    ORDER BY cm.join_date ASC
";
$pending_clubs = $conn->query($pending_clubs_sql);

$total_pending = ($pending_clubs ? $pending_clubs->num_rows : 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pending Approvals - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: #e2e8f0; display: flex; min-height: 100vh; }
        
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; width: calc(100% - 260px); }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;}
        .page-header h1 { color: #1a202c; font-size: 28px; font-weight: 700; }
        
        .section-header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 20px; border-radius: 8px 8px 0 0; border: 1px solid #cbd5e0; border-bottom: 1px solid #e2e8f0; margin-top: 30px;}
        .section-header h2 { color: #2d3748; font-size: 16px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 10px;}
        .badge-count { background: #dd6b20; color: white; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: bold;}

        .table-card { background: white; border-radius: 0 0 8px 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 30px; border: 1px solid #cbd5e0; border-top: none; }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; min-width: 700px; }
        th { padding: 15px 20px; font-size: 12px; color: #718096; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
        td { padding: 15px 20px; color: #4a5568; border-bottom: 1px solid #edf2f7; font-size: 14px; white-space: nowrap; }
        
        .action-links { display: flex; gap: 8px; }
        .btn-sm { padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; transition: 0.2s;}
        .btn-approve { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .btn-approve:hover { background: #9ae6b4; }
        .btn-reject { background: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        .btn-reject:hover { background: #feb2b2; }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .alert-success { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        
        .empty-state { text-align: center; padding: 40px; color: #718096; font-size: 15px; background: #f8fafc;}

        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .main-content { margin-left: 200px; width: calc(100% - 200px); padding: 20px; }
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>System Approvals</h1>
            <span style="color: #718096; font-weight: 500; background: white; padding: 8px 15px; border-radius: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">Total Pending: <?php echo $total_pending; ?></span>
        </div>

        <?php echo $message; ?>

        <div class="section-header">
            <h2>🏛️ Club Join Requests <?php if($pending_clubs && $pending_clubs->num_rows > 0) echo "<span class='badge-count'>".$pending_clubs->num_rows."</span>"; ?></h2>
        </div>
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Target Club</th>
                            <th>Application Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pending_clubs && $pending_clubs->num_rows > 0): ?>
                            <?php while($club = $pending_clubs->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($club['user_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($club['name']); ?></td>
                                    <td><?php echo htmlspecialchars($club['club_name']); ?></td>
                                    <td><?php echo date("d M Y", strtotime($club['join_date'])); ?></td>
                                    <td>
                                        <div class="action-links">
                                            <form method="POST" style="margin:0;">
                                                <input type="hidden" name="member_user_id" value="<?php echo $club['user_id']; ?>">
                                                <input type="hidden" name="member_club_id" value="<?php echo $club['club_id']; ?>">
                                                <button type="submit" name="approve_club" class="btn-sm btn-approve">Approve</button>
                                                <button type="submit" name="reject_club" class="btn-sm btn-reject" onclick="return confirm('Reject this club application?');">Reject</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="empty-state">No pending club join requests.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>
</html>