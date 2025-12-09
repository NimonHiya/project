<?php
session_start();
require '../config/koneksi.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get products with search filter
if (!empty($search)) {
    $products_stmt = $conn->prepare("SELECT id, nama_produk, harga, stok, gambar FROM produk WHERE stok > 0 AND nama_produk LIKE ? ORDER BY nama_produk");
    $search_param = "%$search%";
    $products_stmt->bind_param("s", $search_param);
} else {
    $products_stmt = $conn->prepare("SELECT id, nama_produk, harga, stok, gambar FROM produk WHERE stok > 0 ORDER BY nama_produk");
}
$products_stmt->execute();
$products_result = $products_stmt->get_result();

// Handle transaction submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $total = 0;
    $items = array();
    $has_items = false;

    // Validasi dan hitung total
    foreach ($_POST['qty'] ?? array() as $pid => $qty) {
        $qty = (int)$qty;
        if ($qty > 0) {
            $has_items = true;

            // Validasi produk dan stok
            $prod_stmt = $conn->prepare("SELECT id, harga, stok FROM produk WHERE id = ?");
            $prod_stmt->bind_param("i", $pid);
            $prod_stmt->execute();
            $prod_result = $prod_stmt->get_result();
            $product = $prod_result->fetch_assoc();

            if (!$product) {
                $error = 'Produk tidak ditemukan';
                break;
            }

            if ($qty > $product['stok']) {
                $error = 'Stok tidak cukup untuk produk tertentu';
                break;
            }

            $subtotal = $product['harga'] * $qty;
            $total += $subtotal;
            $items[] = array('pid' => $pid, 'qty' => $qty, 'subtotal' => $subtotal);
        }
    }

    if ($has_items && !$error) {
        try {
            $conn->begin_transaction();

            // Insert transaksi
            $stmt = $conn->prepare("INSERT INTO transaksi (user_id, total, tanggal) VALUES (?, ?, NOW())");
            $stmt->bind_param("id", $user_id, $total);
            $stmt->execute();
            $transaction_id = $conn->insert_id;

            // Insert detail transaksi dan update stok
            foreach ($items as $item) {
                $detail_stmt = $conn->prepare("INSERT INTO transaksi_detail (transaksi_id, produk_id, jumlah, subtotal) VALUES (?, ?, ?, ?)");
                $detail_stmt->bind_param("iiii", $transaction_id, $item['pid'], $item['qty'], $item['subtotal']);
                $detail_stmt->execute();

                // Update stok
                $update_stmt = $conn->prepare("UPDATE produk SET stok = stok - ? WHERE id = ?");
                $update_stmt->bind_param("ii", $item['qty'], $item['pid']);
                $update_stmt->execute();
            }

            $conn->commit();
            // Redirect to checkout page instead of showing success message
            header('Location: checkout.php?transaction_id=' . $transaction_id);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Terjadi kesalahan saat memproses transaksi';
        }
    } elseif (!$has_items && !$error) {
        $error = 'Pilih minimal 1 produk';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Penjualan - Kasir Kopi</title>
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
        }
        .btn-primary {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a3d2a 0%, #764a2e 100%);
        }
        .product-row {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .product-row:last-child {
            border-bottom: none;
        }
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #dee2e6;
            flex-shrink: 0;
        }
        .product-info {
            flex: 1;
            min-width: 0;
        }
        .product-name {
            font-weight: bold;
            margin-bottom: 3px;
            font-size: 0.95rem;
        }
        .product-price {
            color: #6f4e37;
            font-weight: bold;
            font-size: 0.9rem;
        }
        .qty-input {
            width: 80px;
            flex-shrink: 0;
        }
        .table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        .table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .form-control:focus {
            border-color: #6f4e37;
            box-shadow: 0 0 0 0.2rem rgba(111, 78, 55, 0.25);
        }
        .total-section {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: right;
        }
        .total-section h5 {
            margin-bottom: 10px;
        }
        .total-amount {
            font-size: 2rem;
            font-weight: bold;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            .content {
                padding: 10px;
            }
            .navbar-brand {
                font-size: 1rem;
            }
            .product-row {
                padding: 10px;
                gap: 10px;
            }
            .product-image {
                width: 50px;
                height: 50px;
            }
            .product-name {
                font-size: 0.85rem;
            }
            .product-price {
                font-size: 0.8rem;
            }
            .qty-input {
                width: 60px;
                font-size: 0.85rem;
            }
            .total-section {
                padding: 15px;
                margin-top: 15px;
            }
            .total-section h5 {
                font-size: 1rem;
            }
            .total-amount {
                font-size: 1.5rem;
            }
            .card-header h4 {
                font-size: 1.1rem;
            }
            .card-header h5 {
                font-size: 1rem;
            }
            /* Mobile bottom navigation */
            .mobile-nav {
                display: flex;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: white;
                border-top: 1px solid #dee2e6;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
                z-index: 1000;
                padding: 10px 0;
            }
            .mobile-nav a {
                flex: 1;
                text-align: center;
                padding: 10px 5px;
                color: #333;
                text-decoration: none;
                font-size: 0.75rem;
                transition: all 0.3s;
            }
            .mobile-nav a.active {
                color: #6f4e37;
                font-weight: bold;
            }
            .mobile-nav a i {
                display: block;
                font-size: 1.2rem;
                margin-bottom: 3px;
            }
            body {
                padding-bottom: 60px;
            }
        }

        @media (min-width: 769px) {
            .mobile-nav {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../home.php">
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
                    <a href="../home.php" class="nav-link">
                        <i class="fas fa-home"></i> Home
                    </a>
                    <a href="transaksi.php" class="nav-link active">
                        <i class="fas fa-shopping-cart"></i> Transaksi
                    </a>
                    <a href="riwayat_transaksi.php" class="nav-link ">
                        <i class="fas fa-history"></i> Riwayat
                    </a>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 content">
                <div class="card">
                    <div class="card-header bg-light">
                        <h4 class="mb-0"><i class="fas fa-receipt"></i> Form Transaksi Penjualan</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Search Box -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <form method="GET" action="" id="searchForm">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white">
                                            <i class="fas fa-search"></i>
                                        </span>
                                        <input type="text" class="form-control" name="search" 
                                               placeholder="Cari nama kopi..." 
                                               value="<?php echo htmlspecialchars($search); ?>"
                                               id="searchInput">
                                        <?php if (!empty($search)): ?>
                                            <a href="transaksi.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-times"></i> Reset
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0">Pilih Produk</h5>
                                                <?php if (!empty($search)): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-coffee"></i> 
                                                        <?php echo $products_result->num_rows; ?> produk ditemukan
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <!-- Products List -->
                                            <div style="max-height: 500px; overflow-y: auto;">
                                            <?php if ($products_result->num_rows > 0): ?>
                                                <?php while ($product = $products_result->fetch_assoc()): ?>
                                                    <div class="product-row">
                                                        <?php 
                                                        $image_path = !empty($product['gambar']) ? '../uploads/produk/' . $product['gambar'] : '../uploads/produk/default.jpg';
                                                        ?>
                                                        <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                                             alt="<?php echo htmlspecialchars($product['nama_produk']); ?>" 
                                                             class="product-image"
                                                             onerror="this.src='../uploads/produk/default.jpg'">
                                                        <div class="product-info">
                                                            <div class="product-name"><?php echo htmlspecialchars($product['nama_produk']); ?></div>
                                                            <div class="product-price">Rp <?php echo number_format($product['harga'], 0, ',', '.'); ?></div>
                                                            <small class="text-muted">Stok: <?php echo htmlspecialchars($product['stok']); ?></small>
                                                        </div>
                                                        <input type="number" class="form-control qty-input" 
                                                               name="qty[<?php echo $product['id']; ?>]" 
                                                               min="0" max="<?php echo $product['stok']; ?>" 
                                                               value="0" placeholder="Qty">
                                                    </div>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <div class="alert alert-info m-3">
                                                    <i class="fas fa-search"></i> 
                                                    <?php if (!empty($search)): ?>
                                                        Tidak ada produk ditemukan untuk "<strong><?php echo htmlspecialchars($search); ?></strong>". 
                                                        <br><a href="transaksi.php" class="alert-link">Tampilkan semua produk</a>
                                                    <?php else: ?>
                                                        <i class="fas fa-info-circle"></i> Tidak ada produk yang tersedia
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <h5 class="mb-0">Ringkasan</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="total-section">
                                                <div style="text-align: left; margin-bottom: 15px;">
                                                    <small>Total Item: <strong id="itemCount">0</strong></small>
                                                </div>
                                                <h5>Total Pembayaran</h5>
                                                <div id="totalAmount" class="total-amount">Rp 0</div>
                                            </div>

                                            <button type="submit" name="submit" class="btn btn-primary w-100 mt-3" 
                                                    <?php echo $products_result->num_rows === 0 ? 'disabled' : ''; ?>>
                                                <i class="fas fa-check-circle"></i> Proses Pembayaran
                                            </button>
                                            <a href="../home.php" class="btn btn-secondary w-100 mt-2">
                                                <i class="fas fa-arrow-left"></i> Kembali
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <a href="../home.php">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="transaksi.php" class="active">
            <i class="fas fa-shopping-cart"></i>
            <span>Transaksi</span>
        </a>
        <a href="riwayat_transaksi.php">
            <i class="fas fa-history"></i>
            <span>Riwayat</span>
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update total calculation on page load and when inputs change
        function calculateTotal() {
            let total = 0;
            let itemCount = 0;
            
            document.querySelectorAll('.product-row').forEach(row => {
                const qtyInput = row.querySelector('input[type="number"]');
                const priceText = row.querySelector('.product-price').textContent;
                const price = parseInt(priceText.replace(/\D/g, ''));
                const qty = parseInt(qtyInput.value) || 0;
                
                if (qty > 0) {
                    total += price * qty;
                    itemCount += qty;
                }
            });

            const formatter = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            });

            document.getElementById('totalAmount').textContent = formatter.format(total);
            
            // Update item count if element exists
            const itemCountElement = document.getElementById('itemCount');
            if (itemCountElement) {
                itemCountElement.textContent = itemCount;
            }
        }

        // Search with debounce
        let searchTimeout;
        const searchInput = document.getElementById('searchInput');
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.form.submit();
                }, 500);
            });
            
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(searchTimeout);
                    this.form.submit();
                }
            });
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
            
            // Attach event listeners to all quantity inputs
            document.querySelectorAll('input[name^="qty"]').forEach(input => {
                input.addEventListener('input', calculateTotal);
                input.addEventListener('change', calculateTotal);
            });
        });
    </script>
</body>
</html>

