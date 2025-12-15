<?php
session_start();
require '../config/koneksi.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        // Get product image to delete
        $stmt = $conn->prepare("SELECT gambar FROM produk WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();

        // Delete image file if exists
        if ($product && !empty($product['gambar'])) {
            $image_path = '../uploads/produk/' . $product['gambar'];
            if (file_exists($image_path)) {
                // Hati-hati: Pastikan Anda memiliki izin tulis pada folder ini
                unlink($image_path); 
            }
        }

        // Delete product from database
        $delete_stmt = $conn->prepare("DELETE FROM produk WHERE id = ?");
        $delete_stmt->bind_param("i", $id);
        $delete_stmt->execute();
        
        $success = 'Produk berhasil dihapus';
    } catch (Exception $e) {
        $error = 'Gagal menghapus produk: ' . $e->getMessage();
    }
}

// Get all products. Note: p.kategori (kolom lama) tetap di-select sebagai referensi jika diperlukan.
$products = $conn->query("SELECT p.id, p.nama_produk, p.kategori, p.kategori_id, p.deskripsi, p.harga, p.gambar, p.stok, k.nama AS nama_kategori FROM produk p LEFT JOIN kategori k ON p.kategori_id = k.id ORDER BY p.id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Produk - Kasir Kopi</title>
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
        .btn-sm {
            padding: 5px 12px;
            font-size: 0.85rem;
        }
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        .product-image-placeholder {
            width: 50px;
            height: 50px;
            background-color: #e9ecef;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }
        /* START: Custom Logout Button Style */
        .btn-logout {
            background-color: #d9534f;
            border-color: #d43f3a;
            color: white;
            transition: all 0.3s;
        }
        .btn-logout:hover {
            background-color: #c9302c;
            border-color: #ac2925;
            color: white;
        }
        /* END: Custom Logout Button Style */
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-coffee"></i> Kasir Kopi
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-3">
                        <span class="nav-link text-white">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-sm btn-logout" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 sidebar">
                <div class="nav flex-column">
                    <a href="../index.php" class="nav-link">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="produk_index.php" class="nav-link active">
                        <i class="fas fa-box"></i> Daftar Produk
                    </a>
                    <a href="kategori_index.php" class="nav-link">
                        <i class="fas fa-tag"></i> Kelola Kategori
                    </a>
                    <a href="../admin/transaksi_list.php" class="nav-link">
                        <i class="fas fa-list"></i> Transaksi
                    </a>
                </div>
            </nav>

            <main class="col-md-10 content">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-box"></i> Daftar Produk</h4>
                        <a href="produk_add.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Tambah Produk
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($products->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="5%">ID</th>
                                            <th>Nama Produk</th>
                                            <th width="15%">Kategori</th>
                                            <th width="15%">Harga</th>
                                            <th width="10%">Stok</th>
                                            <th width="15%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $products->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($row['gambar'])): ?>
                                                            <img src="../uploads/produk/<?php echo $row['gambar']; ?>" 
                                                                 alt="<?php echo htmlspecialchars($row['nama_produk']); ?>" 
                                                                 class="product-image"
                                                                 onerror="this.onerror=null; this.src='../uploads/produk/default.jpg';"> 
                                                        <?php else: ?>
                                                            <div class="product-image-placeholder">
                                                                <i class="fas fa-image"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <span class="ms-2"><?php echo htmlspecialchars($row['nama_produk']); ?></span>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['nama_kategori'] ?? '-'); ?></td>
                                                <td>Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?></td>
                                                <td>
                                                    <?php 
                                                         $stok_val = (int)$row['stok'];
                                                         $badge_class = 'bg-success';
                                                         if ($stok_val <= 5 && $stok_val > 0) {
                                                             $badge_class = 'bg-warning text-dark';
                                                         } elseif ($stok_val <= 0) {
                                                             $badge_class = 'bg-danger';
                                                         }
                                                     ?>
                                                    <span class="badge <?php echo $badge_class; ?>">
                                                        <?php echo htmlspecialchars($stok_val); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="produk_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Yakin ingin menghapus produk <?php echo htmlspecialchars($row['nama_produk']); ?>?')">
                                                        <i class="fas fa-trash"></i> Hapus
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Belum ada produk. <a href="produk_add.php">Tambah produk sekarang</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>