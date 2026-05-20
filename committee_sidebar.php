<?php
// Committee Sidebar Component
// Include this file in all committee pages
?>

<div class="sidebar">
    <div class="sidebar-brand">FK Club System</div>
    <div class="nav-links">
        <a href="committee_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'committee_dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
        <a href="committee_profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'committee_profile.php' ? 'active' : ''; ?>">My Profile</a>
        <a href="committee_club_details.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'committee_club_details.php' ? 'active' : ''; ?>">Club Details</a>
        <a href="committee_manage_committee.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'committee_manage_committee.php' ? 'active' : ''; ?>">Committee Members</a>
        <a href="committee_events.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'committee_events.php' ? 'active' : ''; ?>">Manage Events</a>
        <a href="committee_registrations.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'committee_registrations.php' ? 'active' : ''; ?>">Event Registrations</a>
        <a href="committee_attendance.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'committee_attendance.php' ? 'active' : ''; ?>">Record Attendance</a>
        <a href="committee_reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'committee_reports.php' ? 'active' : ''; ?>">Event Reports</a>
        <a href="committee_participation.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'committee_participation.php' ? 'active' : ''; ?>">Participation Stats</a>
    </div>
    <a href="logout.php" class="btn-logout">Logout</a>
</div>

<style>
    .sidebar {
        width: 260px;
        background-color: #1a202c;
        color: white;
        display: flex;
        flex-direction: column;
        padding: 30px 20px;
        position: fixed;
        height: 100vh;
        box-shadow: 4px 0 10px rgba(0,0,0,0.1);
    }
    .sidebar-brand {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 40px;
        color: #fff;
        text-align: center;
    }
    .nav-links {
        display: flex;
        flex-direction: column;
        gap: 15px;
        flex-grow: 1;
    }
    .nav-links a {
        text-decoration: none;
        color: #a0aec0;
        font-weight: 600;
        padding: 12px 15px;
        border-radius: 8px;
        transition: 0.3s;
        display: block;
    }
    .nav-links a:hover,
    .nav-links a.active {
        background-color: #2d3748;
        color: white;
    }
    .btn-logout {
        background-color: #e53e3e;
        color: white;
        text-align: center;
        text-decoration: none;
        padding: 12px;
        border-radius: 8px;
        font-weight: bold;
        margin-top: auto;
        transition: 0.2s;
        display: block;
    }
    .btn-logout:hover {
        background-color: #c53030;
    }
</style>