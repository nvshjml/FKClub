<?php
session_start();
require 'db_connect.php';

// SECURITY CHECK: Only Admins can access this page!
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

$message = "";

// ---------------------------------------------------------
// LOGIC: Check if the Admin clicked "Approve" or "Reject"
// ---------------------------------------------------------
if (isset($_POST['action']) && isset($_POST['target_user_id'])) {
    $target_id = $_POST['target_user_id'];
    $action = $_POST['action'];

    if ($action == 'approve') {
        $sql = "UPDATE `USER` SET account_status = 'Approved' WHERE user_id = ?";
        $msg_text = "Student successfully Approved!";
        $msg_color = "green";
    } elseif ($action == 'reject') {
        $sql = "UPDATE `USER` SET account_status = 'Rejected' WHERE user_id = ?";
        $msg_text = "Student has been Rejected.";
        $msg_color = "red";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // FIXED: Binding parameter 's' because Matrix ID is a string!
        $stmt->bind_param("s", $target_id);
        if ($stmt->execute()) {
            $message = "<div style='color: $msg_color; background-color: #f8f9fa; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-weight: bold;'>✅ $msg_text</div>";
        }
    }
}

// ---------------------------------------------------------
// LOGIC: Fetch all students who are currently 'Pending'
// ---------------------------------------------------------
$pending_query = "SELECT user_id, name, email, phone FROM `USER` WHERE account_status = 'Pending'";
$result = $conn->query($pending_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approvals</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f6; margin: 0; }
        .navbar { background-color: #0056b3; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; font-weight: bold; padding: 8px 12px; background-color: #6c757d; border-radius: 4px; }
        .navbar a:hover { background-color: #5a6268; }
        .container { display: flex; justify-content: center; padding: 40px; }
        .card { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); width: 100%; max-width: 800px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; color: #333; }
        tr:hover { background-color: #f1f1f1; }
        
        form { display: inline; }
        .btn-approve { background-color: #28a745; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; margin-right: 5px; }
        .btn-approve:hover { background-color: #218838; }
        .btn-reject { background-color: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-reject:hover { background-color: #c82333; }
    </style>
</head>
<body>

    <div class="navbar">
        <div>FK Club System - Admin Panel</div>
        <a href="admin_dashboard.php">← Back to Dashboard</a>
    </div>

    <div class="container">
        <div class="card">
            <h2>🔔 Pending Student Registrations</h2>
            
            <?php echo $message; ?>

            <?php if ($result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Matrix ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td>
                                    <form method="POST" action="pending_approvals.php">
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
                <p style="color: #666; font-style: italic; padding: 20px 0;">Hooray! There are no pending registrations at the moment.</p>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>