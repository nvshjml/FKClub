<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Student') {
    header("Location: index.php"); 
    exit();
}

$club_id = $_GET['club_id'] ?? null;
$message = "";

if (isset($_POST['apply_btn'])) {
    $reason = $_POST['reason'];
    $stmt = $conn->prepare("INSERT INTO club_membership (user_id, club_id, join_date, status) VALUES (?, ?, CURDATE(), 'Pending')");
    $stmt->bind_param("ss", $_SESSION['user_id'], $club_id);
    
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>✅ Application submitted! Waiting for Admin approval.</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #e2e8f0; padding: 50px; }
        .card { background: white; max-width: 500px; margin: auto; padding: 30px; border-radius: 12px; }
        textarea { width: 100%; height: 100px; margin: 10px 0; padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px; font-family: 'Inter', sans-serif; }
        .btn { background: #3182ce; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn:hover { background: #2b6cb0; }
        .alert-success { background: #c6f6d5; color: #22543d; padding: 12px; border-radius: 6px; margin-bottom: 15px; }
        a { color: #718096; text-decoration: none; margin-left: 15px; font-weight: 600; }
        a:hover { color: #4a5568; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Club Application</h2>
        <?php echo $message; ?>
        <form method="POST">
            <label>Why do you want to join this club?</label>
            <textarea name="reason" required></textarea>
            <button type="submit" name="apply_btn" class="btn">Submit Application</button>
            <a href="student_browse_clubs.php">Cancel</a>
        </form>
    </div>
</body>
</html>