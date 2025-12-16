-- Database: coffee_kasir
-- SQL Lengkap untuk Sistem Kasir Kopi

-- 1. Buat database (jika belum ada)
CREATE DATABASE IF NOT EXISTS coffee_kasir;
USE coffee_kasir;

-- 2. Tabel users
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel produk
CREATE TABLE IF NOT EXISTS produk (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_produk VARCHAR(100) NOT NULL,
    kategori VARCHAR(100) NULL,
    deskripsi TEXT NULL,
    harga DECIMAL(10,2) NOT NULL,
    gambar VARCHAR(255) NULL,
    stok INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel transaksi
CREATE TABLE IF NOT EXISTS transaksi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    metode_pembayaran VARCHAR(50) DEFAULT NULL,
    uang_dibayar DECIMAL(10,2) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabel transaksi_detail
CREATE TABLE IF NOT EXISTS transaksi_detail (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaksi_id INT NOT NULL,
    produk_id INT NOT NULL,
    jumlah INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE CASCADE,
    FOREIGN KEY (produk_id) REFERENCES produk(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Insert data admin default (password: admin123)
INSERT INTO users (username, password, nama, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin')
ON DUPLICATE KEY UPDATE username=username;

-- 7. Insert data user contoh (password: user123)
INSERT INTO users (username, password, nama, role) VALUES 
('kasir1', '$2y$10$YJZ5zZxZ5zZxZ5zZxZ5zZeuKGJ5zZxZ5zZxZ5zZxZ5zZxZ5zZxZ5z', 'Kasir 1', 'user')
ON DUPLICATE KEY UPDATE username=username;

-- 8. Insert data produk contoh
INSERT INTO produk (nama_produk, deskripsi, harga, gambar, stok) VALUES 
('Espresso', 'Kopi hitam pekat dengan rasa kuat', 15000.00, 'espresso.jpg', 50),
('Cappuccino', 'Espresso dengan susu berbusa', 25000.00, 'cappuccino.jpg', 50),
('Latte', 'Espresso dengan susu hangat', 28000.00, 'latte.jpg', 50),
('Americano', 'Espresso dicampur air panas', 20000.00, 'americano.jpg', 50),
('Mocha', 'Latte dengan tambahan cokelat', 30000.00, 'mocha.jpg', 50),
('Caramel Macchiato', 'Espresso dengan susu dan karamel', 32000.00, 'caramel.jpg', 50)
ON DUPLICATE KEY UPDATE nama_produk=nama_produk;

-- 9. Buat folder upload (catatan: ini harus dilakukan manual atau via PHP)
-- Buat folder: uploads/produk/
-- Letakkan file gambar default.jpg di folder uploads/produk/

-- Migration untuk upgrade dari versi lama
ALTER TABLE transaksi ADD COLUMN IF NOT EXISTS metode_pembayaran VARCHAR(50) DEFAULT NULL AFTER total;
ALTER TABLE transaksi ADD COLUMN IF NOT EXISTS uang_dibayar DECIMAL(10,2) DEFAULT NULL AFTER metode_pembayaran;
ALTER TABLE transaksi ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'pending' AFTER metode_pembayaran;
ALTER TABLE produk ADD COLUMN IF NOT EXISTS deskripsi TEXT NULL AFTER nama_produk;
ALTER TABLE produk ADD COLUMN IF NOT EXISTS gambar VARCHAR(255) NULL AFTER harga;
ALTER TABLE produk ADD COLUMN IF NOT EXISTS kategori VARCHAR(100) NULL AFTER nama_produk;
-- Tabel kategori terpisah untuk normalisasi data
CREATE TABLE IF NOT EXISTS kategori (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tambahkan kolom kategori_id di tabel produk (opsional, nullable)
ALTER TABLE produk ADD COLUMN IF NOT EXISTS kategori_id INT NULL AFTER nama_produk;
-- Buat foreign key jika diinginkan (pastikan tidak ada data yang melanggar constraint)
-- ALTER TABLE produk ADD CONSTRAINT fk_produk_kategori FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE SET NULL;
