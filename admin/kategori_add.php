<?php
session_start();
require '../config/koneksi.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$error = '';
$success = '';
$current_page = 'kategori'; // Penanda untuk sidebar aktif

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $nama = trim($_POST['nama'] ?? '');
    if (empty($nama)) {
        $error = 'Nama kategori tidak boleh kosong';
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO kategori (nama) VALUES (?)");
            $stmt->bind_param("s", $nama);
            $stmt->execute();
            $success = 'Kategori berhasil ditambahkan';
            $nama = '';
        } catch (Exception $e) {
            $error = 'Gagal menambah kategori';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Kategori - Kasir Kopi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --color-primary: #6f4e37; 
            --color-secondary: #8b5a3c; 
            --color-accent: #e8dcc8; 
            --sidebar-width: 220px;
        }

        body {
            background-color: #f8f9fa;
            padding-top: 56px; /* Offset for fixed navbar */
        }
        .navbar {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1030;
        }
        
        /* Sidebar Styling */
        .sidebar {
            background-color: #fff;
            border-right: 1px solid #dee2e6;
            padding: 0;
            min-height: calc(100vh - 56px); /* Mengisi sisa tinggi layar */
            position: fixed;
            top: 56px;
            left: 0;
            width: var(--sidebar-width);
            z-index: 1000;
        }
        .sidebar a {
            color: #333;
            padding: 12px 20px;
            display: block;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .sidebar a:hover {
            background-color: #f8f9fa;
            border-left-color: var(--color-primary);
            color: var(--color-primary);
        }
        .sidebar a.active {
            background-color: var(--color-accent);
            border-left-color: var(--color-primary);
            color: var(--color-primary);
            font-weight: bold;
        }
        
        /* Content Area */
        .content {
            margin-left: var(--sidebar-width); /* Offset konten utama */
            padding: 20px;
        }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            .sidebar {
                position: static;
                width: 100%;
                min-height: auto;
                border-right: none;
                border-bottom: 1px solid #dee2e6;
            }
            .content {
                margin-left: 0;
                padding-top: 10px;
            }
        }

        .card-header {
            background-color: var(--color-primary);
            color: white;
            font-weight: bold;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="../index.php"><i class="fas fa-coffee"></i> Kopi 21</a>
        <div class="d-flex">
            <span class="navbar-text me-3 text-white">
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
            </span>
            <a href="../auth/logout.php" class="btn btn-sm btn-outline-light">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-3 col-lg-2 d-md-block sidebar">
            <div class="position-sticky pt-3">
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
            </div>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 content">
            <div class="mt-4">
                <div class="card">
                    <div class="card-header">
                         <i class="fas fa-tag"></i> Tambah Kategori Baru
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="nama" class="form-label">Nama Kategori</label>
                                <input type="text" class="form-control" id="nama" name="nama" value="<?php echo htmlspecialchars($nama ?? ''); ?>" required>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" name="tambah" class="btn btn-success">
                                    <i class="fas fa-plus-circle"></i> Simpan Kategori
                                </button>
                                <a href="kategori_index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Kembali
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>