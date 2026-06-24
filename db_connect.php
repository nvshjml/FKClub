<?php
$servername = "10.26.30.17";
$username = "cb24022";    
$password = "cb24022";       
$dbname = "cb24022"; 

$conn = mysqli_connect($servername, $username, $password, $dbname);

//check connection
if (mysqli_connect_errno()){
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
    exit();
}

?>