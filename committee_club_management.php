<?php
session_start();
require 'db_connect.php';

// SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Committee') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// ---------------------------------------------------------
// 1. HANDLE CLUB INFO UPDATE (POST)
// ---------------------------------------------------------
if (isset($_POST['update_club'])) {
    $upd_club_id = $_POST['club_id'];
    $description = trim($_POST['description']);
    $advisor_name = trim($_POST['advisor_name']);

    // Security check: Verify they actually have high authority for this specific club before updating
    $auth_check = $conn->prepare("SELECT position FROM committee WHERE user_id = ? AND club_id = ? AND position IN ('President', 'Vice President', 'Secretary', 'Treasurer')");
    $auth_check->bind_param("ss", $user_id, $upd_club_id);
    $auth_check->execute();
    
    if ($auth_check->get_result()->num_rows > 0) {
        $upd_stmt = $conn->prepare("UPDATE club SET description = ?, advisor_name = ? WHERE club_id = ?");
        $upd_stmt->bind_param("sss", $description, $advisor_name, $upd_club_id);
        if ($upd_stmt->execute()) {
            $message = "<div class='alert alert-success'>✅ Club profile updated successfully!</div>";
        } else {
            $message = "<div class='alert alert-error'>❌ Error updating club: " . $conn->error . "</div>";
        }
    } else {
        $message = "<div class='alert alert-error'>❌ Unauthorized to edit this club.</div>";
    }
}

// ---------------------------------------------------------
// 2. FETCH HIGH AUTHORITY CLUBS FOR DROPDOWN
// ---------------------------------------------------------
$auth_clubs = [];
$stmt = $conn->prepare("
    SELECT c.club_id, c.club_name, com.position 
    FROM committee com 
    JOIN club c ON com.club_id = c.club_id 
    WHERE com.user_id = ? AND com.position IN ('President', 'Vice President', 'Secretary', 'Treasurer')
");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $auth_clubs[] = $row;
}

// Determine which club is currently selected (defaults to the first one they have authority over)
$selected_club_id = isset($_GET['club_id']) ? $_GET['club_id'] : ($auth_clubs[0]['club_id'] ?? null);

// ---------------------------------------------------------
// 3. FETCH SELECTED CLUB DETAILS & STATS
// ---------------------------------------------------------
$club_data = null;
$total_events = 0;
$total_registrations = 0;
$attendance_rate = 0;
$total_members = 0;

if ($selected_club_id) {
    // Get Club Info
    $c_stmt = $conn->prepare("SELECT * FROM club WHERE club_id = ?");
    $c_stmt->bind_param("s", $selected_club_id);
    $c_stmt->execute();
    $club_data = $c_stmt->get_result()->fetch_assoc();

    // Get Stats for THIS club
    $stat_evt = $conn->prepare("SELECT COUNT(*) as t FROM event WHERE club_id = ?");
    $stat_evt->bind_param("s", $selected_club_id); $stat_evt->execute();
    $total_events = $stat_evt->get_result()->fetch_assoc()['t'];

    $stat_reg = $conn->prepare("SELECT COUNT(*) as t FROM event_registration er JOIN event e ON er.event_id = e.event_id WHERE e.club_id = ?");
    $stat_reg->bind_param("s", $selected_club_id); $stat_reg->execute();
    $total_registrations = $stat_reg->get_result()->fetch_assoc()['t'];

    $stat_mem = $conn->prepare("SELECT COUNT(*) as t FROM club_membership WHERE club_id = ? AND status = 'Approved'");
    $stat_mem->bind_param("s", $selected_club_id); $stat_mem->execute();
    $total_members = $stat_mem->get_result()->fetch_assoc()['t'];

    // Calculate Attendance Rate
    $stat_att = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM attendance a JOIN event_registration er ON a.register_id = er.register_id JOIN event e ON er.event_id = e.event_id WHERE e.club_id = ? AND a.attend_status IN ('Present', 'Late', 'Volunteer')) as attended,
            (SELECT COUNT(*) FROM event_registration er JOIN event e ON er.event_id = e.event_id WHERE e.club_id = ? AND er.status = 'Registered' AND e.date <= CURDATE()) as expected
    ");
    $stat_att->bind_param("ss", $selected_club_id, $selected_club_id);
    $stat_att->execute();
    $att_data = $stat_att->get_result()->fetch_assoc();
    if ($att_data['expected'] > 0) {
        $attendance_rate = round(($att_data['attended'] / $att_data['expected']) * 100);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Club Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css"> 
    <style>
        /* THE MAGIC LAYOUT LINES */
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; color: #333; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; max-width: 1200px; }
        
        .header-card { background-color: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #3182ce; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;}
        .header-text h2 { color: #1a202c; margin-bottom: 5px; }
        
        .club-selector { padding: 10px 15px; border-radius: 8px; border: 2px solid #cbd5e0; outline: none; font-size: 15px; font-weight: 600; color: #2d3748; background: #f8fafc; min-width: 250px; cursor: pointer;}
        .club-selector:focus { border-color: #3182ce; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 12px; color: #718096; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;}
        .stat-card .number { font-size: 2.8rem; font-weight: 800; color: #2d3748; }

        /* Club Profile Card */
        .profile-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; }
        .profile-header { background: #f8fafc; padding: 20px 30px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .profile-header h3 { color: #2d3748; font-size: 18px; }
        .profile-body { padding: 30px; }
        
        .info-group { margin-bottom: 20px; }
        .info-label { font-size: 13px; color: #718096; font-weight: 600; text-transform: uppercase; margin-bottom: 5px; display: block;}
        .info-value { font-size: 16px; color: #2d3748; line-height: 1.5;}

        /* Form Inputs */
        .form-input { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; font-family: 'Inter', sans-serif; margin-bottom: 15px;}
        textarea.form-input { min-height: 120px; resize: vertical; }
        
        .btn-action { background: #3182ce; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.2s; font-size: 14px;}
        .btn-action:hover { background: #2b6cb0; }
        .btn-cancel { background: #e2e8f0; color: #4a5568; margin-left: 10px;}
        .btn-cancel:hover { background: #cbd5e0; }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background-color: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <?php echo $message; ?>

        <?php if (empty($auth_clubs)): ?>
            <div class="header-card" style="border-left-color: #e53e3e;">
                <h2>⚠️ Access Restricted</h2>
                <p>You do not currently hold a high-authority position (President, VP, Secretary, Treasurer) in any active clubs.</p>
            </div>
        <?php else: ?>

            <div class="header-card">
                <div class="header-text">
                    <h2>🏢 Club Management</h2>
                    <p style="color: #718096; font-size: 14px;">Select a club to view analytics and update its profile.</p>
                </div>
                
                <select class="club-selector" onchange="window.location.href='?club_id='+this.value">
                    <?php foreach ($auth_clubs as $c): ?>
                        <option value="<?php echo $c['club_id']; ?>" <?php echo ($selected_club_id == $c['club_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['club_name']); ?> (<?php echo htmlspecialchars($c['position']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="stats-grid">
                <div class="stat-card" style="border-bottom: 4px solid #3182ce;">
                    <h3>Total Events</h3><div class="number"><?php echo $total_events; ?></div>
                </div>
                <div class="stat-card" style="border-bottom: 4px solid #38a169;">
                    <h3>Registrations</h3><div class="number"><?php echo $total_registrations; ?></div>
                </div>
                <div class="stat-card" style="border-bottom: 4px solid #805ad5;">
                    <h3>Attendance Rate</h3><div class="number"><?php echo $attendance_rate; ?>%</div>
                </div>
                <div class="stat-card" style="border-bottom: 4px solid #ecc94b;">
                    <h3>Club Members</h3><div class="number"><?php echo $total_members; ?></div>
                </div>
            </div>

            <?php if ($club_data): ?>
                <div class="profile-card">
                    <div class="profile-header">
                        <h3>📋 Club Profile: <?php echo htmlspecialchars($club_data['club_name']); ?></h3>
                        <button class="btn-action" id="btnEdit" onclick="toggleEditMode(true)">✏️ Edit Profile</button>
                    </div>
                    
                    <div class="profile-body">
                        <div id="viewMode">
                            <div class="info-group">
                                <span class="info-label">Advisor Name</span>
                                <div class="info-value"><?php echo htmlspecialchars($club_data['advisor_name'] ?? 'Not Assigned'); ?></div>
                            </div>
                            <div class="info-group">
                                <span class="info-label">Club Description</span>
                                <div class="info-value"><?php echo nl2br(htmlspecialchars($club_data['description'] ?? 'No description provided.')); ?></div>
                            </div>
                        </div>

                        <form id="editMode" method="POST" action="" style="display: none;">
                            <input type="hidden" name="club_id" value="<?php echo htmlspecialchars($club_data['club_id']); ?>">
                            
                            <div class="info-group">
                                <label class="info-label">Advisor Name</label>
                                <input type="text" name="advisor_name" class="form-input" value="<?php echo htmlspecialchars($club_data['advisor_name'] ?? ''); ?>" placeholder="E.g., Dr. Azlan" required>
                            </div>
                            
                            <div class="info-group">
                                <label class="info-label">Club Description</label>
                                <textarea name="description" class="form-input" placeholder="What does this club do?" required><?php echo htmlspecialchars($club_data['description'] ?? ''); ?></textarea>
                            </div>

                            <div style="margin-top: 20px;">
                                <button type="submit" name="update_club" class="btn-action" style="background:#38a169;">💾 Save Changes</button>
                                <button type="button" class="btn-action btn-cancel" onclick="toggleEditMode(false)">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <script>
        function toggleEditMode(isEditing) {
            document.getElementById('viewMode').style.display = isEditing ? 'none' : 'block';
            document.getElementById('editMode').style.display = isEditing ? 'block' : 'none';
            document.getElementById('btnEdit').style.display = isEditing ? 'none' : 'block';
        }
    </script>
</body>
</html>