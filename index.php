<?php
// Start the session so we can remember who logged in later
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FK Club System - Welcome</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* 1. Global Reset & Modern Font */
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
        
        /* 2. Beautiful Gradient Background */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: #333;
        }

        /* 3. Floating Modern Card */
        .login-container {
            background-color: rgba(255, 255, 255, 0.95);
            padding: 40px 50px;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1), 0 5px 15px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 420px;
            backdrop-filter: blur(10px); /* Gives a slight glass effect */
        }

        /* 4. Sleek Typography */
        h2 {
            text-align: center;
            color: #1a202c;
            margin-bottom: 8px;
            font-size: 28px;
            font-weight: 700;
        }
        .subtitle {
            text-align: center;
            color: #718096;
            margin-top: 0;
            margin-bottom: 30px;
            font-size: 14px;
        }

        /* 5. Clean Input Fields */
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 600;
            font-size: 14px;
        }
        input[type="email"], input[type="password"] {
            width: 100%;
            padding: 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background-color: #f8fafc;
            font-size: 15px;
            transition: all 0.3s ease; /* Smooth animation */
        }
        
        /* The glow effect when typing */
        input[type="email"]:focus, input[type="password"]:focus {
            border-color: #3182ce;
            background-color: #ffffff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.2);
        }
        
        /* 6. Vibrant, Animated Buttons */
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #3182ce 0%, #2b6cb0 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-login:hover { 
            transform: translateY(-2px); /* Button lifts up slightly */
            box-shadow: 0 7px 14px rgba(49, 130, 206, 0.3);
        }

        /* 7. Elegant Divider */
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 25px 0;
            color: #a0aec0;
            font-size: 14px;
            font-weight: 600;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e2e8f0;
        }
        .divider:not(:empty)::before { margin-right: .25em; }
        .divider:not(:empty)::after { margin-left: .25em; }
        
        /* Secondary Action Button */
        .btn-register {
            display: block;
            width: 100%;
            padding: 14px;
            background-color: #edf2f7;
            color: #2d3748;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.2s;
        }
        .btn-register:hover { 
            background-color: #e2e8f0; 
            color: #1a202c;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <h2>Welcome Back</h2>
        <p class="subtitle">Log in to the FK Club System</p>
        
        <form action="login_process.php" method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="student@siswa.edu">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>

            <button type="submit" name="login_btn" class="btn-login">Sign In</button>
        </form>

        <div class="divider">New Student?</div>
        
        <a href="public_register.php" class="btn-register">Create an Account</a>
    </div>

</body>
</html>