<?php
session_start();
require '../config/koneksi.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: kategori_index.php');
    exit;
}

$id = (int)$_GET['id'];
$error = '';
$success = '';

try {
    $stmt = $conn->prepare("SELECT id, nama FROM kategori WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $kategori = $result->fetch_assoc();

    if (!$kategori) {
        header('Location: kategori_index.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
        $nama = trim($_POST['nama'] ?? '');
        if (empty($nama)) {
            $error = 'Nama kategori tidak boleh kosong';
        } else {
            $upd = $conn->prepare("UPDATE kategori SET nama = ? WHERE id = ?");
            $upd->bind_param("si", $nama, $id);
            $upd->execute();
            $success = 'Kategori berhasil diperbarui';
            $kategori['nama'] = $nama;
        }
    }
} catch (Exception $e) {
    $error = 'Terjadi kesalahan sistem';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Kategori - Kasir Kopi</title>
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
        .form-control:focus {
            border-color: #6f4e37;
            box-shadow: 0 0 0 0.2rem rgba(111, 78, 55, 0.25);
        }
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
                <a href="kategori_index.php" class="nav-link active">
                    <i class="fas fa-tag"></i> Kelola Kategori
                </a>
                <a href="../admin/transaksi_list.php" class="nav-link">
                    <i class="fas fa-list"></i> Transaksi
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 content">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h4 class="mb-0"><i class="fas fa-edit"></i> Edit Kategori</h4>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
                            <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="nama" class="form-label">Nama Kategori</label>
                                    <input type="text" class="form-control" id="nama" name="nama" value="<?php echo htmlspecialchars($kategori['nama']); ?>" required>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" name="edit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Simpan Perubahan
                                    </button>
                                    <a href="kategori_index.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Kembali
                                    </a>
                                </div>
                            </form>
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