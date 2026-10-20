<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka', 'guru', 'guru_bk']);

$id = (int)($_GET['id'] ?? 0);
$samarkan = (int)($_GET['samarkan'] ?? 0);
$nip_type = isset($_GET['nip_type']) && in_array(strtoupper($_GET['nip_type']), ['NIP', 'NIPPPK']) ? strtoupper($_GET['nip_type']) : 'NIPPPK';

if ($id <= 0) die("ID Laporan Kunjungan Rumah tidak valid.");

// Fetch report
$query = "SELECT bl.*, s.nama_siswa, s.nis, s.alamat, k.nama_kelas, g.nip as nip_guru, u.nama_lengkap as nama_user_guru
          FROM bk_laporan bl
          LEFT JOIN siswa s ON bl.siswa_id = s.id
          LEFT JOIN kelas k ON bl.kelas_id = k.id
          LEFT JOIN guru g ON bl.guru_id = g.id
          LEFT JOIN users u ON g.user_id = u.id
          WHERE bl.id = $id AND bl.jenis_bimbingan = 'Kunjungan Rumah' LIMIT 1";
$res = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($res);

if (!$data) die("Data laporan kunjungan rumah tidak ditemukan.");

// Get Headmaster & Counselor info from settings
$res_set = mysqli_query($conn, "SELECT * FROM pengaturan");
$sets = [];
while ($r = mysqli_fetch_assoc($res_set)) {
    $key = $r['nama_setting'] ?? ($r['kunci'] ?? '');
    $val = $r['nilai_setting'] ?? ($r['nilai'] ?? '');
    if ($key) $sets[$key] = $val;
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

$nama_guru = !empty($data['nama_user_guru']) ? $data['nama_user_guru'] : 'Guru BK';
$nip_guru = !empty($data['nip_guru']) ? $data['nip_guru'] : '-';

// Date formatting
$days = ['Sunday' => 'Senin', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$time_stamp = strtotime($data['tanggal']);
$day_name = $days[date('l', $time_stamp)] ?? '';
$formatted_date = $day_name . ', ' . date('j', $time_stamp) . ' ' . $months[(int)date('n', $time_stamp)] . ' ' . date('Y', $time_stamp);
$formatted_date_short = date('j', $time_stamp) . ' ' . $months[(int)date('n', $time_stamp)] . ' ' . date('Y', $time_stamp);

$m_num = (int)date('n', $time_stamp);
$semester = ($m_num >= 7) ? 'GANJIL' : 'GENAP';
$tahun_pelajaran = ($m_num >= 7) ? date('Y', $time_stamp) . '/' . (date('Y', $time_stamp) + 1) : (date('Y', $time_stamp) - 1) . '/' . date('Y', $time_stamp);

$konseli_display = ($samarkan == 1 && !empty($data['kode_konseli'])) ? $data['kode_konseli'] : $data['nama_siswa'];
$kelas_display = (!empty($data['nama_kelas']) ? $data['nama_kelas'] : '-') . ' / ' . ucfirst(strtolower($semester));
$alamat_display = !empty($data['alamat_kunjungan']) ? $data['alamat_kunjungan'] : (!empty($data['alamat']) ? $data['alamat'] : '-');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kunjungan Rumah - <?= htmlspecialchars($konseli_display) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: '<?= htmlspecialchars($kop_font_family) ?>', 'Times New Roman', Times, serif; font-size: 11pt; background-color: #ffffff; color: #000000; line-height: 1.4; }
        table.border-table, table.border-table th, table.border-table td {
            border: 1px solid #000000;
            border-collapse: collapse;
        }
        table.border-table td {
            padding: 5px 8px;
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
            <span class="text-xs font-bold text-slate-700 block">Laporan Kunjungan Rumah (Home Visit)</span>
            <span class="text-[11px] text-slate-500 font-mono">Dokumen Cetak Format Resmi BK</span>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center gap-2">
                <i class="fa fa-print"></i> Cetak Laporan
            </button>
            <button onclick="window.close()" class="px-4 py-2.5 bg-slate-200 text-slate-700 font-bold text-xs rounded-xl hover:bg-slate-300">
                Tutup
            </button>
        </div>
    </div>

    <!-- HALAMAN LAPORAN -->
    <div class="space-y-4">
        <!-- Dynamic Kop Surat -->
        <div class="border-b-4 border-black pb-3 mb-4 flex items-center justify-between gap-4">
            <?php if (!empty($logo_kiri)): ?>
                <div class="w-20 flex-shrink-0 text-center">
                    <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($logo_kiri) ?>" class="max-h-20 max-w-full inline-block">
                </div>
            <?php endif; ?>

            <div class="flex-1 text-center">
                <?php if (!empty($kop_html)): ?>
                    <?= $kop_html ?>
                <?php else: ?>
                    <h3 class="text-sm font-bold uppercase tracking-wide">PEMERINTAH PROVINSI JAWA TIMUR</h3>
                    <h3 class="text-sm font-bold uppercase tracking-wide">DINAS PENDIDIKAN</h3>
                    <h2 class="text-base font-bold uppercase tracking-wide"><?= htmlspecialchars($nama_sekolah) ?></h2>
                    <p class="text-xs font-normal"><?= htmlspecialchars($alamat_sekolah) ?></p>
                    <p class="text-[11px] italic">Email: <?= htmlspecialchars($email_sekolah) ?> Website: <?= htmlspecialchars($web_sekolah) ?></p>
                <?php endif; ?>
            </div>

            <?php if (!empty($logo_kanan)): ?>
                <div class="w-20 flex-shrink-0 text-center">
                    <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($logo_kanan) ?>" class="max-h-20 max-w-full inline-block">
                </div>
            <?php endif; ?>
        </div>

        <!-- Judul Document -->
        <div class="text-center my-4 space-y-1">
            <h2 class="text-base font-bold uppercase tracking-wider">LAPORAN KUNJUNGAN RUMAH</h2>
            <p class="text-xs font-bold uppercase">SEMESTER <?= $semester ?> TAHUN PELAJARAN <?= $tahun_pelajaran ?></p>
        </div>

        <!-- 14 Rows Table -->
        <table class="w-full border-table text-xs mb-8">
            <tbody>
                <tr>
                    <td class="w-10 text-center font-bold">1</td>
                    <td class="w-1/3 font-bold">Nama peserta didik/konseli</td>
                    <td class="font-bold"><?= htmlspecialchars($konseli_display) ?></td>
                </tr>
                <tr>
                    <td class="text-center font-bold">2</td>
                    <td class="font-bold">Kelas/Semester</td>
                    <td><?= htmlspecialchars($kelas_display) ?></td>
                </tr>
                <tr>
                    <td class="text-center font-bold">3</td>
                    <td class="font-bold">Bidang Layanan</td>
                    <td><?= htmlspecialchars($data['bidang_layanan'] ?: 'Pribadi / Sosial') ?></td>
                </tr>
                <tr>
                    <td class="text-center font-bold">4</td>
                    <td class="font-bold">Topik / Permasalahan</td>
                    <td><?= htmlspecialchars($data['topik_permasalahan']) ?></td>
                </tr>
                <tr>
                    <td class="text-center font-bold">5</td>
                    <td class="font-bold">Fungsi layanan</td>
                    <td><?= htmlspecialchars($data['fungsi_layanan'] ?: 'Pengentasan') ?></td>
                </tr>
                <tr>
                    <td class="text-center font-bold">6</td>
                    <td class="font-bold">Pihak yang Terlibat</td>
                    <td><?= htmlspecialchars($data['pihak_dilibatkan'] ?: 'Wali Kelas') ?></td>
                </tr>
                <tr>
                    <td class="text-center font-bold">7</td>
                    <td class="font-bold">Tujuan Kegiatan</td>
                    <td><?= nl2br(htmlspecialchars($data['tujuan_umum'] ?: ($data['tujuan_konseling'] ?: 'Siswa bisa kembali masuk sekolah seperti semula'))) ?></td>
                </tr>
                <tr>
                    <td class="text-center font-bold">8</td>
                    <td class="font-bold">Gambaran ringkas masalah</td>
                    <td class="leading-relaxed"><?= nl2br(htmlspecialchars($data['ringkasan_pembahasan'] ?: ($data['deskripsi_kasus'] ?: '-'))) ?></td>
                </tr>
                <tr>
                    <td class="text-center font-bold">9</td>
                    <td class="font-bold">Alamat Kunjungan</td>
                    <td><?= htmlspecialchars($alamat_display) ?></td>
                </tr>
                <tr>
                    <td class="text-center font-bold">10</td>
                    <td class="font-bold">Hari /Tanggal dan lama kunjungan</td>
                    <td><?= htmlspecialchars($formatted_date) ?></td>
                </tr>
                <tr>
                    <td class="text-center font-bold">11</td>
                    <td class="font-bold">Anggota keluarga yang dikunjungi</td>
                    <td><?= htmlspecialchars($data['anggota_keluarga'] ?: 'Orang Tua') ?></td>
                </tr>
                <tr>
                    <td class="text-center font-bold">12</td>
                    <td class="font-bold">Rencana Evaluasi</td>
                    <td><?= htmlspecialchars($data['evaluasi_proses'] ?: 'Pemantauan secara berkala') ?></td>
                </tr>
                <tr>
                    <td class="text-center font-bold">13</td>
                    <td class="font-bold">Tindak lanjut</td>
                    <td><?= htmlspecialchars($data['tindak_lanjut'] ?: 'Konseling Pribadi') ?></td>
                </tr>
                <tr>
                    <td class="text-center font-bold">14</td>
                    <td class="font-bold">Catatan Khusus</td>
                    <td><?= htmlspecialchars($data['catatan_khusus'] ?: '-') ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Signature Block -->
        <div class="mt-12 pt-4 flex justify-between items-start text-xs leading-normal">
            <!-- Left: Headmaster -->
            <div class="w-5/12 text-center flex flex-col justify-between h-36">
                <div>
                    Mengetahui<br>
                    Kepala <?= htmlspecialchars($nama_sekolah) ?>
                </div>
                <div>
                    <div class="font-bold underline uppercase"><?= htmlspecialchars($nama_ks) ?></div>
                    <div>NIP. <?= htmlspecialchars($nip_ks) ?></div>
                </div>
            </div>

            <!-- Right: Counselor -->
            <div class="w-5/12 text-center flex flex-col justify-between h-36">
                <div>
                    <?= htmlspecialchars($sets['kota_sekolah'] ?? 'Bondowoso') ?>, <?= $formatted_date_short ?><br><br>
                    Guru BK
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
