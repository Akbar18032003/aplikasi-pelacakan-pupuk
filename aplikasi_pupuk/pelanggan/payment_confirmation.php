<?php
session_start();
require_once '../config/database.php';

// --- KEAMANAN: Periksa apakah pengguna sudah login dan memiliki peran 'pelanggan' ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pelanggan') {
    header("Location: ../public/login.php");
    exit;
}
// --- AKHIR KEAMANAN ---

$pesanan_id = $_GET['pesanan_id'] ?? null;
$order_data = null;
$items_in_order = [];

// Validasi pesanan_id
if (!$pesanan_id || !is_numeric($pesanan_id)) {
    $_SESSION['message'] = "ID Pesanan tidak valid atau tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: order_history.php"); 
    exit;
}

// Ambil detail pesanan utama dari database
try {
    if ($conn->connect_error) {
        throw new Exception("Koneksi gagal: " . $conn->connect_error);
    }

    $stmt_order = $conn->prepare("
        SELECT
            p.id, p.tanggal_pesan, p.total_harga, p.alamat_pengiriman, p.catatan,
            p.status_pesanan, p.status_pembayaran, p.metode_pembayaran,
            u.nama_lengkap AS nama_pelanggan
        FROM pesanan p
        JOIN users u ON p.id_pelanggan = u.id
        WHERE p.id = ? AND p.id_pelanggan = ?
        LIMIT 1
    ");
    $stmt_order->bind_param("ii", $pesanan_id, $_SESSION['user_id']);
    $stmt_order->execute();
    $result_order = $stmt_order->get_result();

    if ($result_order->num_rows == 1) {
        $order_data = $result_order->fetch_assoc();

        $stmt_items = $conn->prepare("
            SELECT dp.jumlah, dp.harga_satuan, pu.nama_pupuk, pu.kemasan
            FROM detail_pesanan dp
            JOIN pupuk pu ON dp.id_pupuk = pu.id
            WHERE dp.id_pesanan = ?
        ");
        $stmt_items->bind_param("i", $pesanan_id);
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();
        while ($row_item = $result_items->fetch_assoc()) {
            $items_in_order[] = $row_item;
        }
        $stmt_items->close();
    } else {
        $_SESSION['message'] = "Pesanan tidak ditemukan atau Anda tidak memiliki akses.";
        $_SESSION['message_type'] = "error";
        header("Location: order_history.php");
        exit;
    }
    $stmt_order->close();
} catch (Exception $e) {
    $_SESSION['message'] = "Terjadi kesalahan: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    header("Location: order_history.php");
    exit;
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pembayaran - Pesanan #<?php echo htmlspecialchars($pesanan_id); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS Lengkap dari checkout.php untuk konsistensi desain */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            overflow-x: hidden;
        }
        body.body-no-scroll { overflow: hidden; }

        .sidebar {
            width: 280px; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(12px);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1); height: 100vh;
            position: fixed; left: 0; top: 0;
            transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            z-index: 1000;
        }
        .sidebar-header {
            padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            background: linear-gradient(135deg, #ffc107, #ff8f00); color: white;
        }
        .sidebar-header h3 { font-size: 1.4rem; margin-bottom: 5px; font-weight: 600; }
        .sidebar-header p { font-size: 0.9rem; opacity: 0.9; word-wrap: break-word; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu a {
            display: flex; align-items: center; padding: 15px 25px; color: #333; text-decoration: none;
            transition: all 0.3s ease; border-left: 4px solid transparent; margin: 5px 0;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: linear-gradient(to right, #ffc107, #ffb300); color: white;
            border-left-color: #ff8f00; transform: translateX(5px);
        }
        .sidebar-menu a i { width: 20px; margin-right: 15px; font-size: 1.1rem; }
        .sidebar-menu a span { font-weight: 500; }
        .logout-btn { position: absolute; bottom: 20px; left: 20px; right: 20px; }
        .logout-btn a {
            background: linear-gradient(135deg, #ff6b6b, #ee5a5a); color: white !important;
            border-radius: 8px; justify-content: center; border-left: none !important;
            transform: none !important; padding: 12px;
        }
        .logout-btn a:hover {
            background: linear-gradient(135deg, #ff5252, #d32f2f);
            transform: translateY(-2px) !important; box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
        }

        .main-content {
            margin-left: 280px; flex-grow: 1; padding: 40px; min-height: 100vh;
            width: calc(100% - 280px); transition: margin-left 0.3s ease;
        }

        .confirmation-card {
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            border-radius: 20px; padding: 30px 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeInUp 0.6s ease-out;
        }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); }}
        .confirmation-card h2 {
            font-size: 2.2rem; margin-bottom: 15px;
            background: linear-gradient(135deg, #28a745, #218838);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
            text-align: center;
        }
        .confirmation-card .subtitle {
            text-align: center; font-size: 1.1rem; color: #555; margin-bottom: 25px;
        }

        .order-details, .payment-instructions {
            margin-bottom: 25px; padding: 20px; border: 1px solid #e0e0e0;
            border-radius: 10px; background-color: #fafafa;
        }
        .order-details h3, .payment-instructions h3 {
            color: #764ba2; border-bottom: 2px solid #764ba2;
            padding-bottom: 10px; margin-bottom: 15px; font-size: 1.3rem;
        }
        .order-details p { margin-bottom: 12px; line-height: 1.6; font-size: 1rem; color: #333; }
        .order-details p strong { color: #000; }
        .order-details table {
            width: 100%; border-collapse: collapse; margin-top: 20px;
        }
        .order-details th, .order-details td {
            border: 1px solid #ddd; padding: 12px; text-align: left;
        }
        .order-details th { background-color: #f2f2f2; font-weight: 600; }
        .order-details tfoot .total-row td {
            font-weight: bold; font-size: 1.2em; color: #667eea;
            text-align: right; padding-top: 15px; border-top: 2px solid #333;
        }
        
        .payment-instructions h3 { color: #28a745; border-bottom-color: #28a745; }
        .payment-instructions ul { list-style: none; padding: 0; margin-top: 15px; }
        .payment-instructions li { margin-bottom: 10px; font-size: 1.1rem; }
        .payment-instructions li strong { display: inline-block; width: 150px; }
        .payment-instructions .total-payment { font-weight: bold; font-size: 1.3em; color: #dc3545; }
        .payment-instructions .cod-message { font-size: 1.1rem; text-align: center; }

        .button-group {
            display: flex; justify-content: center; gap: 15px; flex-wrap: wrap; margin-top: 30px;
        }
        .btn {
            color: white; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer;
            font-size: 1rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); }
        .btn-secondary { background: linear-gradient(135deg, #ffc107, #ff8f00); }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.2); }

        /* --- BAGIAN KUNCI UNTUK RESPONSIVE --- */

        /* Tombol 'Hamburger' Menu: disembunyikan di desktop */
        .mobile-toggle {
            display: none; position: fixed; top: 15px; left: 15px; background: #ffc107;
            color: white; border: none; width: 45px; height: 45px; border-radius: 50%;
            cursor: pointer; z-index: 1001; font-size: 1.2rem; box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        
        /* Lapisan Gelap (Overlay): disembunyikan di desktop */
        .overlay {
            display: none; 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5); z-index: 999; opacity: 0;
            transition: opacity 0.3s ease;
        }
        /* Style untuk overlay ketika aktif */
        .overlay.active { display: block; opacity: 1; }
        
        /* Media Query: Aturan ini berlaku jika lebar layar 768px atau kurang */
        @media (max-width: 768px) {
            /* 1. Sembunyikan sidebar ke kiri layar */
            .sidebar { transform: translateX(-100%); }
            /* 2. Tampilkan sidebar jika memiliki kelas 'active' (di-trigger oleh JS) */
            .sidebar.active { transform: translateX(0); }
            /* 3. Buat konten utama memenuhi layar & hapus margin kiri */
            .main-content { margin-left: 0; padding: 20px; width: 100%; }
            /* 4. Tampilkan tombol hamburger menu */
            .mobile-toggle { display: block; }
            /* 5. Kurangi padding pada kartu utama agar tidak terlalu mepet */
            .confirmation-card { padding: 25px 20px; }
            /* 6. Kecilkan ukuran judul utama */
            .confirmation-card h2 { font-size: 1.8rem; }
            /* 7. Susun tombol secara vertikal ke bawah */
            .button-group { flex-direction: column; align-items: stretch; }
            /* 8. Buat setiap tombol memenuhi lebar container-nya */
            .btn { width: 100%; text-align: center; justify-content: center; }
        }
    </style>
</head>
<body>
    <!-- Overlay untuk efek background saat sidebar aktif di mobile -->
    <div class="overlay" id="overlay"></div>
    <!-- Tombol hamburger yang hanya muncul di mobile -->
    <button class="mobile-toggle" id="mobile-toggle-btn"><i class="fas fa-bars"></i></button>
    
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>Dashboard Pelanggan</h3>
            <p>Selamat Datang,<br><?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
        </div>
        <div class="sidebar-menu">
            <a href="index.php"><i class="fas fa-home"></i><span>Dashboard</span></a>
            <a href="order_pupuk.php" class="active"><i class="fas fa-shopping-cart"></i><span>Pesan Pupuk</span></a>
            <a href="track_delivery.php"><i class="fas fa-truck"></i><span>Lacak Pesanan Aktif</span></a>
            <a href="order_history.php"><i class="fas fa-history"></i><span>Riwayat Pesanan</span></a>
        </div>
        <div class="logout-btn">
            <a href="../public/logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </div>

    <div class="main-content" id="main-content">
        <div class="confirmation-card">
            <h2><i class="fas fa-check-circle"></i> Pesanan Berhasil Dibuat!</h2>
            <p class="subtitle">Terima kasih, <strong><?php echo htmlspecialchars($order_data['nama_pelanggan']); ?></strong>. Pesanan Anda sedang kami proses.</p>

            <div class="order-details">
                <h3><i class="fas fa-receipt"></i> Detail Pesanan Anda</h3>
                <p><strong>Nomor Pesanan:</strong> #<?php echo htmlspecialchars($order_data['id']); ?></p>
                <p><strong>Tanggal Pesan:</strong> <?php echo date('d F Y, H:i', strtotime($order_data['tanggal_pesan'])); ?></p>
                <p><strong>Alamat Pengiriman:</strong><br><?php echo nl2br(htmlspecialchars($order_data['alamat_pengiriman'])); ?></p>
                <?php if (!empty($order_data['catatan'])): ?>
                    <p><strong>Catatan:</strong><br><em>"<?php echo nl2br(htmlspecialchars($order_data['catatan'])); ?>"</em></p>
                <?php endif; ?>

                <div style="overflow-x:auto;"> <!-- Tambahan agar tabel bisa di-scroll horizontal di layar sangat kecil -->
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Pupuk</th>
                                <th>Kemasan</th>
                                <th>Jumlah</th>
                                <th>Harga Satuan</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items_in_order as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['nama_pupuk']); ?></td>
                                <td><?php echo htmlspecialchars($item['kemasan']); ?></td>
                                <td><?php echo htmlspecialchars($item['jumlah']); ?></td>
                                <td>Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?></td>
                                <td>Rp <?php echo number_format($item['jumlah'] * $item['harga_satuan'], 0, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="4">Total Pembayaran:</td>
                                <td>Rp <?php echo number_format($order_data['total_harga'], 0, ',', '.'); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="payment-instructions">
                <h3><i class="fas fa-money-bill-wave"></i> Instruksi Pembayaran</h3>
                <?php if ($order_data['metode_pembayaran'] == 'Transfer Bank'): ?>
                    <p>Silakan selesaikan pembayaran dengan mentransfer ke rekening di bawah ini:</p>
                    <ul>
                        <li><strong>Bank Tujuan:</strong> Bank Central Asia (BCA)</li>
                        <li><strong>Nomor Rekening:</strong> 1234-5678-90</li>
                        <li><strong>Atas Nama:</strong> PT. Agro Lestari Jaya</li>
                        <li><strong>Jumlah Transfer:</strong> <span class="total-payment">Rp <?php echo number_format($order_data['total_harga'], 0, ',', '.'); ?></span></li>
                    </ul>
                    <p style="text-align:center; margin-top: 20px; color: #842029; background-color: #f8d7da; padding: 10px; border-radius: 5px;">
                        Penting: Lakukan pembayaran dalam <strong>1x24 jam</strong> untuk menghindari pembatalan otomatis.
                    </p>
                <?php elseif ($order_data['metode_pembayaran'] == 'Cash on Delivery (COD)'): ?>
                    <p class="cod-message">
                        Anda telah memilih pembayaran di tempat (COD).<br>
                        Mohon siapkan uang tunai sejumlah <strong>Rp <?php echo number_format($order_data['total_harga'], 0, ',', '.'); ?></strong> untuk dibayarkan kepada kurir saat pesanan tiba.
                    </p>
                <?php else: ?>
                    <p>Metode pembayaran tidak valid. Silakan hubungi dukungan pelanggan kami.</p>
                <?php endif; ?>
            </div>

            <div class="button-group">
                <a href="order_pupuk.php" class="btn btn-secondary"><i class="fas fa-shopping-cart"></i> Pesan Lagi</a>
                <a href="track_delivery.php" class="btn btn-primary"><i class="fas fa-truck"></i> Lacak Pesanan Saya</a>
            </div>
        </div>
    </div>

    <script>
        // JavaScript untuk toggle sidebar pada tampilan mobile
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const toggleButton = document.getElementById('mobile-toggle-btn'); 

            if (sidebar && overlay && toggleButton) {
                // Fungsi untuk membuka/menutup sidebar
                function toggleSidebar() {
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('active');
                    document.body.classList.toggle('body-no-scroll'); // Mencegah scroll body saat sidebar terbuka
                }

                // Fungsi untuk menutup sidebar (jika diklik di luar)
                function closeSidebar() {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.classList.remove('body-no-scroll');
                }
                
                // Event listener untuk tombol hamburger
                toggleButton.addEventListener('click', function(event) {
                    event.stopPropagation();
                    toggleSidebar();
                });
                
                // Event listener untuk overlay
                overlay.addEventListener('click', closeSidebar);
            }
            
            // Event listener untuk mengubah ukuran window, memastikan layout kembali normal di desktop
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    if (sidebar && sidebar.classList.contains('active')) {
                        closeSidebar();
                    }
                }
            });
        });
    </script>
</body>
</html>