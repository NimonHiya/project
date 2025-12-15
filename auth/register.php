<?php
session_start();
require '../config/koneksi.php';

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validasi input
    if (empty($nama) || empty($username) || empty($password) || empty($password_confirm)) {
        $error = 'Semua field harus diisi';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } elseif ($password !== $password_confirm) {
        $error = 'Password tidak cocok';
    } elseif (strlen($username) < 3) {
        $error = 'Username minimal 3 karakter';
    } else {
        try {
            // Check apakah username sudah terdaftar
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = 'Username sudah terdaftar';
            } else {
                // Hash password dan insert user baru
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $role = 'user';

                $stmt = $conn->prepare("INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $nama, $username, $hashed_password, $role);
                $stmt->execute();

                $success = 'Registrasi berhasil! Silakan login.';
                // Clear form
                $nama = $username = '';
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Kasir Kopi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .register-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
        }
        .register-header {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        .register-header h1 {
            margin: 0;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .register-header i {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        .register-body {
            padding: 40px;
        }
        .form-control {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px 15px;
            /* margin-bottom dihapus karena ada input-group */
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #6f4e37;
            box-shadow: 0 0 0 0.2rem rgba(111, 78, 55, 0.25);
        }
        /* START: Styling Tambahan untuk Toggle Password */
        .input-group .form-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .input-group .btn-outline-secondary {
            border-color: #ddd;
            background-color: #fff;
            color: #666; 
            transition: all 0.3s;
            border-left: none; 
            border-radius: 0 8px 8px 0; 
        }

        .input-group .btn-outline-secondary:hover {
            background-color: #f8f8f8;
            color: #6f4e37;
            border-color: #6f4e37;
            z-index: 2;
        }

        .input-group .btn-outline-secondary:focus {
            box-shadow: none;
            border-color: #6f4e37;
        }
        /* END: Styling Tambahan untuk Toggle Password */

        .btn-register {
            width: 100%;
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            border: none;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: bold;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(111, 78, 55, 0.4);
            color: white;
        }
        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .login-link a {
            color: #6f4e37;
            text-decoration: none;
            font-weight: bold;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .password-strength {
            font-size: 0.85rem;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <i class="fas fa-user-plus"></i>
            <h1>Daftar Akun</h1>
            <p>Kopi 21 - Sistem Manajemen Penjualan</p>
        </div>

        <div class="register-body">
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

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="nama" class="form-label">Nama Lengkap</label>
                    <input type="text" class="form-control" id="nama" name="nama" 
                           placeholder="Masukkan nama lengkap" value="<?php echo htmlspecialchars($nama ?? ''); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="Masukkan username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                    <small class="form-text text-muted">Minimal 3 karakter</small>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Masukkan password" required aria-label="Password">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" title="Tampilkan/Sembunyikan Password">
                            <i class="fas fa-eye"></i> 
                        </button>
                    </div>
                    <small class="form-text text-muted">Minimal 6 karakter</small>
                </div>
                <div class="mb-3">
                    <label for="password_confirm" class="form-label">Konfirmasi Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                               placeholder="Masukkan password lagi" required aria-label="Konfirmasi Password">
                        <button class="btn btn-outline-secondary" type="button" id="togglePasswordConfirm" title="Tampilkan/Sembunyikan Password Konfirmasi">
                            <i class="fas fa-eye"></i> 
                        </button>
                    </div>
                </div>
                <button type="submit" name="register" class="btn-register">
                    <i class="fas fa-user-check"></i> Daftar
                </button>
            </form>

            <div class="login-link">
                Sudah punya akun? <a href="login.php">Login di sini</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Fungsi untuk toggle password visibility
        function setupPasswordToggle(toggleId, inputId) {
            const toggleButton = document.getElementById(toggleId);
            const passwordInput = document.getElementById(inputId);

            if (toggleButton && passwordInput) {
                toggleButton.addEventListener('click', function () {
                    // Toggle the type attribute
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    // Toggle the eye icon: fa-eye (show) / fa-eye-slash (hide)
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
            }
        }

        // Setup untuk field Password utama
        setupPasswordToggle('togglePassword', 'password');

        // Setup untuk field Konfirmasi Password
        setupPasswordToggle('togglePasswordConfirm', 'password_confirm');
    </script>
    </body>
</html>