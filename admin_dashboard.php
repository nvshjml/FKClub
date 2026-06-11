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

$current_page = basename($_SERVER['PHP_SELF']);

$total_students = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE role IN ('Student', 'Committee') AND account_status = 'Approved'")->fetch_assoc()['total'];
$total_clubs = $conn->query("SELECT COUNT(*) AS total FROM CLUB WHERE isActive = 1")->fetch_assoc()['total'] ?? 0;
$total_events = $conn->query("SELECT COUNT(*) AS total FROM EVENT WHERE date >= CURDATE()")->fetch_assoc()['total'] ?? 0;

// FIXED: Count pending club memberships instead of pending users
$pending_approvals_query = $conn->query("SELECT COUNT(*) AS total FROM CLUB_MEMBERSHIP WHERE status = 'Pending'");
$pending_approvals = $pending_approvals_query ? $pending_approvals_query->fetch_assoc()['total'] : 0;

$role_query = $conn->query("SELECT role, COUNT(*) as count FROM `USER` GROUP BY role");
$role_labels = []; $role_data = [];
while($row = $role_query->fetch_assoc()) { $role_labels[] = $row['role']; $role_data[] = $row['count']; }

$recent_users_sql = "SELECT user_id, name, email, account_status, role FROM `USER` ORDER BY user_id DESC LIMIT 5";
$recent_users_result = $conn->query($recent_users_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>General Overview - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: #e2e8f0; display: flex; min-height: 100vh; }
        
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; width: calc(100% - 260px); }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;}
        .page-header h1 { color: #1a202c; font-size: 28px; font-weight: 700; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: center; border-bottom: 4px solid #3182ce; }
        .stat-card h3 { color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        .stat-card .number { font-size: 32px; font-weight: 800; color: #2d3748; }

        .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .action-card { background: white; border-radius: 12px; padding: 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); flex-wrap: wrap; gap: 15px;}
        .action-card div h3 { font-size: 18px; color: #2d3748; margin-bottom: 5px; }
        .action-card div p { font-size: 14px; color: #718096; }
        .btn-hub { background: #3182ce; color: white; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; transition: 0.2s; white-space: nowrap;}
        .btn-hub:hover { background: #2b6cb0; }
        .btn-hub-warning { background: #dd6b20; }
        .btn-hub-warning:hover { background: #c05621; }
        
        .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .chart-card h3 { color: #2d3748; font-size: 16px; margin-bottom: 20px; border-bottom: 2px solid #edf2f7; padding-bottom: 10px;}
        .chart-container { position: relative; height: 250px; width: 100%; }

        .table-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 30px; }
        .table-header { background: #f8fafc; padding: 20px; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #2d3748; font-size: 16px;}
        
        .table-responsive { width: 100%; overflow-x: auto; }
        
        table { width: 100%; border-collapse: collapse; text-align: left; min-width: 600px; }
        th { padding: 15px 20px; font-size: 12px; color: #718096; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
        td { padding: 15px 20px; color: #4a5568; border-bottom: 1px solid #edf2f7; font-size: 14px; white-space: nowrap; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-active { background: #c6f6d5; color: #22543d; }
        .status-pending { background: #feebc8; color: #744210; }
        .status-rejected { background: #fed7d7; color: #822727; }

        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
            .action-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .main-content { margin-left: 200px; width: calc(100% - 200px); padding: 20px; }
            .stats-grid { grid-template-columns: 1fr; }
            .page-header h1 { font-size: 22px; }
            .action-card { flex-direction: column; align-items: flex-start; }
            .btn-hub { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>General User Overview</h1>
            <span style="color: #718096; font-weight: 500; background: white; padding: 8px 15px; border-radius: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">Admin: <?php echo htmlspecialchars($_SESSION['name']); ?></span>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card"><h3>Registered Students</h3><div class="number"><?php echo $total_students; ?></div></div>
            <div class="stat-card" style="border-bottom-color: #38a169;"><h3>Active Clubs</h3><div class="number"><?php echo $total_clubs; ?></div></div>
            <div class="stat-card" style="border-bottom-color: #805ad5;"><h3>Upcoming Events</h3><div class="number"><?php echo $total_events; ?></div></div>
            <div class="stat-card" style="border-bottom-color: #dd6b20;"><h3>Pending Approvals</h3><div class="number"><?php echo $pending_approvals; ?></div></div>
        </div>

        <div class="action-grid">
            <div class="action-card">
                <div>
                    <h3>User Management</h3>
                    <p>Register users, edit profiles, and clean up inactive accounts.</p>
                </div>
                <a href="admin_manage_users.php" class="btn-hub">Manage Users</a>
            </div>
            <div class="action-card">
                <div>
                    <h3>System Approvals</h3>
                    <p>Review and approve new student accounts and club join requests.</p>
                </div>
                <a href="pending_approvals.php" class="btn-hub btn-hub-warning">Review Approvals (<?php echo $pending_approvals; ?>)</a>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h3>👥 System Users by Role</h3>
                <div class="chart-container"><canvas id="roleChart"></canvas></div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-header">🆕 Recent User Registrations</div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Matrix ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_users_result && $recent_users_result->num_rows > 0): ?>
                            <?php while($user = $recent_users_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($user['user_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><span style="background:#edf2f7; padding:4px 8px; border-radius:6px; font-size:12px; font-weight:600;"><?php echo htmlspecialchars($user['role']); ?></span></td>
                                    <td>
                                        <span class="status-badge <?php echo ($user['account_status'] == 'Approved') ? 'status-active' : (($user['account_status'] == 'Pending') ? 'status-pending' : 'status-rejected'); ?>">
                                            <?php echo htmlspecialchars($user['account_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; padding: 30px;">No recent registrations.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = "#718096";

        new Chart(document.getElementById('roleChart'), { 
            type: 'bar', 
            data: { labels: <?php echo json_encode($role_labels); ?>, datasets: [{ label: 'Users', data: <?php echo json_encode($role_data); ?>, backgroundColor: ['#3182ce', '#805ad5', '#38a169'], borderRadius: 6 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    </script>
</body>
</html>