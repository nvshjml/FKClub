<?php
// XAMPP Default Database Credentials
$servername = "10.26.30.17";
$username = "cb24022";    
$password = "cb24022";       
$dbname = "cb24022"; 

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
else {/* echo "Connected successfully!"; */}

?>