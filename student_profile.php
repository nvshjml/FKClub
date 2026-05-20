<?php
session_start();
require 'db_connect.php';
// require 'session_timeout.php'; // Uncomment if you are using the timeout script

// SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'Admin') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$show_form = false; 

// ---------------------------------------------------------
// 1. HANDLE PROFILE UPDATE
// ---------------------------------------------------------
if (isset($_POST['update_btn'])) {
    $new_phone = $_POST['phone'];
    $new_password = $_POST['password'];
    $show_form = true; 
    
    if (!empty($new_password)) {
        $update_sql = "UPDATE `USER` SET phone = ?, pass_hash = ? WHERE user_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sss", $new_phone, $new_password, $user_id);
    } else {
        $update_sql = "UPDATE `USER` SET phone = ? WHERE user_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ss", $new_phone, $user_id);
    }
    
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>✅ Profile updated successfully!</div>";
    } else {
        $message = "<div class='alert alert-error'>❌ Error updating profile.</div>";
    }
}

// ---------------------------------------------------------
// 2. FETCH CURRENT PROFILE DATA
// ---------------------------------------------------------
$fetch_sql = "SELECT * FROM `USER` WHERE user_id = ?";
$stmt = $conn->prepare($fetch_sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

$words = explode(" ", $user_data['name']);
$initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));

$clubs_sql = "SELECT c.club_name FROM CLUB_MEMBERSHIP cm JOIN CLUB c ON cm.club_id = c.club_id WHERE cm.user_id = ?";
$stmt_clubs = $conn->prepare($clubs_sql);
$stmt_clubs->bind_param("s", $user_id);
$stmt_clubs->execute();
$clubs_result = $stmt_clubs->get_result();
$club_names = [];
while($row = $clubs_result->fetch_assoc()) {
    $club_names[] = $row['club_name'];
}
$clubs_string = empty($club_names) ? "No clubs joined yet" : implode(", ", $club_names);

$comm_sql = "SELECT c.club_name, com.position FROM COMMITTEE com JOIN CLUB c ON com.club_id = c.club_id WHERE com.user_id = ?";
$stmt_comm = $conn->prepare($comm_sql);
$stmt_comm->bind_param("s", $user_id);
$stmt_comm->execute();
$comm_result = $stmt_comm->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        
        body {  
            display: flex; /* Added flex for sidebar layout */
            background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('https://images.unsplash.com/photo-1541339907198-e08756dedf3f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') no-repeat center center/cover; 
            background-attachment: fixed;
            min-height: 100vh; color: #333; 
            backdrop-filter: blur(5px);
        }

        /* --- SIDEBAR STYLES --- */
        .sidebar { width: 260px; background-color: #1a202c; color: white; display: flex; flex-direction: column; padding: 30px 20px; position: fixed; height: 100vh; box-shadow: 4px 0 10px rgba(0,0,0,0.1); z-index: 1000; top: 0; left: 0;}
        .sidebar-header { text-align: center; margin-bottom: 35px; }
        .sidebar-logo { max-width: 85px; margin-bottom: 12px; display: inline-block; }
        .sidebar-brand { font-size: 20px; font-weight: 700; color: #ffffff; margin-bottom: 6px; }
        .sidebar-role { font-size: 11px; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 1.5px; background: rgba(255,255,255,0.1); padding: 4px 12px; border-radius: 20px; display: inline-block; }
        .nav-links { display: flex; flex-direction: column; gap: 15px; flex-grow: 1; }
        .nav-links a { text-decoration: none; color: #a0aec0; font-weight: 600; padding: 12px 15px; border-radius: 8px; transition: 0.3s; display: block; }
        .nav-links a:hover, .nav-links a.active { background-color: #2d3748; color: white; }
        .btn-logout { background-color: #e53e3e; color: white; text-align: center; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: bold; margin-top: auto; transition: 0.2s; }
        .btn-logout:hover { background-color: #c53030; }

        /* --- MAIN CONTENT & PROFILE STYLES --- */
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px 20px; width: calc(100% - 260px); box-sizing: border-box; }
        .profile-wrapper { display: flex; flex-direction: column; gap: 20px; max-width: 600px; margin: 0 auto; }
        
        .card { background-color: rgba(255, 255, 255, 0.95); padding: 35px; border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.05); backdrop-filter: blur(10px); }
        .card-header { font-size: 18px; font-weight: 700; color: #1a202c; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #edf2f7; padding-bottom: 15px; }

        .user-intro { display: flex; align-items: center; gap: 20px; margin-bottom: 30px; }
        .avatar { width: 70px; height: 70px; background: #e2e8f0; color: #2b6cb0; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 24px; font-weight: 700; border: 3px solid white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .user-details h3 { font-size: 20px; color: #2d3748; margin-bottom: 5px; }
        .user-details p { color: #718096; font-size: 14px; }
        
        .badge { display: inline-block; background: #ebf8ff; color: #2b6cb0; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-top: 8px; border: 1px solid #bee3f8; }

        .info-list { list-style: none; margin-bottom: 25px; }
        .info-list li { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #edf2f7; font-size: 14px; }
        .info-label { color: #718096; font-weight: 500; }
        .info-value { color: #2d3748; font-weight: 600; text-align: right; max-width: 60%; }

        .btn-toggle { width: 100%; padding: 14px; background: #edf2f7; color: #4a5568; border: 2px dashed #cbd5e0; border-radius: 8px; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; }
        .btn-toggle:hover { background: #e2e8f0; border-color: #a0aec0; color: #2d3748; }

        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #4a5568; font-weight: 600; font-size: 14px; }
        input { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; background-color: #f8fafc; font-size: 15px; transition: 0.3s; }
        input:focus { border-color: #3182ce; background-color: #fff; outline: none; box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.2); }
        
        .btn-save { width: 100%; padding: 14px; background: linear-gradient(135deg, #3182ce 0%, #2b6cb0 100%); color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; margin-top: 10px; font-size: 15px; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 7px 14px rgba(49, 130, 206, 0.3); }

        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 600; text-align: center; }
        .alert-success { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background-color: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        
        #editFormCard { animation: slideDown 0.3s ease-out forwards; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">
            <img src="image/LogoUMP5.png" alt="UMPSA Logo" class="sidebar-logo">
            <div class="sidebar-brand">FK Club System</div>
            <div class="sidebar-role"><?php echo htmlspecialchars($_SESSION['role']); ?> Dashboard</div>
        </div>
        <div class="nav-links">
            <?php if ($_SESSION['role'] == 'Committee'): ?>
                <a href="committee_dashboard.php">Dashboard</a>
                <a href="student_profile.php" class="active">My Profile</a>
                <a href="committee_club_details.php">Club Details</a>
                <a href="committee_events.php">Manage Events</a>
                <a href="committee_attendance.php">Record Attendance</a>
            <?php else: ?>
                <a href="student_dashboard.php">Dashboard</a>
                <a href="student_profile.php" class="active">My Profile</a>
            <?php endif; ?>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>

    <div class="main-content">
        
        <?php if($message) echo "<div style='max-width: 600px; margin: 0 auto;'>$message</div>"; ?>

        <div class="profile-wrapper">
            
            <div class="card">
                <div class="card-header">👤 My Profile</div>
                
                <div class="user-intro">
                    <div class="avatar"><?php echo $initials; ?></div>
                    <div class="user-details">
                        <h3><?php echo htmlspecialchars($user_data['name']); ?></h3>
                        <p><?php echo htmlspecialchars($user_data['user_id']); ?> &bull; <?php echo htmlspecialchars($user_data['role']); ?></p>
                        
                        <?php 
                        if ($comm_result->num_rows > 0) {
                            while($comm = $comm_result->fetch_assoc()) {
                                echo "<div class='badge'>" . htmlspecialchars($comm['club_name']) . " — " . htmlspecialchars($comm['position']) . "</div>";
                            }
                        }
                        ?>
                    </div>
                </div>

                <ul class="info-list">
                    <li><span class="info-label">Email</span><span class="info-value"><?php echo htmlspecialchars($user_data['email']); ?></span></li>
                    <li><span class="info-label">Phone</span><span class="info-value"><?php echo htmlspecialchars($user_data['phone']); ?></span></li>
                    
                    <?php if ($user_data['role'] == 'Student'): ?>
                        <li><span class="info-label">Total Points</span><span class="info-value" style="color: #38a169;"><?php echo htmlspecialchars($user_data['total_point']); ?></span></li>
                    <?php endif; ?>
                    
                    <li><span class="info-label">Clubs Joined</span><span class="info-value"><?php echo htmlspecialchars($clubs_string); ?></span></li>
                </ul>

                <button class="btn-toggle" onclick="toggleEditForm()">✏️ Edit Profile</button>
            </div>

            <div class="card" id="editFormCard" style="display: <?php echo $show_form ? 'block' : 'none'; ?>;">
                <div class="card-header">✏️ Edit Profile Info</div>
                
                <form action="student_profile.php" method="POST">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user_data['phone']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>New Password (leave blank to keep current)</label>
                        <input type="password" name="password" placeholder="New password">
                    </div>

                    <button type="submit" name="update_btn" class="btn-save">✓ Save Changes</button>
                </form>
            </div>

        </div>
    </div>

    <script>
        function toggleEditForm() {
            var formCard = document.getElementById("editFormCard");
            if (formCard.style.display === "none" || formCard.style.display === "") {
                formCard.style.display = "block";
            } else {
                formCard.style.display = "none";
            }
        }
    </script>

</body>
</html>