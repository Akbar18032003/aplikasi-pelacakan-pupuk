<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

$user_id = $_GET['id'] ?? null;

if (!$user_id || !is_numeric($user_id)) {
    $_SESSION['message'] = "ID pengguna tidak valid.";
    $_SESSION['message_type'] = "error";
    header("Location: manage_users.php");
    exit;
}

try {
    // Ambil semua id pesanan milik user
    $getPesanan = $conn->prepare("SELECT id FROM pesanan WHERE id_pelanggan = ?");
    $getPesanan->bind_param("i", $user_id);
    $getPesanan->execute();
    $result = $getPesanan->get_result();

    $pesanan_ids = [];
    while ($row = $result->fetch_assoc()) {
        $pesanan_ids[] = $row['id'];
    }
    $getPesanan->close();

    // Hapus detail_pesanan terlebih dahulu jika ada pesanan
    if (!empty($pesanan_ids)) {
        $placeholders = implode(',', array_fill(0, count($pesanan_ids), '?'));
        $types = str_repeat('i', count($pesanan_ids));

        $stmt = $conn->prepare("DELETE FROM detail_pesanan WHERE id_pesanan IN ($placeholders)");
        $stmt->bind_param($types, ...$pesanan_ids);
        $stmt->execute();
        $stmt->close();
    }

    // Hapus semua pesanan milik user
    $deletePesanan = $conn->prepare("DELETE FROM pesanan WHERE id_pelanggan = ?");
    $deletePesanan->bind_param("i", $user_id);
    $deletePesanan->execute();
    $deletePesanan->close();

    // Hapus user (hanya sopir atau pelanggan, bukan admin)
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role IN ('sopir', 'pelanggan')");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $_SESSION['message'] = "Pengguna berhasil dihapus beserta semua data terkait.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Gagal menghapus pengguna atau pengguna tidak ditemukan (mungkin Admin lain).";
        $_SESSION['message_type'] = "error";
    }

    $stmt->close();
} catch (Exception $e) {
    $_SESSION['message'] = "Terjadi kesalahan: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

close_db_connection($conn);
header("Location: manage_users.php");
exit;
