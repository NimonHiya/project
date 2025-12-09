<?php
// ==========================================
// Database Connection Configuration
// ==========================================

$host = "localhost";
$user = "root";
$pass = "";
$db   = "coffee_kasir";

// Create connection dengan mysqli improved mode
$conn = mysqli_connect($host, $user, $pass, $db);

// Check connection
if (!$conn) {
    http_response_code(500);
    die("Database connection failed: " . mysqli_connect_error());
}

// Set charset to utf8mb4 untuk support emoji dan special characters
mysqli_set_charset($conn, "utf8mb4");

// Set error reporting untuk development
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

