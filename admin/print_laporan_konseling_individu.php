<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka', 'guru', 'guru_bk']);

$id = (int)($_GET['id'] ?? 0);
$samarkan = isset($_GET['samarkan']) ? ($_GET['samarkan'] == '1') : true;

if ($id <= 0) die("ID Laporan tidak valid.");

$query = "SELECT bl.*, s.nama_siswa, s.nis, s.nisn, k.nama_kelas, u.nama_lengkap as nama_guru, g.nip as nip_guru
          FROM bk_laporan bl
          JOIN siswa s ON bl.siswa_id = s.id
          LEFT JOIN kelas k ON bl.kelas_id = k.id
          LEFT JOIN guru g ON bl.guru_id = g.id
          LEFT JOIN users u ON g.user_id = u.id
          WHERE bl.id = $id LIMIT 1";
$res = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($res);

if (!$data) die("Data laporan tidak ditemukan.");

// Determine student name display
$display_nama_siswa = $samarkan ? (!empty($data['kode_konseli']) ? $data['kode_konseli'] . ' ( inisial/disamarkan )' : 'Siswa Anonim ( inisial/disamarkan )') : $data['nama_siswa'];

// Fetch school settings
$res_set = mysqli_query($conn, "SELECT nama_setting, nilai_setting FROM pengaturan");
$sets = [];
while ($r = mysqli_fetch_assoc($res_set)) {
    $sets[$r['nama_setting']] = $r['nilai_setting'];
}

$nama_sekolah = $sets['nama_sekolah'] ?? 'SEKOLAH MENENGAH KEJURUAN NEGERI 2 BONDOWOSO';
$alamat_sekolah = $sets['alamat_sekolah'] ?? 'Jalan Jend. Ahmad Yani No. 48 Telp/Fax. (0332) 421286';
$email_sekolah = $sets['email_sekolah'] ?? 'smknbws2@yahoo.com';
$web_sekolah = $sets['web_sekolah'] ?? 'www.smkn2bondowoso.sch.id';
$nama_kepsek = $sets['nama_kepsek'] ?? 'Sofyan Sauri, S.Pd., M.Pd.';
$nip_kepsek = $sets['nip_kepsek'] ?? '19770725 200701 1 017';

$kop_html = $sets['kop_sekolah_html'] ?? '';
$kop_font_family = $sets['kop_font_family'] ?? 'Times New Roman';
$kop_font_size = $sets['kop_font_size'] ?? '12';
$logo_kiri = $sets['logo_kiri'] ?? '';
$logo_kanan = $sets['logo_kanan'] ?? '';

// Date formatting
$days = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$time_stamp = strtotime($data['tanggal']);
$day_name = $days[date('l', $time_stamp)] ?? '';
$date_num = date('j', $time_stamp);
$month_name = $months[(int)date('n', $time_stamp)];
$year_num = date('Y', $time_stamp);

$formatted_date = $day_name . ', ' . $date_num . ' ' . $month_name . ' ' . $year_num;
$signature_date = 'Bondowoso, ' . $date_num . ' ' . $month_name . ' ' . $year_num;

$m_num = (int)date('n', $time_stamp);
$semester = ($m_num >= 7) ? 'GANJIL' : 'GENAP';
$tahun_pelajaran = ($m_num >= 7) ? $year_num . '-' . ($year_num + 1) : ($year_num - 1) . '-' . $year_num;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Konseling Individual - <?= htmlspecialchars($samarkan ? ($data['kode_konseli'] ?: 'Anonim') : $data['nama_siswa']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: '<?= htmlspecialchars($kop_font_family) ?>', 'Times New Roman', Times, serif; font-size: <?= (int)$kop_font_size ?>pt; background-color: #ffffff; color: #000000; line-height: 1.5; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; }
            @page { size: A4; margin: 15mm 20mm; }
        }
    </style>
</head>
<body class="p-6 sm:p-10 max-w-4xl mx-auto text-black">

    <!-- Action Bar -->
    <div class="no-print flex items-center justify-between mb-8 p-4 bg-slate-100 rounded-2xl font-sans text-xs">
        <div>
            <span class="text-xs font-bold text-slate-700 block">Laporan Pelaksanaan Layanan Konseling Individual</span>
            <span class="text-[11px] text-slate-500 font-mono">Status Penyamaran Nama: <strong><?= $samarkan ? 'AKTIF (Kode Konseli)' : 'NONAKTIF (Nama Asli)' ?></strong></span>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>admin/print_laporan_konseling_individu.php?id=<?= $id ?>&samarkan=<?= $samarkan ? '0' : '1' ?>" class="px-3 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs rounded-xl transition-all">
                Ganti ke <?= $samarkan ? 'Nama Asli' : 'Kode Konseli' ?>
            </a>
            <button onclick="window.print()" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center gap-2">
                <i class="fa fa-print"></i> Cetak Laporan
            </button>
        </div>
    </div>

    <!-- Dynamic Kop Surat -->
    <div class="border-b-4 border-black pb-2 mb-6 flex items-center justify-between gap-4 relative">
        <?php if (!empty($logo_kiri)): ?>
            <div class="w-20 flex-shrink-0 text-center">
                <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($logo_kiri) ?>" class="max-h-20 max-w-full inline-block">
            </div>
        <?php endif; ?>

        <div class="flex-1 text-center">
            <?php if (!empty($kop_html)): ?>
                <?= $kop_html ?>
            <?php else: ?>
                <h3 class="text-sm font-bold uppercase tracking-wide margin-0">PEMERINTAH PROVINSI JAWA TIMUR</h3>
                <h3 class="text-sm font-bold uppercase tracking-wide margin-0">DINAS PENDIDIKAN</h3>
                <h2 class="text-base font-bold uppercase tracking-wide my-1"><?= htmlspecialchars($nama_sekolah) ?></h2>
                <p class="text-xs font-normal margin-0"><?= htmlspecialchars($alamat_sekolah) ?></p>
                <p class="text-[11px] italic margin-0">Email: <?= htmlspecialchars($email_sekolah) ?> Website: <?= htmlspecialchars($web_sekolah) ?></p>
                <div class="flex justify-between items-center mt-1">
                    <h2 class="text-sm font-bold uppercase">BONDOWOSO</h2>
                    <span class="text-xs font-bold font-mono">68214</span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($logo_kanan)): ?>
            <div class="w-20 flex-shrink-0 text-center">
                <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($logo_kanan) ?>" class="max-h-20 max-w-full inline-block">
            </div>
        <?php endif; ?>
    </div>

    <!-- Document Title -->
    <div class="text-center my-6 space-y-1">
        <h2 class="text-base font-bold uppercase tracking-wider">LAPORAN PELAKSANAAN</h2>
        <h2 class="text-base font-bold uppercase tracking-wider">LAYANAN KONSELING INDIVIDUAL</h2>
        <p class="text-xs font-bold uppercase">SEMESTER <?= $semester ?> TAHUN PELAJARAN <?= $tahun_pelajaran ?></p>
    </div>

    <!-- Content Items -->
    <div class="text-xs space-y-3 my-8 px-4">
        <div class="grid grid-cols-12 gap-1">
            <span class="col-span-5 font-normal">1. Nama Konseli</span>
            <span class="col-span-7">: <?= htmlspecialchars($display_nama_siswa) ?></span>
        </div>
        <div class="grid grid-cols-12 gap-1">
            <span class="col-span-5 font-normal">2. Kelas/Semester</span>
            <span class="col-span-7">: <?= htmlspecialchars($data['nama_kelas'] ?? '-') ?></span>
        </div>
        <div class="grid grid-cols-12 gap-1">
            <span class="col-span-5 font-normal">3. Hari, Tanggal</span>
            <span class="col-span-7">: <?= htmlspecialchars($formatted_date) ?></span>
        </div>
        <div class="grid grid-cols-12 gap-1">
            <span class="col-span-5 font-normal">4. Pertemuan ke-</span>
            <span class="col-span-7">: <?= htmlspecialchars($data['pertemuan_ke'] ?: '1') ?></span>
        </div>
        <div class="grid grid-cols-12 gap-1">
            <span class="col-span-5 font-normal">5. Waktu</span>
            <span class="col-span-7">: <?= htmlspecialchars($data['waktu'] ?: '45 Menit') ?></span>
        </div>
        <div class="grid grid-cols-12 gap-1">
            <span class="col-span-5 font-normal">6. Tempat</span>
            <span class="col-span-7">: <?= htmlspecialchars($data['tempat'] ?: 'Ruang BK') ?></span>
        </div>
        <div class="grid grid-cols-12 gap-1">
            <span class="col-span-5 font-normal">7. Pendekatan dan teknik konseling yang digunakan</span>
            <span class="col-span-7">: <?= htmlspecialchars($data['pendekatan_teknik'] ?: 'Eksplorasi') ?></span>
        </div>
        <div class="grid grid-cols-12 gap-1">
            <span class="col-span-5 font-normal">8. Hasil yang dicapai</span>
            <div class="col-span-7 leading-relaxed">
                : <?= nl2br(htmlspecialchars($data['hasil_dicapai'] ?: $data['ringkasan_pembahasan'])) ?>
            </div>
        </div>
    </div>

    <!-- Signatures -->
    <div class="mt-16 text-xs">
        <div class="flex justify-end mb-4 pr-4">
            <p><?= htmlspecialchars($signature_date) ?></p>
        </div>

        <div class="grid grid-cols-2 gap-8 text-left">
            <div class="space-y-16">
                <div>
                    <p>Mengetahui</p>
                    <p>Kepala <?= htmlspecialchars($nama_sekolah) ?></p>
                </div>
                <div>
                    <p class="font-bold underline uppercase"><?= htmlspecialchars($nama_kepsek) ?></p>
                    <p>NIP. <?= htmlspecialchars($nip_kepsek) ?></p>
                </div>
            </div>

            <div class="space-y-16 pl-8">
                <div>
                    <p>&nbsp;</p>
                    <p>Guru BK</p>
                </div>
                <div>
                    <p class="font-bold underline uppercase"><?= htmlspecialchars($data['nama_guru'] ?? 'Laili Anggraini, S.Pd') ?></p>
                    <p>NIPPPK. <?= htmlspecialchars($data['nip_guru'] ?? '-') ?></p>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
