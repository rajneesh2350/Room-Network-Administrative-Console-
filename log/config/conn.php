<?php

error_reporting(1);
ini_set('display_errors', 1);
$servername = "localhost";
$username = "igipess_c41duigipess";
$password = "MyPassword26November1972";
$dbname = "igipess_r261172";
// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Encryption key (keep this secure!)
define('ENCRYPTION_KEY', 'your-256-bit-secret-key-here-change-this');
define('ENCRYPTION_IV', '1234567890123456'); // 16 chars for AES-256-CBC

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


?>