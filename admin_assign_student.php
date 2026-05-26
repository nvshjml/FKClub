<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

// SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

$msg = "";
// Handle Assignment Submission
if (isset($_POST['assign_btn'])) {
    $user_id = $_POST['user_id'];
    $club_id = $_POST['club_id'];
    $status = 'Approved'; // Admin assignment is automatically approved

    // Check if the student is already a member of this club
    $check_stmt = $conn->prepare("SELECT * FROM club_membership WHERE user_id = ? AND club_id = ?");
    $check_stmt->bind_param("si", $user_id, $club_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $msg = "<div class='alert alert-error'>❌ This student is already a member of this club.</div>";
    } else {
        // Corrected: Insert into club_membership table
        $stmt = $conn->prepare("INSERT INTO club_membership (user_id, club_id, status) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $user_id, $club_id, $status);
        
        if($stmt->execute()) {
            $msg = "<div class='alert alert-success'>✅ Student assigned to club successfully!</div>";
        } else {
            $msg = "<div class='alert alert-error'>❌ Error: " . $conn->error . "</div>";
        }
    }
}

// Get user from URL
$selected_user_id = $_GET['user_id'] ?? '';

// Fetch Students and Clubs
$students = $conn->query("SELECT user_id, name FROM `USER` WHERE role = 'Student'");
$clubs = $conn->query("SELECT club_id, club_name FROM CLUB");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Student to Club</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Global Reset & Layout */
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: #e2e8f0; display: flex; min-height: 100vh; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; }
        
        /* Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h2 { color: #1a202c; font-size: 24px; margin: 0; display: flex; align-items: center; gap: 8px;}

        /* Buttons */
        .btn-primary { background: #3182ce; color: white; border: none; padding: 12px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 14px; width: 100%; margin-top: 10px; }
        .btn-primary:hover { background: #2b6cb0; }
        
        .btn-back { display: inline-block; background: #718096; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 14px; transition: 0.2s; }
        .btn-back:hover { background: #4a5568; }

        /* Form Card */
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 600px; border-top: 4px solid #3182ce; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 8px; color: #4a5568; }
        
        select { width: 100%; padding: 12px 15px; border-radius: 8px; border: 1px solid #cbd5e0; outline: none; font-size: 14px; transition: border 0.2s; color: #2d3748; background-color: #fff; }
        select:focus { border-color: #3182ce; box-shadow: 0 0 0 3px rgba(49,130,206,0.1); }

        /* Alerts */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .alert-success { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2>🎓 Assign Student to Club</h2>
            <a href="admin_manage_users.php" class="btn-back">← Back to Manage Users</a>
        </div>

        <div class="card">
            <?php echo $msg; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Select Student</label>
                    <select name="user_id" required>
                        <?php while($s = $students->fetch_assoc()): ?>
                            <option value="<?php echo $s['user_id']; ?>" <?php if($selected_user_id == $s['user_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($s['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Select Club</label>
                    <select name="club_id" required>
                        <?php while($c = $clubs->fetch_assoc()): ?>
                            <option value="<?php echo $c['club_id']; ?>"><?php echo htmlspecialchars($c['club_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <button type="submit" name="assign_btn" class="btn-primary">Assign Student</button>
            </form>
        </div>
    </div>

</body>
</html>