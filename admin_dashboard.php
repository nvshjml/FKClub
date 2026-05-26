<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

// Force no-cache to prevent "Back" button after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

// ---------------------------------------------------------
// HANDLE DELETIONS
// ---------------------------------------------------------
if (isset($_POST['delete_club_id'])) {
    $conn->prepare("DELETE FROM CLUB WHERE club_id = ?")->execute([$_POST['delete_club_id']]);
    header("Location: admin_dashboard.php?msg=club_deleted"); 
    exit();
}
if (isset($_POST['delete_event_id'])) {
    $conn->prepare("DELETE FROM EVENT WHERE event_id = ?")->execute([$_POST['delete_event_id']]);
    header("Location: admin_dashboard.php?msg=event_deleted"); 
    exit();
}

// ---------------------------------------------------------
// 1. FETCH SUMMARY STATISTICS
// ---------------------------------------------------------
$total_students = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE role IN ('Student', 'Committee') AND account_status = 'Approved'")->fetch_assoc()['total'];
$pending_count = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE account_status = 'Pending'")->fetch_assoc()['total'];
$total_clubs = $conn->query("SELECT COUNT(*) AS total FROM CLUB WHERE isActive = 1")->fetch_assoc()['total'] ?? 0;
$total_events = $conn->query("SELECT COUNT(*) AS total FROM EVENT")->fetch_assoc()['total'] ?? 0;

// ---------------------------------------------------------
// 2. FETCH CHART DATA
// ---------------------------------------------------------
$role_query = $conn->query("SELECT role, COUNT(*) as count FROM `USER` GROUP BY role");
$role_labels = []; $role_data = [];
while($row = $role_query->fetch_assoc()) { $role_labels[] = $row['role']; $role_data[] = $row['count']; }

$status_query = $conn->query("SELECT account_status, COUNT(*) as count FROM `USER` GROUP BY account_status");
$status_labels = []; $status_data = [];
while($row = $status_query->fetch_assoc()) { $status_labels[] = $row['account_status']; $status_data[] = $row['count']; }

$club_events_query = $conn->query("SELECT c.club_name, COUNT(e.event_id) as count FROM CLUB c LEFT JOIN EVENT e ON c.club_id = e.club_id GROUP BY c.club_name");
$club_labels = []; $club_data = [];
while($row = $club_events_query->fetch_assoc()) {
    $name = strlen($row['club_name']) > 15 ? substr($row['club_name'], 0, 15) . '...' : $row['club_name'];
    $club_labels[] = $name; $club_data[] = $row['count'];
}

// ---------------------------------------------------------
// 3. FETCH TABLE DATA
// ---------------------------------------------------------
// Clubs Table
$clubs_sql = "
    SELECT c.club_id, c.club_name, c.isActive, u.name AS president_name 
    FROM CLUB c 
    LEFT JOIN committee com ON c.club_id = com.club_id AND com.position = 'President' 
    LEFT JOIN `USER` u ON com.user_id = u.user_id 
    ORDER BY c.club_name ASC
";
$clubs_result = $conn->query($clubs_sql);

// Events Table (Assuming time and venue columns exist based on UI)
// Fallback applied in case the DB structure differs slightly
$events_sql = "
    SELECT e.event_id, e.event_name, e.date, 
           IFNULL(e.time, 'N/A') as time, 
           IFNULL(e.venue, 'N/A') as venue, 
           c.club_name 
    FROM EVENT e 
    JOIN CLUB c ON e.club_id = c.club_id 
    ORDER BY e.date ASC
";
$events_result = $conn->query($events_sql);

// Recent Users Table
$recent_users_sql = "SELECT user_id, name, email, account_status FROM `USER` ORDER BY user_id DESC LIMIT 5";
$recent_users_result = $conn->query($recent_users_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: #e2e8f0; display: flex; min-height: 100vh; }
        
        .main-content { margin-left: 260px; flex-grow: 1; padding: 30px; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center; }
        .stat-card h3 { color: #a0aec0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        .stat-card .number { font-size: 24px; font-weight: 700; color: #2d3748; }
        
        /* Charts Grid */
        .charts-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .chart-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; flex-direction: column; align-items: center; }
        .chart-card h3 { color: #4a5568; font-size: 13px; margin-bottom: 15px; width: 100%; text-align: center; font-weight: 600; }
        .chart-container { position: relative; height: 180px; width: 100%; }

        /* Unified Section Headers & Tables */
        .section-header { 
            display: flex; justify-content: space-between; align-items: center; 
            background: #e2e8f0; padding: 12px 20px; 
            border-radius: 8px 8px 0 0; margin-top: 25px; 
            border: 1px solid #cbd5e0; border-bottom: none;
        }
        .section-header h2 { color: #2d3748; font-size: 15px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;}
        .btn-add { background: #3182ce; color: white; padding: 6px 12px; border-radius: 4px; border: none; font-weight: 600; font-size: 12px; cursor: pointer; text-decoration: none; }
        .btn-add:hover { background: #2b6cb0; }
        
        .table-card { background: white; border-radius: 0 0 8px 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 30px; border: 1px solid #cbd5e0; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 12px 20px; font-size: 11px; color: #718096; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        td { padding: 12px 20px; color: #4a5568; border-bottom: 1px solid #edf2f7; font-size: 13px; vertical-align: middle; }
        tr:hover { background-color: #f8fafc; }
        
        /* Badges */
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .status-active { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .status-inactive { background: #fed7d7; color: #822727; border: 1px solid #feb2b2; }

        /* Action Buttons styling matching the screenshot */
        .action-links { display: flex; gap: 6px; }
        .btn-sm { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; background: transparent; }
        
        .btn-edit { color: #3182ce; border: 1px solid #bee3f8; }
        .btn-edit:hover { background: #ebf8ff; }
        
        .btn-delete { color: #e53e3e; border: 1px solid #fed7d7; }
        .btn-delete:hover { background: #fff5f5; }
        
        .btn-manage { color: #3182ce; background: #ebf8ff; border: 1px solid #bee3f8; }
        .btn-manage:hover { background: #bee3f8; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        
        <div class="stats-grid">
            <div class="stat-card"><h3>Total Students</h3><div class="number"><?php echo $total_students; ?></div></div>
            <div class="stat-card"><h3>Pending Approvals</h3><div class="number"><?php echo $pending_count; ?></div></div>
            <div class="stat-card"><h3>Active Clubs</h3><div class="number"><?php echo $total_clubs; ?></div></div>
            <div class="stat-card"><h3>Upcoming Events</h3><div class="number"><?php echo $total_events; ?></div></div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h3>👥 System Users by Role</h3>
                <div class="chart-container"><canvas id="roleChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h3>🔒 Registration Statuses</h3>
                <div class="chart-container"><canvas id="statusChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h3>📅 Events Hosted per Club</h3>
                <div class="chart-container"><canvas id="clubEventsChart"></canvas></div>
            </div>
        </div>

        <div class="section-header">
            <h2>🏆 Manage Clubs</h2>
            <a href="admin_add_club.php" class="btn-add">+ Add New Club</a>
        </div>
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Club Name</th>
                        <th>President Name</th>
                        <th>Status</th>
                        <th>Actions</th>
                        <th>Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($clubs_result && $clubs_result->num_rows > 0): ?>
                        <?php while($club = $clubs_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($club['club_name']); ?></strong></td>
                                <td><?php echo $club['president_name'] ? htmlspecialchars($club['president_name']) : '<span style="color:#a0aec0;">No President</span>'; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $club['isActive'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $club['isActive'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-links">
                                        <a href="admin_edit_club.php?club_id=<?php echo $club['club_id']; ?>" class="btn-sm btn-edit">Edit</a>
                                        <form method="POST" action="" style="display:inline; margin:0;" onsubmit="return confirm('Delete this Club?');">
                                            <input type="hidden" name="delete_club_id" value="<?php echo $club['club_id']; ?>">
                                            <button type="submit" class="btn-sm btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                                <td>
                                    <a href="admin_manage_club.php?club_id=<?php echo $club['club_id']; ?>" class="btn-sm btn-manage">View Members</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center;">No clubs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="section-header">
            <h2>📅 Manage Events</h2>
            <a href="admin_add_event.php" class="btn-add">+ Add New Event</a>
        </div>
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Event Name</th>
                        <th>Organizing Club</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Venue</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($events_result && $events_result->num_rows > 0): ?>
                        <?php while($event = $events_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($event['event_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($event['club_name']); ?></td>
                                <td><?php echo date("d M Y", strtotime($event['date'])); ?></td>
                                <td><?php echo htmlspecialchars($event['time']); ?></td>
                                <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                <td>
                                    <div class="action-links">
                                        <a href="admin_edit_event.php?event_id=<?php echo $event['event_id']; ?>" class="btn-sm btn-edit">Edit</a>
                                        <form method="POST" action="" style="display:inline; margin:0;" onsubmit="return confirm('Delete this Event?');">
                                            <input type="hidden" name="delete_event_id" value="<?php echo $event['event_id']; ?>">
                                            <button type="submit" class="btn-sm btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center;">No events found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="section-header" style="justify-content: flex-start;">
            <h2>🆕 Recent User Registrations</h2>
        </div>
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Matrix ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_users_result && $recent_users_result->num_rows > 0): ?>
                        <?php while($user = $recent_users_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['user_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo ($user['account_status'] == 'Approved') ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo htmlspecialchars($user['account_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center;">No recent registrations found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <script>
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = "#718096";

        // Chart 1: Bar
        new Chart(document.getElementById('roleChart'), { 
            type: 'bar', 
            data: { labels: <?php echo json_encode($role_labels); ?>, datasets: [{ data: <?php echo json_encode($role_data); ?>, backgroundColor: ['#3182ce', '#805ad5', '#38a169'], borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });

        // Chart 2: Pie
        new Chart(document.getElementById('statusChart'), { 
            type: 'pie', 
            data: { labels: <?php echo json_encode($status_labels); ?>, datasets: [{ data: <?php echo json_encode($status_data); ?>, backgroundColor: ['#38a169', '#dd6b20', '#e53e3e'] }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
        });

        // Chart 3: Doughnut
        new Chart(document.getElementById('clubEventsChart'), { 
            type: 'doughnut', 
            data: { labels: <?php echo json_encode($club_labels); ?>, datasets: [{ data: <?php echo json_encode($club_data); ?>, backgroundColor: ['#4299e1', '#4fd1c5', '#ecc94b', '#f687b3', '#a0aec0'] }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }, cutout: '70%' }
        });
    </script>
</body>
</html>