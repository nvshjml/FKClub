<?php
session_start(); 
require 'db_connect.php'; 

if (isset($_POST['login_btn'])) {
    // CHANGED: Grab user_id instead of email
    $user_id_input = $_POST['user_id'];
    $password = $_POST['password'];

    // CHANGED: Query searches by user_id instead of email
    $sql = "SELECT * FROM `USER` WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $user_id_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if ($password == $user['pass_hash']) {
            
            if ($user['account_status'] == 'Pending') {
                echo "<script>alert('Your account is still waiting for Admin approval.'); window.location.href='index.php';</script>";
                exit();
            } elseif ($user['account_status'] == 'Rejected') {
                echo "<script>alert('Your registration was rejected by the Admin.'); window.location.href='index.php';</script>";
                exit();
            }

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

            if ($user['role'] == 'Admin') {
                header("Location: admin_dashboard.php");
                exit();
            } elseif ($user['role'] == 'Committee') {
                header("Location: committee_dashboard.php");
                exit();
            } else {
                header("Location: student_dashboard.php");
                exit();
            }

        } else {
            echo "<script>alert('Incorrect Password!'); window.location.href='index.php';</script>";
        }
    } else {
        // CHANGED: Alert text updated to reflect Matrix ID
        echo "<script>alert('Matrix ID not found!'); window.location.href='index.php';</script>";
    }
} else {
    header("Location: index.php");
    exit();
}
?>