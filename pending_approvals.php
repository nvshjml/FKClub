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
        $msg_color = "#276749"; // Dark green
        $msg_bg = "#f0fff4"; // Light green background
        $msg_border = "#c6f6d5";
    } elseif ($action == 'reject') {
        $sql = "UPDATE `USER` SET account_status = 'Rejected' WHERE user_id = ?";
        $msg_text = "Student has been Rejected.";
        $msg_color = "#9b2c2c"; // Dark red
        $msg_bg = "#fff5f5"; // Light red background
        $msg_border = "#fed7d7";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // Binding parameter 's' because Matrix ID is a string!
        $stmt->bind_param("s", $target_id);
        if ($stmt->execute()) {
            $message = "<div style='color: $msg_color; background-color: $msg_bg; border: 1px solid $msg_border; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;'>✅ $msg_text</div>";
        }
    }
}

// ---------------------------------------------------------
// LOGIC: Fetch Data
// ---------------------------------------------------------
$pending_query = "SELECT user_id, name, email, phone FROM `USER` WHERE account_status = 'Pending'";
$result = $conn->query($pending_query);

// Fetch pending count for sidebar badge
$sidebar_pending_count = 0;
$sidebar_stmt = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE account_status = 'Pending'");
if($sidebar_stmt) {
    $sidebar_pending_count = $sidebar_stmt->fetch_assoc()['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approvals</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Global Reset */
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; margin: 0; }

        /* Sidebar Styles (Embedded directly!) */
        .sidebar { 
            width: 260px; background-color: #1a202c; color: white; display: flex; flex-direction: column; 
            padding: 30px 20px; position: fixed; height: 100vh; box-shadow: 4px 0 10px rgba(0,0,0,0.1); 
            z-index: 1000; top: 0; left: 0; box-sizing: border-box; 
        }
        .sidebar-brand { font-size: 20px; font-weight: 700; margin-bottom: 40px; color: #fff; text-align: center; }
        .nav-links-sidebar { display: flex; flex-direction: column; gap: 15px; flex-grow: 1; }
        .nav-links-sidebar a { 
            text-decoration: none; color: #a0aec0; font-weight: 600; padding: 12px 15px; 
            border-radius: 8px; transition: 0.3s; display: flex; align-items: center; justify-content: space-between; font-size: 15px; 
        }
        .nav-links-sidebar a:hover, .nav-links-sidebar a.active { background-color: #2d3748; color: white; }
        .badge-sidebar { background: #e53e3e; color: white; border-radius: 12px; padding: 2px 8px; font-size: 12px; font-weight: bold; }
        .btn-logout-sidebar { background-color: #e53e3e; color: white; text-align: center; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: bold; margin-top: auto; transition: 0.2s; }
        .btn-logout-sidebar:hover { background-color: #c53030; }

        /* Main Content Layout */
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; width: calc(100% - 260px); box-sizing: border-box; }
        .content-wrapper { display: flex; justify-content: center; width: 100%; align-items: flex-start; }
        
        /* Card Styling */
        .card { background-color: rgba(255, 255, 255, 0.95); padding: 35px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 900px; backdrop-filter: blur(10px); border-top: 6px solid #dd6b20; }
        .card h2 { color: #1a202c; font-size: 22px; margin-bottom: 25px; }
        
        /* Modern Table */
        table { width: 100%; border-collapse: collapse; text-align: left; }
        thead { background-color: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        th { padding: 16px 20px; font-size: 13px; color: #4a5568; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 16px 20px; font-size: 15px; color: #2d3748; border-bottom: 1px solid #edf2f7; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #f8fafc; }
        
        /* Button Styling */
        form { display: inline; margin: 0; }
        .btn-approve { background-color: #f0fff4; color: #276749; border: 1px solid #c6f6d5; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; margin-right: 5px; transition: 0.2s; }
        .btn-approve:hover { background-color: #c6f6d5; }
        .btn-reject { background-color: #fff5f5; color: #c53030; border: 1px solid #fed7d7; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; transition: 0.2s; }
        .btn-reject:hover { background-color: #fed7d7; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-brand">FK Club Admin</div>
        <div class="nav-links-sidebar">
            <a href="admin_dashboard.php">Dashboard</a>
            
            <a href="admin_attendance.php">Attendance</a>
            
            <a href="pending_approvals.php" class="active">
                <span>Approvals</span>
                <?php if($sidebar_pending_count > 0): ?>
                    <span class="badge-sidebar"><?php echo $sidebar_pending_count; ?></span>
                <?php endif; ?>
            </a>
        </div>
        <a href="logout.php" class="btn-logout-sidebar">Logout</a>
    </div>

    <div class="main-content">
        <div class="content-wrapper">
            
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
                                    <td><strong><?php echo htmlspecialchars($row['user_id']); ?></strong></td>
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
                    <p style="color: #718096; font-style: italic; padding: 20px 0; text-align: center; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e0;">
                        🎉 Hooray! There are no pending registrations at the moment.
                    </p>
                <?php endif; ?>

            </div>
            
        </div>
    </div>

</body>
</html>