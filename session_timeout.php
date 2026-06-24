<?php
// Set the timeout duration in seconds. 
// 1800 seconds = 30 minutes.
$timeout_duration = 1800;

if (isset($_SESSION['LAST_ACTIVITY'])) {
    
    $seconds_inactive = time() - $_SESSION['LAST_ACTIVITY'];

    if ($seconds_inactive > $timeout_duration) {
        
        session_unset();     
        session_destroy();   

        header("Location: index.php?status=logged_out");
        exit();
    }
}

$_SESSION['LAST_ACTIVITY'] = time();
?>