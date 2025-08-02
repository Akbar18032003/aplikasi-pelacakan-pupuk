<?php
// print_deliveries_list.php
session_start();
require_once '../config/database.php';

// Keamanan: Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: text/plain');
    echo "Akses ditolak. Silakan login sebagai admin.";
    exit;
}

// --- LOGIKA FILTER TANGGAL ---
$tanggal_filter = $_GET['tanggal'] ?? null;
$title_date = 'Semua Periode';
$where_clause = '';
$params = [];
$types = '';

if ($tanggal_filter && preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $tanggal_filter)) {
    // Menggunakan nama kolom 'tanggal_kirim' sesuai struktur database Anda.
    $where_clause = " WHERE DATE(peng.tanggal_kirim) = ?";
    $params[] = $tanggal_filter;
    $types .= 's';
    $date_obj = date_create($tanggal_filter);
    $title_date = $date_obj ? date_format($date_obj, 'd F Y') : 'Format Tanggal Salah';
}

// --- QUERY LAPORAN PENGIRIMAN YANG ROBUST ---
$sql = '
    SELECT
        peng.id AS pengiriman_id,
        peng.tanggal_kirim,
        peng.status_pengiriman,
        peng.no_kendaraan,
        u_sopir.nama_lengkap AS nama_sopir,
        p.id AS pesanan_id,
        u_pelanggan.nama_lengkap AS nama_pelanggan,
        p.alamat_pengiriman,
        GROUP_CONCAT(CONCAT(pu.nama_pupuk, " (", dp.jumlah, " sak)") SEPARATOR "\n") AS detail_barang
    FROM pengiriman peng
    LEFT JOIN pesanan p ON peng.id_pesanan = p.id
    LEFT JOIN users u_pelanggan ON p.id_pelanggan = u_pelanggan.id
    LEFT JOIN users u_sopir ON peng.id_sopir = u_sopir.id
    LEFT JOIN detail_pesanan dp ON p.id = dp.id_pesanan
    LEFT JOIN pupuk pu ON dp.id_pupuk = pu.id
    ' . $where_clause . '
    GROUP BY peng.id
    ORDER BY peng.tanggal_kirim ASC, peng.id ASC
';

$stmt = $conn->prepare($sql);
if ($stmt === false) { die("Error preparing query: " . htmlspecialchars($conn->error)); }
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();
$deliveries = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();
if (isset($conn)) { $conn->close(); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pengiriman - <?php echo htmlspecialchars($title_date); ?></title>
    <style>
        /* [PERBAIKAN] CSS yang disempurnakan untuk layout yang rapi */
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #333; }
        .container { width: 98%; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        h1 { font-size: 16px; margin: 0; }
        h2 { font-size: 13px; font-weight: normal; margin: 5px 0 0; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { 
            border: 1px solid #555; 
            padding: 8px; /* Tambah padding agar tidak terlalu padat */
            vertical-align: top; /* Kunci utama: Semua konten rata atas */
        }
        
        th { 
            background-color: #e8e8e8; 
            font-size: 10px; 
            text-transform: uppercase;
            text-align: left; /* Header rata kiri sesuai kontennya */
        }
        
        /* Kelas utilitas untuk perataan teks */
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        
        .footer { margin-top: 30px; text-align: right; font-size: 10px; color: #777; }
        .no-data { text-align: center; padding: 40px; font-size: 14px; font-weight: bold; }
        
        /* White-space: pre-wrap lebih baik dari pre-line untuk menjaga spasi */
        .alamat, .barang, .sopir { 
            white-space: pre-wrap; 
            word-wrap: break-word; 
        }

        .missing-data { color: #dc3545; font-style: italic; font-size:10px; }
        
        @media print {
            body { margin: 1cm; font-size: 10pt; }
            .container { width: 100%; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="container">
        <div class="header">
            <h1>LAPORAN PENGIRIMAN BARANG</h1>
            <h2>Tanggal Pengiriman: <?php echo htmlspecialchars($title_date); ?></h2>
        </div>

        <table>
            <thead>
                <tr>
                    <!-- [PERBAIKAN] Tambahkan kelas untuk perataan header -->
                    <th style="width:5%;" class="text-center">ID Kirim</th>
                    <th style="width:18%;">Pelanggan</th>
                    <th style="width:25%;">Alamat Pengiriman</th>
                    <th style="width:22%;">Detail Barang</th>
                    <th style="width:15%;">Sopir & Kendaraan</th>
                    <th style="width:10%;" class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($deliveries)): ?>
                    <tr>
                        <td colspan="6" class="no-data">Tidak ada data pengiriman untuk periode yang dipilih.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($deliveries as $delivery): ?>
                        <tr>
                            <!-- [PERBAIKAN] Tambahkan kelas perataan pada setiap TD -->
                            <td class="text-center">#<?php echo htmlspecialchars($delivery['pengiriman_id']); ?></td>
                            <td class="text-left">
                                <?php echo htmlspecialchars($delivery['nama_pelanggan'] ?? ''); ?>
                                <?php if (empty($delivery['nama_pelanggan'])): ?>
                                    <span class="missing-data">Data Pelanggan Hilang</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-left alamat">
                                <?php echo nl2br(htmlspecialchars($delivery['alamat_pengiriman'] ?? '')); ?>
                                <?php if (empty($delivery['alamat_pengiriman'])): ?>
                                    <span class="missing-data">Data Alamat Hilang</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-left barang">
                                <?php echo nl2br(htmlspecialchars($delivery['detail_barang'] ?? '')); ?>
                                <?php if (empty($delivery['detail_barang'])): ?>
                                    <span class="missing-data">Tidak ada item detail</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-left sopir">
                                <strong>Sopir:</strong> <?php echo htmlspecialchars($delivery['nama_sopir'] ?? 'N/A'); ?><br>
                                <strong>No. Pol:</strong> <?php echo htmlspecialchars($delivery['no_kendaraan'] ?? 'N/A'); ?>
                            </td>
                            <td class="text-center">
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $delivery['status_pengiriman']))); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="footer">
            <p>Dicetak oleh <?php echo htmlspecialchars($_SESSION['username']); ?> pada: <?php echo date('d F Y, H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>