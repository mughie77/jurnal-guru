<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka', 'guru', 'guru_bk']);

$id = (int)($_GET['id'] ?? 0);
$samarkan = (int)($_GET['samarkan'] ?? 1);
$nip_type = isset($_GET['nip_type']) && in_array(strtoupper($_GET['nip_type']), ['NIP', 'NIPPPK']) ? strtoupper($_GET['nip_type']) : 'NIPPPK';

if ($id <= 0) die("ID Laporan Konferensi Kasus tidak valid.");

// Fetch report
$query = "SELECT bl.*, s.nama_siswa, s.nis, s.alamat, k.nama_kelas, g.nip as nip_guru, u.nama_lengkap as nama_user_guru
          FROM bk_laporan bl
          LEFT JOIN siswa s ON bl.siswa_id = s.id
          LEFT JOIN kelas k ON bl.kelas_id = k.id
          LEFT JOIN guru g ON bl.guru_id = g.id
          LEFT JOIN users u ON g.user_id = u.id
          WHERE bl.id = $id AND bl.jenis_bimbingan = 'Konferensi Kasus' LIMIT 1";
$res = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($res);

if (!$data) die("Data laporan konferensi kasus tidak ditemukan.");

// Get Headmaster & Counselor info from settings
$res_set = mysqli_query($conn, "SELECT * FROM pengaturan");
$sets = [];
if ($res_set) {
    while ($r = mysqli_fetch_assoc($res_set)) {
        $key = $r['nama_setting'] ?? ($r['kunci'] ?? '');
        $val = $r['nilai_setting'] ?? ($r['nilai'] ?? '');
        if ($key) $sets[$key] = $val;
    }
}
$nama_sekolah = $sets['nama_sekolah'] ?? 'SEKOLAH MENENGAH KEJURUAN NEGERI 2 BONDOWOSO';
$alamat_sekolah = $sets['alamat_sekolah'] ?? 'Jalan Jend. Ahmad Yani No. 48 Telp/Fax. (0332) 421286';
$email_sekolah = $sets['email_sekolah'] ?? 'smknbws2@yahoo.com';
$web_sekolah = $sets['web_sekolah'] ?? 'www.smkn2bondowoso.sch.id';

$nama_ks = $sets['nama_kepsek'] ?? 'Sofyan Sauri, S.Pd., M.Pd.';
$nip_ks = $sets['nip_kepsek'] ?? '19779725 200701 1 017';

$kop_html = $sets['kop_sekolah_html'] ?? '';
$kop_font_family = $sets['kop_font_family'] ?? 'Times New Roman';
$kop_font_size = $sets['kop_font_size'] ?? '12';
$logo_kiri = $sets['logo_kiri'] ?? '';
$logo_kanan = $sets['logo_kanan'] ?? '';

$nama_guru = !empty($data['nama_user_guru']) ? $data['nama_user_guru'] : 'Guru BK / Konselor';
$nip_guru = !empty($data['nip_guru']) ? $data['nip_guru'] : '-';

// Date formatting
$days = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$time_stamp = strtotime($data['tanggal']);
$day_name = $days[date('l', $time_stamp)] ?? '';
$formatted_hari_tanggal = $day_name . '/ ' . date('j', $time_stamp) . ' ' . $months[(int)date('n', $time_stamp)] . ' ' . date('Y', $time_stamp);
$formatted_date_short = date('j', $time_stamp) . ' ' . $months[(int)date('n', $time_stamp)] . ' ' . date('Y', $time_stamp);

$m_num = (int)date('n', $time_stamp);
$semester = ($m_num >= 7) ? 'GANJIL' : 'GENAP';
$tahun_pelajaran = ($m_num >= 7) ? date('Y', $time_stamp) . '/' . (date('Y', $time_stamp) + 1) : (date('Y', $time_stamp) - 1) . '/' . date('Y', $time_stamp);

$konseli_display = ($samarkan == 1 && !empty($data['kode_konseli'])) ? $data['kode_konseli'] . '/ konseli' : $data['nama_siswa'];
$kelas_display = !empty($data['nama_kelas']) ? $data['nama_kelas'] : '-';
$waktu_display = !empty($data['waktu']) ? $data['waktu'] : '60 Menit';
$pihak_display = !empty($data['pihak_dilibatkan']) ? $data['pihak_dilibatkan'] : '-';
$deskripsi_display = !empty($data['deskripsi_kasus']) ? $data['deskripsi_kasus'] : '-';
$urun_pendapat_display = !empty($data['urun_pendapat']) ? $data['urun_pendapat'] : '-';
$keputusan_display = !empty($data['keputusan_kasus']) ? $data['keputusan_kasus'] : (!empty($data['ringkasan_pembahasan']) ? $data['ringkasan_pembahasan'] : '-');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rencana Pelaksanaan Konferensi Kasus - <?= htmlspecialchars($konseli_display) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: '<?= htmlspecialchars($kop_font_family) ?>', 'Times New Roman', Times, serif; font-size: 11pt; background-color: #ffffff; color: #000000; line-height: 1.4; }
        table.border-table, table.border-table th, table.border-table td {
            border: 1px solid #000000;
            border-collapse: collapse;
        }
        table.border-table td {
            padding: 6px 10px;
            vertical-align: top;
        }
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
            <span class="text-xs font-bold text-slate-700 block">Rencana Pelaksanaan Konferensi Kasus</span>
            <span class="text-[11px] text-slate-500 font-mono">Dokumen Cetak Format Resmi BK</span>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center gap-2">
                <i class="fa fa-print"></i> Cetak Dokumen
            </button>
            <button onclick="window.close()" class="px-4 py-2.5 bg-slate-200 text-slate-700 font-bold text-xs rounded-xl hover:bg-slate-300">
                Tutup
            </button>
        </div>
    </div>

    <!-- HALAMAN DOKUMEN -->
    <div class="space-y-4">
        <!-- Kop Surat -->
        <?php if (!empty($kop_html)): ?>
            <div class="mb-4">
                <?= $kop_html ?>
            </div>
        <?php else: ?>
            <div class="border-b-4 border-black pb-3 mb-4 flex items-center justify-between gap-4">
                <?php if (!empty($logo_kiri)): ?>
                    <div class="w-20 flex-shrink-0 text-center">
                        <img src="<?= BASE_URL . ltrim($logo_kiri, '/') ?>" alt="Logo Kiri" class="max-h-20 max-w-full mx-auto object-contain">
                    </div>
                <?php endif; ?>
                <div class="flex-1 text-center">
                    <p class="font-bold text-base tracking-wide uppercase">PEMERINTAH PROVINSI JAWA TIMUR</p>
                    <p class="font-bold text-base tracking-wide uppercase">DINAS PENDIDIKAN</p>
                    <p class="font-bold text-lg tracking-wider uppercase"><?= htmlspecialchars($nama_sekolah) ?></p>
                    <p class="text-xs mt-0.5"><?= htmlspecialchars($alamat_sekolah) ?></p>
                    <p class="text-xs">Email: <?= htmlspecialchars($email_sekolah) ?> Website: <?= htmlspecialchars($web_sekolah) ?></p>
                    <div class="flex justify-between items-center text-xs font-bold mt-1 px-4">
                        <span class="uppercase"><?= htmlspecialchars($sets['kota_sekolah'] ?? 'BONDOWOSO') ?></span>
                        <span><?= htmlspecialchars($sets['kode_pos'] ?? '68214') ?></span>
                    </div>
                </div>
                <?php if (!empty($logo_kanan)): ?>
                    <div class="w-20 flex-shrink-0 text-center">
                        <img src="<?= BASE_URL . ltrim($logo_kanan, '/') ?>" alt="Logo Kanan" class="max-h-20 max-w-full mx-auto object-contain">
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Title Header -->
        <div class="text-center font-bold mb-6">
            <h2 class="text-base tracking-wide uppercase">RENCANA PELAKSANAAN KONFERENSI KASUS</h2>
            <h3 class="text-sm tracking-wide uppercase">SEMESTER <?= $semester ?> TAHUN PELAJARAN <?= $tahun_pelajaran ?></h3>
        </div>

        <!-- Official Format Table (1-7) -->
        <table class="w-full border-table text-xs sm:text-sm">
            <tbody>
                <tr>
                    <td class="w-10 text-center font-medium">1</td>
                    <td class="w-64 font-medium">Nama peserta didik/konseli</td>
                    <td class="font-medium"><?= htmlspecialchars($konseli_display) ?></td>
                </tr>
                <tr>
                    <td class="text-center font-medium">2</td>
                    <td class="font-medium">Kelas</td>
                    <td><?= htmlspecialchars($kelas_display) ?></td>
                </tr>
                <tr>
                    <td class="text-center font-medium">3</td>
                    <td class="font-medium">Hari/Tanggal</td>
                    <td><?= htmlspecialchars($formatted_hari_tanggal) ?></td>
                </tr>
                <tr>
                    <td class="text-center font-medium">4</td>
                    <td class="font-medium">Waktu</td>
                    <td><?= htmlspecialchars($waktu_display) ?></td>
                </tr>
                <tr>
                    <td class="text-center font-medium">5</td>
                    <td class="font-medium">Deskripsi kasus</td>
                    <td class="whitespace-pre-line leading-relaxed"><?= htmlspecialchars($deskripsi_display) ?></td>
                </tr>
                <tr>
                    <td class="text-center font-medium">6</td>
                    <td class="font-medium">Pihak-pihak yang terlibat</td>
                    <td class="leading-relaxed"><?= htmlspecialchars($pihak_display) ?></td>
                </tr>
                <tr>
                    <td class="text-center font-medium">7</td>
                    <td class="font-medium">Langkah-langkah Pelaksanaan</td>
                    <td></td>
                </tr>
                <tr>
                    <td></td>
                    <td class="pl-6 font-medium">a. Deskripsi Kasus</td>
                    <td class="whitespace-pre-line leading-relaxed"><?= htmlspecialchars($deskripsi_display) ?></td>
                </tr>
                <tr>
                    <td></td>
                    <td class="pl-6 font-medium">b. Urun pendapat/tanggapan</td>
                    <td class="whitespace-pre-line leading-relaxed"><?= htmlspecialchars($urun_pendapat_display) ?></td>
                </tr>
                <tr>
                    <td></td>
                    <td class="pl-6 font-medium">c. Pengambilan keputusan</td>
                    <td class="whitespace-pre-line leading-relaxed"><?= htmlspecialchars($keputusan_display) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Signature Block -->
        <div class="mt-12 pt-4 flex justify-between items-start text-xs leading-normal">
            <!-- Left: Headmaster / Plh. Kepala Sekolah -->
            <div class="w-5/12 text-left flex flex-col justify-between h-36">
                <div>
                    Mengetahui :<br><br>
                    Plh. Kepala Sekolah,
                </div>
                <div>
                    <div class="font-bold underline uppercase"><?= htmlspecialchars($nama_ks) ?></div>
                    <div>NIP. <?= htmlspecialchars($nip_ks) ?></div>
                </div>
            </div>

            <!-- Right: Counselor / Guru BK -->
            <div class="w-5/12 text-left flex flex-col justify-between h-36 pl-8">
                <div>
                    <?= htmlspecialchars($sets['kota_sekolah'] ?? 'Bondowoso') ?>, <?= $formatted_date_short ?><br><br>
                    Guru BK/ Konselor
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
