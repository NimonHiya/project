<?php
session_start();
require '../config/koneksi.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$success = '';
$error = '';

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        // Cek apakah ada produk yang masih menggunakan kategori ini sebelum dihapus
        $check_stmt = $conn->prepare("SELECT COUNT(id) FROM produk WHERE kategori_id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_row()[0];

        if ($check_result > 0) {
            // Jika ada produk yang menggunakan kategori ini
            $error = 'Gagal menghapus kategori. Terdapat ' . $check_result . ' produk yang masih menggunakan kategori ini.';
        } else {
            // Jika aman, hapus kategori
            $stmt = $conn->prepare("DELETE FROM kategori WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $success = 'Kategori berhasil dihapus';
        }
    } catch (Exception $e) {
        $error = 'Gagal menghapus kategori: ' . $e->getMessage();
    }
}

// Handle search query
$search_q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (!empty($search_q)) {
    $categories_stmt = $conn->prepare("SELECT id, nama FROM kategori WHERE nama LIKE ? ORDER BY id DESC");
    $search_param = "%$search_q%";
    $categories_stmt->bind_param("s", $search_param);
    $categories_stmt->execute();
    $categories = $categories_stmt->get_result();
} else {
    $categories = $conn->query("SELECT id, nama FROM kategori ORDER BY id DESC");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Kategori - Kasir Kopi</title>
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
        <a class="navbar-brand" href="../index.php"><i class="fas fa-coffee"></i> Kopi 21</a>
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
                <a href="produk_index.php" class="nav-link">
                    <i class="fas fa-box"></i> Daftar Produk
                </a>
                <a href="kategori_index.php" class="nav-link active">
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
                    <h4 class="mb-0"><i class="fas fa-tags"></i> Daftar Kategori</h4>
                    <a href="kategori_add.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Tambah Kategori
                    </a>
                </div>
                <div class="card-body">
                    
                    <div class="mb-3">
                        <form method="GET" action="">
                            <div class="input-group">
                                <input type="text" name="q" class="form-control" placeholder="Cari kategori..." value="<?php echo htmlspecialchars($search_q); ?>">
                                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                                <?php if (!empty($search_q)): ?>
                                    <a href="kategori_index.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i> Reset</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

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

                    <?php if ($categories && $categories->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">ID</th>
                                        <th>Nama Kategori</th>
                                        <th width="20%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $categories->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['id']); ?></td>
                                            <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                            <td>
                                                <a href="kategori_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Yakin ingin menghapus kategori <?php echo htmlspecialchars($row['nama']); ?>? Tindakan ini tidak dapat dibatalkan.')">
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
                            <i class="fas fa-info-circle"></i> 
                            <?php echo !empty($search_q) ? "Tidak ada kategori ditemukan untuk '<strong>" . htmlspecialchars($search_q) . "</strong>'." : "Belum ada kategori. <a href='kategori_add.php'>Tambah kategori pertama</a>"; ?>
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