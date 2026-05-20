<?php
require 'db_connect.php';
$message = "";

if (isset($_POST['register_btn'])) {
    $student_id = $_POST['student_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password']; 
    $role = 2; // FIXED: Changed from $role_id to $role
    $status = 'Pending'; 

    // Check if email ends with the university domain
    if (strpos($email, '@siswa.edu') === false) {
        $message = "<div class='alert alert-error'>❌ You must use a valid university email (@siswa.edu).</div>";
    } else {
        // FIXED: Added backticks to `USER` and changed role_id to role
        $sql = "INSERT INTO `USER` (user_id, role, name, email, pass_hash, phone, account_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("iisssss", $student_id, $role, $name, $email, $password, $phone, $status);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'>✅ Registration successful! Please wait for Admin approval before logging in.</div>";
            } else {
                $message = "<div class='alert alert-error'>❌ Error: Matrix ID or Email already exists.</div>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Modern UI Reset */
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            color: #333;
            padding: 20px;
        }

        .form-card {
            background-color: rgba(255, 255, 255, 0.95);
            padding: 40px 50px;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1), 0 5px 15px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 450px;
            backdrop-filter: blur(10px);
        }

        h2 { text-align: center; color: #1a202c; margin-top: 0; margin-bottom: 25px; font-size: 26px; }

        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; color: #4a5568; font-weight: 600; font-size: 14px; }
        
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background-color: #f8fafc;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        input:focus {
            border-color: #3182ce; background-color: #ffffff; outline: none;
            box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.2);
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #3182ce 0%, #2b6cb0 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button:hover { transform: translateY(-2px); box-shadow: 0 7px 14px rgba(49, 130, 206, 0.3); }

        .link {
            display: block; text-align: center; margin-top: 25px;
            color: #718096; text-decoration: none; font-size: 14px; font-weight: 600; transition: 0.2s;
        }
        .link:hover { color: #2b6cb0; }

        /* Alerts */
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 600; text-align: center; }
        .alert-success { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background-color: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
    </style>
</head>
<body>
    <div class="form-card">
        <h2>Student Registration</h2>
        
        <?php echo $message; ?>
        
        <form action="public_register.php" method="POST">
            <div class="form-group">
                <label>Matrix ID</label>
                <input type="number" name="student_id" required placeholder="e.g. 123456">
            </div>
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" required placeholder="John Doe">
            </div>
            <div class="form-group">
                <label>University Email</label>
                <input type="email" name="email" placeholder="must end in @siswa.edu" required>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" required placeholder="01X-XXXXXXX">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" name="register_btn">Create Account</button>
        </form>
        
        <a href="index.php" class="link">Already have an account? Login here</a>
    </div>
</body>
</html>