<?php
// Start the session so we can remember who logged in later
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FKClub - Login</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            /* Simulates the blurred campus background */
            background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('https://images.unsplash.com/photo-1541339907198-e08756dedf3f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') no-repeat center center/cover;
            backdrop-filter: blur(5px);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .auth-card {
            background-color: #ffffff;
            width: 100%;
            max-width: 450px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .card-header {
            padding: 30px 20px 20px;
            text-align: center;
            border-bottom: 1px solid #e0e0e0;
        }

        .card-header img {
            max-width: 120px;
            margin-bottom: 10px;
        }

        .card-header h2 {
            font-size: 1.2rem;
            color: #333;
            font-weight: 600;
        }

        .card-body {
            padding: 30px 40px 40px;
        }

        .instruction {
            text-align: center;
            color: #555;
            font-size: 0.95rem;
            margin-bottom: 25px;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            color: #333;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 0.95rem;
            background-color: #f8f9fa;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #0d6efd; 
            background-color: #fff;
        }

        .btn-primary {
            width: 100%;
            padding: 12px;
            background-color: #0d6efd; 
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: background-color 0.3s;
        }

        .btn-primary:hover {
            background-color: #0b5ed7;
        }
        
        .register-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #0d6efd;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .register-link:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 12px 15px;
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <div class="auth-card">
        <div class="card-header">
            <img src="image/LogoUMP5.png" alt="UMPSA Logo" class="logo">
            <h2>FKClub Login</h2>
        </div>
        
        <div class="card-body">
            <?php if (isset($_GET['status']) && $_GET['status'] === 'logged_out'): ?>
                <div class="alert">
                    🔒 Session has ended. Please login again.
                </div>
            <?php endif; ?>

            <p class="instruction">Enter credentials to access dashboard</p>
            
            <form action="login_process.php" method="POST">
                <div class="form-group">
                    <label>Matrix ID</label>
                    <input type="text" name="user_id" class="form-control" placeholder="e.g., CB26001" required>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                
                <button type="submit" name="login_btn" class="btn-primary">Login</button>
            </form>
            
            <a href="public_register.php" class="register-link">Create an Account</a>
        </div>
    </div>

</body>
</html>