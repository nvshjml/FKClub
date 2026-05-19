<?php
require 'db_connect.php';
$message = "";

if (isset($_POST['register_btn'])) {
    $student_id = $_POST['student_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password']; 
    $role_id = 2; // Student
    $status = 'Pending'; // NEW: They go into the waiting list!

    // Check if email ends with the university domain (e.g., @siswa.edu)
    // You can change this to whatever your university email format is
    if (strpos($email, '@siswa.edu') === false) {
        $message = "<div style='color: red; margin-bottom: 15px;'>❌ You must use a valid university email (@siswa.edu).</div>";
    } else {
        $sql = "INSERT INTO USER (user_id, role_id, name, email, pass_hash, phone, account_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("iisssss", $student_id, $role_id, $name, $email, $password, $phone, $status);
            if ($stmt->execute()) {
                $message = "<div style='color: green; margin-bottom: 15px;'>✅ Registration successful! Please wait for Admin approval before logging in.</div>";
            } else {
                $message = "<div style='color: red; margin-bottom: 15px;'>❌ Error: Matrix ID or Email already exists.</div>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Registration</title>
    <style>
        /* Reusing your clean styles */
        body { font-family: Arial, sans-serif; background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .form-card { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background-color: #0056b3; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 10px; }
        button:hover { background-color: #004494; }
        .link { display: block; text-align: center; margin-top: 15px; color: #0056b3; text-decoration: none; }
    </style>
</head>
<body>
    <div class="form-card">
        <h2 style="text-align: center;">Student Registration</h2>
        <?php echo $message; ?>
        <form action="public_register.php" method="POST">
            <div class="form-group"><label>Matrix ID</label><input type="number" name="student_id" required></div>
            <div class="form-group"><label>Full Name</label><input type="text" name="name" required></div>
            <div class="form-group"><label>University Email</label><input type="email" name="email" placeholder="must end in @siswa.edu" required></div>
            <div class="form-group"><label>Phone Number</label><input type="text" name="phone" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <button type="submit" name="register_btn">Register</button>
        </form>
        <a href="index.php" class="link">Already have an account? Login here</a>
    </div>
</body>
</html>