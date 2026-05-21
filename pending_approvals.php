<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php"); exit();
}

$message = "";
if (isset($_POST['action']) && isset($_POST['target_user_id'])) {
    $target_id = $_POST['target_user_id'];
    $action = $_POST['action'];

    if ($action == 'approve') {
        $sql = "UPDATE `USER` SET account_status = 'Approved' WHERE user_id = ?";
        $message = "<div class='alert' style='background:#f0fff4; color:#276749; border:1px solid #c6f6d5;'>✅ Student Approved!</div>";
    } elseif ($action == 'reject') {
        $sql = "UPDATE `USER` SET account_status = 'Rejected' WHERE user_id = ?";
        $message = "<div class='alert' style='background:#fff5f5; color:#c53030; border:1px solid #fed7d7;'>❌ Student Rejected.</div>";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $target_id);
    $stmt->execute();
}

$pending_query = "SELECT user_id, name, email FROM `USER` WHERE account_status = 'Pending'";
$result = $conn->query($pending_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pending Approvals</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; display: flex; justify-content: center; }
        .card { background: white; padding: 35px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 900px; border-top: 6px solid #dd6b20; }
        .card h2 { margin-bottom: 25px; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 16px; font-size: 13px; color: #4a5568; border-bottom: 2px solid #e2e8f0; }
        td { padding: 16px; color: #2d3748; border-bottom: 1px solid #edf2f7; }
        .btn-approve { background: #f0fff4; color: #276749; border: 1px solid #c6f6d5; padding: 8px 14px; border-radius: 6px; cursor: pointer; }
        .btn-reject { background: #fff5f5; color: #c53030; border: 1px solid #fed7d7; padding: 8px 14px; border-radius: 6px; cursor: pointer; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="card">
            <h2>🔔 Pending Student Registrations</h2>
            <?php echo $message; ?>

            <?php if ($result->num_rows > 0): ?>
                <table>
                    <thead><tr><th>Matrix ID</th><th>Name</th><th>Email</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['user_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="target_user_id" value="<?php echo $row['user_id']; ?>">
                                        <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
                                        <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; padding: 20px; color: #718096;">No pending registrations.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>