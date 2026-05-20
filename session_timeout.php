<?php
// Set the timeout duration in seconds. 
// 1800 seconds = 30 minutes. (Change this number if you want a longer/shorter timeout)
$timeout_duration = 1800;

// Check if the "LAST_ACTIVITY" timestamp exists in the session
if (isset($_SESSION['LAST_ACTIVITY'])) {
    
    // Calculate how many seconds have passed since they last did something
    $seconds_inactive = time() - $_SESSION['LAST_ACTIVITY'];
    
    // If they have been inactive longer than our allowed duration...
    if ($seconds_inactive > $timeout_duration) {
        
        // 1. Wipe all session data
        session_unset();     
        session_destroy();   
        
        // 2. Redirect them to the login page with the logged_out message
        header("Location: index.php?status=logged_out");
        exit();
    }
}

// Update the "LAST_ACTIVITY" timestamp to the current time every time they load a page
$_SESSION['LAST_ACTIVITY'] = time();
?>