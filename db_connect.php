<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "cmc_flag_system";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Ensure $mysqli is available for scripts expecting it
$mysqli = $conn;
?>