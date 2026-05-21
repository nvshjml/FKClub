<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

if (isset($_POST['delete_club_id'])) {
    $delete_id = $_POST['delete_club_id'];
    $conn->prepare("DELETE FROM CLUB WHERE club_id = ?")->execute([$delete_id]);
    header("Location: admin_dashboard.php"); exit();
}

if (isset($_POST['delete_event_id'])) {
    $delete_id = $_POST['delete_event_id'];
    $conn->prepare("DELETE FROM EVENT WHERE event_id = ?")->execute([$delete_id]);
    header("Location: admin_dashboard.php"); exit();
}

$stmt1 = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE role IN ('Student', 'Committee') AND account_status = 'Approved'");
$total_students = $stmt1->fetch_assoc()['total'];

$stmt2 = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE account_status = 'Pending'");
$pending_count = $stmt2->fetch_assoc()['total'];

$stmt3 = $conn->query("SELECT COUNT(*) AS total FROM CLUB WHERE isActive = 1");
$total_clubs = $stmt3 ? $stmt3->fetch_assoc()['total'] : 0; 

$stmt4 = $conn->query("SELECT COUNT(*) AS total FROM EVENT");
$total_events = $stmt4 ? $stmt4->fetch_assoc()['total'] : 0;

$clubs_sql = "SELECT c.club_id, c.club_name, c.isActive, u.name AS president_name FROM CLUB c LEFT JOIN COMMITTEE com ON c.club_id = com.club_id AND com.position = 'President' LEFT JOIN `USER` u ON com.user_id = u.user_id ORDER BY c.club_name ASC";
$clubs_result = $conn->query($clubs_sql);

$events_sql = "SELECT e.event_id, e.event_name, e.date, c.club_name FROM EVENT e JOIN CLUB c ON e.club_id = c.club_id ORDER BY e.date ASC";
$events_result = $conn->query($events_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; margin: 0; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; width: calc(100% - 260px); box-sizing: border-box; }
        .welcome-card { background-color: rgba(255, 255, 255, 0.95); padding: 30px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #3182ce; }
        .welcome-card h2 { color: #1a202c; font-size: 24px; margin-bottom: 8px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .stat-card { background-color: white; padding: 25px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; border-bottom: 5px solid transparent; }
        .border-blue { border-bottom-color: #3182ce; } .border-orange { border-bottom-color: #dd6b20; } .border-green { border-bottom-color: #38a169; } .border-purple { border-bottom-color: #805ad5; }
        .stat-card h3 { color: #a0aec0; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .number { font-size: 2.8rem; font-weight: 700; color: #2d3748; margin-top: 10px; }
        .section-header { display: flex; justify-content: space-between; margin-bottom: 15px; margin-top: 40px; }
        .btn-add { background: #3182ce; color: white; padding: 10px 15px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; }
        .table-card { background: white; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 16px 20px; font-size: 14px; color: #4a5568; font-weight: 600; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        td { padding: 16px 20px; color: #2d3748; border-bottom: 1px solid #edf2f7; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .status-active { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .status-inactive { background: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        .action-links { display: flex; gap: 8px; }
        .btn-sm { padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; }
        .btn-edit { background: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; }
        .btn-delete { background: #fff5f5; color: #c53030; border: 1px solid #fed7d7; }
        .btn-approve { background: #f0fff4; color: #276749; border: 1px solid #c6f6d5; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

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

        <div class="section-header"><h2>🏆 Manage Clubs</h2><button class="btn-add">+ Add New Club</button></div>
        <div class="table-card">
            <table>
                <thead><tr><th>Club Name</th><th>President Name</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if ($clubs_result && $clubs_result->num_rows > 0): ?>
                        <?php while($club = $clubs_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($club['club_name']); ?></strong></td>
                                <td><?php echo $club['president_name'] ? htmlspecialchars($club['president_name']) : '<span style="color:#a0aec0;font-style:italic;">No President</span>'; ?></td>
                                <td><span class="status-badge <?php echo $club['isActive'] ? 'status-active' : 'status-inactive'; ?>"><?php echo $club['isActive'] ? 'Active' : 'Inactive'; ?></span></td>
                                <td>
                                    <div class="action-links">
                                        <button class="btn-sm btn-edit">Edit</button>
                                        <?php if ($club['isActive'] == 0): ?><button class="btn-sm btn-approve">Approve</button><?php endif; ?>
                                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this Club?');">
                                            <input type="hidden" name="delete_club_id" value="<?php echo $club['club_id']; ?>">
                                            <button type="submit" class="btn-sm btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="section-header"><h2>📅 Manage Events</h2><button class="btn-add">+ Add New Event</button></div>
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
                                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this Event?');">
                                            <input type="hidden" name="delete_event_id" value="<?php echo $event['event_id']; ?>">
                                            <button type="submit" class="btn-sm btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>