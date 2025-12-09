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
$success = '';
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

// Handle payment method selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bayar'])) {
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? '';

    if (empty($metode_pembayaran)) {
        $error = 'Pilih metode pembayaran terlebih dahulu';
    } else {
        try {
            $update_stmt = $conn->prepare("UPDATE transaksi SET metode_pembayaran = ?, status = 'completed' WHERE id = ?");
            $update_stmt->bind_param("si", $metode_pembayaran, $transaction_id);
            $update_stmt->execute();

            // Redirect to success page
            header('Location: pembayaran_sukses.php?transaction_id=' . $transaction_id);
            exit;
        } catch (Exception $e) {
            $error = 'Gagal memproses pembayaran';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pembayaran - Kasir Kopi</title>
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
        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .invoice-card {
            background: white;
            border: 2px solid #6f4e37;
            border-radius: 10px;
            padding: 30px;
        }
        .invoice-header {
            text-align: center;
            border-bottom: 2px solid #6f4e37;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .invoice-header h2 {
            color: #6f4e37;
            margin-bottom: 5px;
        }
        .invoice-header .invoice-number {
            color: #666;
            font-size: 0.9rem;
        }
        .invoice-section {
            margin-bottom: 20px;
        }
        .invoice-section h5 {
            color: #6f4e37;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        .invoice-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }
        .invoice-item.header {
            border-bottom: 2px solid #6f4e37;
            font-weight: bold;
            margin-bottom: 10px;
            color: #6f4e37;
        }
        .invoice-item.total {
            border-bottom: 2px solid #6f4e37;
            border-top: 2px solid #6f4e37;
            font-weight: bold;
            color: #6f4e37;
            font-size: 1.1rem;
            padding: 12px 0;
            margin-top: 10px;
        }
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .payment-option {
            position: relative;
        }
        .payment-option input[type="radio"] {
            display: none;
        }
        .payment-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            border: 2px solid #ddd;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }
        .payment-option input[type="radio"]:checked + .payment-label {
            border-color: #6f4e37;
            background: #f8f6f1;
            color: #6f4e37;
            font-weight: bold;
        }
        .payment-label i {
            font-size: 2rem;
            margin-bottom: 8px;
            color: #6f4e37;
        }
        .payment-label small {
            font-size: 0.8rem;
            text-align: center;
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
        .detail-info {
            background: #f8f6f1;
            padding: 10px;
            border-radius: 5px;
            font-size: 0.9rem;
            color: #333;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-coffee"></i> Kasir Kopi
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
                <div class="row">
                    <!-- Invoice Section -->
                    <div class="col-lg-6">
                        <h4 class="mb-3"><i class="fas fa-receipt"></i> Invoice</h4>
                        <div class="invoice-card">
                            <div class="invoice-header">
                                <h2><i class="fas fa-receipt"></i> INVOICE</h2>
                                <div class="invoice-number">#<?php echo str_pad($transaction['id'], 6, '0', STR_PAD_LEFT); ?></div>
                            </div>

                            <div class="invoice-section">
                                <h5>Informasi Transaksi</h5>
                                <div class="detail-info">
                                    <div class="row mb-2">
                                        <div class="col-6">Nomor Transaksi:</div>
                                        <div class="col-6 text-end"><strong>#<?php echo str_pad($transaction['id'], 6, '0', STR_PAD_LEFT); ?></strong></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-6">Tanggal & Waktu:</div>
                                        <div class="col-6 text-end"><strong><?php echo date('d/m/Y H:i', strtotime($transaction['tanggal'])); ?></strong></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6">Kasir:</div>
                                        <div class="col-6 text-end"><strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></div>
                                    </div>
                                </div>
                            </div>

                            <div class="invoice-section">
                                <h5>Detail Produk</h5>
                                <div class="invoice-item header">
                                    <span>Produk</span>
                                    <span>Subtotal</span>
                                </div>
                                <?php foreach ($details as $detail): ?>
                                    <div class="invoice-item">
                                        <span><?php echo htmlspecialchars($detail['nama_produk']); ?> x<?php echo $detail['jumlah']; ?></span>
                                        <span>Rp <?php echo number_format($detail['subtotal'], 0, ',', '.'); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="invoice-item total">
                                <span>TOTAL</span>
                                <span>Rp <?php echo number_format($transaction['total'], 0, ',', '.'); ?></span>
                            </div>

                            <div class="text-center mt-4">
                                <p style="font-size: 0.85rem; color: #999;">Terima kasih telah berbelanja</p>
                                <small style="color: #999;">Kasir Kopi - Sistem Manajemen Penjualan</small>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Section -->
                    <div class="col-lg-6">
                        <h4 class="mb-3"><i class="fas fa-credit-card"></i> Pilih Metode Pembayaran</h4>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3"><i class="fas fa-list"></i> Pilihan Metode Pembayaran</h5>
                                    
                                    <div class="payment-methods">
                                        <div class="payment-option">
                                            <input type="radio" id="cash" name="metode_pembayaran" value="cash" required>
                                            <label for="cash" class="payment-label">
                                                <i class="fas fa-money-bill-wave"></i>
                                                <span>Tunai</span>
                                                <small>Cash</small>
                                            </label>
                                        </div>

                                        <div class="payment-option">
                                            <input type="radio" id="debit" name="metode_pembayaran" value="debit">
                                            <label for="debit" class="payment-label">
                                                <i class="fas fa-credit-card"></i>
                                                <span>Debit</span>
                                                <small>Card</small>
                                            </label>
                                        </div>

                                        <div class="payment-option">
                                            <input type="radio" id="credit" name="metode_pembayaran" value="credit">
                                            <label for="credit" class="payment-label">
                                                <i class="fas fa-university"></i>
                                                <span>Kredit</span>
                                                <small>Card</small>
                                            </label>
                                        </div>

                                        <div class="payment-option">
                                            <input type="radio" id="transfer" name="metode_pembayaran" value="transfer">
                                            <label for="transfer" class="payment-label">
                                                <i class="fas fa-exchange-alt"></i>
                                                <span>Transfer</span>
                                                <small>Bank</small>
                                            </label>
                                        </div>

                                        <div class="payment-option">
                                            <input type="radio" id="ewallet" name="metode_pembayaran" value="ewallet">
                                            <label for="ewallet" class="payment-label">
                                                <i class="fas fa-wallet"></i>
                                                <span>E-Wallet</span>
                                                <small>Digital</small>
                                            </label>
                                        </div>

                                        <div class="payment-option">
                                            <input type="radio" id="qris" name="metode_pembayaran" value="qris">
                                            <label for="qris" class="payment-label">
                                                <i class="fas fa-qrcode"></i>
                                                <span>QRIS</span>
                                                <small>Scan</small>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="card bg-light mt-3">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="fas fa-calculator"></i> Ringkasan Pembayaran</h6>
                                            <div class="row mb-2">
                                                <div class="col-6">Total Belanja:</div>
                                                <div class="col-6 text-end"><strong>Rp <?php echo number_format($transaction['total'], 0, ',', '.'); ?></strong></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-6">Jumlah Item:</div>
                                                <div class="col-6 text-end"><strong><?php echo count($details); ?> produk</strong></div>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" name="bayar" class="btn btn-primary">
                                        <i class="fas fa-check-circle"></i> Konfirmasi Pembayaran
                                    </button>
                                    <a href="transaksi.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Kembali
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
