<?php
session_start(); 
require 'db_connect.php';
require 'session_timeout.php';

if (isset($_POST['login_btn'])) {
    $user_id_input = $_POST['user_id'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM `user` WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $user_id_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // SECURE PASSWORD VERIFICATION
        if (password_verify($password, $user['pass_hash'])) {
            
            // --- THE FIX: NEW ACCOUNT STATUS CHECK ---
            if ($user['account_status'] == 'Inactive') {
                echo "<script>alert('Your account is currently inactive. Please contact the administrator.'); window.location.href='index.php';</script>";
                exit();
            }

            // Set Session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

            // Role-based Redirection
            if ($user['role'] == 'Admin') {
                header("Location: admin_dashboard.php");
            } elseif ($user['role'] == 'Committee') {
                header("Location: student_dashboard.php");
            } else {
                header("Location: student_dashboard.php");
            }
            exit();

        } else {
            // Generic message for both password/ID failure is safer against enumeration
            echo "<script>alert('Invalid Matrix ID or Password!'); window.location.href='index.php';</script>";
        }
    } else {
        echo "<script>alert('Invalid Matrix ID or Password!'); window.location.href='index.php';</script>";
    }
} else {
    header("Location: index.php");
    exit();
}
?>