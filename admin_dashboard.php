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

<<<<<<< HEAD
$current_page = basename($_SERVER['PHP_SELF']);
$message = "";
$message_type = "";

// --- CRUD Operations for Clubs ---
// Create Club
if (isset($_POST['create_club'])) {
    $club_name = trim($_POST['club_name']);
    $description = trim($_POST['description']);
    $advisor_name = trim($_POST['advisor_name']);
    $isActive = isset($_POST['isActive']) ? 1 : 0;
    
    if (!empty($club_name)) {
        $stmt = $conn->prepare("INSERT INTO CLUB (club_name, description, advisor_name, isActive) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $club_name, $description, $advisor_name, $isActive);
        if ($stmt->execute()) {
            $message = "Club created successfully!";
            $message_type = "success";
        } else {
            $message = "Error creating club";
            $message_type = "error";
        }
    }
=======
// Handle Deletions
if (isset($_POST['delete_club_id'])) {
    $delete_id = $_POST['delete_club_id'];
    $conn->prepare("DELETE FROM CLUB WHERE club_id = ?")->execute([$delete_id]);
    header("Location: admin_dashboard.php"); exit();
>>>>>>> f782160efa86ae440593c8633caea2f7a84f6518
}

// Update Club
if (isset($_POST['update_club'])) {
    $club_id = $_POST['club_id'];
    $club_name = trim($_POST['club_name']);
    $description = trim($_POST['description']);
    $advisor_name = trim($_POST['advisor_name']);
    $isActive = isset($_POST['isActive']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE CLUB SET club_name = ?, description = ?, advisor_name = ?, isActive = ? WHERE club_id = ?");
    $stmt->bind_param("sssii", $club_name, $description, $advisor_name, $isActive, $club_id);
    if ($stmt->execute()) {
        $message = "Club updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating club";
        $message_type = "error";
    }
}

<<<<<<< HEAD
// Delete Club
if (isset($_POST['delete_club'])) {
    $club_id = $_POST['club_id'];
    $stmt = $conn->prepare("DELETE FROM CLUB WHERE club_id = ?");
    $stmt->bind_param("i", $club_id);
    if ($stmt->execute()) {
        $message = "Club deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting club";
        $message_type = "error";
    }
}

// Approve Club
if (isset($_POST['approve_club_id'])) {
    $club_id = $_POST['approve_club_id'];
    $stmt = $conn->prepare("UPDATE CLUB SET isActive = 1 WHERE club_id = ?");
    $stmt->bind_param("i", $club_id);
    if ($stmt->execute()) {
        $message = "Club approved successfully!";
        $message_type = "success";
    }
}

// --- CRUD Operations for Events ---
// Create Event
if (isset($_POST['create_event'])) {
    $event_name = trim($_POST['event_name']);
    $club_id = $_POST['club_id'];
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $venue = trim($_POST['venue']);
    
    if (!empty($event_name) && !empty($club_id)) {
        $stmt = $conn->prepare("INSERT INTO EVENT (event_name, club_id, date, time, venue) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sisss", $event_name, $club_id, $event_date, $event_time, $venue);
        if ($stmt->execute()) {
            $message = "Event created successfully!";
            $message_type = "success";
        } else {
            $message = "Error creating event";
            $message_type = "error";
        }
    }
}

// Update Event
if (isset($_POST['update_event'])) {
    $event_id = $_POST['event_id'];
    $event_name = trim($_POST['event_name']);
    $club_id = $_POST['club_id'];
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $venue = trim($_POST['venue']);
    
    $stmt = $conn->prepare("UPDATE EVENT SET event_name = ?, club_id = ?, date = ?, time = ?, venue = ? WHERE event_id = ?");
    $stmt->bind_param("sisssi", $event_name, $club_id, $event_date, $event_time, $venue, $event_id);
    if ($stmt->execute()) {
        $message = "Event updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating event";
        $message_type = "error";
    }
}

// Delete Event
if (isset($_POST['delete_event'])) {
    $event_id = $_POST['event_id'];
    $stmt = $conn->prepare("DELETE FROM EVENT WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    if ($stmt->execute()) {
        $message = "Event deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting event";
        $message_type = "error";
    }
}

// Fetch dashboard data
=======
// ---------------------------------------------------------
// 1. FETCH SUMMARY STATISTICS (Top Cards)
// ---------------------------------------------------------
>>>>>>> f782160efa86ae440593c8633caea2f7a84f6518
$stmt1 = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE role IN ('Student', 'Committee') AND account_status = 'Approved'");
$total_students = $stmt1->fetch_assoc()['total'];

$stmt2 = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE account_status = 'Pending'");
$pending_count = $stmt2->fetch_assoc()['total'];

$stmt3 = $conn->query("SELECT COUNT(*) AS total FROM CLUB WHERE isActive = 1");
$total_clubs = $stmt3 ? $stmt3->fetch_assoc()['total'] : 0;

$stmt4 = $conn->query("SELECT COUNT(*) AS total FROM EVENT");
$total_events = $stmt4 ? $stmt4->fetch_assoc()['total'] : 0;

<<<<<<< HEAD
$clubs_sql = "SELECT c.club_id, c.club_name, c.description, c.advisor_name, c.isActive, 
                     u.name AS president_name,
                     (SELECT COUNT(*) FROM CLUB_MEMBERSHIP WHERE club_id = c.club_id) as member_count
              FROM CLUB c 
              LEFT JOIN COMMITTEE com ON c.club_id = com.club_id AND com.position = 'President' 
              LEFT JOIN `USER` u ON com.user_id = u.user_id 
              ORDER BY c.club_name ASC";
=======
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
>>>>>>> f782160efa86ae440593c8633caea2f7a84f6518
$clubs_result = $conn->query($clubs_sql);

$events_sql = "SELECT e.event_id, e.event_name, e.date, e.time, e.venue, e.club_id, c.club_name 
               FROM EVENT e 
               JOIN CLUB c ON e.club_id = c.club_id 
               ORDER BY e.date ASC";
$events_result = $conn->query($events_sql);

$all_clubs_sql = "SELECT club_id, club_name FROM CLUB WHERE isActive = 1 ORDER BY club_name";
$all_clubs = $conn->query($all_clubs_sql);
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
        
        /* Modal Styles - Hidden by default */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; border-radius: 16px; padding: 30px; width: 500px; max-width: 90%; }
        .modal-title { font-size: 20px; font-weight: 700; margin-bottom: 20px; color: #1a202c; }
        .modal .form-group { margin-bottom: 15px; }
        .modal .form-group label { display: block; font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 5px; }
        .modal .form-group input, .modal .form-group textarea, .modal .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px; font-family: 'Inter', sans-serif; }
        .modal-buttons { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .btn-save { background: #3182ce; color: white; padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-cancel-modal { background: #e2e8f0; color: #4a5568; padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; }
        
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background-color: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

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

<<<<<<< HEAD
        <!-- Manage Clubs Section -->
        <div class="section-header"><h2>🏆 Manage Clubs</h2><button class="btn-add" onclick="openClubModal()">+ Add New Club</button></div>
=======
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
>>>>>>> f782160efa86ae440593c8633caea2f7a84f6518
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
<<<<<<< HEAD
                                        <button class="btn-sm btn-edit" onclick="editClub(<?php echo htmlspecialchars(json_encode($club)); ?>)">Edit</button>
                                        <?php if ($club['isActive'] == 0): ?>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="approve_club_id" value="<?php echo $club['club_id']; ?>">
                                                <button type="submit" class="btn-sm btn-approve">Approve</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this Club?');">
                                            <input type="hidden" name="delete_club" value="1">
                                            <input type="hidden" name="club_id" value="<?php echo $club['club_id']; ?>">
                                            <button type="submit" class="btn-sm btn-delete">Delete</button>
                                        </form>
                                    </div>
                                 </span>
=======
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
>>>>>>> f782160efa86ae440593c8633caea2f7a84f6518
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Manage Events Section -->
        <div class="section-header"><h2>📅 Manage Events</h2><button class="btn-add" onclick="openEventModal()">+ Add New Event</button></div>
        <div class="table-card">
            <table>
                <thead><tr><th>Event Name</th><th>Organizing Club</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if ($events_result && $events_result->num_rows > 0): ?>
                        <?php while($event = $events_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($event['event_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($event['club_name']); ?></td>
                                <td><?php echo date("d M Y", strtotime($event['date'])); ?></span>
                                <td>
                                    <div class="action-links">
<<<<<<< HEAD
                                        <button class="btn-sm btn-edit" onclick="editEvent(<?php echo htmlspecialchars(json_encode($event)); ?>)">Edit</button>
                                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this Event?');">
                                            <input type="hidden" name="delete_event" value="1">
                                            <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
=======
                                        <button class="btn-sm btn-edit">Edit</button>
                                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this Event?');">
                                            <input type="hidden" name="delete_event_id" value="<?php echo $event['event_id']; ?>">
>>>>>>> f782160efa86ae440593c8633caea2f7a84f6518
                                            <button type="submit" class="btn-sm btn-delete">Delete</button>
                                        </form>
                                    </div>
                                 </span>
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

<<<<<<< HEAD
    <!-- Club Modal (Hidden by default) -->
    <div id="clubModal" class="modal">
        <div class="modal-content">
            <div class="modal-title" id="clubModalTitle">Add New Club</div>
            <form method="POST" action="" id="clubForm">
                <input type="hidden" name="club_id" id="club_id">
                <div class="form-group">
                    <label>Club Name</label>
                    <input type="text" name="club_name" id="club_name" placeholder="e.g., Web Development Club" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="club_description" rows="3" placeholder="Brief description of the club..."></textarea>
                </div>
                <div class="form-group">
                    <label>Advisor Name</label>
                    <input type="text" name="advisor_name" id="club_advisor" placeholder="e.g., Dr. Azlan">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="isActive" id="club_active" value="1" checked> Active
                    </label>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel-modal" onclick="closeClubModal()">Cancel</button>
                    <button type="submit" class="btn-save" id="clubSubmitBtn">Create Club</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Event Modal (Hidden by default) -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div class="modal-title" id="eventModalTitle">Add New Event</div>
            <form method="POST" action="" id="eventForm">
                <input type="hidden" name="event_id" id="event_id">
                <div class="form-group">
                    <label>Event Name</label>
                    <input type="text" name="event_name" id="event_name" placeholder="e.g., PHP Bootcamp" required>
                </div>
                <div class="form-group">
                    <label>Organizing Club</label>
                    <select name="club_id" id="event_club" required>
                        <option value="">Select Club</option>
                        <?php 
                        $all_clubs->data_seek(0);
                        while($club = $all_clubs->fetch_assoc()): ?>
                            <option value="<?php echo $club['club_id']; ?>"><?php echo htmlspecialchars($club['club_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="event_date" id="event_date" required>
                </div>
                <div class="form-group">
                    <label>Time</label>
                    <input type="time" name="event_time" id="event_time">
                </div>
                <div class="form-group">
                    <label>Venue</label>
                    <input type="text" name="venue" id="event_venue" placeholder="e.g., Lab 1 Computing">
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel-modal" onclick="closeEventModal()">Cancel</button>
                    <button type="submit" class="btn-save" id="eventSubmitBtn">Create Event</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Club Modal Functions
        function openClubModal() {
            document.getElementById('clubModal').style.display = 'flex';
            document.getElementById('clubModalTitle').innerText = 'Add New Club';
            document.getElementById('club_id').value = '';
            document.getElementById('club_name').value = '';
            document.getElementById('club_description').value = '';
            document.getElementById('club_advisor').value = '';
            document.getElementById('club_active').checked = true;
            document.getElementById('clubSubmitBtn').innerText = 'Create Club';
            
            // Remove any existing hidden inputs
            var oldInput = document.getElementById('update_club_input');
            if (oldInput) oldInput.remove();
            
            var form = document.getElementById('clubForm');
            form.onsubmit = function() {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'create_club';
                input.value = '1';
                input.id = 'create_club_input';
                form.appendChild(input);
                return true;
            };
        }
        
        function editClub(clubData) {
            document.getElementById('clubModal').style.display = 'flex';
            document.getElementById('clubModalTitle').innerText = 'Edit Club';
            document.getElementById('club_id').value = clubData.club_id;
            document.getElementById('club_name').value = clubData.club_name;
            document.getElementById('club_description').value = clubData.description || '';
            document.getElementById('club_advisor').value = clubData.advisor_name || '';
            document.getElementById('club_active').checked = clubData.isActive == 1;
            document.getElementById('clubSubmitBtn').innerText = 'Update Club';
            
            // Remove any existing hidden inputs
            var oldInput = document.getElementById('create_club_input');
            if (oldInput) oldInput.remove();
            
            var form = document.getElementById('clubForm');
            form.onsubmit = function() {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'update_club';
                input.value = '1';
                input.id = 'update_club_input';
                form.appendChild(input);
                return true;
            };
        }
        
        function closeClubModal() { 
            document.getElementById('clubModal').style.display = 'none'; 
        }
        
        // Event Modal Functions
        function openEventModal() {
            document.getElementById('eventModal').style.display = 'flex';
            document.getElementById('eventModalTitle').innerText = 'Add New Event';
            document.getElementById('event_id').value = '';
            document.getElementById('event_name').value = '';
            document.getElementById('event_club').value = '';
            document.getElementById('event_date').value = '';
            document.getElementById('event_time').value = '';
            document.getElementById('event_venue').value = '';
            document.getElementById('eventSubmitBtn').innerText = 'Create Event';
            
            var oldInput = document.getElementById('update_event_input');
            if (oldInput) oldInput.remove();
            
            var form = document.getElementById('eventForm');
            form.onsubmit = function() {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'create_event';
                input.value = '1';
                input.id = 'create_event_input';
                form.appendChild(input);
                return true;
            };
        }
        
        function editEvent(eventData) {
            document.getElementById('eventModal').style.display = 'flex';
            document.getElementById('eventModalTitle').innerText = 'Edit Event';
            document.getElementById('event_id').value = eventData.event_id;
            document.getElementById('event_name').value = eventData.event_name;
            document.getElementById('event_club').value = eventData.club_id;
            document.getElementById('event_date').value = eventData.date;
            document.getElementById('event_time').value = eventData.time || '';
            document.getElementById('event_venue').value = eventData.venue || '';
            document.getElementById('eventSubmitBtn').innerText = 'Update Event';
            
            var oldInput = document.getElementById('create_event_input');
            if (oldInput) oldInput.remove();
            
            var form = document.getElementById('eventForm');
            form.onsubmit = function() {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'update_event';
                input.value = '1';
                input.id = 'update_event_input';
                form.appendChild(input);
                return true;
            };
        }
        
        function closeEventModal() { 
            document.getElementById('eventModal').style.display = 'none'; 
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
=======
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
>>>>>>> f782160efa86ae440593c8633caea2f7a84f6518
    </script>
</body>
</html>