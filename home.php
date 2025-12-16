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
    $stmt_produk = $conn->prepare("SELECT COUNT(*) as total FROM produk WHERE stok > 0"); // Hanya hitung produk tersedia
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
    // Fallback jika koneksi/query gagal
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
        :root {
            --color-primary: #6f4e37; /* Coffee Brown */
            --color-secondary: #8b5a3c; /* Lighter Brown */
            --color-light: #f8f9fa;
            --color-accent: #e8dcc8; /* Cream/Light Coffee */
            --color-danger: #dc3545;
        }

        body {
            /* Background lebih lembut, padding atas menyesuaikan navbar fixed */
            background-color: var(--color-light); 
            min-height: 100vh;
            padding-top: 70px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container-main {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 15px;
        }

        /* --- Navbar Styles --- */
        .navbar {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
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
        .user-name {
            color: white;
            font-weight: 500;
            margin-right: 15px;
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
        
        /* --- Welcome Hero Styles --- */
        .welcome-hero {
            background: var(--color-primary); 
            color: white;
            padding: 50px 20px;
            text-align: center;
            border-radius: 15px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%238b5a3c" fill-opacity="0.1" d="M0,192L48,170.7C96,149,192,107,288,112C384,117,480,171,576,192C672,213,768,203,864,186.7C960,171,1056,149,1152,149.3C1248,149,1344,171,1392,181.3L1440,192L1440,0L1392,0C1344,0,1248,0,1152,0C1056,0,960,0,864,0C768,0,672,0,576,0C480,0,384,0,288,0C192,0,96,0,48,0L0,0Z"></path></svg>');
            background-repeat: no-repeat;
            background-position: bottom;
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
        .welcome-icon {
            font-size: 3.5rem;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* --- Stats Card Styles --- */
        .quick-stats {
            background: white;
            padding: 30px 20px;
            border-radius: 15px;
            margin-bottom: 40px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        .stat-item {
            text-align: center;
            padding: 20px 10px;
            border-right: 1px solid var(--color-accent);
            /* Styling for individual stat box */
            background-color: var(--color-light); 
            border-radius: 8px;
            margin: 5px 0;
            transition: background-color 0.3s;
        }
        .stat-item:hover {
             background-color: var(--color-accent); 
        }
        .stat-item:last-child {
             border-right: none;
        }
        @media (max-width: 768px) {
            .stat-item {
                border-right: none;
                border-bottom: 1px solid var(--color-accent);
            }
             .stat-item:last-child {
                border-bottom: none;
            }
        }
        .stat-value {
            font-size: 2.2rem; /* Ukuran lebih besar */
            color: var(--color-primary);
            font-weight: 700; /* Lebih tebal */
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* --- Menu Card Styles --- */
        .menu-title {
            color: var(--color-primary);
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 1.8rem;
            text-align: center;
        }
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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
            border: 1px solid var(--color-accent); /* Border baru */
        }
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            background-color: var(--color-accent);
        }
        .menu-card i {
            font-size: 3.5rem; /* Lebih besar */
            color: var(--color-primary);
            margin-bottom: 15px;
            display: block;
        }
        .menu-card h5 {
            color: var(--color-primary);
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 1.2rem;
        }
        .menu-card p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        
        /* Override Logout Card Styling */
        .menu-card.logout-card {
            border-left: 5px solid var(--color-danger);
            border-top: none;
        }
        .menu-card.logout-card i {
             color: var(--color-danger) !important;
        }
        .menu-card.logout-card h5 {
             color: var(--color-danger) !important;
        }
        .menu-card.logout-card:hover {
            background-color: #fcebeb; /* Light red hover */
        }

        .footer-info {
            text-align: center;
            color: #666;
            margin-top: 40px;
            padding: 20px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid container-main">
            <a class="navbar-brand" href="home.php">
                <i class="fas fa-coffee"></i> Kopi 21
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item d-flex align-items-center">
                        <span class="user-name"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($username); ?></span>
                        <a href="auth/logout.php" class="btn-logout">
                            <i class="fas fa-sign-out-alt"></i> Keluar
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-main">
        <div class="welcome-hero">
            <div class="welcome-icon">
                <i class="fas fa-mug-hot"></i> </div>
            <h1>Dashboard Kasir Kopi</h1>
            <div class="greeting">Halo, <?php echo htmlspecialchars($username); ?> Selamat Berbelanja.</div>
            <p>Akses cepat untuk tugas harian kasir Anda.</p>
        </div>

        <div class="quick-stats">
            <h4><i class="fas fa-chart-line"></i> Kinerja Singkat</h4>
            <div class="row gx-2">
                <div class="col-md-4 stat-item">
                    <div class="stat-value"><?php echo $today_stats['count']; ?></div>
                    <div class="stat-label"><i class="fas fa-receipt"></i> Transaksi Hari Ini</div>
                </div>
                <div class="col-md-4 stat-item">
                    <div class="stat-value">Rp <?php echo number_format($today_stats['total'], 0, ',', '.'); ?></div>
                    <div class="stat-label"><i class="fas fa-money-bill-wave"></i> Total Pendapatan Hari Ini</div>
                </div>
                <div class="col-md-4 stat-item">
                    <div class="stat-value"><?php echo $produk_stats['total']; ?></div>
                    <div class="stat-label"><i class="fas fa-box-open"></i> Produk Tersedia</div>
                </div>
            </div>
        </div>

        <div class="menu-container">
            <h2 class="menu-title"><i class="fas fa-th-large"></i> Menu Utama Kasir</h2>
            <div class="menu-grid">
                
                <a href="user/transaksi.php" class="menu-card">
                    <i class="fas fa-cash-register"></i>
                    <h5>Mulai Transaksi Baru</h5>
                    <p>Lakukan penjualan dengan cepat dan mudah.</p>
                </a>

                <a href="user/riwayat_transaksi.php" class="menu-card">
                    <i class="fas fa-clipboard-list"></i>
                    <h5>Lihat Riwayat Penjualan</h5>
                    <p>Cek kembali daftar semua transaksi yang sudah diselesaikan.</p>
                </a>

                <a href="auth/logout.php" class="menu-card logout-card">
                    <i class="fas fa-sign-out-alt"></i>
                    <h5>Logout Sistem</h5>
                    <p>Amankan sesi Anda sebelum meninggalkan perangkat.</p>
                </a>
                
                </div>
        </div>

        <div class="footer-info">
            <p>
                <i class="fas fa-info-circle"></i>
                Kasir Kopi - Sistem Penjualan | Versi 1.0
                <br>
                <small>© 2025 - Hak Cipta Dilindungi</small>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>