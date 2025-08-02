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

// --- [BARU] LOGIKA UNTUK FILTER ---
$filter_status_pesanan = $_GET['status_pesanan'] ?? '';
$filter_status_pembayaran = $_GET['status_pembayaran'] ?? '';
$filter_status_pengiriman = $_GET['status_pengiriman'] ?? '';
$filter_nama_pelanggan = $_GET['nama_pelanggan'] ?? '';

// --- QUERY DETAIL DENGAN FILTER DINAMIS ---
$sql = "
    SELECT
        p.id AS pesanan_id,
        p.tanggal_pesan,
        p.total_harga,
        p.alamat_pengiriman,
        p.status_pesanan,
        p.metode_pembayaran,
        p.status_pembayaran,
        u_pelanggan.nama_lengkap AS nama_pelanggan,
        u_pelanggan.telepon AS telepon_pelanggan,
        peng.id AS pengiriman_id,
        peng.status_pengiriman AS status_pengiriman_saat_ini,
        u_sopir.nama_lengkap AS nama_sopir_ditugaskan
    FROM pesanan p
    JOIN users u_pelanggan ON p.id_pelanggan = u_pelanggan.id
    LEFT JOIN pengiriman peng ON p.id = peng.id_pesanan
    LEFT JOIN users u_sopir ON peng.id_sopir = u_sopir.id
";

$where_clauses = [];
$params = [];
$types = '';

if (!empty($filter_status_pesanan)) {
    $where_clauses[] = "p.status_pesanan = ?";
    $params[] = $filter_status_pesanan;
    $types .= 's';
}
if (!empty($filter_status_pembayaran)) {
    $where_clauses[] = "p.status_pembayaran = ?";
    $params[] = $filter_status_pembayaran;
    $types .= 's';
}
if (!empty($filter_status_pengiriman)) {
    if ($filter_status_pengiriman == 'menunggu_penugasan') {
        $where_clauses[] = "peng.status_pengiriman IS NULL";
    } else {
        $where_clauses[] = "peng.status_pengiriman = ?";
        $params[] = $filter_status_pengiriman;
        $types .= 's';
    }
}
if (!empty($filter_nama_pelanggan)) {
    $where_clauses[] = "u_pelanggan.nama_lengkap LIKE ?";
    $params[] = "%" . $filter_nama_pelanggan . "%";
    $types .= 's';
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= "
    ORDER BY
        CASE p.status_pesanan
            WHEN 'pending' THEN 1
            WHEN 'diproses' THEN 2
            ELSE 3
        END,
        p.tanggal_pesan ASC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$orders_to_manage = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengiriman - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS Lengkap & Terbaru (Tetap Sama) */
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
        
        .header-actions { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .header-actions .print-form { display: flex; align-items: center; gap: 10px; }
        .header-actions label { font-size: 0.9em; color: #34495e; font-weight: 600; }
        .header-actions input[type="date"] { padding: 8px 12px; border: 2px solid #ddd; border-radius: 8px; font-family: inherit; color: #333; transition: border-color 0.2s; }
        .header-actions input[type="date"]:focus { border-color: #3498db; outline: none; }
        .header-actions .btn-print { padding: 10px 15px; background-color: #16a085; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: background-color 0.2s, transform 0.2s; text-decoration: none; }
        .header-actions .btn-print:hover { background-color: #1abc9c; transform: translateY(-2px); }
        .header-actions .btn-print.all { background-color: #2980b9; }
        .header-actions .btn-print.all:hover { background-color: #3498db; }

        /* [BARU] CSS untuk Filter Container */
        .filter-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.07);
            margin-bottom: 30px;
        }
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { margin-bottom: 8px; font-weight: 600; color: #34495e; font-size: 0.9em; }
        .filter-group input, .filter-group select {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
        }
        .filter-group input:focus, .filter-group select:focus { border-color: #3498db; outline: none; }
        .filter-buttons { display: flex; gap: 10px; align-items: flex-end; }
        .filter-buttons button, .filter-buttons a {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: white;
            text-decoration: none;
            text-align: center;
        }
        .filter-buttons .btn-filter { background-color: #2980b9; }
        .filter-buttons .btn-filter:hover { background-color: #3498db; }
        .filter-buttons .btn-reset { background-color: #7f8c8d; }
        .filter-buttons .btn-reset:hover { background-color: #95a5a6; }

        .table-container { background: white; border-radius: 15px; overflow-x: auto; box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 10px 15px; text-align: left; border-bottom: 1px solid #ecf0f1; vertical-align: middle; white-space: nowrap; }
        .data-table th { background-color: #f8f9fa; color: #34495e; text-transform: uppercase; font-size: 0.8em; }
        .data-table tbody tr:hover { background-color: #f1f3f5; }
        td:first-child, th:first-child { text-align: center; }
        
       .status-badge { display: inline-block; padding: .4em .8em; font-size: .8em; font-weight: 700; color: #fff; border-radius: 50px; text-transform: capitalize; }
        .status-badge.pending, 
        .status-badge.menunggu_penugasan, 
        .status-badge.pending_pembayaran { background-color: #ffc107; color: #333; }

        .status-badge.diproses, 
        .status-badge.dalam_perjalanan { background-color: #007bff; }

        .status-badge.sudah_sampai, 
        .status-badge.selesai, 
        .status-badge.sudah_dibayar { background-color: #28a745; }

        .status-badge.dibatalkan, 
        .status-badge.bermasalah, 
        .status-badge.dibatalkan_pembayaran { background-color: #dc3545; }
        .table-actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: 4px; }
        .action-btn { padding: 6px; border-radius: 8px; text-decoration: none; color: white; display: inline-flex; align-items: center; justify-content: center; border: none; font-size: 0.8em; transition: all 0.2s ease; gap: 5px; width: 100%; text-align: center; }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .action-btn.assign { background-color: #f39c12; }
        .action-btn.edit { background-color: #e67e22; }
        .action-btn.print-sj { background-color: #5bc0de; } 
        .action-btn.detail { background-color: #3498db; }
        .action-btn.btn-success { background-color: #28a745; }
        .action-btn.btn-danger { background-color: #dc3545; }
        .action-btn.btn-warning { background-color: #ffc107; color: #333; }

        .message { padding: 20px; margin-bottom: 25px; border-radius: 12px; }
        .message.success { background-color: #d4edda; color: #155724; }
        .message.error { background-color: #f8d7da; color: #721c24; }
        .message.info { background-color: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <div class="sidebar">
        <!-- Sidebar HTML Tetap Sama -->
        <div class="sidebar-header">
            <h3><i class="fas fa-user-shield"></i> Admin Panel</h3>
            <p>Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i>Dashboard</a></div>
            <div class="nav-item"><a href="manage_users.php" class="nav-link"><i class="fas fa-users-cog"></i>Manajemen Pengguna</a></div>
            <div class="nav-item"><a href="manage_pupuk.php" class="nav-link"><i class="fas fa-seedling"></i>Manajemen Pupuk</a></div>
            <div class="nav-item"><a href="manage_deliveries.php" class="nav-link active"><i class="fas fa-truck"></i>Manajemen Pengiriman</a></div>
            <div class="nav-item"><a href="transaction_history.php" class="nav-link"><i class="fas fa-exchange-alt"></i>Riwayat Transaksi</a></div>
            <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i>Laporan</a></div>
            <div class="nav-item"><a href="../public/logout.php" class="nav-link" style="margin-top:20px; background-color:rgba(231, 76, 60, 0.1);"><i class="fas fa-sign-out-alt"></i>Logout</a></div>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <div class="header-text">
                <h1><i class="fas fa-truck-loading"></i> Manajemen Pengiriman</h1>
                <p>Kelola semua pesanan, ubah status pembayaran, dan tugaskan sopir.</p>
            </div>
            <div class="header-actions">
                <form action="print_deliveries_list.php" method="GET" target="_blank" class="print-form">
                    <label for="tanggal">Cetak Laporan Harian:</label>
                    <input type="date" id="tanggal" name="tanggal" value="<?php echo date('Y-m-d'); ?>">
                    <button type="submit" class="btn-print">
                        <i class="fas fa-print"></i> Cetak per Tanggal
                    </button>
                </form>
                 <a href="print_deliveries_list.php" target="_blank" class="btn-print all" title="Cetak Laporan Semua Pengiriman">
                    <i class="fas fa-globe"></i> Cetak Semua
                </a>
            </div>
        </div>

        <!-- [BARU] FORM FILTER -->
        <div class="filter-container">
            <form action="manage_deliveries.php" method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="filter_nama_pelanggan">Nama Pelanggan</label>
                    <input type="text" name="nama_pelanggan" id="filter_nama_pelanggan" value="<?php echo htmlspecialchars($filter_nama_pelanggan); ?>" placeholder="Cari nama...">
                </div>
                <div class="filter-group">
                    <label for="filter_status_pesanan">Status Pesanan</label>
                    <select name="status_pesanan" id="filter_status_pesanan">
                        <option value="">Semua</option>
                        <option value="pending" <?php if($filter_status_pesanan == 'pending') echo 'selected'; ?>>Pending</option>
                        <option value="diproses" <?php if($filter_status_pesanan == 'diproses') echo 'selected'; ?>>Diproses</option>
                        <option value="selesai" <?php if($filter_status_pesanan == 'selesai') echo 'selected'; ?>>Selesai</option>
                        <option value="dibatalkan" <?php if($filter_status_pesanan == 'dibatalkan') echo 'selected'; ?>>Dibatalkan</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_status_pembayaran">Status Pembayaran</label>
                    <select name="status_pembayaran" id="filter_status_pembayaran">
                        <option value="">Semua</option>
                        <option value="pending_pembayaran" <?php if($filter_status_pembayaran == 'pending_pembayaran') echo 'selected'; ?>>Pending Pembayaran</option>
                        <option value="sudah_dibayar" <?php if($filter_status_pembayaran == 'sudah_dibayar') echo 'selected'; ?>>Sudah Dibayar</option>
                        <option value="dibatalkan_pembayaran" <?php if($filter_status_pembayaran == 'dibatalkan_pembayaran') echo 'selected'; ?>>Dibatalkan</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_status_pengiriman">Status Pengiriman</label>
                    <select name="status_pengiriman" id="filter_status_pengiriman">
                        <option value="">Semua</option>
                        <option value="menunggu_penugasan" <?php if($filter_status_pengiriman == 'menunggu_penugasan') echo 'selected'; ?>>Menunggu Penugasan</option>
                        <option value="dalam_perjalanan" <?php if($filter_status_pengiriman == 'dalam_perjalanan') echo 'selected'; ?>>Dalam Perjalanan</option>
                        <option value="sudah_sampai" <?php if($filter_status_pengiriman == 'sudah_sampai') echo 'selected'; ?>>Sudah Sampai</option>
                        <option value="bermasalah" <?php if($filter_status_pengiriman == 'bermasalah') echo 'selected'; ?>>Bermasalah</option>
                    </select>
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                    <a href="manage_deliveries.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
                </div>
            </form>
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
                        <th>Alamat Pengiriman</th>
                        <th>Total Harga</th>
                        <th>Metode Pembayaran</th>
                        <th>Status Pembayaran</th>
                        <th>Status Pesanan</th>
                        <th>Status Pengiriman</th>
                        <th>Sopir</th>
                        <th style="width: 220px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders_to_manage)): ?>
                        <tr><td colspan="11" style="text-align:center; padding: 40px;">Tidak ada pesanan yang cocok dengan kriteria filter.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders_to_manage as $order): ?>
                            <tr>
                                <td><strong>#<?php echo htmlspecialchars($order['pesanan_id']); ?></strong></td>
                                <td><?php echo date('d M Y, H:i', strtotime($order['tanggal_pesan'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($order['nama_pelanggan']); ?><br>
                                    <small style="color:#555;">Telp: <?php echo htmlspecialchars($order['telepon_pelanggan']); ?></small>
                                </td>
                                <td style="white-space: normal; min-width: 250px;"><?php echo nl2br(htmlspecialchars($order['alamat_pengiriman'])); ?></td>
                                <td>Rp <?php echo number_format($order['total_harga'], 0, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($order['metode_pembayaran']); ?></td>
                               <td>
                                    <span class="status-badge <?php echo strtolower(htmlspecialchars($order['status_pembayaran'])); ?>">
                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['status_pembayaran']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo strtolower(str_replace(' ', '_', htmlspecialchars($order['status_pesanan']))); ?>">
                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['status_pesanan']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($order['status_pengiriman_saat_ini'])): ?>
                                        <span class="status-badge <?php echo strtolower(str_replace(' ', '_', htmlspecialchars($order['status_pengiriman_saat_ini']))); ?>">
                                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['status_pengiriman_saat_ini']))); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge menunggu_penugasan">Menunggu Penugasan</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($order['nama_sopir_ditugaskan'] ?? '<i>Belum Ada</i>'); ?>
                                </td>
                                
                                <td class="table-actions">
                                    <!-- Tombol Aksi Pembayaran -->
                                    <?php if ($order['status_pembayaran'] == 'pending_pembayaran'): ?>
                                        <a href="update_payment_status.php?id=<?php echo $order['pesanan_id']; ?>&status=sudah_dibayar" class="action-btn btn-success" onclick="return confirm('Anda yakin ingin menandai pesanan ini LUNAS?');" title="Tandai Sudah Dibayar">
                                            <i class="fas fa-check-circle"></i> Lunas
                                        </a>
                                        <a href="update_payment_status.php?id=<?php echo $order['pesanan_id']; ?>&status=dibatalkan_pembayaran" class="action-btn btn-danger" onclick="return confirm('Anda yakin ingin MEMBATALKAN pembayaran ini?');" title="Batalkan Pembayaran">
                                            <i class="fas fa-times-circle"></i> Batal
                                        </a>
                                    <?php else: ?>
                                        <a href="update_payment_status.php?id=<?php echo $order['pesanan_id']; ?>&status=pending_pembayaran" class="action-btn btn-warning" onclick="return confirm('Kembalikan status pembayaran ke PENDING?');" title="Reset Status Pembayaran">
                                            <i class="fas fa-undo"></i> Reset
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Tombol Detail Pesanan -->
                                    <a href="detail_pesanan.php?id=<?php echo $order['pesanan_id']; ?>" class="action-btn detail" title="Lihat Detail Pesanan">
                                        <i class="fas fa-eye"></i> Detail
                                    </a>

                                    <!-- Tombol Aksi Pengiriman -->
                                    <?php if (empty($order['pengiriman_id'])): ?>
                                        <a href="assign_delivery.php?pesanan_id=<?php echo $order['pesanan_id']; ?>" class="action-btn assign" title="Tugaskan Sopir untuk Pengiriman">
                                            <i class="fas fa-user-plus"></i> Tugaskan
                                        </a>
                                    <?php else: ?>
                                        <a href="edit_delivery.php?pengiriman_id=<?php echo $order['pengiriman_id']; ?>" class="action-btn edit" title="Edit Data Pengiriman">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="print_surat_jalan.php?pengiriman_id=<?php echo $order['pengiriman_id']; ?>" target="_blank" class="action-btn print-sj" title="Cetak Surat Jalan">
                                            <i class="fas fa-file-alt"></i> Cetak SJ
                                        </a>
                                    <?php endif; ?>

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
// Selalu tutup koneksi di akhir skrip
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>