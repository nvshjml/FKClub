<?php
// Fetch pending count so the badge always updates on every page
require_once 'db_connect.php';
$sidebar_pending_count = 0;
$sidebar_stmt = $conn->query("SELECT COUNT(*) AS total FROM `USER` WHERE account_status = 'Pending'");
if($sidebar_stmt) {
    $sidebar_pending_count = $sidebar_stmt->fetch_assoc()['total'];
}

// Get current page name to highlight the active menu item
$current_page = basename($_SERVER['PHP_SELF']); 
?>

<style>
    /* Reset body for the sidebar layout */
    body { display: flex; background: #e2e8f0; min-height: 100vh; margin: 0; }
    
    /* Sidebar Styles */
    .sidebar { width: 260px; background-color: #1a202c; color: white; display: flex; flex-direction: column; padding: 30px 20px; position: fixed; height: 100vh; box-shadow: 4px 0 10px rgba(0,0,0,0.1); z-index: 1000; top: 0; left: 0; box-sizing: border-box; }
    .sidebar-brand { font-size: 20px; font-weight: 700; margin-bottom: 40px; color: #fff; text-align: center; font-family: 'Inter', sans-serif; }
    
    .nav-links-sidebar { display: flex; flex-direction: column; gap: 15px; flex-grow: 1; }
    .nav-links-sidebar a { text-decoration: none; color: #a0aec0; font-weight: 600; padding: 12px 15px; border-radius: 8px; transition: 0.3s; display: flex; align-items: center; justify-content: space-between; font-family: 'Inter', sans-serif; font-size: 15px; }
    .nav-links-sidebar a:hover, .nav-links-sidebar a.active { background-color: #2d3748; color: white; }
    
    .badge-sidebar { background: #e53e3e; color: white; border-radius: 12px; padding: 2px 8px; font-size: 12px; font-weight: bold; }
    
    .btn-logout-sidebar { background-color: #e53e3e; color: white; text-align: center; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: bold; margin-top: auto; transition: 0.2s; font-family: 'Inter', sans-serif; }
    .btn-logout-sidebar:hover { background-color: #c53030; }

    /* Push the main content to the right so it doesn't hide behind the sidebar */
    .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; width: calc(100% - 260px); box-sizing: border-box; }
</style>

<div class="sidebar">
    <div class="sidebar-brand">FK Club Admin</div>
    <div class="nav-links-sidebar">
        <a href="admin_dashboard.php" class="<?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
        
        <a href="admin_qr_dashboard.php" class="<?php echo ($current_page == 'admin_qr_dashboard.php') ? 'active' : ''; ?>">QR & Attendance</a>
        
        <a href="pending_approvals.php" class="<?php echo ($current_page == 'pending_approvals.php') ? 'active' : ''; ?>">
            <span>Approvals</span>
            <?php if($sidebar_pending_count > 0): ?>
                <span class="badge-sidebar"><?php echo $sidebar_pending_count; ?></span>
            <?php endif; ?>
        </a>
    </div>
    <a href="logout.php" class="btn-logout-sidebar">Logout</a>
</div>