<?php
session_start();
require_once '../config/database.php';

// Keamanan: Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Jika bukan admin, kirim pesan error dan alihkan
    $_SESSION['message'] = "Anda tidak memiliki izin untuk melakukan aksi ini.";
    $_SESSION['message_type'] = "error";
    header("Location: manage_deliveries.php");
    exit;
}

// 1. Validasi Input dari URL
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['id']) || !isset($_GET['status'])) {
    $_SESSION['message'] = "Permintaan tidak valid.";
    $_SESSION['message_type'] = "error";
    header("Location: manage_deliveries.php");
    exit;
}

$pesanan_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$new_status = $_GET['status'];

// Daftar status yang diizinkan untuk mencegah input sembarangan
$allowed_statuses = ['sudah_dibayar', 'dibatalkan_pembayaran', 'pending_pembayaran'];

if (!$pesanan_id || !in_array($new_status, $allowed_statuses)) {
    $_SESSION['message'] = "ID pesanan atau status tidak valid.";
    $_SESSION['message_type'] = "error";
    header("Location: manage_deliveries.php");
    exit;
}

// 2. Proses Update ke Database
try {
    // Gunakan prepared statement untuk keamanan
    $stmt = $conn->prepare("UPDATE pesanan SET status_pembayaran = ? WHERE id = ?");
    if ($stmt === false) {
        throw new Exception("Gagal mempersiapkan statement: " . $conn->error);
    }

    $stmt->bind_param("si", $new_status, $pesanan_id);
    
    if ($stmt->execute()) {
        // Cek apakah ada baris yang terpengaruh
        if ($stmt->affected_rows > 0) {
            $_SESSION['message'] = "Status pembayaran untuk pesanan #{$pesanan_id} berhasil diubah menjadi '" . ucwords(str_replace('_', ' ', $new_status)) . "'.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Tidak ada perubahan status atau pesanan #{$pesanan_id} tidak ditemukan.";
            $_SESSION['message_type'] = "info";
        }
    } else {
        throw new Exception("Eksekusi statement gagal: " . $stmt->error);
    }
    
    $stmt->close();

} catch (Exception $e) {
    // Tangani error database
    $_SESSION['message'] = "Terjadi kesalahan database: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

// 3. Alihkan kembali ke halaman manajemen
header("Location: manage_deliveries.php");
exit;
?>