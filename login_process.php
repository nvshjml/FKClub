<?php
session_start(); // Start the session to remember the user
require 'db_connect.php'; // Bring in your silent database connection!

// Check if the login button was clicked
if (isset($_POST['login_btn'])) {
    
    // Grab the email and password the user typed in the form
    $email = $_POST['email'];
    $password = $_POST['password'];

    // SQL Query to find the user by email
    $sql = "SELECT * FROM USER WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // If a user is found with that email
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Check if the password matches
        if ($password == $user['pass_hash']) {
            
            // NEW SECURITY CHECK: Are they approved?
            if ($user['account_status'] == 'Pending') {
                echo "<script>alert('Your account is still waiting for Admin approval.'); window.location.href='index.php';</script>";
                exit();
            } elseif ($user['account_status'] == 'Rejected') {
                echo "<script>alert('Your registration was rejected by the Admin.'); window.location.href='index.php';</script>";
                exit();
            }

            // Success! Save their details in the Session memory
            
            // Success! Save their details in the Session memory
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['name'] = $user['name'];

            // Route them to the correct dashboard based on their role
            if ($user['role_id'] == 1) {
                // It's an Admin
                header("Location: admin_dashboard.php");
                exit();
            } else {
                // It's a Student or Committee
                header("Location: student_dashboard.php");
                exit();
            }

        } else {
            // Password was wrong
            echo "<script>alert('Incorrect Password!'); window.location.href='index.php';</script>";
        }
    } else {
        // Email not found in database
        echo "<script>alert('Email not found!'); window.location.href='index.php';</script>";
    }
} else {
    // If someone tries to visit this page without clicking the login button
    header("Location: index.php");
    exit();
}
?>