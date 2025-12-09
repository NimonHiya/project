<?php
session_start();
include 'config/koneksi.php';

// Redirect ke login jika belum login
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

// Redirect user regular ke halaman user, hanya admin yang bisa akses dashboard
if ($_SESSION['role'] !== 'admin') {
    header('Location: user/transaksi.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Ambil statistik untuk dashboard
$total_produk = mysqli_query($conn, "SELECT COUNT(*) as total FROM produk");
$row_produk = mysqli_fetch_assoc($total_produk);

$total_transaksi = mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi");
$row_transaksi = mysqli_fetch_assoc($total_transaksi);

$total_pendapatan = mysqli_query($conn, "SELECT SUM(total) as total FROM transaksi");
$row_pendapatan = mysqli_fetch_assoc($total_pendapatan);
$pendapatan = $row_pendapatan['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Kasir Kopi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        .sidebar {
            background-color: #fff;
            border-right: 1px solid #dee2e6;
            min-height: 100vh;
            padding: 20px 0;
        }
        .sidebar a {
            color: #333;
            padding: 10px 20px;
            display: block;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .sidebar a:hover {
            background-color: #f8f9fa;
            border-left-color: #6f4e37;
            color: #6f4e37;
        }
        .sidebar a.active {
            background-color: #e8dcc8;
            border-left-color: #6f4e37;
            color: #6f4e37;
            font-weight: bold;
        }
        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .stat-card {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            color: white;
        }
        .stat-card h5 {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: bold;
        }
        .stat-card i {
            font-size: 2.5rem;
            opacity: 0.3;
        }
        .content {
            padding: 20px;
        }
        .welcome-section {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .welcome-section h1 {
            margin-bottom: 10px;
        }
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .menu-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
        }
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
            color: #6f4e37;
        }
        .menu-card i {
            font-size: 3rem;
            color: #6f4e37;
            margin-bottom: 15px;
        }
        .menu-card h5 {
            margin-top: 15px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-coffee"></i> Kasir Kopi
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($username); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php">Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 sidebar">
                <div class="nav flex-column">
                    <a href="index.php" class="nav-link active">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="admin/produk_index.php" class="nav-link">
                        <i class="fas fa-box"></i> Produk
                    </a>
                    <a href="user/transaksi.php" class="nav-link">
                        <i class="fas fa-shopping-cart"></i> Transaksi
                    </a>
                    <a href="admin/transaksi_list.php" class="nav-link">
                        <i class="fas fa-list"></i> Laporan
                    </a>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 content">
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <h1><i class="fas fa-wave-hand"></i> Selamat Datang, <?php echo htmlspecialchars($username); ?>!</h1>
                    <p>Sistem Manajemen Kasir Kopi - <?php echo date('l, d F Y'); ?></p>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="card-title">Total Produk</h5>
                                        <div class="stat-value"><?php echo $row_produk['total']; ?></div>
                                    </div>
                                    <i class="fas fa-box"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="card-title">Total Transaksi</h5>
                                        <div class="stat-value"><?php echo $row_transaksi['total']; ?></div>
                                    </div>
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="card-title">Total Pendapatan</h5>
                                        <div class="stat-value">Rp <?php echo number_format($pendapatan, 0, ',', '.'); ?></div>
                                    </div>
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Menu -->
                <h3 class="mb-4"><i class="fas fa-th"></i> Menu Utama</h3>
                <div class="menu-grid">
                    <a href="admin/produk_index.php" class="menu-card">
                        <i class="fas fa-box"></i>
                        <h5>Kelola Produk</h5>
                        <p class="text-muted">Lihat dan kelola daftar produk</p>
                    </a>

                    <a href="admin/produk_add.php" class="menu-card">
                        <i class="fas fa-plus-circle"></i>
                        <h5>Tambah Produk</h5>
                        <p class="text-muted">Tambahkan produk baru</p>
                    </a>

                    <a href="user/transaksi.php" class="menu-card">
                        <i class="fas fa-shopping-cart"></i>
                        <h5>Buat Transaksi</h5>
                        <p class="text-muted">Proses penjualan baru</p>
                    </a>

                    <a href="admin/transaksi_list.php" class="menu-card">
                        <i class="fas fa-list"></i>
                        <h5>Riwayat Transaksi</h5>
                        <p class="text-muted">Lihat laporan penjualan</p>
                    </a>
                </div>

                <!-- Recent Transactions -->
                <div class="row mt-5">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="fas fa-history"></i> Transaksi Terakhir</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $query_recent = "SELECT t.*, u.username FROM transaksi t 
                                                LEFT JOIN users u ON t.user_id = u.id 
                                                ORDER BY t.tanggal DESC LIMIT 5";
                                $result = mysqli_query($conn, $query_recent);

                                if (mysqli_num_rows($result) > 0) {
                                    echo '<table class="table table-hover">';
                                    echo '<thead><tr><th>ID</th><th>Tanggal</th><th>User</th><th>Total</th><th>Status</th></tr></thead>';
                                    echo '<tbody>';
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        echo '<tr>';
                                        echo '<td>' . $row['id'] . '</td>';
                                        echo '<td>' . date('d/m/Y H:i', strtotime($row['tanggal'])) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['username']) . '</td>';
                                        echo '<td>Rp ' . number_format($row['total'], 0, ',', '.') . '</td>';
                                        echo '<td><span class="badge bg-success">Selesai</span></td>';
                                        echo '</tr>';
                                    }
                                    echo '</tbody>';
                                    echo '</table>';
                                } else {
                                    echo '<div class="alert alert-info">Belum ada transaksi</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
