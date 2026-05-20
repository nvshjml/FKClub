<?php
session_start();

// SECURITY CHECK: If they are not logged in, OR they are not a Student (role 2), kick them out!
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f6; margin: 0; }
        .navbar { background-color: #28a745; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; font-weight: bold; padding: 8px 12px; background-color: #d9534f; border-radius: 4px; }
        .navbar a:hover { background-color: #c9302c; }
        .container { padding: 20px; }
        .card { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
    </style>
</head>
<body>

    <div class="navbar">
        <div>FK Club System - Student Portal</div>
        <a href="logout.php">Logout</a>
    </div>

    <div class="container">
        <div class="card">
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>! 🎓</h2>
            <p>You are logged in as a <strong>Student</strong>. Here you can browse clubs, register for events, and check your points.</p>
        </div>
    </div>

</body>
</html>