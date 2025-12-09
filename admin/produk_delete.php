<?php
session_start();
require '../config/koneksi.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Validasi ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: produk_index.php');
    exit;
}

$id = (int)$_GET['id'];

try {
    $stmt = $conn->prepare("DELETE FROM produk WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    header('Location: produk_index.php?success=Product deleted');
} catch (Exception $e) {
    header('Location: produk_index.php?error=Failed to delete');
}
exit;

