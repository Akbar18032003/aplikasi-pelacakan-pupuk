<?php
session_start();
require_once '../config/database.php'; // Sertakan file koneksi

// Set header ke JSON karena kita akan merespons dengan format JSON
header('Content-Type: application/json');

// --- Fungsi untuk mengirim respons JSON dan keluar ---
function send_json_response($success, $message, $data = []) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// --- KEAMANAN: Periksa login dan peran admin ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    send_json_response(false, 'Akses ditolak. Anda harus login sebagai admin.');
}

// --- Pastikan request adalah POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(false, 'Metode request tidak valid.');
}

// --- Validasi Input ---
$pupuk_id = filter_input(INPUT_POST, 'pupuk_id', FILTER_VALIDATE_INT);
$new_stock = filter_input(INPUT_POST, 'stok', FILTER_VALIDATE_INT);

if ($pupuk_id === false || $pupuk_id <= 0) {
    send_json_response(false, 'ID pupuk tidak valid.');
}

// Angka 0 adalah nilai stok yang valid
if ($new_stock === false || $new_stock < 0) {
    send_json_response(false, 'Jumlah stok tidak boleh negatif atau bukan angka.');
}

// --- Proses Update ke Database ---
try {
    // Siapkan query untuk update kolom stok saja
    $stmt = $conn->prepare("UPDATE pupuk SET stok = ? WHERE id = ?");
    
    // Periksa jika statement berhasil disiapkan
    if (!$stmt) {
        send_json_response(false, 'Gagal menyiapkan statement database.');
    }
    
    // Bind parameter (integer untuk stok, integer untuk id)
    $stmt->bind_param("ii", $new_stock, $pupuk_id);

    // Eksekusi statement
    if ($stmt->execute()) {
        // Cek apakah ada baris yang benar-benar terpengaruh (berubah)
        if ($stmt->affected_rows > 0) {
            send_json_response(true, 'Stok berhasil diperbarui!');
        } else {
            // Bisa jadi karena stok yang diinput sama dengan stok lama
            send_json_response(true, 'Tidak ada perubahan. Stok sudah sesuai.');
        }
    } else {
        // Gagal eksekusi query
        send_json_response(false, 'Gagal mengeksekusi query update.');
    }

    $stmt->close();
    close_db_connection($conn);

} catch (Exception $e) {
    // Tangani error database lainnya
    send_json_response(false, 'Terjadi kesalahan pada server: ' . $e->getMessage());
}

?>