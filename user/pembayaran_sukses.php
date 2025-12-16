<?php
session_start();
require '../config/koneksi.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$transaction = null;

// Check if transaction_id provided
if (!isset($_GET['transaction_id']) || !is_numeric($_GET['transaction_id'])) {
    header('Location: transaksi.php');
    exit;
}

$transaction_id = (int)$_GET['transaction_id'];

// Get transaction details
try {
    $stmt = $conn->prepare("
        SELECT t.id, t.user_id, t.total, t.tanggal, t.status, t.metode_pembayaran
        FROM transaksi t
        WHERE t.id = ? AND t.user_id = ?
    ");
    $stmt->bind_param("ii", $transaction_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $transaction = $result->fetch_assoc();

    if (!$transaction) {
        header('Location: transaksi.php');
        exit;
    }

    // Get transaction details
    $detail_stmt = $conn->prepare("
        SELECT td.id, td.produk_id, td.jumlah, td.subtotal,
               p.nama_produk, p.harga
        FROM transaksi_detail td
        JOIN produk p ON td.produk_id = p.id
        WHERE td.transaksi_id = ?
        ORDER BY td.id
    ");
    $detail_stmt->bind_param("i", $transaction_id);
    $detail_stmt->execute();
    $details_result = $detail_stmt->get_result();
    $details = array();
    while ($row = $details_result->fetch_assoc()) {
        $details[] = $row;
    }
} catch (Exception $e) {
    $error = 'Gagal mengambil data transaksi';
}

// Get payment method display name
$metode_display = array(
    'cash' => 'Tunai (Cash)',
    'debit' => 'Debit Card',
    'credit' => 'Credit Card',
    'transfer' => 'Transfer Bank',
    'ewallet' => 'E-Wallet',
    'qris' => 'QRIS'
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Sukses - Kasir Kopi</title>
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
        .success-container {
            text-align: center;
            padding: 40px 20px;
        }
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
            animation: scaleIn 0.5s ease-in-out;
        }
        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.5);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        .success-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }
        .success-card h2 {
            color: #28a745;
            margin-bottom: 10px;
        }
        .success-card p {
            color: #666;
            margin-bottom: 20px;
        }
        .invoice-card {
            background: #f8f9fa;
            border: 2px solid #6f4e37;
            border-radius: 10px;
            padding: 20px;
            text-align: left;
            margin: 20px 0;
        }
        .invoice-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }
        .invoice-row.header {
            font-weight: bold;
            color: #6f4e37;
            border-bottom: 2px solid #6f4e37;
        }
        .invoice-row.total {
            font-weight: bold;
            color: #6f4e37;
            border-top: 2px solid #6f4e37;
            border-bottom: 2px solid #6f4e37;
            padding: 10px 0;
            margin-top: 10px;
        }
        .detail-info {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            font-size: 0.95rem;
        }
        .detail-row.last {
            border-bottom: none;
        }
        .detail-row strong {
            color: #6f4e37;
        }
        .btn-primary {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            border: none;
            width: 100%;
            padding: 12px;
            font-weight: bold;
            margin-top: 10px;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a3d2a 0%, #764a2e 100%);
            color: white;
        }
        .btn-secondary {
            width: 100%;
            padding: 12px;
            margin-top: 10px;
        }
        .payment-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 25px;
            display: inline-block;
            margin-bottom: 20px;
        }
        .print-button {
            margin-top: 10px;
        }
        @media print {
            .navbar, .sidebar, .no-print {
                display: none;
            }
            .success-container {
                padding: 0;
            }
            .success-card {
                box-shadow: none;
                border: 1px solid #ddd;
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
                    <a href="transaksi.php" class="nav-link active">
                        <i class="fas fa-shopping-cart"></i> Transaksi
                    </a>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 content">
                <div class="success-container">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>

                    <div class="success-card">
                        <h2>Pembayaran Berhasil!</h2>
                        <p>Transaksi Anda telah berhasil diproses</p>

                        <div class="payment-badge">
                            <i class="fas fa-check"></i> Metode Pembayaran: 
                            <?php echo htmlspecialchars($metode_display[$transaction['metode_pembayaran']] ?? $transaction['metode_pembayaran']); ?>
                        </div>

                        <div class="invoice-card">
                            <div class="invoice-row header">
                                <span><i class="fas fa-receipt"></i> Invoice #<?php echo str_pad($transaction['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                <span><?php echo date('d/m/Y H:i', strtotime($transaction['tanggal'])); ?></span>
                            </div>

                            <div style="margin-top: 20px;">
                                <h6 style="color: #6f4e37; margin-bottom: 10px;">Detail Pembelian:</h6>
                                <div class="invoice-row header">
                                    <span>Produk</span>
                                    <span>Subtotal</span>
                                </div>
                                <?php foreach ($details as $detail): ?>
                                    <div class="invoice-row">
                                        <span><?php echo htmlspecialchars($detail['nama_produk']); ?> x<?php echo $detail['jumlah']; ?></span>
                                        <span>Rp <?php echo number_format($detail['subtotal'], 0, ',', '.'); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="invoice-row total">
                                <span>TOTAL PEMBAYARAN</span>
                                <span>Rp <?php echo number_format($transaction['total'], 0, ',', '.'); ?></span>
                            </div>
                        </div>

                        <div class="detail-info">
                            <div class="detail-row">
                                <strong>Status:</strong>
                                <span><i class="fas fa-check-circle" style="color: #28a745;"></i> Selesai</span>
                            </div>
                            <div class="detail-row">
                                <strong>Nomor Transaksi:</strong>
                                <span>#<?php echo str_pad($transaction['id'], 6, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="detail-row">
                                <strong>Tanggal & Waktu:</strong>
                                <span><?php echo date('d/m/Y H:i:s', strtotime($transaction['tanggal'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <strong>Kasir:</strong>
                                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            </div>
                            <div class="detail-row last">
                                <strong>Metode Pembayaran:</strong>
                                <span><?php echo htmlspecialchars($metode_display[$transaction['metode_pembayaran']] ?? $transaction['metode_pembayaran']); ?></span>
                            </div>
                        </div>

                        <div class="no-print">
                            <button class="btn btn-primary print-button" onclick="window.print()">
                                <i class="fas fa-print"></i> Cetak Invoice
                            </button>
                            <a href="transaksi.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali ke Transaksi
                            </a>
                            <a href="../index.php" class="btn btn-secondary">
                                <i class="fas fa-home"></i> Kembali ke Dashboard
                            </a>
                        </div>
                    </div>

                    <div class="text-muted mt-4">
                        <small>Terima kasih telah berbelanja di Kasir Kopi. Kami tunggu kunjungan Anda berikutnya!</small>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
