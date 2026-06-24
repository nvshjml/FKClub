<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

$total_events_query = $conn->query("SELECT COUNT(*) AS total FROM event");
$total_events = $total_events_query->fetch_assoc()['total'];

$total_registrations_query = $conn->query("SELECT COUNT(*) AS total FROM event_registration WHERE status != 'Cancelled'");
$total_registrations = $total_registrations_query->fetch_assoc()['total'];

$waitlisted_query = $conn->query("SELECT COUNT(*) AS total FROM event_registration WHERE status = 'Waitlisted'");
$total_waitlisted = $waitlisted_query->fetch_assoc()['total'];

$popular_events_query = $conn->query("
    SELECT e.event_name, COUNT(er.register_id) as reg_count 
    FROM event e 
    LEFT JOIN event_registration er ON e.event_id = er.event_id AND er.status != 'Cancelled'
    GROUP BY e.event_id, e.event_name 
    ORDER BY reg_count DESC 
    LIMIT 5
");

$popular_labels = [];
$popular_data = [];
while($row = $popular_events_query->fetch_assoc()) {
    $name = strlen($row['event_name']) > 20 ? substr($row['event_name'], 0, 20) . '...' : $row['event_name'];
    $popular_labels[] = $name; 
    $popular_data[] = $row['reg_count'];
}

$monthly_trends_query = $conn->query("
    SELECT DATE_FORMAT(date, '%b %Y') as month_year, COUNT(event_id) as event_count 
    FROM event 
    GROUP BY DATE_FORMAT(date, '%Y-%m'), month_year 
    ORDER BY date ASC 
    LIMIT 6
");

$trend_labels = [];
$trend_data = [];
while($row = $monthly_trends_query->fetch_assoc()) {
    $trend_labels[] = $row['month_year']; 
    $trend_data[] = $row['event_count'];
}

$events_per_club_query = $conn->query("
    SELECT c.club_name, COUNT(e.event_id) as event_count 
    FROM club c 
    LEFT JOIN event e ON c.club_id = e.club_id 
    GROUP BY c.club_id, c.club_name 
    ORDER BY event_count DESC
");

$club_event_labels = [];
$club_event_data = [];
while($row = $events_per_club_query->fetch_assoc()) {
    $name = strlen($row['club_name']) > 20 ? substr($row['club_name'], 0, 20) . '...' : $row['club_name'];
    $club_event_labels[] = $name; 
    $club_event_data[] = $row['event_count'];
}

$events_sql = "
    SELECT e.event_id, e.event_name, e.date, IFNULL(e.time, 'N/A') as time, e.max_cap, c.club_name,
           (SELECT COUNT(*) FROM event_registration er WHERE er.event_id = e.event_id AND er.status = 'Registered') as registered_count,
           (SELECT COUNT(*) FROM event_registration er WHERE er.event_id = e.event_id AND er.status = 'Waitlisted') as waitlist_count
    FROM event e 
    JOIN club c ON e.club_id = c.club_id 
    ORDER BY e.date ASC
";
$events_result = $conn->query($events_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Analytics - Admin</title>
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
        .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .chart-card h3 { color: #2d3748; font-size: 16px; margin-bottom: 20px; border-bottom: 2px solid #edf2f7; padding-bottom: 10px;}
        .chart-container { position: relative; height: 300px; width: 100%; display: flex; justify-content: center;}
        .section-header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 20px; border-radius: 8px 8px 0 0; border: 1px solid #cbd5e0; border-bottom: 1px solid #e2e8f0; }
        .section-header h2 { color: #2d3748; font-size: 15px; font-weight: 700; margin: 0; }
        .table-card { background: white; border-radius: 0 0 8px 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 30px; border: 1px solid #cbd5e0; border-top: none; }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; min-width: 800px; }
        th { padding: 15px 20px; font-size: 12px; color: #718096; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
        td { padding: 15px 20px; color: #4a5568; border-bottom: 1px solid #edf2f7; font-size: 14px; white-space: nowrap; }
        .capacity-bar { background: #e2e8f0; border-radius: 10px; height: 8px; width: 100px; margin-top: 5px; overflow: hidden;}
        .capacity-fill { background: #38a169; height: 100%; border-radius: 10px; }
        .capacity-fill.full { background: #dd6b20; }

        @media (max-width: 1024px) { .charts-grid { grid-template-columns: 1fr; } }
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
            <h1>Event Analytics</h1>
            <span style="color: #718096; font-weight: 500; background: white; padding: 8px 15px; border-radius: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">Admin: <?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><h3>Total Events Created</h3><div class="number"><?php echo $total_events; ?></div></div>
            <div class="stat-card" style="border-bottom-color: #38a169;"><h3>Total Registrations</h3><div class="number"><?php echo $total_registrations; ?></div></div>
            <div class="stat-card" style="border-bottom-color: #dd6b20;"><h3>Students Waitlisted</h3><div class="number"><?php echo $total_waitlisted; ?></div></div>
        </div>

        <div class="charts-grid">
            <div class="chart-card" style="grid-column: 1 / -1;">
                <h3>📈 Monthly Event Activity Trends</h3>
                <div class="chart-container" style="height: 250px;"><canvas id="trendChart"></canvas></div>
            </div>
            
            <div class="chart-card">
                <h3>🔥 Top 5 Popular Events (by Registration)</h3>
                <div class="chart-container"><canvas id="popularChart"></canvas></div>
            </div>
            
            <div class="chart-card">
                <h3>📊 Events Organized per Club</h3>
                <div class="chart-container"><canvas id="clubEventsChart"></canvas></div>
            </div>
        </div>

        <div class="section-header">
            <h2>📅 Event Capacity Overview</h2>
        </div>
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Organizing Club</th>
                            <th>Date & Time</th>
                            <th>Participants</th>
                            <th>Waitlist</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($events_result && $events_result->num_rows > 0): ?>
                            <?php while($event = $events_result->fetch_assoc()): 
                                $max_cap = isset($event['max_cap']) ? $event['max_cap'] : 100;
                                $reg_cnt = $event['registered_count'] ?: 0;
                                $percent = ($max_cap > 0) ? ($reg_cnt / $max_cap) * 100 : 0;
                                $is_full = ($reg_cnt >= $max_cap);
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($event['event_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($event['club_name']); ?></td>
                                    <td>
                                        <?php echo date("d M Y", strtotime($event['date'])); ?><br>
                                        <span style="color: #718096; font-size: 12px;"><?php echo date("h:i A", strtotime($event['time'])); ?></span>
                                    </td>
                                    <td>
                                        <?php echo $reg_cnt; ?> / <?php echo $max_cap; ?> 
                                        <?php if($is_full) echo "<span style='color:#dd6b20; font-size:12px; font-weight:bold;'>(FULL)</span>"; ?>
                                        <div class="capacity-bar"><div class="capacity-fill <?php echo $is_full ? 'full' : ''; ?>" style="width: <?php echo min($percent, 100); ?>%;"></div></div>
                                    </td>
                                    <td>
                                        <?php if($event['waitlist_count'] > 0): ?>
                                            <span style="background: #feebc8; color: #dd6b20; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold;"><?php echo $event['waitlist_count']; ?> Waiting</span>
                                        <?php else: ?>
                                            <span style="color: #a0aec0;">0</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; padding: 30px;">No events found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = "#718096";

        new Chart(document.getElementById('trendChart'), { 
            type: 'line', 
            data: { 
                labels: <?php echo json_encode($trend_labels); ?>, 
                datasets: [{ 
                    label: 'Number of Events', 
                    data: <?php echo json_encode($trend_data); ?>, 
                    borderColor: '#3182ce', 
                    backgroundColor: 'rgba(49, 130, 206, 0.1)', 
                    borderWidth: 2, 
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#3182ce',
                    pointRadius: 4
                }] 
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });

        new Chart(document.getElementById('popularChart'), { 
            type: 'bar', 
            data: { 
                labels: <?php echo json_encode($popular_labels); ?>, 
                datasets: [{ 
                    label: 'Registrations', 
                    data: <?php echo json_encode($popular_data); ?>, 
                    backgroundColor: ['#4fd1c5', '#3182ce', '#805ad5', '#dd6b20', '#38a169'], 
                    borderRadius: 4 
                }] 
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });

        new Chart(document.getElementById('clubEventsChart'), { 
            type: 'doughnut', 
            data: { 
                labels: <?php echo json_encode($club_event_labels); ?>, 
                datasets: [{ 
                    data: <?php echo json_encode($club_event_data); ?>, 
                    backgroundColor: ['#38a169', '#dd6b20', '#3182ce', '#e53e3e', '#805ad5'], 
                    borderWidth: 0 
                }] 
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } }, cutout: '60%' }
        });
    </script>
</body>
</html>