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
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);">
    <div class="container-fluid">
        <a class="navbar-brand" href="../index.php"><i class="fas fa-coffee"></i> Kasir Kopi</a>
    </div>
</nav>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">Tambah Kategori</div>
        <div class="card-body">
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="nama" class="form-label">Nama Kategori</label>
                    <input type="text" class="form-control" id="nama" name="nama" value="<?php echo htmlspecialchars($nama ?? ''); ?>" required>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" name="tambah" class="btn btn-primary">Simpan</button>
                    <a href="kategori_index.php" class="btn btn-secondary">Kembali</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>