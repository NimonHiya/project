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
$product = null;

// Validasi ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: produk_index.php');
    exit;
}

$id = (int)$_GET['id'];

try {
    // Get product data
    $stmt = $conn->prepare("SELECT id, nama_produk, kategori, kategori_id, deskripsi, harga, gambar, stok FROM produk WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        header('Location: produk_index.php');
        exit;
    }

    // Handle update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
        $nama = trim($_POST['nama'] ?? '');
        $kategori = trim($_POST['kategori'] ?? '');
        $kategori_id = isset($_POST['kategori_id']) && is_numeric($_POST['kategori_id']) ? (int)$_POST['kategori_id'] : null;
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $harga = $_POST['harga'] ?? '';
        $stok = $_POST['stok'] ?? '';
        $gambar = $product['gambar'] ?? '';

        // Handle file upload
        if (isset($_FILES['gambar']) && $_FILES['gambar']['size'] > 0) {
            $upload_dir = '../uploads/produk/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file = $_FILES['gambar'];
            $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'webp');
            $max_size = 5 * 1024 * 1024; // 5MB
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($file['error'] === UPLOAD_ERR_OK) {
                if (!in_array($file_ext, $allowed_ext)) {
                    $error = 'Tipe file tidak didukung. Gunakan JPG, PNG, GIF, atau WebP';
                } elseif ($file['size'] > $max_size) {
                    $error = 'Ukuran file terlalu besar. Maksimal 5MB';
                } else {
                    // Delete old image if exists
                    if ($product['gambar'] && file_exists($upload_dir . $product['gambar'])) {
                        unlink($upload_dir . $product['gambar']);
                    }

                    // Upload new file
                    $gambar = 'produk_' . time() . '_' . uniqid() . '.' . $file_ext;
                    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $gambar)) {
                        $error = 'Gagal mengupload file';
                    }
                }
            } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
                $error = 'Terjadi kesalahan saat upload file';
            }
        }

        // Validasi input
        if (empty($nama) || empty($harga) || !isset($stok)) {
            $error = 'Semua field harus diisi';
        } elseif (!is_numeric($harga) || $harga <= 0) {
            $error = 'Harga harus berupa angka positif';
        } elseif (!is_numeric($stok) || $stok < 0) {
            $error = 'Stok harus berupa angka';
        } elseif (empty($error)) {
            $harga = (float)$harga;
            $stok = (int)$stok;

            $update_stmt = $conn->prepare("UPDATE produk SET nama_produk = ?, kategori = ?, kategori_id = ?, deskripsi = ?, harga = ?, gambar = ?, stok = ? WHERE id = ?");
            $update_stmt->bind_param("ssisdsii", $nama, $kategori, $kategori_id, $deskripsi, $harga, $gambar, $stok, $id);
            $update_stmt->execute();

            $success = 'Produk berhasil diperbarui';
            // Refresh product data
            $product['nama_produk'] = $nama;
            $product['kategori'] = $kategori;
            $product['kategori_id'] = $kategori_id;
            $product['deskripsi'] = $deskripsi;
            $product['harga'] = $harga;
            $product['gambar'] = $gambar;
            $product['stok'] = $stok;
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
    <title>Edit Produk - Kasir Kopi</title>
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
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            margin-top: 15px;
        }
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            border-radius: 5px;
        }
        .current-image {
            text-align: center;
            margin-bottom: 20px;
        }
        .upload-area {
            border: 2px dashed #6f4e37;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background-color: #f8f6f1;
            transition: all 0.3s;
        }
        .upload-area:hover {
            background-color: #ede8e0;
            border-color: #8b5a3c;
        }
        .upload-area i {
            font-size: 2rem;
            color: #6f4e37;
            margin-bottom: 10px;
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
                    <a href="produk_index.php" class="nav-link">
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

            <!-- Main Content -->
            <main class="col-md-10 content">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h4 class="mb-0"><i class="fas fa-edit"></i> Edit Produk</h4>
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

                                <form method="POST" action="" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="id" class="form-label">ID Produk</label>
                                        <input type="text" class="form-control" id="id" value="<?php echo htmlspecialchars($product['id']); ?>" disabled>
                                    </div>

                                    <div class="mb-3">
                                        <label for="nama" class="form-label">Nama Produk</label>
                                        <input type="text" class="form-control" id="nama" name="nama" 
                                               placeholder="Nama produk" 
                                               value="<?php echo htmlspecialchars($product['nama_produk']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="kategori_id" class="form-label">Kategori</label>
                                        <select class="form-control" id="kategori_id" name="kategori_id">
                                            <option value="">-- Pilih Kategori --</option>
                                            <?php
                                            $cats = $conn->query("SELECT id, nama FROM kategori ORDER BY nama");
                                            if ($cats) {
                                                while ($c = $cats->fetch_assoc()) {
                                                    $sel = (isset($product['kategori_id']) && $product['kategori_id'] == $c['id']) || (isset($product['kategori']) && $product['kategori'] == $c['nama']) ? 'selected' : '';
                                                    echo '<option value="' . $c['id'] . '" ' . $sel . '>' . htmlspecialchars($c['nama']) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                        <small class="form-text text-muted">Opsional - pilih kategori yang sesuai</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="harga" class="form-label">Harga (Rp)</label>
                                        <input type="number" class="form-control" id="harga" name="harga" 
                                               placeholder="Harga" step="100" min="0"
                                               value="<?php echo htmlspecialchars($product['harga']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="stok" class="form-label">Stok</label>
                                        <input type="number" class="form-control" id="stok" name="stok" 
                                               placeholder="Stok" min="0"
                                               value="<?php echo htmlspecialchars($product['stok']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="deskripsi" class="form-label">Deskripsi Produk</label>
                                        <textarea class="form-control" id="deskripsi" name="deskripsi" 
                                                  rows="3" placeholder="Masukkan deskripsi singkat produk..."><?php echo htmlspecialchars($product['deskripsi'] ?? ''); ?></textarea>
                                        <small class="form-text text-muted">Opsional - deskripsi akan ditampilkan di menu</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Foto Produk</label>
                                        <?php if (!empty($product['gambar']) && file_exists('../uploads/produk/' . $product['gambar'])): ?>
                                            <div class="current-image">
                                                <p class="text-muted mb-2">Foto saat ini:</p>
                                                <img src="../uploads/produk/<?php echo htmlspecialchars($product['gambar']); ?>" 
                                                     alt="Foto produk" class="image-preview">
                                            </div>
                                        <?php endif; ?>
                                        <div class="upload-area">
                                            <input type="file" class="form-control d-none" id="gambar" name="gambar" 
                                                   accept=".jpg,.jpeg,.png,.gif,.webp" onchange="previewImage(this)">
                                            <label for="gambar" style="cursor: pointer; margin: 0;">
                                                <i class="fas fa-cloud-upload-alt"></i>
                                                <div class="mt-2">
                                                    <strong>Klik untuk ganti gambar</strong>
                                                    <div style="font-size: 0.9rem; color: #666;">
                                                        atau drag & drop di sini
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                        <div class="image-preview" id="imagePreview" style="display: none;">
                                            <img id="previewImg" src="" alt="Preview">
                                        </div>
                                        <small class="form-text text-muted d-block mt-2">
                                            Format: JPG, JPEG, PNG, GIF, WebP | Ukuran maksimal: 5MB
                                        </small>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" name="edit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Simpan Perubahan
                                        </button>
                                        <a href="produk_index.php" class="btn btn-secondary">
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
    <script>
        function previewImage(input) {
            const file = input.files[0];
            const preview = document.getElementById('previewImg');
            const imagePreview = document.getElementById('imagePreview');

            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    imagePreview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                preview.src = '';
                imagePreview.style.display = 'none';
            }
        }
    </script>
</body>
</html>
