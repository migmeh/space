<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);

$servername = "localhost";
$username = "root";
$password = "";

$database="";
$table="";

// Create connection
$conn = mysqli_connect($servername, $username, $password, $database);

// Check connection
if (!$conn) {
    die("malo u_u: " . mysqli_connect_error());
}

?>