<?php

$servername = "localhost";
$username = "root";    
$password = "";       
$dbname = "fkclubdb"; 

$conn = mysqli_connect($servername, $username, $password, $dbname);

//check connection
if (mysqli_connect_errno()){
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
    exit();
}

?>