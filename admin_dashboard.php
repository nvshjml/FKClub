<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

// HANDLE DELETIONS
if (isset($_POST['delete_club_id'])) {
    $delete_id = $_POST['delete_club_id'];
    $del_stmt = $conn->prepare("DELETE FROM CLUB WHERE club_id = ?");
    $del_stmt->bind_param("i", $delete_id);
    $del_stmt->execute();
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_POST['delete_event_id'])) {
    $delete_id = $_POST['delete_event_id'];
    $del_stmt = $conn->prepare("DELETE FROM EVENT WHERE event_id = ?");
    $del_stmt->bind_param("i", $delete_id);
    $del_stmt->execute();
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch pending count for sidebar badge
$sidebar_pending_count = 0;
$sidebar_stmt = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE account_status = 'Pending'");
if($sidebar_stmt) { $sidebar_pending_count = $sidebar_stmt->fetch_assoc()['total']; }

// Stats queries
$stmt1 = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE role IN ('Student', 'Committee') AND account_status = 'Approved'");
$total_students = $stmt1->fetch_assoc()['total'];

$stmt2 = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE account_status = 'Pending'");
$pending_count = $stmt2->fetch_assoc()['total'];

$stmt3 = $conn->query("SELECT COUNT(*) AS total FROM CLUB WHERE isActive = 1");
$total_clubs = $stmt3 ? $stmt3->fetch_assoc()['total'] : 0; 

$stmt4 = $conn->query("SELECT COUNT(*) AS total FROM EVENT");
$total_events = $stmt4 ? $stmt4->fetch_assoc()['total'] : 0;

// Table queries
$clubs_sql = "SELECT c.club_id, c.club_name, c.isActive, u.name AS president_name FROM CLUB c LEFT JOIN COMMITTEE com ON c.club_id = com.club_id AND com.position = 'President' LEFT JOIN `USER` u ON com.user_id = u.user_id ORDER BY c.club_name ASC";
$clubs_result = $conn->query($clubs_sql);

$events_sql = "SELECT e.event_id, e.event_name, e.date, c.club_name FROM EVENT e JOIN CLUB c ON e.club_id = c.club_id ORDER BY e.date ASC";
$events_result = $conn->query($events_sql);

$current_page = basename($_SERVER['PHP_SELF']); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; margin: 0; }
        
        .sidebar { width: 260px; background-color: #1a202c; color: white; display: flex; flex-direction: column; padding: 30px 20px; position: fixed; height: 100vh; box-shadow: 4px 0 10px rgba(0,0,0,0.1); z-index: 1000; top: 0; left: 0; box-sizing: border-box; }
        
        .sidebar-header { text-align: center; margin-bottom: 35px; }
        .sidebar-logo { max-width: 85px; margin-bottom: 12px; display: inline-block; }
        .sidebar-brand { font-size: 20px; font-weight: 700; color: #ffffff; margin-bottom: 6px; }
        .sidebar-role { font-size: 11px; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 1.5px; background: rgba(255,255,255,0.1); padding: 4px 12px; border-radius: 20px; display: inline-block; }

        .nav-links-sidebar { display: flex; flex-direction: column; gap: 15px; flex-grow: 1; }
        .nav-links-sidebar a { text-decoration: none; color: #a0aec0; font-weight: 600; padding: 12px 15px; border-radius: 8px; transition: 0.3s; display: flex; align-items: center; justify-content: space-between; font-size: 15px; }
        .nav-links-sidebar a:hover, .nav-links-sidebar a.active { background-color: #2d3748; color: white; }
        .badge-sidebar { background: #e53e3e; color: white; border-radius: 12px; padding: 2px 8px; font-size: 12px; font-weight: bold; }
        .btn-logout-sidebar { background-color: #e53e3e; color: white; text-align: center; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: bold; margin-top: auto; transition: 0.2s; }
        .btn-logout-sidebar:hover { background-color: #c53030; }
        
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; width: calc(100% - 260px); box-sizing: border-box; }
        .welcome-card { background-color: rgba(255, 255, 255, 0.95); padding: 30px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); margin-bottom: 30px; backdrop-filter: blur(10px); border-left: 6px solid #3182ce; }
        .welcome-card h2 { color: #1a202c; font-size: 24px; margin-bottom: 8px; }
        .welcome-card p { color: #718096; font-size: 15px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .stat-card { background-color: rgba(255, 255, 255, 0.95); padding: 25px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; backdrop-filter: blur(10px); border-bottom: 5px solid transparent; transition: 0.2s;}
        .stat-card:hover { transform: translateY(-5px); }
        .border-blue { border-bottom-color: #3182ce; } .border-orange { border-bottom-color: #dd6b20; } .border-green { border-bottom-color: #38a169; } .border-purple { border-bottom-color: #805ad5; }
        .stat-card h3 { margin: 0; color: #a0aec0; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .number { font-size: 2.8rem; font-weight: 700; color: #2d3748; margin: 10px 0 0 0; }
        
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; margin-top: 40px; }
        .section-header h2 { font-size: 22px; color: #1a202c; }
        .btn-add { background: #3182ce; color: white; border: none; padding: 10px 15px; border-radius: 8px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 14px; }
        .btn-add:hover { background: #2b6cb0; }
        .table-card { background-color: rgba(255, 255, 255, 0.95); border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); backdrop-filter: blur(10px); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        thead { background-color: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        th { padding: 16px 20px; font-size: 14px; color: #4a5568; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 16px 20px; font-size: 15px; color: #2d3748; border-bottom: 1px solid #edf2f7; vertical-align: middle; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .status-active { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .status-inactive { background-color: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        .action-links { display: flex; gap: 8px; }
        .btn-sm { padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
        .btn-edit { background-color: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; }
        .btn-delete { background-color: #fff5f5; color: #c53030; border: 1px solid #fed7d7; }
        .btn-approve { background-color: #f0fff4; color: #276749; border: 1px solid #c6f6d5; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">
            <img src="image/LogoUMP5.png" alt="UMPSA Logo" class="sidebar-logo">
            <div class="sidebar-brand">FK Club System</div>
            <div class="sidebar-role"><?php echo htmlspecialchars($_SESSION['role']); ?> Dashboard</div>
        </div>
        <div class="nav-links-sidebar">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="admin_attendance.php">Attendance</a>
            <a href="pending_approvals.php">
                <span>Approvals</span>
                <?php if($sidebar_pending_count > 0): ?>
                    <span class="badge-sidebar"><?php echo $sidebar_pending_count; ?></span>
                <?php endif; ?>
            </a>
        </div>
        <a href="logout.php" class="btn-logout-sidebar">Logout</a>
    </div>

    <div class="main-content">
        <div class="welcome-card">
            <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>! 👋</h2>
            <p>You are logged in as an <strong>Administrator</strong>. Here is the current overview of your system.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card border-blue"><h3>Total Students</h3><div class="number"><?php echo $total_students; ?></div></div>
            <div class="stat-card border-orange"><h3>Pending Approvals</h3><div class="number"><?php echo $pending_count; ?></div></div>
            <div class="stat-card border-green"><h3>Active Clubs</h3><div class="number"><?php echo $total_clubs; ?></div></div>
            <div class="stat-card border-purple"><h3>Upcoming Events</h3><div class="number"><?php echo $total_events; ?></div></div>
        </div>

        <div class="section-header">
            <h2>🏆 Manage Clubs</h2>
            <button class="btn-add">+ Add New Club</button>
        </div>
        <div class="table-card">
            <table>
                <thead><tr><th>Club Name</th><th>President Name</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if ($clubs_result && $clubs_result->num_rows > 0): ?>
                        <?php while($club = $clubs_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($club['club_name']); ?></strong></td>
                                <td><?php echo $club['president_name'] ? htmlspecialchars($club['president_name']) : '<span style="color: #a0aec0; font-style: italic;">No President</span>'; ?></td>
                                <td>
                                    <?php if ($club['isActive'] == 1): ?><span class="status-badge status-active">Active</span>
                                    <?php else: ?><span class="status-badge status-inactive">Inactive</span><?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-links">
                                        <button class="btn-sm btn-edit">Edit</button>
                                        <?php if ($club['isActive'] == 0): ?><button class="btn-sm btn-approve">Approve</button><?php endif; ?>
                                        <form method="POST" action="admin_dashboard.php" style="display:inline;" onsubmit="return confirm('🚨 Are you absolutely sure you want to delete this Club? All events and memberships tied to this club will also be permanently deleted!');">
                                            <input type="hidden" name="delete_club_id" value="<?php echo $club['club_id']; ?>">
                                            <button type="submit" class="btn-sm btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center; color: #718096;">No clubs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="section-header">
            <h2>📅 Manage Events</h2>
            <button class="btn-add">+ Add New Event</button>
        </div>
        <div class="table-card">
            <table>
                <thead><tr><th>Event Name</th><th>Organizing Club</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if ($events_result && $events_result->num_rows > 0): ?>
                        <?php while($event = $events_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($event['event_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($event['club_name']); ?></td>
                                <td><?php echo date("d M Y", strtotime($event['date'])); ?></td>
                                <td>
                                    <div class="action-links">
                                        <button class="btn-sm btn-edit">Edit</button>
                                        <form method="POST" action="admin_dashboard.php" style="display:inline;" onsubmit="return confirm('⚠️ Are you sure you want to delete this Event? This cannot be undone.');">
                                            <input type="hidden" name="delete_event_id" value="<?php echo $event['event_id']; ?>">
                                            <button type="submit" class="btn-sm btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center; color: #718096;">No events found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>