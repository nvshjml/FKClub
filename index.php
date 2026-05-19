<?php
// Start the session so we can remember who logged in later
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FK Club System - Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-weight: bold;
        }
        input[type="email"], input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        /* Login Button Styling */
        .btn-login {
            width: 100%;
            padding: 12px;
            background-color: #0056b3;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn-login:hover { background-color: #004494; }

        /* Registration Divider and Button */
        .divider {
            text-align: center;
            margin: 20px 0;
            color: #888;
            font-size: 14px;
            border-bottom: 1px solid #eee;
            line-height: 0.1em;
        }
        .divider span { background: white; padding: 0 10px; }
        
        .btn-register {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: #28a745;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 4px;
            box-sizing: border-box;
            font-weight: bold;
            font-size: 16px;
            transition: 0.3s;
        }
        .btn-register:hover { background-color: #218838; }

    </style>
</head>
<body>

    <div class="login-container">
        <h2>System Login</h2>
        
        <form action="login_process.php" method="POST">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">
            </div>

            <button type="submit" name="login_btn" class="btn-login">Login</button>
        </form>

        <p class="divider"><span>OR</span></p>
        <a href="public_register.php" class="btn-register">Register as Student</a>

    </div>

</body>
</html>