<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

// Force no-cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
$message = "";

// ---------------------------------------------------------
// HANDLE DELETE CLUB
// ---------------------------------------------------------
if (isset($_POST['delete_club_id'])) {
    $del_stmt = $conn->prepare("DELETE FROM CLUB WHERE club_id = ?");
    if ($del_stmt->execute([$_POST['delete_club_id']])) {
        $message = "<div class='alert alert-success' style='background:#c6f6d5; color:#22543d; padding:12px; border-radius:8px; margin-bottom:20px;'>✅ Club deleted successfully!</div>";
    }
}

// ---------------------------------------------------------
// FETCH MODULE 2 SUMMARY STATISTICS (Req 4a)
// ---------------------------------------------------------
$total_clubs_query = $conn->query("SELECT COUNT(*) AS total FROM CLUB");
$total_clubs = $total_clubs_query->fetch_assoc()['total'];

$active_clubs_query = $conn->query("SELECT COUNT(*) AS total FROM CLUB WHERE isActive = 1");
$active_clubs = $active_clubs_query->fetch_assoc()['total'];

// FIXED: Count distinct students from BOTH membership and committee tables
$students_involved_query = $conn->query("
    SELECT COUNT(DISTINCT user_id) AS total FROM (
        SELECT user_id FROM CLUB_MEMBERSHIP WHERE status = 'Approved'
        UNION
        SELECT user_id FROM COMMITTEE
    ) AS all_members
");
$students_involved = $students_involved_query->fetch_assoc()['total'];

// ---------------------------------------------------------
// FETCH CHART DATA: Distribution of students across clubs
// ---------------------------------------------------------
// FIXED: Join club table with a combined list of members and committee
$distribution_query = $conn->query("
    SELECT c.club_name, COUNT(DISTINCT combined.user_id) as member_count 
    FROM CLUB c 
    LEFT JOIN (
        SELECT club_id, user_id FROM CLUB_MEMBERSHIP WHERE status = 'Approved'
        UNION
        SELECT club_id, user_id FROM COMMITTEE
    ) as combined ON c.club_id = combined.club_id
    GROUP BY c.club_id, c.club_name
");

$club_labels = [];
$member_data = [];
while($row = $distribution_query->fetch_assoc()) {
    $name = strlen($row['club_name']) > 20 ? substr($row['club_name'], 0, 20) . '...' : $row['club_name'];
    $club_labels[] = $name; 
    $member_data[] = $row['member_count'];
}

// ---------------------------------------------------------
// FETCH TABLE DATA FOR MANAGEMENT
// ---------------------------------------------------------
$clubs_sql = "
    SELECT c.club_id, c.club_name, c.isActive, c.advisor_name, u.name AS president_name 
    FROM CLUB c 
    LEFT JOIN committee com ON c.club_id = com.club_id AND com.position = 'President' 
    LEFT JOIN `USER` u ON com.user_id = u.user_id 
    ORDER BY c.club_name ASC
";
$clubs_result = $conn->query($clubs_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Club Analytics - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: #e2e8f0; display: flex; min-height: 100vh; }
        
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; width: calc(100% - 260px); }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;}
        .page-header h1 { color: #1a202c; font-size: 28px; font-weight: 700; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: center; border-bottom: 4px solid #3182ce; }
        .stat-card h3 { color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        .stat-card .number { font-size: 32px; font-weight: 800; color: #2d3748; }

        .charts-grid { display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .chart-card h3 { color: #2d3748; font-size: 16px; margin-bottom: 20px; border-bottom: 2px solid #edf2f7; padding-bottom: 10px;}
        .chart-container { position: relative; height: 300px; width: 100%; display: flex; justify-content: center;}

        .section-header { display: flex; justify-content: space-between; align-items: center; background: #e2e8f0; padding: 12px 20px; border-radius: 8px 8px 0 0; border: 1px solid #cbd5e0; border-bottom: none; }
        .section-header h2 { color: #2d3748; font-size: 15px; font-weight: 700; margin: 0; }
        .btn-add { background: #3182ce; color: white; padding: 8px 15px; border-radius: 6px; border: none; font-weight: 600; font-size: 13px; cursor: pointer; text-decoration: none; }
        .btn-add:hover { background: #2b6cb0; }

        .table-card { background: white; border-radius: 0 0 8px 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 30px; border: 1px solid #cbd5e0; }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; min-width: 800px; }
        th { padding: 15px 20px; font-size: 12px; color: #718096; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
        td { padding: 15px 20px; color: #4a5568; border-bottom: 1px solid #edf2f7; font-size: 14px; white-space: nowrap; }
        
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-active { background: #c6f6d5; color: #22543d; }
        .status-inactive { background: #fed7d7; color: #822727; }

        .action-links { display: flex; gap: 6px; }
        .btn-sm { padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer; text-decoration: none; background: transparent; }
        .btn-edit { color: #3182ce; border: 1px solid #bee3f8; background: #ebf8ff;}
        .btn-edit:hover { background: #bee3f8; }
        .btn-manage { color: #805ad5; border: 1px solid #d6bcfa; background: #faf5ff;}
        .btn-manage:hover { background: #e9d8fd; }
        .btn-delete { color: #e53e3e; border: 1px solid #fed7d7; background: #fff5f5;}
        .btn-delete:hover { background: #fed7d7; }

        @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .main-content { margin-left: 200px; width: calc(100% - 200px); padding: 20px; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>Club Management Analytics</h1>
            <span style="color: #718096; font-weight: 500; background: white; padding: 8px 15px; border-radius: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">Admin: Ajmal</span>
        </div>
        
        <?php echo $message; ?>

        <div class="stats-grid">
            <div class="stat-card"><h3>Total Clubs</h3><div class="number"><?php echo $total_clubs; ?></div></div>
            <div class="stat-card" style="border-bottom-color: #38a169;"><h3>Active Clubs</h3><div class="number"><?php echo $active_clubs; ?></div></div>
            <div class="stat-card" style="border-bottom-color: #805ad5;"><h3>Students Involved</h3><div class="number"><?php echo $students_involved; ?></div></div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h3>📊 Distribution of Students Across Clubs</h3>
                <div class="chart-container"><canvas id="distributionChart"></canvas></div>
            </div>
        </div>

        <div class="section-header">
            <h2>🏆 Manage Club Information & Committees</h2>
            <a href="admin_add_club.php" class="btn-add">+ Add New Club</a>
        </div>
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Club Name</th>
                            <th>President</th>
                            <th>Advisor</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($clubs_result && $clubs_result->num_rows > 0): ?>
                            <?php while($club = $clubs_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($club['club_name']); ?></strong></td>
                                    <td><?php echo $club['president_name'] ? htmlspecialchars($club['president_name']) : '<span style="color:#a0aec0;">TBD</span>'; ?></td>
                                    <td><?php echo $club['advisor_name'] ? htmlspecialchars($club['advisor_name']) : '<span style="color:#a0aec0;">TBD</span>'; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $club['isActive'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $club['isActive'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-links">
                                            <a href="admin_edit_club.php?club_id=<?php echo $club['club_id']; ?>" class="btn-sm btn-edit">Edit Details</a>
                                            <a href="admin_manage_club.php?club_id=<?php echo $club['club_id']; ?>" class="btn-sm btn-manage">Manage Committee</a>
                                            <form method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to completely delete this club?');">
                                                <input type="hidden" name="delete_club_id" value="<?php echo $club['club_id']; ?>">
                                                <button type="submit" class="btn-sm btn-delete">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; padding: 30px;">No clubs found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = "#718096";

        new Chart(document.getElementById('distributionChart'), { 
            type: 'pie', 
            data: { 
                labels: <?php echo json_encode($club_labels); ?>, 
                datasets: [{ 
                    data: <?php echo json_encode($member_data); ?>, 
                    backgroundColor: ['#3182ce', '#38a169', '#dd6b20', '#e53e3e', '#805ad5', '#4fd1c5'], 
                    borderWidth: 1,
                    borderColor: '#ffffff'
                }] 
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { 
                    legend: { position: 'right', labels: { boxWidth: 15, padding: 20 } },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) { label += ': '; }
                                label += context.raw + ' student(s)';
                                return label;
                            }
                        }
                    }
                } 
            }
        });
    </script>
</body>
</html>