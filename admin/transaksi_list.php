<?php
session_start();
require '../config/koneksi.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Get filter parameters
$filter_date = $_GET['filter_date'] ?? '';

// Build query with filter
$query = "SELECT t.id, t.tanggal, u.nama, u.username, t.total
          FROM transaksi t
          JOIN users u ON t.user_id = u.id";

if ($filter_date) {
    $query .= " WHERE DATE(t.tanggal) = ?";
}

$query .= " ORDER BY t.tanggal DESC";

$stmt = $conn->prepare($query);
if ($filter_date) {
    $stmt->bind_param("s", $filter_date);
}
$stmt->execute();
$result = $stmt->get_result();

// Calculate total pendapatan
$total_query = "SELECT SUM(total) as total_pendapatan FROM transaksi";
if ($filter_date) {
    $total_query .= " WHERE DATE(tanggal) = ?";
}

$total_stmt = $conn->prepare($total_query);
if ($filter_date) {
    $total_stmt->bind_param("s", $filter_date);
}
$total_stmt->execute();
$total_result = $total_stmt->get_result()->fetch_assoc();
$total_pendapatan = $total_result['total_pendapatan'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Transaksi - Kasir Kopi</title>
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
        .stat-card {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            color: white;
        }
        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
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
                    <a href="produk_index.php" class="nav-link">
                        <i class="fas fa-box"></i> Daftar Produk
                    </a>
                    <a href="kategori_index.php" class="nav-link">
                        <i class="fas fa-tag"></i> Kelola Kategori
                    </a>
                    <a href="transaksi_list.php" class="nav-link active">
                        <i class="fas fa-list"></i> Transaksi
                    </a>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 content">
                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Pendapatan Hari Ini</h5>
                                <div class="stat-value">Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Transaksi</h5>
                                <div class="stat-value"><?php echo $result->num_rows; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter dan Table -->
                <div class="card">
                    <div class="card-header bg-light">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h4 class="mb-0"><i class="fas fa-list"></i> Daftar Transaksi</h4>
                            </div>
                            <div class="col-md-6">
                                <form method="GET" class="d-flex gap-2">
                                    <input type="date" class="form-control form-control-sm" name="filter_date" 
                                           value="<?php echo htmlspecialchars($filter_date); ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <?php if ($filter_date): ?>
                                        <a href="transaksi_list.php" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-times"></i> Reset
                                        </a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="10%">ID</th>
                                            <th>Tanggal & Waktu</th>
                                            <th>Nama Kasir</th>
                                            <th width="20%">Total</th>
                                            <th width="10%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($row['tanggal'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                                <td><strong>Rp <?php echo number_format($row['total'], 0, ',', '.'); ?></strong></td>
                                                <td>
                                                    <a href="invoice.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-receipt"></i> Invoice
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Belum ada transaksi
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

