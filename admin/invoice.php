<?php
session_start();
if ($_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require '../config/koneksi.php';

// Validasi ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: transaksi_list.php");
    exit;
}

$id = (int)$_GET['id'];

// Get transaction details with prepared statement
$trans_stmt = $conn->prepare("
    SELECT transaksi.*, users.nama, users.username
    FROM transaksi
    JOIN users ON transaksi.user_id = users.id
    WHERE transaksi.id = ?
");
$trans_stmt->bind_param("i", $id);
$trans_stmt->execute();
$trans_result = $trans_stmt->get_result();
$trans = $trans_result->fetch_assoc();

if (!$trans) {
    header("Location: transaksi_list.php");
    exit;
}

// Get transaction details
$detail_stmt = $conn->prepare("
    SELECT transaksi_detail.*, produk.nama_produk, produk.harga
    FROM transaksi_detail
    JOIN produk ON transaksi_detail.produk_id = produk.id
    WHERE transaksi_id = ?
    ORDER BY transaksi_detail.id
");
$detail_stmt->bind_param("i", $id);
$detail_stmt->execute();
$detail = $detail_stmt->get_result();

// Get payment method display name
$metode_display = array(
    'cash' => 'Tunai (Cash)',
    'debit' => 'Debit Card',
    'credit' => 'Credit Card',
    'transfer' => 'Transfer Bank',
    'ewallet' => 'E-Wallet',
    'qris' => 'QRIS'
);
$metode = isset($metode_display[$trans['metode_pembayaran']]) ? $metode_display[$trans['metode_pembayaran']] : $trans['metode_pembayaran'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo str_pad($trans['id'], 6, '0', STR_PAD_LEFT); ?> - Kopi 21</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        .content {
            padding: 20px;
        }
        .invoice-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .invoice-card {
            background: white;
            border: 2px solid #6f4e37;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .invoice-header {
            text-align: center;
            border-bottom: 3px solid #6f4e37;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .invoice-header h1 {
            color: #6f4e37;
            margin-bottom: 5px;
            font-size: 2.5rem;
            font-weight: bold;
        }
        .invoice-number {
            color: #999;
            font-size: 1rem;
            margin-bottom: 10px;
        }
        .invoice-brand {
            font-size: 0.9rem;
            color: #666;
        }
        .invoice-section {
            margin-bottom: 25px;
        }
        .invoice-section-title {
            color: #6f4e37;
            font-weight: bold;
            font-size: 0.9rem;
            text-transform: uppercase;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #ddd;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.95rem;
        }
        .detail-row.header {
            font-weight: bold;
            color: #6f4e37;
            border-bottom: 2px solid #6f4e37;
            margin-bottom: 10px;
        }
        .detail-row.total {
            font-weight: bold;
            color: #6f4e37;
            font-size: 1.1rem;
            border-top: 2px solid #6f4e37;
            border-bottom: 2px solid #6f4e37;
            padding: 12px 0;
            margin-top: 15px;
        }
        .table-items {
            margin-bottom: 20px;
        }
        .payment-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            display: inline-block;
            font-size: 0.85rem;
            margin-bottom: 15px;
        }
        .payment-badge.pending {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .info-block {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        .info-block-title {
            color: #6f4e37;
            font-weight: bold;
            font-size: 0.85rem;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .info-block-content {
            color: #333;
            font-size: 0.95rem;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            justify-content: center;
        }
        .btn-print {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-print:hover {
            background: linear-gradient(135deg, #5a3d2a 0%, #764a2e 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
            text-decoration: none;
        }
        .btn-back {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-back:hover {
            background: #5a6268;
            color: white;
        }
        .footer-text {
            text-align: center;
            color: #999;
            font-size: 0.85rem;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        @media print {
            .navbar, .sidebar, .no-print, .action-buttons {
                display: none !important;
            }
            body {
                background: white;
            }
            .content {
                padding: 0;
            }
            .invoice-card {
                box-shadow: none;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-coffee"></i> Kopi 21
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
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
                    <a href="../index.php" class="nav-link">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="produk_index.php" class="nav-link">
                        <i class="fas fa-box"></i> Daftar Produk
                    </a>
                    <a href="kategori_index.php" class="nav-link">
                        <i class="fas fa-tag"></i> Kelola Kategori
                    </a>
                    <a href="transaksi_list.php" class="nav-link active">
                        <i class="fas fa-list"></i> Transaksi
                    </a>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 content">
                <div class="invoice-container">
                    <div class="invoice-card">
                        <!-- Header -->
                        <div class="invoice-header">
                            <h1><i class="fas fa-receipt"></i> INVOICE</h1>
                            <div class="invoice-number">#<?php echo str_pad($trans['id'], 6, '0', STR_PAD_LEFT); ?></div>
                            <div class="invoice-brand">Kopi 21 - Sistem Manajemen Penjualan</div>
                        </div>

                        <!-- Transaction Info -->
                        <div class="invoice-section">
                            <div class="info-grid">
                                <div class="info-block">
                                    <div class="info-block-title">Nomor Transaksi</div>
                                    <div class="info-block-content">#<?php echo str_pad($trans['id'], 6, '0', STR_PAD_LEFT); ?></div>
                                </div>
                                <div class="info-block">
                                    <div class="info-block-title">Tanggal & Waktu</div>
                                    <div class="info-block-content"><?php echo date('d/m/Y H:i', strtotime($trans['tanggal'])); ?></div>
                                </div>
                                <div class="info-block">
                                    <div class="info-block-title">Kasir</div>
                                    <div class="info-block-content"><?php echo htmlspecialchars($trans['nama']); ?></div>
                                </div>
                                <div class="info-block">
                                    <div class="info-block-title">Status Pembayaran</div>
                                    <div class="info-block-content">
                                        <span class="payment-badge <?php echo ($trans['status'] === 'completed') ? '' : 'pending'; ?>">
                                            <?php echo ($trans['status'] === 'completed') ? '✓ Selesai' : 'Pending'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Items Table -->
                        <div class="invoice-section">
                            <div class="invoice-section-title"><i class="fas fa-boxes"></i> Detail Produk</div>
                            <div class="table-items">
                                <div class="detail-row header">
                                    <span style="flex: 1;">Produk</span>
                                    <span style="flex: 0 0 100px; text-align: right;">Harga</span>
                                    <span style="flex: 0 0 80px; text-align: center;">Jumlah</span>
                                    <span style="flex: 0 0 120px; text-align: right;">Subtotal</span>
                                </div>

                                <?php while ($d = $detail->fetch_assoc()): ?>
                                    <div class="detail-row">
                                        <span style="flex: 1;"><?php echo htmlspecialchars($d['nama_produk']); ?></span>
                                        <span style="flex: 0 0 100px; text-align: right;">Rp <?php echo number_format($d['harga'], 0, ',', '.'); ?></span>
                                        <span style="flex: 0 0 80px; text-align: center;"><?php echo $d['jumlah']; ?></span>
                                        <span style="flex: 0 0 120px; text-align: right;">Rp <?php echo number_format($d['subtotal'], 0, ',', '.'); ?></span>
                                    </div>
                                <?php endwhile; ?>

                                <div class="detail-row total">
                                    <span style="flex: 1;">TOTAL</span>
                                    <span style="flex: 0 0 300px; text-align: right;">Rp <?php echo number_format($trans['total'], 0, ',', '.'); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <?php if (!empty($trans['metode_pembayaran'])): ?>
                            <div class="invoice-section">
                                <div class="invoice-section-title"><i class="fas fa-credit-card"></i> Metode Pembayaran</div>
                                <div class="detail-row">
                                    <span>Metode:</span>
                                    <strong><?php echo $metode; ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Footer -->
                        <div class="footer-text">
                            <p style="margin: 0;">Terima kasih telah berbelanja di <strong>Kopi 21</strong></p>
                            <p style="margin: 5px 0 0 0;">Sistem Manajemen Penjualan | Versi 1.0</p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons no-print">
                        <button class="btn-print" onclick="window.print()">
                            <i class="fas fa-print"></i> Cetak Invoice
                        </button>
                        <a href="transaksi_list.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
