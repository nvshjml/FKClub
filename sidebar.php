<style>
    /* SIDEBAR CSS ONLY */
    .sidebar { width: 260px; background-color: #1a202c; color: white; display: flex; flex-direction: column; padding: 30px 20px; position: fixed; height: 100vh; box-shadow: 4px 0 10px rgba(0,0,0,0.1); z-index: 1000; top: 0; left: 0; box-sizing: border-box; }
    .sidebar-header { text-align: center; margin-bottom: 35px; }
    .sidebar-logo { max-width: 85px; margin-bottom: 12px; display: inline-block; }
    .sidebar-brand { font-size: 20px; font-weight: 700; color: #ffffff; margin-bottom: 6px; }
    .sidebar-role { font-size: 11px; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 1.5px; background: rgba(255,255,255,0.1); padding: 4px 12px; border-radius: 20px; display: inline-block; }
    
    .nav-links-sidebar { display: flex; flex-direction: column; gap: 15px; flex-grow: 1; }
    .nav-links-sidebar a { text-decoration: none; color: #a0aec0; font-weight: 600; padding: 12px 15px; border-radius: 8px; transition: 0.3s; display: flex; align-items: center; justify-content: space-between; font-size: 15px; }
    .nav-links-sidebar a:hover, .nav-links-sidebar a.active { background-color: #2d3748; color: white; }
    
    .badge-sidebar { background: #e53e3e; color: white; border-radius: 12px; padding: 2px 8px; font-size: 12px; font-weight: bold; }
    .btn-logout-sidebar { background-color: #e53e3e; color: white; text-align: center; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: bold; margin-top: auto; transition: 0.2s; display: block; }
    .btn-logout-sidebar:hover { background-color: #c53030; }
</style>

<?php
// Smart variables for the sidebar
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF']);
}

// Only query the pending count if the user is an Admin
if ($_SESSION['role'] == 'Admin' && !isset($sidebar_pending_count)) {
    $sidebar_stmt = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE account_status = 'Pending'");
    $sidebar_pending_count = $sidebar_stmt ? $sidebar_stmt->fetch_assoc()['total'] : 0;
}
?>

<div class="sidebar">
    <div class="sidebar-header">
        <img src="image/LogoUMP5.png" alt="UMPSA Logo" class="sidebar-logo">
        <div class="sidebar-brand">FK Club System</div>
        <div class="sidebar-role"><?php echo htmlspecialchars($_SESSION['role']); ?> Dashboard</div>
    </div>
    
    <div class="nav-links-sidebar">
        <?php if ($_SESSION['role'] == 'Admin'): ?>
            <a href="admin_dashboard.php" class="<?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
            <a href="admin_attendance.php" class="<?php echo ($current_page == 'admin_attendance.php') ? 'active' : ''; ?>">Attendance</a>
            <a href="pending_approvals.php" class="<?php echo ($current_page == 'pending_approvals.php') ? 'active' : ''; ?>">
                <span>Approvals</span>
                <?php if(isset($sidebar_pending_count) && $sidebar_pending_count > 0): ?>
                    <span class="badge-sidebar"><?php echo $sidebar_pending_count; ?></span>
                <?php endif; ?>
            </a>
            
        <?php elseif ($_SESSION['role'] == 'Committee'): ?>
            <a href="committee_dashboard.php" class="<?php echo ($current_page == 'committee_dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
            <a href="student_profile.php" class="<?php echo ($current_page == 'student_profile.php') ? 'active' : ''; ?>">My Profile</a>
            <a href="committee_club_details.php" class="<?php echo ($current_page == 'committee_club_details.php') ? 'active' : ''; ?>">Club Details</a>
            <a href="committee_events.php" class="<?php echo ($current_page == 'committee_events.php') ? 'active' : ''; ?>">Manage Events</a>
            <a href="committee_attendance.php" class="<?php echo ($current_page == 'committee_attendance.php') ? 'active' : ''; ?>">Record Attendance</a>
            
        <?php else: ?>
            <a href="student_dashboard.php" class="<?php echo ($current_page == 'student_dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
            <a href="student_profile.php" class="<?php echo ($current_page == 'student_profile.php') ? 'active' : ''; ?>">My Profile</a>
            <a href="student_browse_clubs.php" class="<?php echo ($current_page == 'student_browse_clubs.php') ? 'active' : ''; ?>">Browse Clubs</a>
            <a href="student_event_registration.php" class="<?php echo ($current_page == 'student_event_registration.php') ? 'active' : ''; ?>">Event Registration</a>
        <?php endif; ?>
    </div>
    
    <a href="logout.php" class="btn-logout-sidebar">Logout</a>
</div>