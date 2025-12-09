<?php
session_start();
require 'config/koneksi.php';

// Redirect ke login jika belum login
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

// Redirect admin ke dashboard
if ($_SESSION['role'] === 'admin') {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Get statistics
try {
    // Transaksi hari ini
    $stmt_today = $conn->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total 
        FROM transaksi 
        WHERE user_id = ? AND DATE(tanggal) = CURDATE()
    ");
    $stmt_today->bind_param("i", $user_id);
    $stmt_today->execute();
    $today_stats = $stmt_today->get_result()->fetch_assoc();
    
    // Total produk
    $stmt_produk = $conn->prepare("SELECT COUNT(*) as total FROM produk");
    $stmt_produk->execute();
    $produk_stats = $stmt_produk->get_result()->fetch_assoc();
    
    // Transaksi bulan ini
    $stmt_month = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM transaksi 
        WHERE user_id = ? AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())
    ");
    $stmt_month->bind_param("i", $user_id);
    $stmt_month->execute();
    $month_stats = $stmt_month->get_result()->fetch_assoc();
} catch (Exception $e) {
    $today_stats = array('count' => 0, 'total' => 0);
    $produk_stats = array('total' => 0);
    $month_stats = array('count' => 0);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Kasir Kopi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        body {
            padding-top: 70px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8dcc8 100%);
            min-height: 100vh;
        }
        .welcome-hero {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
            border-radius: 15px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .welcome-hero h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .welcome-hero .greeting {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        .welcome-hero p {
            font-size: 1rem;
            opacity: 0.8;
        }
        .welcome-icon {
            font-size: 3.5rem;
            margin-bottom: 20px;
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .menu-container {
            margin-bottom: 40px;
        }
        .menu-title {
            color: #6f4e37;
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 1.8rem;
            text-align: center;
        }
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        .menu-card {
            background: white;
            padding: 30px 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            text-decoration: none;
            color: #333;
            border-top: 5px solid #6f4e37;
        }
        .menu-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            color: #333;
            text-decoration: none;
        }
        .menu-card i {
            font-size: 3rem;
            color: #6f4e37;
            margin-bottom: 15px;
            display: block;
        }
        .menu-card h5 {
            color: #6f4e37;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .menu-card p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        .quick-stats {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 40px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .quick-stats h4 {
            color: #6f4e37;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
        }
        .stat-item {
            text-align: center;
            padding: 20px;
        }
        .stat-value {
            font-size: 1.8rem;
            color: #6f4e37;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        .footer-info {
            text-align: center;
            color: #666;
            margin-top: 40px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .container-main {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-name {
            color: white;
            font-weight: 500;
        }
        .btn-logout {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid white;
            padding: 6px 15px;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="home.php">
                <i class="fas fa-coffee"></i> Kasir Kopi
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <div class="user-menu">
                            <span class="user-name"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($username); ?></span>
                            <a href="auth/logout.php" class="btn-logout">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-main">
        <!-- Welcome Hero -->
        <div class="welcome-hero">
            <div class="welcome-icon">
                <i class="fas fa-coffee"></i>
            </div>
            <h1>Selamat Datang di Kasir Kopi</h1>
            <div class="greeting">Halo, <?php echo htmlspecialchars($username); ?>! 👋</div>
            <p>Sistem Manajemen Penjualan Kopi Terpadu</p>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
            <h4><i class="fas fa-chart-bar"></i> Ringkasan Transaksi Saya</h4>
            <div class="row">
                <div class="col-md-4 stat-item">
                    <div class="stat-value"><?php echo $today_stats['count']; ?></div>
                    <div class="stat-label"><i class="fas fa-calendar-day"></i> Transaksi Hari Ini</div>
                </div>
                <div class="col-md-4 stat-item">
                    <div class="stat-value"><?php echo $produk_stats['total']; ?></div>
                    <div class="stat-label"><i class="fas fa-box"></i> Total Produk Tersedia</div>
                </div>
                <div class="col-md-4 stat-item">
                    <div class="stat-value"><?php echo $month_stats['count']; ?></div>
                    <div class="stat-label"><i class="fas fa-calendar"></i> Transaksi Bulan Ini</div>
                </div>
            </div>
        </div>

        <!-- Menu Section -->
        <div class="menu-container">
            <h2 class="menu-title"><i class="fas fa-th"></i> Menu Utama</h2>
            <div class="menu-grid">
                <a href="user/transaksi.php" class="menu-card">
                    <i class="fas fa-shopping-cart"></i>
                    <h5>Buat Transaksi</h5>
                    <p>Proses penjualan dan pembayaran</p>
                </a>

                <a href="user/riwayat_transaksi.php" class="menu-card">
                    <i class="fas fa-history"></i>
                    <h5>Riwayat Transaksi</h5>
                    <p>Lihat semua transaksi Anda</p>
                </a>

                <a href="auth/logout.php" class="menu-card" style="border-top-color: #dc3545;">
                    <i class="fas fa-sign-out-alt" style="color: #dc3545;"></i>
                    <h5 style="color: #dc3545;">Logout</h5>
                    <p>Keluar dari aplikasi</p>
                </a>
            </div>
        </div>

        <!-- Footer Info -->
        <div class="footer-info">
            <p>
                <i class="fas fa-info-circle"></i>
                Sistem Kasir Kopi - Versi 1.0
                <br>
                <small>© 2025 - Hak Cipta Dilindungi</small>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
