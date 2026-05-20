<?php
session_start();
require 'db_connect.php';

// SECURITY CHECK: If they are not logged in, OR they are not an Admin (role 1), kick them out!
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header("Location: index.php");
    exit();
}

// ---------------------------------------------------------
// FETCH DASHBOARD STATISTICS
// ---------------------------------------------------------

// 1. Count Total Approved Students
$stmt1 = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE role = 2 AND account_status = 'Approved'");
$total_students = $stmt1->fetch_assoc()['total'];

// 2. Count Pending Approvals
$stmt2 = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE account_status = 'Pending'");
$pending_count = $stmt2->fetch_assoc()['total'];

// 3. Count Total Active Clubs
$stmt3 = $conn->query("SELECT COUNT(*) AS total FROM CLUB WHERE isActive = 1");
$total_clubs = $stmt3 ? $stmt3->fetch_assoc()['total'] : 0; 

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Global Reset & Gradient Background */
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; color: #333; }
        
        /* Modern Navbar */
        .navbar { 
            background-color: rgba(255, 255, 255, 0.95); 
            padding: 15px 30px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            backdrop-filter: blur(10px);
            position: sticky; top: 0; z-index: 100;
        }
        .navbar-brand { font-size: 1.2rem; font-weight: 700; color: #1a202c; }
        .nav-links { display: flex; align-items: center; gap: 15px; }
        .nav-links a { 
            text-decoration: none; font-weight: 600; padding: 10px 16px; 
            border-radius: 8px; transition: 0.2s; font-size: 14px;
        }
        
        /* Navbar Button Styles */
        .btn-standard { color: #4a5568; background-color: transparent; }
        .btn-standard:hover { background-color: #edf2f7; color: #2b6cb0; }
        
        .btn-pending { background-color: #fefcbf; color: #744210; display: flex; align-items: center; gap: 8px; }
        .btn-pending:hover { background-color: #fbd38d; }
        
        .btn-logout { background-color: #fed7d7; color: #c53030; }
        .btn-logout:hover { background-color: #feb2b2; }

        /* Notification Badge */
        .badge { background: #e53e3e; color: white; border-radius: 50%; padding: 2px 8px; font-size: 12px; font-weight: bold; }
        
        /* Main Container */
        .container { padding: 40px; max-width: 1200px; margin: 0 auto; }
        
        /* Welcome Card */
        .welcome-card { 
            background-color: rgba(255, 255, 255, 0.95); 
            padding: 30px; 
            border-radius: 16px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.05); 
            margin-bottom: 30px; 
            backdrop-filter: blur(10px);
            border-left: 6px solid #3182ce;
        }
        .welcome-card h2 { color: #1a202c; font-size: 24px; margin-bottom: 8px; }
        .welcome-card p { color: #718096; font-size: 15px; }
        
        /* Statistics Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; }
        
        .stat-card { 
            background-color: rgba(255, 255, 255, 0.95); 
            padding: 25px; 
            border-radius: 16px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.05); 
            text-align: center; 
            backdrop-filter: blur(10px);
            transition: transform 0.2s, box-shadow 0.2s;
            border-bottom: 5px solid transparent;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
        
        /* Specific bottom borders */
        .border-blue { border-bottom-color: #3182ce; }
        .border-orange { border-bottom-color: #dd6b20; }
        .border-green { border-bottom-color: #38a169; }
        .border-purple { border-bottom-color: #805ad5; }
        
        .stat-card h3 { margin: 0; color: #a0aec0; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .number { font-size: 2.8rem; font-weight: 700; color: #2d3748; margin: 10px 0 0 0; }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="navbar-brand">FK Club System</div>
        <div class="nav-links">
            <a href="admin_qr_dashboard.php" class="btn-standard">QR & Attendance</a>
            
            <a href="pending_approvals.php" class="btn-pending">
                Approvals 
                <?php if($pending_count > 0) { echo "<span class='badge'>$pending_count</span>"; } ?>
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