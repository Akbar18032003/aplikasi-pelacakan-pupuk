<?php
session_start();
require_once '../config/database.php';

// --- KEAMANAN: Periksa apakah pengguna sudah login dan memiliki peran 'admin' ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}
// --- AKHIR KEAMANAN ---

$pengiriman_id = $_GET['pengiriman_id'] ?? null;
$surat_jalan_data = null;
$pupuk_items_detail = [];

if (!$pengiriman_id || !is_numeric($pengiriman_id)) {
    // Bisa redirect atau tampilkan pesan error
    die("ID Pengiriman tidak valid.");
}

// --- Query untuk mengambil semua data yang dibutuhkan untuk Surat Jalan ---
$stmt = $conn->prepare("
    SELECT
        peng.id AS pengiriman_id_no,
        peng.no_kendaraan,
        peng.tanggal_kirim,
        peng.catatan_sopir,
        p.id AS pesanan_id,
        p.alamat_pengiriman,
        p.catatan AS catatan_pesanan,
        u_pelanggan.nama_lengkap AS nama_pelanggan,
        u_pelanggan.telepon AS telepon_pelanggan,
        u_sopir.nama_lengkap AS nama_sopir
    FROM pengiriman peng
    JOIN pesanan p ON peng.id_pesanan = p.id
    JOIN users u_pelanggan ON p.id_pelanggan = u_pelanggan.id
    LEFT JOIN users u_sopir ON peng.id_sopir = u_sopir.id
    WHERE peng.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $pengiriman_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $surat_jalan_data = $result->fetch_assoc();

    // Query untuk mengambil detail pupuk dalam pesanan ini
    $stmt_items = $conn->prepare("
        SELECT
            dp.jumlah,
            dp.harga_satuan,
            pu.nama_pupuk,
            pu.jenis_pupuk,
            pu.kemasan, -- Ambil kolom kemasan
            pu.deskripsi -- Untuk referensi jika perlu item_keterangan
        FROM detail_pesanan dp
        JOIN pupuk pu ON dp.id_pupuk = pu.id
        WHERE dp.id_pesanan = ?
    ");
    $stmt_items->bind_param("i", $surat_jalan_data['pesanan_id']);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    while ($row_item = $result_items->fetch_assoc()) {
        $pupuk_items_detail[] = $row_item;
    }
    $stmt_items->close();

} else {
    die("Data pengiriman tidak ditemukan untuk Surat Jalan ini.");
}
$stmt->close();
close_db_connection($conn); // Tutup koneksi database setelah semua data diambil

// --- Informasi Statis Perusahaan (Ganti dengan data perusahaan Anda) ---
$company_name = "PT. Usaha Enam Saudara";
$company_address = "Jl. Cimanuk Blok D No. 11, Komplek Pusri, Sukamaju, Kenten, Palembang - kode pos 30164";
$company_phone = "Telp: 0711-XXXXXXX";
// --- AKHIR INFORMASI STATIS ---

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan - No. <?php echo htmlspecialchars($surat_jalan_data['pengiriman_id_no']); ?></title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20mm; /* Margin cetak standar */
            font-size: 11pt;
            line-height: 1.5;
            color: #000;
        }
        .container {
            width: 100%;
            max-width: 190mm; /* Lebar A4 potret */
            margin: 0 auto;
            border: 1px solid #000;
            padding: 10mm;
            box-sizing: border-box;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        /* --- CSS BARU UNTUK LOGO & DETAIL PERUSAHAAN --- */
        .company-logo {
            max-height: 150px; /* Sesuaikan ukuran logo Anda */
            width: auto;
            margin-right: 15px; /* Jarak antara logo dan teks */
        }
        .company-details {
            display: flex;
            align-items: center; /* Menjaga logo dan teks sejajar */
            width: 60%;
        }
        /* --- AKHIR CSS BARU --- */

        .header .company-info {
            text-align: left;
            font-size: 10pt;
            line-height: 1.3;
        }
        .header .company-info h2 {
            margin: 0 0 5px 0;
            font-size: 14pt;
            text-transform: uppercase;
        }
        .header .recipient-info {
            text-align: right;
            font-size: 10pt;
            width: 40%;
        }
        .doc-title {
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 20px;
            text-decoration: underline;
        }
        .doc-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .doc-meta .left-meta, .doc-meta .right-meta {
            font-size: 10pt;
        }
        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }
        .item-table th, .item-table td {
            border: 1px solid #000;
            padding: 8px 5px;
            text-align: center;
            font-size: 10pt;
        }
        .item-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .item-table .item-name {
            text-align: left;
        }
        /* Ganti CSS lama dengan yang ini */
.footer-section {
    display: flex;
    justify-content: space-between;
    align-items: flex-start; /* Ratakan dari atas, ini kunci utamanya */
    margin-top: 50px;
    text-align: center;
}
.footer-section .sign-block {
    width: 30%;
    font-size: 10pt;
}
.footer-section .sign-line {
    height: 60px; /* Beri tinggi tetap untuk area tanda tangan */
    margin-top: 5px;
    margin-bottom: 5px;
    border-bottom: 1px solid #000;
}
.footer-section .sign-name {
    font-weight: bold;
    margin: 0;
}
.footer-section .sign-title {
    margin: 0;
}
        .note-section {
            margin-top: 20px;
            font-size: 10pt;
        }
        .vehicle-info {
            margin-top: 20px;
            font-size: 10pt;
        }

        /* Print specific styles */
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .container {
                border: none; /* Hapus border kotak utama saat dicetak */
                padding: 0;
            }
            .item-table th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .header, .doc-title, .doc-meta, .item-table, .footer-section, .note-section, .vehicle-info {
                page-break-inside: avoid; /* Hindari pemotongan di tengah elemen */
            }
        }
    </style>
    <script>
        // Skrip untuk memicu dialog cetak secara otomatis
        window.onload = function() {
            window.print();
        };
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="company-details">
                <!-- PASTIKAN PATH LOGO INI BENAR -->
                <img src="../includes/img/logo.png" alt="Logo Perusahaan" class="company-logo">
                <div class="company-info">
                    <h2><?php echo htmlspecialchars($company_name); ?></h2>
                    <p><?php echo htmlspecialchars($company_address); ?><br>
                       <?php echo htmlspecialchars($company_phone); ?></p>
                </div>
            </div>
            <div class="recipient-info">
                <p>Kepada Yth.:</p>
                <p><strong><?php echo htmlspecialchars($surat_jalan_data['nama_pelanggan']); ?></strong></p>
                <p><?php echo nl2br(htmlspecialchars($surat_jalan_data['alamat_pengiriman'])); ?></p>
                <p>Telp: <?php echo htmlspecialchars($surat_jalan_data['telepon_pelanggan']); ?></p>
            </div>
        </div>

        <div class="doc-title">SURAT JALAN</div>

        <div class="doc-meta">
            <div class="left-meta">
                <p>No.: <strong>SJ/<?php echo date('Y/m').'/'.str_pad($surat_jalan_data['pengiriman_id_no'], 4, '0', STR_PAD_LEFT); ?></strong></p>
            </div>
            <div class="right-meta">
                <p>Palembang, <?php echo date('d F Y', strtotime($surat_jalan_data['tanggal_kirim'])); ?></p>
            </div>
        </div>

        <table class="item-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No.</th>
                    <th class="item-name" style="width: 40%;">Nama Barang</th>
                    <th style="width: 15%;">Kemasan</th>
                    <th style="width: 10%;">Banyaknya</th>
                    <th style="width: 30%;">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                foreach ($pupuk_items_detail as $item):
                    $item_keterangan = htmlspecialchars($item['jenis_pupuk']);
                ?>
                <tr>
                    <td><?php echo $no++; ?>.</td>
                    <td class="item-name"><?php echo htmlspecialchars($item['nama_pupuk']); ?></td>
                    <td><?php echo htmlspecialchars($item['kemasan']); ?></td>
                    <td><?php echo htmlspecialchars($item['jumlah']); ?></td>
                    <td><?php echo $item_keterangan; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="vehicle-info">
            <p>No. Kendaraan: <strong><?php echo htmlspecialchars($surat_jalan_data['no_kendaraan'] ?? '-'); ?></strong></p>
        </div>

        <?php if (!empty($surat_jalan_data['catatan_pesanan']) || !empty($surat_jalan_data['catatan_sopir'])): ?>
        <div class="note-section">
            <p><strong>Keterangan Umum:</strong></p>
            <p style="margin-top: -5px;">
                <?php echo nl2br(htmlspecialchars($surat_jalan_data['catatan_pesanan'] ?? '')); ?>
                <?php if (!empty($surat_jalan_data['catatan_sopir'])): ?>
                    <br><em>Catatan Sopir: <?php echo nl2br(htmlspecialchars($surat_jalan_data['catatan_sopir'])); ?></em>
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>

       <div class="footer-section">
    <div class="sign-block">
        <p>Tanda Terima,</p>
        <!-- Area kosong untuk tanda tangan -->
        <div class="sign-line"></div>
        <!-- Nama dan jabatan di bawah garis -->
        <p class="sign-name">( .............................. )</p>
        <p class="sign-title">Penerima</p>
    </div>
    <div class="sign-block">
        <p>Sopir,</p>
        <div class="sign-line"></div>
        <p class="sign-name"><?php echo htmlspecialchars($surat_jalan_data['nama_sopir'] ?? '( .............................. )'); ?></p>
        <p class="sign-title">Pengemudi</p>
    </div>
    <div class="sign-block">
        <p>Hormat Kami,</p>
        <div class="sign-line"></div>
        <p class="sign-name">( .............................. )</p>
        <p class="sign-title">PT. Usaha Enam Saudara</p>
    </div>
</div>
    </div>
</body>
</html>