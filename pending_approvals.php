<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php"); exit();
}

$message = "";

// 1. Handle Approval/Rejection for both Users and Clubs
if (isset($_POST['action']) && isset($_POST['target_id']) && isset($_POST['type'])) {
    $target_id = $_POST['target_id'];
    $action = $_POST['action'];
    $type = $_POST['type'];

    if ($type == 'User') {
        $sql = ($action == 'approve') ? "UPDATE `USER` SET account_status = 'Approved' WHERE user_id = ?" : "UPDATE `USER` SET account_status = 'Rejected' WHERE user_id = ?";
    } else {
        $sql = ($action == 'approve') ? "UPDATE CLUB_MEMBERSHIP SET status = 'Approved' WHERE membership_id = ?" : "UPDATE CLUB_MEMBERSHIP SET status = 'Rejected' WHERE membership_id = ?";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $target_id);
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>✅ Action completed successfully!</div>";
    }
}

// 2. Fetch combined list
// 2. Fetch combined list
$pending_query = "
    SELECT 'User' as type, user_id as id, name as detail, email as extra 
    FROM `USER` 
    WHERE account_status = 'Pending'
    UNION
    SELECT 'Club' as type, cm.membership_id as id, c.club_name as detail, u.name as extra 
    FROM CLUB_MEMBERSHIP cm 
    JOIN CLUB c ON cm.club_id = c.club_id 
    JOIN USER u ON cm.user_id = u.user_id
    WHERE 'Pending' = 'Pending' 
";
$result = $conn->query($pending_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pending Approvals</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; display: flex; justify-content: center; }
        .card { background: white; padding: 35px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 900px; border-top: 6px solid #dd6b20; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f8fafc; padding: 16px; font-size: 13px; color: #4a5568; border-bottom: 2px solid #e2e8f0; }
        td { padding: 16px; color: #2d3748; border-bottom: 1px solid #edf2f7; }
        .btn-approve { background: #f0fff4; color: #276749; border: 1px solid #c6f6d5; padding: 8px 14px; border-radius: 6px; cursor: pointer; }
        .btn-reject { background: #fff5f5; color: #c53030; border: 1px solid #fed7d7; padding: 8px 14px; border-radius: 6px; cursor: pointer; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; text-align: center; }
        .alert-success { background: #c6f6d5; color: #22543d; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="card">
            <h2>🔔 Pending Approvals</h2>
            <?php echo $message; ?>

            <?php if ($result && $result->num_rows > 0): ?>
                <table>
                    <thead><tr><th>Type</th><th>Details</th><th>Extra Info</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><span style="font-weight:bold; color:<?php echo $row['type']=='User'?'#3182ce':'#805ad5';?>"><?php echo $row['type']; ?></span></td>
                                <td><?php echo htmlspecialchars($row['detail']); ?></td>
                                <td><?php echo htmlspecialchars($row['extra']); ?></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="target_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="type" value="<?php echo $row['type']; ?>">
                                        <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
                                        <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; padding: 20px; color: #718096;">No pending items.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>