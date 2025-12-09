<?php
session_start();
if ($_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
}

require '../config/koneksi.php';

$id = $_GET['id'];

$trans = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT transaksi.*, users.nama
    FROM transaksi
    JOIN users ON transaksi.user_id = users.id
    WHERE transaksi.id = $id
"));

$detail = mysqli_query($conn, "
    SELECT transaksi_detail.*, produk.nama_produk, produk.harga
    FROM transaksi_detail
    JOIN produk ON transaksi_detail.produk_id = produk.id
    WHERE transaksi_id = $id
");
?>

<h2>Invoice</h2>

<p>ID Transaksi: <?= $trans['id'] ?></p>
<p>Nama Kasir: <?= $trans['nama'] ?></p>
<p>Tanggal: <?= $trans['tanggal'] ?></p>

<table border="1">
<tr>
    <th>Produk</th>
    <th>Harga</th>
    <th>Jumlah</th>
    <th>Subtotal</th>
</tr>

<?php while ($d = mysqli_fetch_assoc($detail)) { ?>
<tr>
    <td><?= $d['nama_produk'] ?></td>
    <td><?= $d['harga'] ?></td>
    <td><?= $d['jumlah'] ?></td>
    <td><?= $d['subtotal'] ?></td>
</tr>
<?php } ?>
</table>

<p>Total: <?= $trans['total'] ?></p>

<a href="javascript:window.print()">Cetak</a>
