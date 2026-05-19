<?php
session_start();
require 'db_connect.php';

// SECURITY CHECK: If they are not logged in, OR they are not an Admin (role 1), kick them out!
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit();
}

// ---------------------------------------------------------
// FETCH DASHBOARD STATISTICS
// ---------------------------------------------------------

// 1. Count Total Approved Students
$stmt1 = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE role_id = 2 AND account_status = 'Approved'");
$total_students = $stmt1->fetch_assoc()['total'];

// 2. Count Pending Approvals
$stmt2 = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE account_status = 'Pending'");
$pending_count = $stmt2->fetch_assoc()['total'];

// 3. Count Total Active Clubs
$stmt3 = $conn->query("SELECT COUNT(*) AS total FROM CLUB WHERE isActive = 1");
$total_clubs = $stmt3 ? $stmt3->fetch_assoc()['total'] : 0; // Using ternary in case table is empty

// 4. Count Total Events
$stmt4 = $conn->query("SELECT COUNT(*) AS total FROM EVENT");
$total_events = $stmt4 ? $stmt4->fetch_assoc()['total'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f6; margin: 0; }
        
        /* Navbar Styling */
        .navbar { background-color: #0056b3; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .navbar-brand { font-size: 1.2rem; font-weight: bold; }
        .nav-links a { text-decoration: none; font-weight: bold; padding: 8px 15px; border-radius: 4px; margin-left: 10px; transition: 0.3s; }
        .btn-pending { background-color: #ffc107; color: #000; }
        .btn-pending:hover { background-color: #e0a800; }
        .btn-logout { background-color: #dc3545; color: white; }
        .btn-logout:hover { background-color: #c82333; }
        
        /* Main Container */
        .container { padding: 30px; max-width: 1200px; margin: 0 auto; }
        .welcome-card { background-color: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); margin-bottom: 30px; border-left: 5px solid #0056b3; }
        
        /* Statistics Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .stat-card { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center; border-bottom: 5px solid #ccc; }
        
        /* Specific colors for the bottom borders of stat cards */
        .border-blue { border-bottom-color: #007bff; }
        .border-orange { border-bottom-color: #fd7e14; }
        .border-green { border-bottom-color: #28a745; }
        .border-purple { border-bottom-color: #6f42c1; }
        
        .stat-card h3 { margin: 0; color: #666; font-size: 1.1rem; }
        .stat-card .number { font-size: 2.5rem; font-weight: bold; color: #333; margin: 10px 0; }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="navbar-brand">FK Club System - Admin Panel</div>
        <div class="nav-links">
            <a href="pending_approvals.php" class="btn-pending">
                Pending Approvals 
                <?php if($pending_count > 0) { echo "<span style='background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 12px;'>$pending_count</span>"; } ?>
            </a>
            <a href="logout.php" class="btn-logout">Logout</a> 
        </div>
    </div>

    <div class="container">
        
        <div class="welcome-card">
            <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>! 👋</h2>
            <p>You are logged in as an <strong>Administrator</strong>. Here is the current overview of your system.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card border-blue">
                <h3>Total Students</h3>
                <div class="number"><?php echo $total_students; ?></div>
            </div>

            <div class="stat-card border-orange">
                <h3>Pending Approvals</h3>
                <div class="number"><?php echo $pending_count; ?></div>
            </div>

            <div class="stat-card border-green">
                <h3>Active Clubs</h3>
                <div class="number"><?php echo $total_clubs; ?></div>
            </div>

            <div class="stat-card border-purple">
                <h3>Upcoming Events</h3>
                <div class="number"><?php echo $total_events; ?></div>
            </div>
        </div>

    </div>

</body>
</html>