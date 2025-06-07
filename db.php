<?php
$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'user_management';

$conn = new mysqli($host, $user, $password, $dbname,4306);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
