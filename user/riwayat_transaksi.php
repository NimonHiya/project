<?php
session_start();
require '../config/koneksi.php';

// Check if user is authenticated and not admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SESSION['role'] === 'admin') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get filter parameters
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_date_start = $_GET['date_start'] ?? '';
$filter_date_end = $_GET['date_end'] ?? '';

// Build query with filters
$query = "SELECT id, total, tanggal, status, metode_pembayaran FROM transaksi WHERE user_id = ?";
$params = array($user_id);
$types = "i";

if (!empty($search)) {
    $query .= " AND (id LIKE ? OR metode_pembayaran LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($filter_status)) {
    $query .= " AND status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($filter_date_start)) {
    $query .= " AND DATE(tanggal) >= ?";
    $params[] = $filter_date_start;
    $types .= "s";
}

if (!empty($filter_date_end)) {
    $query .= " AND DATE(tanggal) <= ?";
    $params[] = $filter_date_end;
    $types .= "s";
}

$query .= " ORDER BY tanggal DESC";

$stmt = $conn->prepare($query);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$transactions = array();
$total_transaksi = 0;
$total_amount = 0;

while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
    $total_transaksi++;
    $total_amount += $row['total'];
}

// Get statistics (all time)
$stat_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_count,
        SUM(total) as total_sum,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
    FROM transaksi
    WHERE user_id = ?
");
$stat_stmt->bind_param("i", $user_id);
$stat_stmt->execute();
$stat = $stat_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - Kasir Kopi</title>
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
        .badge-pending {
            background-color: #ffc107;
            color: #333;
        }
        .badge-completed {
            background-color: #28a745;
        }
        .btn-primary {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a3d2a 0%, #764a2e 100%);
        }
        .stat-card {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .export-btn {
            margin-left: 10px;
        }
        .search-box {
            position: relative;
        }
        .search-box input {
            padding-left: 40px;
        }
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        .no-data {
            text-align: center;
            padding: 40px;
        }
        .no-data i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 15px;
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
                    <li class="nav-item">
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($username); ?>
                        </a>
                    </li>
                    <a class="nav-link" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
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
                    <a href="transaksi.php" class="nav-link">
                        <i class="fas fa-shopping-cart"></i> Transaksi Baru
                    </a>
                    <a href="riwayat_transaksi.php" class="nav-link active">
                        <i class="fas fa-history"></i> Riwayat
                    </a>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 content">
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $stat['total_count']; ?></div>
                            <div class="stat-label"><i class="fas fa-receipt"></i> Total Transaksi</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value">Rp <?php echo number_format($stat['total_sum'] ?? 0, 0, ',', '.'); ?></div>
                            <div class="stat-label"><i class="fas fa-dollar-sign"></i> Total Pembelian</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $stat['completed_count']; ?></div>
                            <div class="stat-label"><i class="fas fa-check-circle"></i> Selesai</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $stat['pending_count']; ?></div>
                            <div class="stat-label"><i class="fas fa-clock"></i> Pending</div>
                        </div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <h5 class="mb-3"><i class="fas fa-filter"></i> Filter & Pencarian</h5>
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" class="form-control" name="search" placeholder="Cari nomor transaksi..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>

                        <div class="col-md-2">
                            <select class="form-control" name="status">
                                <option value="">Semua Status</option>
                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Selesai</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_start" placeholder="Dari tanggal"
                                   value="<?php echo htmlspecialchars($filter_date_start); ?>">
                        </div>

                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_end" placeholder="Sampai tanggal"
                                   value="<?php echo htmlspecialchars($filter_date_end); ?>">
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>

                        <?php if (!empty($search) || !empty($filter_status) || !empty($filter_date_start) || !empty($filter_date_end)): ?>
                            <div class="col-md-12">
                                <a href="riwayat_transaksi.php" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-times"></i> Reset Filter
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Transactions Table -->
                <div class="card">
                    <div class="card-header bg-light">
                        <div class="row align-items-center">
                            <div class="col">
                                <h4 class="mb-0"><i class="fas fa-history"></i> Riwayat Transaksi Saya</h4>
                            </div>
                            <div class="col-auto">
                                <?php if (count($transactions) > 0): ?>
                                    <small class="text-muted">Menampilkan <?php echo count($transactions); ?> dari <?php echo $stat['total_count']; ?> transaksi</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (count($transactions) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="10%">No. Transaksi</th>
                                            <th width="20%">Tanggal & Waktu</th>
                                            <th width="15%">Total</th>
                                            <th width="15%">Metode Bayar</th>
                                            <th width="10%">Status</th>
                                            <th width="15%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $trans): ?>
                                            <tr>
                                                <td><strong>#<?php echo str_pad($trans['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($trans['tanggal'])); ?></td>
                                                <td><strong>Rp <?php echo number_format($trans['total'], 0, ',', '.'); ?></strong></td>
                                                <td>
                                                    <?php if ($trans['metode_pembayaran']): ?>
                                                        <i class="fas fa-credit-card"></i> <?php echo htmlspecialchars($trans['metode_pembayaran']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($trans['status'] === 'completed'): ?>
                                                        <span class="badge badge-completed">
                                                            <i class="fas fa-check-circle"></i> Selesai
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-pending">
                                                            <i class="fas fa-clock"></i> Pending
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="pembayaran_sukses.php?transaction_id=<?php echo $trans['id']; ?>" 
                                                       class="btn btn-sm btn-info">
                                                        <i class="fas fa-receipt"></i> Invoice
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-inbox"></i>
                                <h5>Belum Ada Data</h5>
                                <p class="text-muted">Riwayat transaksi Anda akan muncul di sini</p>
                                <a href="transaksi.php" class="btn btn-primary mt-3">
                                    <i class="fas fa-shopping-cart"></i> Buat Transaksi Baru
                                </a>
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
