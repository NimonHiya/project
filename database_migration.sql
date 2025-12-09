
ALTER TABLE transaksi ADD COLUMN metode_pembayaran VARCHAR(50) DEFAULT NULL AFTER total;
ALTER TABLE transaksi ADD COLUMN status VARCHAR(20) DEFAULT 'pending' AFTER metode_pembayaran;


-- Tambahkan kolom deskripsi dan gambar ke tabel produk
ALTER TABLE produk ADD COLUMN deskripsi TEXT NULL AFTER nama_produk;
ALTER TABLE produk ADD COLUMN gambar VARCHAR(255) NULL AFTER harga;
