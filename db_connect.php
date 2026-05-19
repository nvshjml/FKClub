<?php
// XAMPP Default Database Credentials
$servername = "localhost";
$username = "root";     // Default XAMPP username
$password = "";         // Default XAMPP password is empty
$dbname = "fkclubdb"; // CHANGE THIS to the exact name of your database in phpMyAdmin

// Create the connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
else {/* echo "Connected successfully!"; */}

// Optional: Uncomment the line below just to test, then delete it later
 
?>