<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
$message = "";
$target_club_id = isset($_GET['manage_club_id']) ? trim($_GET['manage_club_id']) : '';

if (isset($_POST['add_club_btn'])) {
    $club_name = trim($_POST['club_name']);
    $isActive = intval($_POST['isActive']);

    $check_stmt = $conn->prepare("SELECT club_id FROM club WHERE club_name = ?");
    $check_stmt->bind_param("s", $club_name);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $message = "<div class='alert alert-error'>❌ Error: A club with this name already exists.</div>";
    } else {
        $insert_stmt = $conn->prepare("INSERT INTO club (club_name, isActive) VALUES (?, ?)");
        $insert_stmt->bind_param("si", $club_name, $isActive);
        
        if ($insert_stmt->execute()) {
            $message = "<div class='alert alert-success'>✅ New club added successfully!</div>";
        } else {
            $message = "<div class='alert alert-error'>❌ Database Error: " . $conn->error . "</div>";
        }
    }
}

if (isset($_POST['update_club_btn'])) {
    $club_id = trim($_POST['club_id']);
    $club_name = trim($_POST['club_name']);
    $isActive = intval($_POST['isActive']);

    if (!empty($club_id) && !empty($club_name)) {
        $update_stmt = $conn->prepare("UPDATE club SET club_name = ?, isActive = ? WHERE club_id = ?");
        $update_stmt->bind_param("sis", $club_name, $isActive, $club_id);
        
        if ($update_stmt->execute()) {
            $message = "<div class='alert alert-success'>✅ Club updated successfully!</div>";
        } else {
            $message = "<div class='alert alert-error'>❌ Database Error: " . $conn->error . "</div>";
        }
    }
}

if (isset($_POST['delete_club_id'])) {
    $del_stmt = $conn->prepare("DELETE FROM club WHERE club_id = ?");
    if ($del_stmt->execute([$_POST['delete_club_id']])) {
        $message = "<div class='alert alert-success'>✅ Club deleted successfully!</div>";
    }
}

if (isset($_POST['remove_member_btn'])) {
    $remove_user_id = trim($_POST['remove_user_id']);
    $remove_club_id = trim($_POST['remove_club_id']);
    
    $stmt = $conn->prepare("DELETE FROM committee WHERE user_id = ? AND club_id = ?");
    $stmt->bind_param("ss", $remove_user_id, $remove_club_id);
    
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>✅ Member removed from club committee successfully.</div>";
    } else {
        $message = "<div class='alert alert-error'>❌ Error removing member.</div>";
    }
}

if (isset($_POST['update_membership_btn'])) {
    $update_user_id = trim($_POST['membership_user_id']);
    $update_club_id = trim($_POST['membership_club_id']);
    $new_position = trim($_POST['position']);
    $can_update = true;

    if ($new_position === 'President') {
        $check_stmt = $conn->prepare("SELECT user_id FROM committee WHERE club_id = ? AND position = 'President' AND user_id != ?");
        $check_stmt->bind_param("ss", $update_club_id, $update_user_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $message = "<div class='alert alert-error'>❌ Error: This club already has an assigned President.</div>";
            $can_update = false;
        }
    }

    if ($can_update) {
        $stmt = $conn->prepare("UPDATE committee SET position = ? WHERE user_id = ? AND club_id = ?");
        $stmt->bind_param("sss", $new_position, $update_user_id, $update_club_id);
        
        if ($stmt->execute()) {
            $_SESSION['dashboard_flash_msg'] = "<div class='alert alert-success'>✅ Committee member position updated successfully!</div>";
            header("Location: " . $current_page);
            exit();
        } else {
            $message = "<div class='alert alert-error'>❌ Error updating position.</div>";
        }
    }
}

if (isset($_SESSION['dashboard_flash_msg'])) {
    $message = $_SESSION['dashboard_flash_msg'];
    unset($_SESSION['dashboard_flash_msg']);
}

$total_clubs_query = $conn->query("SELECT COUNT(*) AS total FROM club");
$total_clubs = $total_clubs_query->fetch_assoc()['total'];

$active_clubs_query = $conn->query("SELECT COUNT(*) AS total FROM club WHERE isActive = 1");
$active_clubs = $active_clubs_query->fetch_assoc()['total'];

$students_involved_query = $conn->query("
    SELECT COUNT(DISTINCT user_id) AS total FROM (
        SELECT user_id FROM club_membership WHERE status = 'Approved'
        UNION
        SELECT user_id FROM committee
    ) AS all_members
");
$students_involved = $students_involved_query->fetch_assoc()['total'];

$distribution_query = $conn->query("
    SELECT c.club_name, COUNT(DISTINCT combined.user_id) as member_count 
    FROM club c 
    LEFT JOIN (
        SELECT club_id, user_id FROM club_membership WHERE status = 'Approved'
        UNION
        SELECT club_id, user_id FROM committee
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

$clubs_sql = "
    SELECT c.club_id, c.club_name, c.isActive, c.advisor_name, u.name AS president_name 
    FROM club c 
    LEFT JOIN committee com ON c.club_id = com.club_id AND com.position = 'President' 
    LEFT JOIN `user` u ON com.user_id = u.user_id 
    ORDER BY c.club_name ASC
";
$clubs_result = $conn->query($clubs_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Club Analytics - Admin Master Dashboard</title>
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
        .btn-add { background: #3182ce; color: white; padding: 8px 15px; border-radius: 6px; border: none; font-weight: 600; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-add:hover { background: #2b6cb0; }
        .table-card { background: white; border-radius: 0 0 8px 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 30px; border: 1px solid #cbd5e0; }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; min-width: 800px; }
        th { padding: 15px 20px; font-size: 12px; color: #718096; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
        td { padding: 15px 20px; color: #4a5568; border-bottom: 1px solid #edf2f7; font-size: 14px; white-space: nowrap; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-active { background: #c6f6d5; color: #22543d; }
        .status-inactive { background: #fed7d7; color: #822727; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #e2e8f0; color: #4a5568; }
        .action-links { display: flex; gap: 6px; align-items: center; }
        .btn-sm { padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer; text-decoration: none; background: transparent; border: 1px solid transparent; }
        .btn-edit { color: #3182ce; border: 1px solid #bee3f8; background: #ebf8ff;}
        .btn-edit:hover { background: #bee3f8; }
        .btn-manage { color: #805ad5; border: 1px solid #d6bcfa; background: #faf5ff;}
        .btn-manage:hover { background: #e9d8fd; }
        .btn-delete { color: #e53e3e; border: 1px solid #fed7d7; background: #fff5f5;}
        .btn-delete:hover { background: #fed7d7; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .alert-success { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; justify-content: center; align-items: center; }
        .modal-card { background: white; width: 100%; max-width: 500px; padding: 30px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-top: 4px solid #3182ce; animation: slideDown 0.2s ease-out; }
        @keyframes slideDown { from { transform: translateY(-15px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { color: #1a202c; font-size: 18px; font-weight: 700; }
        .modal-close-btn { font-size: 24px; color: #a0aec0; cursor: pointer; background: none; border: none; font-weight: bold; }
        .modal-close-btn:hover { color: #4a5568; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; font-size: 12px; text-transform: uppercase; margin-bottom: 8px; color: #4a5568; text-align: left; }
        .form-group input[type="text"], .form-group select { width: 100%; padding: 12px 15px; border-radius: 8px; border: 1px solid #cbd5e0; font-size: 14px; color: #2d3748; outline: none; background: #fff; }
        .form-group input:focus, .form-group select:focus { border-color: #3182ce; box-shadow: 0 0 0 3px rgba(49,130,206,0.1); }
        .btn-submit-form { background: #3182ce; color: white; border: none; padding: 12px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; width: 100%; margin-top: 10px; }
        .btn-submit-form:hover { background: #2b6cb0; }
        @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
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
            <button type="button" class="btn-add" onclick="openAddClubModal()">+ Add New Club</button>
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
                                            <button type="button" class="btn-sm btn-edit" onclick="openEditClubModal('<?php echo htmlspecialchars($club['club_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($club['club_name'], ENT_QUOTES); ?>', '<?php echo $club['isActive']; ?>')">Edit Details</button>
                                            <a href="?manage_club_id=<?php echo urlencode($club['club_id']); ?>#committee_panel" class="btn-sm btn-manage">Manage Committee</a>
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

        <?php 
        if (!empty($target_club_id)): 
            $club_stmt = $conn->prepare("SELECT club_name FROM club WHERE club_id = ?");
            $club_stmt->bind_param("s", $target_club_id);
            $club_stmt->execute();
            $target_club = $club_stmt->get_result()->fetch_assoc();

            if ($target_club):
                $members_stmt = $conn->prepare("SELECT u.user_id, u.name, c.position FROM committee c JOIN `user` u ON c.user_id = u.user_id WHERE c.club_id = ? ORDER BY c.position ASC, u.name ASC");
                $members_stmt->bind_param("s", $target_club_id);
                $members_stmt->execute();
                $members_res = $members_stmt->get_result();
        ?>
        <div id="committee_panel" style="margin-top: 40px; scroll-margin-top: 20px;">
            <div class="section-header" style="background: #2d3748; border-color: #2d3748;">
                <h2 style="color: #fff;">👥 Committee List: <?php echo htmlspecialchars($target_club['club_name']); ?></h2>
            </div>
            <div class="table-card" style="border-radius: 0 0 8px 8px; box-shadow: 0 6px 12px rgba(0,0,0,0.08);">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Matrix ID</th>
                                <th>Name</th>
                                <th>Role / Position</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($members_res && $members_res->num_rows > 0): ?>
                                <?php while($m = $members_res->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($m['user_id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($m['name']); ?></td>
                                        <td><span class="badge"><?php echo htmlspecialchars($m['position']); ?></span></td>
                                        <td>
                                            <div class="action-links">
                                                <button type="button" class="btn-sm btn-edit" style="background: #fffdf5; color: #b7791f; border-color: #fbd38d;" onclick="openMembershipModal('<?php echo htmlspecialchars($m['user_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($m['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($target_club_id, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($m['position'], ENT_QUOTES); ?>')">Edit Position</button>
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to remove this member from the club?');" style="margin:0;">
                                                    <input type="hidden" name="remove_user_id" value="<?php echo htmlspecialchars($m['user_id']); ?>">
                                                    <input type="hidden" name="remove_club_id" value="<?php echo htmlspecialchars($target_club_id); ?>">
                                                    <button type="submit" name="remove_member_btn" class="btn-sm btn-delete">Remove</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align: center; color: #718096; padding: 40px;">No committee members assigned to this club.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php 
            endif;
        endif; 
        ?>
    </div>

    <div id="addClubModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>🏆 Add New Club</h3>
                <button type="button" class="modal-close-btn" onclick="closeAddClubModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Club Name</label>
                    <input type="text" name="club_name" placeholder="e.g. AI & Robotics Society" required>
                </div>
                <div class="form-group">
                    <label>Club Status</label>
                    <select name="isActive" required>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <button type="submit" name="add_club_btn" class="btn-submit-form">Create Club</button>
            </form>
        </div>
    </div>

    <div id="editClubModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>🏆 Edit Club Details</h3>
                <button type="button" class="modal-close-btn" onclick="closeEditClubModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="club_id" id="edit_club_id">
                <div class="form-group">
                    <label>Club Name</label>
                    <input type="text" name="club_name" id="edit_club_name" required>
                </div>
                <div class="form-group">
                    <label>Club Status</label>
                    <select name="isActive" id="edit_isActive" required>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <button type="submit" name="update_club_btn" class="btn-submit-form">Save Changes</button>
            </form>
        </div>
    </div>

    <div id="editMembershipModal" class="modal-overlay">
        <div class="modal-card" style="border-top-color: #805ad5;">
            <div class="modal-header">
                <h3>✏️ Edit Member Position</h3>
                <button type="button" class="modal-close-btn" onclick="closeMembershipModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="membership_user_id" id="member_user_id">
                <input type="hidden" name="membership_club_id" id="member_club_id">
                <p style="margin-bottom: 20px; color: #4a5568; font-size: 14px;">
                    Updating role for: <strong id="member_display_name">Student</strong>
                </p>
                <div class="form-group">
                    <label>New Position/Role:</label>
                    <select name="position" id="member_position">
                        <option value="President">President</option>
                        <option value="Vice President">Vice President</option>
                        <option value="Secretary">Secretary</option>
                        <option value="Treasurer">Treasurer</option>
                        <option value="Committee Member">Committee Member</option>
                    </select>
                </div>
                <button type="submit" name="update_membership_btn" class="btn-submit-form" style="background: #805ad5;">Save Position</button>
            </form>
        </div>
    </div>

    <script>
        function openAddClubModal() { document.getElementById('addClubModal').style.display = 'flex'; }
        function closeAddClubModal() { document.getElementById('addClubModal').style.display = 'none'; }

        function openEditClubModal(id, name, active) {
            document.getElementById('edit_club_id').value = id;
            document.getElementById('edit_club_name').value = name;
            document.getElementById('edit_isActive').value = active;
            document.getElementById('editClubModal').style.display = 'flex';
        }
        function closeEditClubModal() { document.getElementById('editClubModal').style.display = 'none'; }

        function openMembershipModal(userId, userName, clubId, currentPosition) {
            document.getElementById('member_user_id').value = userId;
            document.getElementById('member_club_id').value = clubId;
            document.getElementById('member_display_name').innerText = userName + " (" + userId + ")";
            document.getElementById('member_position').value = currentPosition;
            document.getElementById('editMembershipModal').style.display = 'flex';
        }
        function closeMembershipModal() { document.getElementById('editMembershipModal').style.display = 'none'; }

        window.addEventListener('click', function(event) {
            if (event.target === document.getElementById('addClubModal')) closeAddClubModal();
            if (event.target === document.getElementById('editClubModal')) closeEditClubModal();
            if (event.target === document.getElementById('editMembershipModal')) closeMembershipModal();
        });

        window.addEventListener('DOMContentLoaded', () => {
            if(window.location.hash === '#committee_panel') {
                const el = document.getElementById('committee_panel');
                if(el) el.scrollIntoView({ behavior: 'smooth' });
            }
        });

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
                                return (context.label || '') + ': ' + context.raw + ' student(s)';
                            }
                        }
                    }
                } 
            }
        });
    </script>
</body>
</html>