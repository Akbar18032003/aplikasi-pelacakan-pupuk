<?php
session_start();
require_once '../config/database.php';

// --- KEAMANAN: Periksa apakah pengguna sudah login dan memiliki peran 'admin' ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}
// --- AKHIR KEAMANAN ---

// Ambil pesan notifikasi dari session
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'info';
unset($_SESSION['message'], $_SESSION['message_type']);

// Query untuk mengambil semua transaksi/pesanan dengan detail pembayaran dan pelanggan
$stmt = $conn->prepare("
    SELECT
        p.id AS pesanan_id,
        p.tanggal_pesan,
        p.total_harga,
        p.metode_pembayaran,
        p.status_pembayaran,
        p.status_pesanan,
        u.nama_lengkap AS nama_pelanggan,
        u.email AS email_pelanggan,
        u.telepon AS telepon_pelanggan
    FROM pesanan p
    JOIN users u ON p.id_pelanggan = u.id
    ORDER BY p.tanggal_pesan DESC
");
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS ini SAMA PERSIS dengan file dashboard admin lainnya untuk konsistensi */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 280px; background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%); color: white; z-index: 1000; transition: transform 0.3s ease; box-shadow: 4px 0 20px rgba(0,0,0,0.1); }
        .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h3 { font-size: 1.4rem; }
        .sidebar-header p { color: #bdc3c7; font-size: 0.9rem; }
        .sidebar-nav { padding: 20px 0; }
        .nav-item { margin: 8px 20px; }
        .nav-link { display: flex; align-items: center; padding: 15px 20px; color: #ecf0f1; text-decoration: none; border-radius: 12px; transition: all 0.3s ease; }
        .nav-link.active { background: #3498db; font-weight: 600; box-shadow: 0 4px 15px rgba(52,152,219,0.3); }
        .nav-link.active:hover { transform: translateX(0); }
        .nav-link:hover:not(.active) { background: rgba(52,152,219,0.2); transform: translateX(5px); color: #3498db; }
        .nav-link i { margin-right: 15px; width: 20px; text-align: center; }
        .main-content { margin-left: 280px; padding: 40px; transition: margin-left 0.3s ease; }
        .page-header { background: white; border-radius: 20px; padding: 30px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 8px 25px rgba(0,0,0,0.07); flex-wrap: wrap; gap: 15px;}
        .page-header .header-text h1 { font-size: 2rem; color: #2c3e50; font-weight: 600; display:flex; align-items:center; }
        .page-header .header-text h1 i { margin-right:15px; color:#f39c12; }
        .page-header .header-text p { color: #7f8c8d; }
        .print-btn { background-color: #3498db; color: white; padding: 12px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; transition: background-color 0.3s ease; }
        .print-btn:hover { background-color: #2980b9; }
        .table-container { background: white; border-radius: 15px; overflow-x: auto; box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 15px 18px; text-align: left; border-bottom: 1px solid #ecf0f1; vertical-align: middle; white-space: nowrap; }
        .data-table th { background-color: #f8f9fa; color: #34495e; text-transform: uppercase; font-size: 0.8em; }
        .data-table tbody tr:hover { background-color: #f1f3f5; }
        td:first-child, th:first-child { text-align: center; }
        
        .status-badge { display: inline-block; padding: .4em .8em; font-size: .8em; font-weight: 700; color: #fff; border-radius: 50px; text-transform: capitalize; }
        .status-badge.pending, .status-badge.menunggu_penugasan, .status-badge.pending_pembayaran { background-color: #ffc107; color: #333; }
        .status-badge.diproses, .status-badge.dalam_perjalanan { background-color: #007bff; }
        .status-badge.sudah_sampai, .status-badge.selesai, .status-badge.sudah_dibayar { background-color: #28a745; }
        .status-badge.dibatalkan, .status-badge.bermasalah, .status-badge.dibatalkan_pembayaran { background-color: #dc3545; }
        
        /* CSS TAMBAHAN UNTUK STATUS PEMBAYARAN */
        .status-badge.status-pending-pembayaran { background-color: #ffc107; color: #333; }
        .status-badge.status-sudah-dibayar { background-color: #28a745; }
        .status-badge.status-dibatalkan-pembayaran { background-color: #dc3545; }

        .table-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-start; }
        .action-btn { padding: 8px 12px; border-radius: 8px; text-decoration: none; color: white; display: inline-flex; align-items: center; border: none; font-size:0.9em; transition: all 0.2s ease; gap: 5px; width: 150px; justify-content: center; }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .action-btn.detail { background-color: #3498db; }
        
        .message { padding: 20px; margin-bottom: 25px; border-radius: 12px; }
        .message.success { background-color: #d4edda; color: #155724; }
        .message.error { background-color: #f8d7da; color: #721c24; }
        .message.info { background-color: #d1ecf1; color: #0c5460; }

    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-user-shield"></i> Admin Panel</h3>
            <p>Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i>Dashboard</a></div>
            <div class="nav-item"><a href="manage_users.php" class="nav-link"><i class="fas fa-users-cog"></i>Manajemen Pengguna</a></div>
            <div class="nav-item"><a href="manage_pupuk.php" class="nav-link"><i class="fas fa-seedling"></i>Manajemen Pupuk</a></div>
            <div class="nav-item"><a href="manage_deliveries.php" class="nav-link"><i class="fas fa-truck"></i>Manajemen Pengiriman</a></div>
            <div class="nav-item"><a href="transaction_history.php" class="nav-link active"><i class="fas fa-exchange-alt"></i>Riwayat Transaksi</a></div>
            <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i>Laporan</a></div>
            <div class="nav-item"><a href="../public/logout.php" class="nav-link" style="margin-top:20px; background-color:rgba(231, 76, 60, 0.1);"><i class="fas fa-sign-out-alt"></i>Logout</a></div>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <div class="header-text">
                <h1><i class="fas fa-exchange-alt"></i> Riwayat Transaksi</h1>
                <p>Lihat semua riwayat transaksi pesanan dari seluruh pelanggan.</p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID Pesanan</th>
                        <th>Tanggal Pesan</th>
                        <th>Pelanggan</th>
                        <th>Total Harga</th>
                        <th>Metode Pembayaran</th>
                        <th>Status Pembayaran</th>
                        <th>Status Pesanan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="8" style="text-align:center; padding: 40px;">Tidak ada riwayat transaksi yang ditemukan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><strong>#<?php echo htmlspecialchars($transaction['pesanan_id']); ?></strong></td>
                                <td><?php echo date('d M Y, H:i', strtotime($transaction['tanggal_pesan'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($transaction['nama_pelanggan']); ?><br>
                                    <small style="color:#555;">Telp: <?php echo htmlspecialchars($transaction['telepon_pelanggan']); ?></small><br>
                                    <small style="color:#555;">Email: <?php echo htmlspecialchars($transaction['email_pelanggan']); ?></small>
                                </td>
                                <td>Rp <?php echo number_format($transaction['total_harga'], 0, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($transaction['metode_pembayaran']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo str_replace('_', '-', strtolower(htmlspecialchars($transaction['status_pembayaran']))); ?>">
                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $transaction['status_pembayaran']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo strtolower(str_replace(' ', '_', htmlspecialchars($transaction['status_pesanan']))); ?>">
                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $transaction['status_pesanan']))); ?>
                                    </span>
                                </td>
                                <td class="table-actions">
                                    <a href="detail_pesanan.php?id=<?php echo $transaction['pesanan_id']; ?>" class="action-btn detail">
                                        <i class="fas fa-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
<?php
close_db_connection($conn);
?>