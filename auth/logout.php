<?php
session_start();

// Destroy session
session_destroy();

// Hapus semua session variables
$_SESSION = array();

// Redirect ke login
header('Location: login.php');
exit;

