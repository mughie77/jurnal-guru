<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka', 'guru', 'guru_bk']);

$id = (int)($_GET['id'] ?? 0);
$nip_type = strtoupper(trim($_GET['nip_type'] ?? 'NIPPPK'));
if ($nip_type !== 'NIP') $nip_type = 'NIPPPK';

if ($id <= 0) die("ID Laporan BK Kelompok tidak valid.");

// Query report & counselor
$query = "SELECT bl.*, k.nama_kelas,
                 g.nip as nip_guru,
                 u.nama_lengkap as nama_user_guru
          FROM bk_laporan bl
          LEFT JOIN kelas k ON bl.kelas_id = k.id
          LEFT JOIN guru g ON bl.guru_id = g.id
          LEFT JOIN users u ON g.user_id = u.id
          WHERE bl.id = $id AND bl.jenis_bimbingan = 'Kelompok'
          LIMIT 1";
$res = mysqli_query($conn, $query);
if (!$res || mysqli_num_rows($res) === 0) die("Data Laporan RPL Bimbingan Kelompok tidak ditemukan.");

$r = mysqli_fetch_assoc($res);

// Parse JSON fields
$student_ids = json_decode($r['siswa_ids_json'] ?? '[]', true) ?: [];
$pelaksanaan = json_decode($r['pelaksanaan_json'] ?? '[]', true) ?: [];

// Fetch students details (avoiding duplicates if student is mapped in multiple historical classes)
$students = [];
if (!empty($student_ids)) {
    $ids_str = implode(',', array_map('intval', $student_ids));
    $q_st = mysqli_query($conn, "SELECT s.id, s.nama_siswa, s.nis, k.nama_kelas
                                 FROM siswa s
                                 LEFT JOIN siswa_kelas sk ON s.id = sk.siswa_id
                                 LEFT JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id AND tp.status = 'aktif'
                                 LEFT JOIN kelas k ON sk.kelas_id = k.id
                                 WHERE s.id IN ($ids_str)
                                 GROUP BY s.id
                                 ORDER BY s.nama_siswa ASC");
    while ($st = mysqli_fetch_assoc($q_st)) {
        $students[] = $st;
    }
}

// Get Headmaster & Counselor info from settings
$q_settings = mysqli_query($conn, "SELECT * FROM pengaturan");
$settings = [];
while ($row = mysqli_fetch_assoc($q_settings)) {
    $key = $row['nama_setting'] ?? ($row['kunci'] ?? '');
    $val = $row['nilai_setting'] ?? ($row['nilai'] ?? '');
    if ($key) $settings[$key] = $val;
}

$nama_ks = $settings['nama_kepsek'] ?? 'Deni Setyo Purnomo, S.Pd., M.M.';
$nip_ks = $settings['nip_kepsek'] ?? '19820512 200903 1 005';
$nama_sekolah = $settings['nama_sekolah'] ?? 'SMK PERTIWI KUNINGAN';

$nama_guru = !empty($r['nama_user_guru']) ? $r['nama_user_guru'] : 'Guru BK';
$nip_guru = !empty($r['nip_guru']) ? $r['nip_guru'] : '-';

// Format Hari & Tanggal
$hari_indo = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$bulan_indo = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

$timestamp = strtotime($r['tanggal']);
$nama_hari = $hari_indo[date('l', $timestamp)];
$tgl_num = date('d', $timestamp);
$bln_num = date('m', $timestamp);
$thn_num = date('Y', $timestamp);

$tanggal_formatted = "$nama_hari, $tgl_num " . $bulan_indo[$bln_num] . " $thn_num";
$tgl_ttd_formatted = "$tgl_num " . $bulan_indo[$bln_num] . " $thn_num";

$kelas_display = !empty($r['nama_kelas']) ? $r['nama_kelas'] : 'Lintas Kelas / Campuran';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPL Bimbingan Kelompok - <?= htmlspecialchars($r['topik_permasalahan']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Times+New+Roman&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            color: #000;
            background: #f3f4f6;
            margin: 0;
            padding: 20px;
        }
        .page-sheet {
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 20px auto;
            padding: 20mm 20mm;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            position: relative;
        }
        table.border-table, table.border-table th, table.border-table td {
            border: 1px solid #000;
            border-collapse: collapse;
        }
        table.border-table th, table.border-table td {
            padding: 6px 8px;
            font-size: 11pt;
            vertical-align: top;
        }
        .no-print {
            display: flex;
        }
        @media print {
            body {
                background: none;
                padding: 0;
            }
            .page-sheet {
                box-shadow: none;
                margin: 0;
                width: 100%;
                padding: 15mm 15mm;
                page-break-after: always;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<!-- Floating Control Bar -->
<div class="no-print max-w-[210mm] mx-auto mb-4 bg-slate-800 text-white p-3 rounded-2xl shadow-xl flex items-center justify-between">
    <div class="text-xs font-bold font-sans flex items-center gap-2">
        <i class="fa fa-print text-teal-400"></i> CETAK RPL BIMBINGAN KELOMPOK
    </div>
    <div class="flex items-center gap-2 font-sans">
        <button onclick="window.print()" class="bg-teal-500 hover:bg-teal-600 text-white px-4 py-2 rounded-xl text-xs font-bold transition-all shadow">
            Cetak Dokumen
        </button>
        <button onclick="window.close()" class="bg-slate-700 hover:bg-slate-600 text-slate-300 px-3 py-2 rounded-xl text-xs font-bold transition-all">
            Tutup
        </button>
    </div>
</div>

<!-- PAGE 1: RPL BIMBINGAN KELOMPOK -->
<div class="page-sheet">
    <!-- Header Document -->
    <div class="text-center font-bold uppercase mb-6 leading-tight">
        <div class="text-base tracking-wide">RENCANA PELAKSANAAN LAYANAN (RPL)</div>
        <div class="text-base tracking-wide">BIMBINGAN KELOMPOK</div>
        <div class="text-sm font-semibold tracking-normal mt-1">SEMESTER GANJIL / GENAP TAHUN PELAJARAN <?= date('Y') ?>/<?= date('Y')+1 ?></div>
    </div>

    <!-- Details Table -->
    <table class="w-full border-table mb-6">
        <tbody>
            <tr>
                <td class="w-1/3 font-bold bg-gray-50">Kompetensi Layanan</td>
                <td class="w-2/3"><?= htmlspecialchars($r['kompetensi_layanan'] ?? 'Pengembangan Pribadi & Sosial') ?></td>
            </tr>
            <tr>
                <td class="font-bold bg-gray-50">Bidang Layanan</td>
                <td><?= htmlspecialchars($r['bidang_layanan'] ?? 'Pribadi / Sosial') ?></td>
            </tr>
            <tr>
                <td class="font-bold bg-gray-50">Fungsi Layanan</td>
                <td><?= htmlspecialchars($r['fungsi_layanan'] ?? 'Pemahaman dan Pengembangan') ?></td>
            </tr>
            <tr>
                <td class="font-bold bg-gray-50">Topik / Bahasan</td>
                <td class="font-bold"><?= htmlspecialchars($r['topik_permasalahan']) ?></td>
            </tr>
            <tr>
                <td class="font-bold bg-gray-50">Tujuan Umum</td>
                <td><?= nl2br(htmlspecialchars($r['tujuan_umum'] ?? '-')) ?></td>
            </tr>
            <tr>
                <td class="font-bold bg-gray-50">Tujuan Khusus</td>
                <td><?= nl2br(htmlspecialchars($r['tujuan_khusus'] ?? '-')) ?></td>
            </tr>
            <tr>
                <td class="font-bold bg-gray-50">Sasaran Layanan</td>
                <td>Siswa Anggota Kelompok (<?= count($students) ?> Siswa - Kelas <?= htmlspecialchars($kelas_display) ?>)</td>
            </tr>
            <tr>
                <td class="font-bold bg-gray-50">Hari, Tanggal & Waktu</td>
                <td><?= $tanggal_formatted ?> (<?= htmlspecialchars($r['waktu'] ?? '45 menit') ?>)</td>
            </tr>
            <tr>
                <td class="font-bold bg-gray-50">Tempat Pelaksanaan</td>
                <td><?= htmlspecialchars($r['tempat'] ?? 'Ruang BK') ?></td>
            </tr>
            <tr>
                <td class="font-bold bg-gray-50">Materi Layanan</td>
                <td><?= nl2br(htmlspecialchars($r['materi_layanan'] ?? '-')) ?></td>
            </tr>
            <tr>
                <td class="font-bold bg-gray-50">Sumber Layanan</td>
                <td><?= htmlspecialchars($r['sumber_layanan'] ?? '-') ?></td>
            </tr>
            <tr>
                <td class="font-bold bg-gray-50">Metode & Teknik</td>
                <td><?= htmlspecialchars($r['metode_teknik'] ?? 'Diskusi Kelompok & Dynamic Group') ?></td>
            </tr>
            <tr>
                <td class="font-bold bg-gray-50">Media / Alat</td>
                <td><?= htmlspecialchars($r['media_alat'] ?? 'PPT & Kertas Kerja') ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Table Stage of Execution -->
    <div class="font-bold text-sm mb-2 uppercase tracking-wide">Tahap Pelaksanaan Kegiatan Bimbingan Kelompok:</div>
    <table class="w-full border-table mb-6">
        <tbody>
            <tr>
                <td class="w-1/3 font-bold bg-gray-50">1. Tahap Pembentukan (Orientation)</td>
                <td class="w-2/3 leading-relaxed"><?= nl2br(htmlspecialchars($pelaksanaan['pembentukan'] ?? '-')) ?></td>
            </tr>
            <tr>
                <td class="font-bold bg-gray-50">2. Tahap Peralihan (Transition)</td>
                <td class="leading-relaxed"><?= nl2br(htmlspecialchars($pelaksanaan['peralihan'] ?? '-')) ?></td>
            </tr>
            <tr>
                <td class="font-bold bg-gray-50">3. Tahap Inti / Kegiatan (Working)</td>
                <td class="leading-relaxed"><?= nl2br(htmlspecialchars($pelaksanaan['inti'] ?? '-')) ?></td>
            </tr>
            <tr>
                <td class="font-bold bg-gray-50">4. Tahap Pengakhiran (Termination)</td>
                <td class="leading-relaxed"><?= nl2br(htmlspecialchars($pelaksanaan['pengakhiran'] ?? '-')) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Evaluation Section -->
    <div class="font-bold text-sm mb-2 uppercase tracking-wide">Rencana Evaluasi:</div>
    <table class="w-full border-table mb-8">
        <tbody>
            <tr>
                <td class="w-1/3 font-bold bg-gray-50">1. Evaluasi Proses</td>
                <td class="w-2/3 leading-relaxed"><?= nl2br(htmlspecialchars($r['evaluasi_proses'] ?? '-')) ?></td>
            </tr>
            <tr>
                <td class="font-bold bg-gray-50">2. Evaluasi Hasil</td>
                <td class="leading-relaxed"><?= nl2br(htmlspecialchars($r['evaluasi_hasil'] ?? '-')) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Student List Attendance Sheet -->
    <div class="font-bold text-sm mb-2 uppercase tracking-wide">Daftar Anggota Kelompok Bimbingan:</div>
    <table class="w-full border-table mb-10">
        <thead>
            <tr class="bg-gray-100 font-bold text-center">
                <th class="w-10">No</th>
                <th>Nama Siswa</th>
                <th class="w-24">NIS</th>
                <th class="w-28">Kelas</th>
                <th class="w-28">Tanda Tangan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($students)): ?>
            <tr><td colspan="5" class="text-center italic text-gray-500 py-3">Tidak ada data siswa anggota.</td></tr>
            <?php else: ?>
                <?php foreach ($students as $idx => $s): ?>
                <tr>
                    <td class="text-center"><?= $idx + 1 ?></td>
                    <td class="font-bold"><?= htmlspecialchars($s['nama_siswa']) ?></td>
                    <td class="text-center font-mono"><?= htmlspecialchars($s['nis']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($s['nama_kelas'] ?? '-') ?></td>
                    <td class="text-xs text-slate-300 font-mono text-right align-bottom h-8">Siswa <?= $idx + 1 ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Signature Block Alignment -->
    <div class="flex justify-between items-start text-xs leading-normal">
        <!-- Left: Headmaster -->
        <div class="w-5/12 text-center flex flex-col justify-between h-40">
            <div>
                Mengetahui,<br>
                Kepala Sekolah
            </div>
            <div>
                <div class="font-bold underline uppercase"><?= htmlspecialchars($nama_ks) ?></div>
                <div>NIP. <?= htmlspecialchars($nip_ks) ?></div>
            </div>
        </div>

        <!-- Right: Counselor -->
        <div class="w-5/12 text-center flex flex-col justify-between h-40">
            <div>
                Kuningan, <?= $tgl_ttd_formatted ?><br>
                Guru Bimbingan Konseling
            </div>
            <div>
                <div class="font-bold underline uppercase"><?= htmlspecialchars($nama_guru) ?></div>
                <div><?= $nip_type ?>. <?= htmlspecialchars($nip_guru) ?></div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
