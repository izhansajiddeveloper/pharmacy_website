<?php
// Start session
session_start();

// Database connection
$server   = "localhost";
$username = "root";
$password = "";
$database = "pharmacy_system"; // removed extra space

// Create connection
$conn = mysqli_connect($server, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8");