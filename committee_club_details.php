<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Committee') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$message = "";
$current_page = basename($_SERVER['PHP_SELF']);

$club_id = null;
$club_name = "No Club Assigned";
$position = "N/A";
$club = [
    'club_id' => null,
    'club_name' => 'No Club Assigned',
    'description' => 'No description available.',
    'advisor_name' => 'Not Assigned',
    'isActive' => 0
];

$stmt = $conn->prepare("SELECT c.*, com.position FROM committee com JOIN club c ON com.club_id = c.club_id WHERE com.user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$club_data = $result->fetch_assoc();

if ($club_data && $club_data['club_id']) {
    $club_id = $club_data['club_id'];
    $club_name = $club_data['club_name'];
    $position = $club_data['position'];
    $club = $club_data;
}

$committee_members = [];
if ($club_id) {
    $stmt2 = $conn->prepare("
        SELECT u.name, u.user_id, com.position 
        FROM committee com 
        JOIN `user` u ON com.user_id = u.user_id 
        WHERE com.club_id = ? 
        ORDER BY FIELD(com.position, 'President', 'Vice President', 'Secretary', 'Treasurer', 'Committee Member')
    ");
    $stmt2->bind_param("s", $club_id);
    $stmt2->execute();
    $committee_members = $stmt2->get_result();
}

$member_count = 0;
if ($club_id) {
    $stmt3 = $conn->prepare("SELECT COUNT(*) as total FROM club_membership WHERE club_id = ? AND status = 'Approved'");
    $stmt3->bind_param("s", $club_id);
    $stmt3->execute();
    $result3 = $stmt3->get_result();
    if ($result3) $member_count = $result3->fetch_assoc()['total'];
}

$event_count = 0;
if ($club_id) {
    $stmt4 = $conn->prepare("SELECT COUNT(*) as total FROM event WHERE club_id = ?");
    $stmt4->bind_param("s", $club_id);
    $stmt4->execute();
    $result4 = $stmt4->get_result();
    if ($result4) $event_count = $result4->fetch_assoc()['total'];
}

if (isset($_POST['update_club']) && $club_id) {
    $new_description = trim($_POST['description']);
    $update_stmt = $conn->prepare("UPDATE club SET description = ? WHERE club_id = ?");
    $update_stmt->bind_param("ss", $new_description, $club_id);
    if ($update_stmt->execute()) {
        $message = "<div style='background:#c6f6d5; color:#22543d; padding:12px; border-radius:8px; margin-bottom:20px;'>✅ Club description updated successfully!</div>";
        $club['description'] = $new_description;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Club Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; color: #333; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; width: calc(100% - 260px); }
        .welcome-card { background-color: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #38a169; }
        .welcome-card h2 { color: #1a202c; margin-bottom: 5px; }
        .welcome-card p { color: #718096; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 13px; color: #718096; text-transform: uppercase; letter-spacing: 1px; }
        .stat-card .number { font-size: 2.5rem; font-weight: 700; color: #2d3748; margin-top: 10px; }
        .section-title { font-size: 22px; color: #1a202c; margin-bottom: 20px; border-bottom: 2px solid #cbd5e0; padding-bottom: 10px; }
        .club-info-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .club-header-bg { background: linear-gradient(135deg, #3182ce 0%, #2b6cb0 100%); padding: 30px; color: white; }
        .club-header-bg h1 { font-size: 28px; margin-bottom: 10px; }
        .club-body { padding: 25px; }
        .info-row { display: flex; padding: 12px 0; border-bottom: 1px solid #edf2f7; }
        .info-label { width: 140px; font-weight: 600; color: #4a5568; }
        .info-value { flex: 1; color: #2d3748; }
        .edit-form { margin-top: 20px; padding-top: 20px; border-top: 2px dashed #e2e8f0; }
        .edit-form textarea { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-family: 'Inter', sans-serif; resize: vertical; outline: none; }
        .edit-form textarea:focus { border-color: #3182ce; }
        .btn-save { background: #38a169; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 10px; }
        .btn-save:hover { background: #2f855a; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f7fafc; font-weight: 600; color: #4a5568; }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-president { background: #fefcbf; color: #744210; }
        .badge-vice { background: #fed7d7; color: #742a2a; }
        .badge-secretary { background: #c6f6d5; color: #22543d; }
        .badge-treasurer { background: #bee3f8; color: #2c5282; }
        .badge-member { background: #e2e8f0; color: #4a5568; }
        .no-data { text-align: center; padding: 40px; color: #a0aec0; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <?php echo $message; ?>
        
        <div class="welcome-card">
            <h2>🏆 Club Management</h2>
            <p><?php echo htmlspecialchars($position); ?> of <?php echo htmlspecialchars($club_name); ?> • View and manage your club details below.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card" style="border-bottom: 4px solid #3182ce;"><h3>Total Members</h3><div class="number"><?php echo $member_count; ?></div></div>
            <div class="stat-card" style="border-bottom: 4px solid #38a169;"><h3>Total Events</h3><div class="number"><?php echo $event_count; ?></div></div>
            <div class="stat-card" style="border-bottom: 4px solid #805ad5;"><h3>Committee Size</h3><div class="number"><?php echo $committee_members ? $committee_members->num_rows : 0; ?></div></div>
        </div>

        <?php if ($club_id): ?>
        <div class="club-info-card">
            <div class="club-header-bg">
                <h1><?php echo htmlspecialchars($club_name); ?></h1>
                <p>Club Information & Details</p>
            </div>
            <div class="club-body">
                <div class="info-row">
                    <div class="info-label">Club ID</div>
                    <div class="info-value"><?php echo htmlspecialchars($club_id); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Advisor</div>
                    <div class="info-value"><?php echo htmlspecialchars($club['advisor_name'] ?? 'Not Assigned'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        <?php if (isset($club['isActive']) && $club['isActive'] == 1): ?>
                            <span class="badge badge-secretary">Active</span>
                        <?php else: ?>
                            <span class="badge badge-treasurer">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Description</div>
                    <div class="info-value"><?php echo htmlspecialchars($club['description'] ?? 'No description provided.'); ?></div>
                </div>
                
                <div class="edit-form">
                    <form method="POST" action="">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">Edit Club Description</label>
                        <textarea name="description" rows="4" placeholder="Enter club description..."><?php echo htmlspecialchars($club['description'] ?? ''); ?></textarea>
                        <button type="submit" name="update_club" class="btn-save">Save Description</button>
                    </form>
                </div>
            </div>
        </div>

        <h2 class="section-title">👥 Committee Members</h2>
        <table>
            <thead>
                <tr><th>Position</th><th>Name</th><th>Matrix ID</th></tr>
            </thead>
            <tbody>
                <?php if ($committee_members && $committee_members->num_rows > 0): ?>
                    <?php while($member = $committee_members->fetch_assoc()): 
                        $badge_class = 'badge-member';
                        if ($member['position'] === 'President') $badge_class = 'badge-president';
                        elseif ($member['position'] === 'Vice President') $badge_class = 'badge-vice';
                        elseif ($member['position'] === 'Secretary') $badge_class = 'badge-secretary';
                        elseif ($member['position'] === 'Treasurer') $badge_class = 'badge-treasurer';
                    ?>
                        <tr>
                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($member['position']); ?></span></td>
                            <td><?php echo htmlspecialchars($member['name']); ?></td>
                            <td><?php echo htmlspecialchars($member['user_id']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="no-data">No committee members found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="club-info-card">
            <div class="club-header-bg">
                <h1>No Club Assigned</h1>
                <p>You have not been assigned to any club yet.</p>
            </div>
            <div class="club-body">
                <p style="color: #718096; text-align: center; padding: 40px;">Please contact the administrator to be assigned to a club.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>