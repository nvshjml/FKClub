<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

// Handle Deletions
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

// ---------------------------------------------------------
// 1. FETCH SUMMARY STATISTICS (Top Cards)
// ---------------------------------------------------------
$stmt1 = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE role IN ('Student', 'Committee') AND account_status = 'Approved'");
$total_students = $stmt1->fetch_assoc()['total'];

$stmt2 = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE account_status = 'Pending'");
$pending_count = $stmt2->fetch_assoc()['total'];

$stmt3 = $conn->query("SELECT COUNT(*) AS total FROM CLUB WHERE isActive = 1");
$total_clubs = $stmt3 ? $stmt3->fetch_assoc()['total'] : 0; 

$stmt4 = $conn->query("SELECT COUNT(*) AS total FROM EVENT");
$total_events = $stmt4 ? $stmt4->fetch_assoc()['total'] : 0;

// ---------------------------------------------------------
// 2. FETCH DATA FOR GRAPHICAL CHARTS (Module 1 Req)
// ---------------------------------------------------------
// Chart A: Users by Role
$role_query = $conn->query("SELECT role, COUNT(*) as count FROM `USER` GROUP BY role");
$role_labels = []; $role_data = [];
while($row = $role_query->fetch_assoc()) {
    $role_labels[] = $row['role'];
    $role_data[] = $row['count'];
}

// Chart B: Account Statuses
$status_query = $conn->query("SELECT account_status, COUNT(*) as count FROM `USER` GROUP BY account_status");
$status_labels = []; $status_data = [];
while($row = $status_query->fetch_assoc()) {
    $status_labels[] = $row['account_status'];
    $status_data[] = $row['count'];
}

// Chart C: Events per Club
$club_events_query = $conn->query("SELECT c.club_name, COUNT(e.event_id) as count FROM CLUB c LEFT JOIN EVENT e ON c.club_id = e.club_id GROUP BY c.club_name");
$club_labels = []; $club_data = [];
while($row = $club_events_query->fetch_assoc()) {
    // Truncate long club names so they fit nicely on the chart
    $name = strlen($row['club_name']) > 15 ? substr($row['club_name'], 0, 15) . '...' : $row['club_name'];
    $club_labels[] = $name;
    $club_data[] = $row['count'];
}

// ---------------------------------------------------------
// 3. FETCH DATA FOR TABLES
// ---------------------------------------------------------
$clubs_sql = "SELECT c.club_id, c.club_name, c.isActive, u.name AS president_name FROM CLUB c LEFT JOIN COMMITTEE com ON c.club_id = com.club_id AND com.position = 'President' LEFT JOIN `USER` u ON com.user_id = u.user_id ORDER BY c.club_name ASC";
$recent_users_sql = "SELECT user_id, name, email, account_status FROM `USER` ORDER BY user_id DESC LIMIT 5";
$recent_users_result = $conn->query($recent_users_sql);
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        
        .welcome-card { background-color: rgba(255, 255, 255, 0.95); padding: 30px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #3182ce; }
        .welcome-card h2 { color: #1a202c; font-size: 24px; margin-bottom: 8px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .stat-card { background-color: white; padding: 25px; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: center; border-bottom: 5px solid transparent; }
        .border-blue { border-bottom-color: #3182ce; } .border-orange { border-bottom-color: #dd6b20; } .border-green { border-bottom-color: #38a169; } .border-purple { border-bottom-color: #805ad5; }
        .stat-card h3 { color: #a0aec0; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .number { font-size: 2.8rem; font-weight: 700; color: #2d3748; margin-top: 10px; }
        
        /* New Chart Grid Styles */
        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .chart-card { background: white; padding: 25px; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .chart-card h3 { color: #1a202c; font-size: 16px; margin-bottom: 20px; border-bottom: 2px solid #edf2f7; padding-bottom: 10px; width: 100%; text-align: center; }
        .chart-container { position: relative; height: 250px; width: 100%; }

        .section-header { display: flex; justify-content: space-between; margin-bottom: 15px; margin-top: 20px; align-items: center; }
        .section-header h2 { color: #1a202c; font-size: 20px; }
        .btn-add { background: #3182ce; color: white; padding: 10px 15px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-add:hover { background: #2b6cb0; }
        
        .table-card { background: white; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 16px 20px; font-size: 13px; color: #4a5568; font-weight: 600; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        td { padding: 16px 20px; color: #2d3748; border-bottom: 1px solid #edf2f7; font-size: 14px; }
        tr:hover { background-color: #f8fafc; }
        
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
            <p style="color: #718096;">You are logged in as an <strong>Administrator</strong>. Here is the current overview of your system.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card border-blue"><h3>Total Students</h3><div class="number"><?php echo $total_students; ?></div></div>
            <div class="stat-card border-orange"><h3>Pending Approvals</h3><div class="number"><?php echo $pending_count; ?></div></div>
            <div class="stat-card border-green"><h3>Active Clubs</h3><div class="number"><?php echo $total_clubs; ?></div></div>
            <div class="stat-card border-purple"><h3>Upcoming Events</h3><div class="number"><?php echo $total_events; ?></div></div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h3>👥 System Users by Role</h3>
                <div class="chart-container">
                    <canvas id="roleChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h3>🔒 Registration Statuses</h3>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h3>📅 Events Hosted per Club</h3>
                <div class="chart-container">
                    <canvas id="clubEventsChart"></canvas>
                </div>
            </div>
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
                                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete this Club?');">
                                            <input type="hidden" name="delete_club_id" value="<?php echo $club['club_id']; ?>">
                                            <button type="submit" class="btn-sm btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                                <td>
    <a href="admin_manage_club.php?club_id=<?php echo $club['club_id']; ?>" class="btn-sm btn-edit">View Members</a>
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
                                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this Event?');">
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
        <div class="section-header">
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
                <tr><td colspan="4" style="text-align: center; color: #718096;">No recent registrations found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
    </div>

    <script>
        // Set default font for all charts to match the UI
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = "#718096";

        // Chart 1: Bar Chart (Users by Role)
        const roleCtx = document.getElementById('roleChart').getContext('2d');
        new Chart(roleCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($role_labels); ?>,
                datasets: [{
                    label: 'Number of Users',
                    data: <?php echo json_encode($role_data); ?>,
                    backgroundColor: ['#3182ce', '#805ad5', '#38a169'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });

        // Chart 2: Pie Chart (Account Status)
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_data); ?>,
                    backgroundColor: ['#38a169', '#dd6b20', '#e53e3e'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Chart 3: Doughnut Chart (Events per Club)
        const clubEventsCtx = document.getElementById('clubEventsChart').getContext('2d');
        new Chart(clubEventsCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($club_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($club_data); ?>,
                    backgroundColor: ['#4299e1', '#4fd1c5', '#ecc94b', '#f687b3', '#a0aec0'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                cutout: '65%'
            }
        });
    </script>
</body>
</html>