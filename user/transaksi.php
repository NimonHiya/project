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

// 1. Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// --- PAGINATION SETUP ---
$limit = 12; // Jumlah produk per halaman
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search_param = "%$search%";
// -------------------------

// 2. Count total products (with search filter)
if (!empty($search)) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM produk WHERE stok > 0 AND nama_produk LIKE ?");
    $count_stmt->bind_param("s", $search_param);
} else {
    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM produk WHERE stok > 0");
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_products = $count_result->fetch_assoc()['total'];

// 4. Calculate total pages
$totalPages = ceil($total_products / $limit);

// Ensure current page is valid
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

// 6. Get products with search filter AND pagination limit
if (!empty($search)) {
    $products_stmt = $conn->prepare("SELECT p.id, p.nama_produk, p.kategori, p.kategori_id, p.harga, p.stok, p.gambar, k.nama AS nama_kategori 
                                      FROM produk p 
                                      LEFT JOIN kategori k ON p.kategori_id = k.id 
                                      WHERE stok > 0 AND nama_produk LIKE ? 
                                      ORDER BY nama_produk 
                                      LIMIT ? OFFSET ?");
    $products_stmt->bind_param("sii", $search_param, $limit, $offset);
} else {
    $products_stmt = $conn->prepare("SELECT p.id, p.nama_produk, p.kategori, p.kategori_id, p.harga, p.stok, p.gambar, k.nama AS nama_kategori 
                                      FROM produk p 
                                      LEFT JOIN kategori k ON p.kategori_id = k.id 
                                      WHERE stok > 0 
                                      ORDER BY nama_produk 
                                      LIMIT ? OFFSET ?");
    $products_stmt->bind_param("ii", $limit, $offset);
}
$products_stmt->execute();
$products_result = $products_stmt->get_result();

// Handle transaction submission (Kode ini tidak diubah)
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
            $prod_stmt = $conn->prepare("SELECT id, nama_produk, kategori, harga, stok FROM produk WHERE id = ?");
            $prod_stmt->bind_param("i", $pid);
            $prod_stmt->execute();
            $prod_result = $prod_stmt->get_result();
            $product = $prod_result->fetch_assoc();

            if (!$product) {
                $error = 'Produk tidak ditemukan';
                break;
            }

            if ($qty > $product['stok']) {
                $error = 'Stok tidak cukup untuk produk: ' . $product['nama_produk'];
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
            $error = 'Terjadi kesalahan saat memproses transaksi: ' . $e->getMessage();
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
    <title>Transaksi Penjualan - Kopi 21</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ... (CSS Styles remain the same) ... */
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky; /* Agar navbar tetap di atas */
            top: 0;
            z-index: 1030;
        }
        .sidebar {
            background-color: #fff;
            border-right: 1px solid #dee2e6;
            padding: 20px 0;
        }
        .sidebar a {
            color: #333;
            padding: 10px 15px;
            display: block;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            font-size: 0.9rem;
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
        
        /* ADJUSTMENT: Sidebar width and main content offset for higher positioning */
        @media (min-width: 769px) {
            .sidebar {
                width: 150px; 
                flex: 0 0 auto;
                min-height: 100vh;
                position: fixed; /* Kunci sidebar di tempatnya */
                top: 56px; /* Offset dari Navbar */
                left: 0;
                z-index: 1000;
                padding-top: 20px;
            }
            .content {
                padding: 20px;
                margin-left: 150px; /* Offset main content */
                width: calc(100% - 150px);
                margin-top: 0; /* Pastikan tidak ada margin atas tambahan */
                min-height: calc(100vh - 56px); /* Biar konten mengisi ruang sisa */
            }
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

        /* NEW PRODUCT CARD STYLES */
        .product-list-container {
            max-height: 70vh; /* Kontainer produk yang bisa discroll */
            overflow-y: auto;
            padding-right: 15px;
            padding-top: 10px;
        }
        .product-item {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 15px;
            transition: all 0.3s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .product-item:hover {
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .product-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-top-left-radius: 9px;
            border-top-right-radius: 9px;
            border-bottom: 1px solid #e9ecef;
        }
        .product-body {
            padding: 10px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .product-name {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 1rem;
            color: #343a40;
        }
        .product-price {
            color: #6f4e37;
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        .qty-controls {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
        }
        .qty-input {
            text-align: center;
            width: 50px !important; 
            font-weight: bold;
            flex-grow: 1;
        }
        .btn-qty {
            width: 30px;
            height: 30px;
            padding: 0;
            line-height: 1;
            font-weight: bold;
            background-color: #f8f9fa;
            color: #6f4e37;
            border-color: #6f4e37;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 50%;
        }
        .btn-qty:hover {
            background-color: #e9ecef;
            color: #5a3d2a;
        }
        /* END NEW PRODUCT CARD STYLES */

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
            .product-list-container {
                max-height: 50vh; /* Sesuaikan untuk layar kecil */
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
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../home.php">
                <i class="fas fa-coffee"></i> Kopi 21
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-auto sidebar">
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

            <main class="content">
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
                                        <input type="hidden" name="page" value="1"> 
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
                                                <small class="text-muted">
                                                     <i class="fas fa-coffee"></i> 
                                                     <?php echo $total_products; ?> produk tersedia
                                                </small>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="product-list-container">
                                                <div class="row row-cols-2 row-cols-md-3 g-3 p-3">
                                                    <?php if ($products_result->num_rows > 0): ?>
                                                        <?php while ($product = $products_result->fetch_assoc()): ?>
                                                            <div class="col">
                                                                <div class="card product-item">
                                                                    <?php 
                                                                    $image_path = !empty($product['gambar']) ? '../uploads/produk/' . $product['gambar'] : '../uploads/produk/default.jpg';
                                                                    ?>
                                                                    <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                                                        alt="<?php echo htmlspecialchars($product['nama_produk']); ?>" 
                                                                        class="product-image"
                                                                        onerror="this.src='../uploads/produk/default.jpg'">
                                                                    <div class="product-body">
                                                                        <div>
                                                                            <div class="product-name"><?php echo htmlspecialchars($product['nama_produk']); ?></div>
                                                                            <div><small class="text-muted">Kategori: <?php echo htmlspecialchars($product['nama_kategori'] ?? '-'); ?></small></div>
                                                                            <div class="product-price">Rp <?php echo number_format($product['harga'], 0, ',', '.'); ?></div>
                                                                            <small class="text-muted">Stok: <strong id="stok-<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['stok']); ?></strong></small>
                                                                        </div>
                                                                        <div class="qty-controls">
                                                                            <button type="button" class="btn btn-sm btn-qty" onclick="changeQty(<?php echo $product['id']; ?>, -1)">-</button>
                                                                            <input type="number" class="form-control form-control-sm qty-input" 
                                                                                id="qty-<?php echo $product['id']; ?>"
                                                                                name="qty[<?php echo $product['id']; ?>]" 
                                                                                min="0" max="<?php echo $product['stok']; ?>" 
                                                                                value="0" placeholder="0">
                                                                            <button type="button" class="btn btn-sm btn-qty" onclick="changeQty(<?php echo $product['id']; ?>, 1)">+</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endwhile; ?>
                                                    <?php else: ?>
                                                        <div class="alert alert-info m-3 w-100">
                                                            <i class="fas fa-search"></i> 
                                                            <?php if (!empty($search)): ?>
                                                                Tidak ada produk ditemukan untuk "<strong><?php echo htmlspecialchars($search); ?></strong>". 
                                                                <br><a href="transaksi.php" class="alert-link">Tampilkan semua produk</a>
                                                            <?php else: ?>
                                                                <i class="fas fa-info-circle"></i> Tidak ada produk yang tersedia.
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if ($totalPages > 1): ?>
                                                <nav class="p-3">
                                                    <ul class="pagination justify-content-center mb-0">
                                                        <?php 
                                                            // Helper function to build pagination URL
                                                            function getPaginationUrl($page, $search) {
                                                                $url = 'transaksi.php?page=' . $page;
                                                                if (!empty($search)) {
                                                                    $url .= '&search=' . urlencode($search);
                                                                }
                                                                return $url;
                                                            }
                                                        ?>

                                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                            <a class="page-link" href="<?php echo getPaginationUrl($page - 1, $search); ?>" tabindex="-1">Previous</a>
                                                        </li>

                                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                                <a class="page-link" href="<?php echo getPaginationUrl($i, $search); ?>">
                                                                    <?php echo $i; ?>
                                                                </a>
                                                            </li>
                                                        <?php endfor; ?>

                                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                                            <a class="page-link" href="<?php echo getPaginationUrl($page + 1, $search); ?>">Next</a>
                                                        </li>
                                                    </ul>
                                                </nav>
                                            <?php endif; ?>
                                            </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="card sticky-top" style="top: 76px;"> 
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
                                                <?php echo $total_products === 0 ? 'disabled' : ''; ?>>
                                                <i class="fas fa-check-circle"></i> Proses Pembayaran
                                            </button>
                                            <!-- <a href="../home.php" class="btn btn-secondary w-100 mt-2">
                                                <i class="fas fa-arrow-left"></i> Kembali
                                            </a> -->
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
            
            // Pilih semua input kuantitas
            document.querySelectorAll('input[name^="qty"]').forEach(input => {
                const qty = parseInt(input.value) || 0;
                
                // Mencari harga produk
                const productItem = input.closest('.product-item');
                if (productItem) {
                    const priceText = productItem.querySelector('.product-price').textContent;
                    // Hapus semua karakter non-angka kecuali koma/titik pemisah ribuan (dan ambil angkanya)
                    // Menggunakan regex yang lebih tepat untuk parsing IDR
                    const price = parseInt(priceText.replace('Rp', '').replace(/\./g, '').trim()) || 0;
                    
                    if (qty > 0) {
                        total += price * qty;
                        itemCount += qty;
                    }
                }
            });

            const formatter = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            });

            document.getElementById('totalAmount').textContent = formatter.format(total);
            
            const itemCountElement = document.getElementById('itemCount');
            if (itemCountElement) {
                itemCountElement.textContent = itemCount;
            }
        }
        
        // Fungsi baru untuk tombol +/-
        function changeQty(productId, delta) {
            const qtyInput = document.getElementById(`qty-${productId}`);
            const stokElement = document.getElementById(`stok-${productId}`);
            
            if (qtyInput && stokElement) {
                let currentQty = parseInt(qtyInput.value) || 0;
                const maxStok = parseInt(stokElement.textContent);
                let newQty = currentQty + delta;

                if (newQty < 0) {
                    newQty = 0;
                } else if (newQty > maxStok) {
                    newQty = maxStok;
                }

                qtyInput.value = newQty;
                calculateTotal();
            }
        }


        // Search with debounce
        let searchTimeout;
        const searchInput = document.getElementById('searchInput');
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                // Reset page to 1 when searching
                document.querySelector('input[name="page"]').value = 1; 
                searchTimeout = setTimeout(() => {
                    this.form.submit();
                }, 500);
            });
            
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(searchTimeout);
                    document.querySelector('input[name="page"]').value = 1;
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
                
                // Tambahkan validasi saat input manual agar tidak melebihi stok
                input.addEventListener('input', function() {
                    // Pastikan input hanya angka positif
                    let value = parseInt(this.value);
                    if (isNaN(value) || value < 0) {
                        value = 0;
                    }

                    const maxStok = parseInt(this.getAttribute('max'));
                    if (value > maxStok) {
                        this.value = maxStok;
                    } else {
                         this.value = value;
                    }
                    calculateTotal(); // Panggil calculateTotal setelah perubahan manual
                });
            });
        });
    </script>
</body>
</html>