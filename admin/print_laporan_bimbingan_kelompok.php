<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka', 'guru', 'guru_bk']);

$id = (int)($_GET['id'] ?? 0);
$nip_type = isset($_GET['nip_type']) && in_array(strtoupper($_GET['nip_type']), ['NIP', 'NIPPPK']) ? strtoupper($_GET['nip_type']) : 'NIPPPK';

if ($id <= 0) die("ID Laporan BK Kelompok tidak valid.");

// Fetch report
$query = "SELECT bl.*, k.nama_kelas, g.nip as nip_guru, u.nama_lengkap as nama_user_guru
          FROM bk_laporan bl
          LEFT JOIN kelas k ON bl.kelas_id = k.id
          LEFT JOIN guru g ON bl.guru_id = g.id
          LEFT JOIN users u ON g.user_id = u.id
          WHERE bl.id = $id AND bl.jenis_bimbingan = 'Kelompok' LIMIT 1";
$res = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($res);

if (!$data) die("Data laporan bimbingan kelompok tidak ditemukan.");

// Parse JSON fields
$pelaksanaan = json_decode($data['pelaksanaan_json'] ?? '[]', true) ?: [];
$siswa_ids = json_decode($data['siswa_ids_json'] ?? '[]', true) ?: [];

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
$days = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$time_stamp = strtotime($data['tanggal']);
$day_name = $days[date('l', $time_stamp)] ?? '';
$formatted_date = $day_name . ', ' . date('j', $time_stamp) . ' ' . $months[(int)date('n', $time_stamp)] . ' ' . date('Y', $time_stamp);
$formatted_date_short = date('j', $time_stamp) . ' ' . $months[(int)date('n', $time_stamp)] . ' ' . date('Y', $time_stamp);

$m_num = (int)date('n', $time_stamp);
$semester = ($m_num >= 7) ? 'GANJIL' : 'GENAP';
$tahun_pelajaran = ($m_num >= 7) ? date('Y', $time_stamp) . '/' . (date('Y', $time_stamp) + 1) : (date('Y', $time_stamp) - 1) . '/' . date('Y', $time_stamp);

$kelas_display = !empty($data['nama_kelas']) ? 'Siswa Kelas ' . $data['nama_kelas'] : 'Siswa Lintas Kelas / Campuran';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Bimbingan Kelompok - <?= htmlspecialchars($data['topik_permasalahan']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: '<?= htmlspecialchars($kop_font_family) ?>', 'Times New Roman', Times, serif; font-size: <?= (int)$kop_font_size ?>pt; background-color: #ffffff; color: #000000; line-height: 1.4; }
        .page-break { page-break-before: always; }
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
            <span class="text-xs font-bold text-slate-700 block">Laporan Pelaksanaan Bimbingan Kelompok</span>
            <span class="text-[11px] text-slate-500 font-mono">Format Laporan Hasil / Evaluasi Pelaksanaan</span>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center gap-2">
                <i class="fa fa-print"></i> Cetak Laporan Bimkel
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
                    <h2 class="text-sm font-bold uppercase mt-1">BONDOWOSO</h2>
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
            <h2 class="text-base font-bold uppercase tracking-wider">LAPORAN PELAKSANAAN BIMBINGAN KELOMPOK</h2>
            <p class="text-xs font-bold uppercase">SEMESTER <?= $semester ?> TAHUN PELAJARAN <?= $tahun_pelajaran ?></p>
        </div>

        <!-- Parameters Grid -->
        <div class="text-xs space-y-2 pt-2">
            <div class="grid grid-cols-12">
                <span class="col-span-3 font-medium">Komponen Layanan</span>
                <span class="col-span-9">: <?= htmlspecialchars($data['kompetensi_layanan'] ?: 'Responsif') ?></span>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-3 font-medium">Bidang layanan</span>
                <span class="col-span-9">: <?= htmlspecialchars($data['bidang_layanan'] ?: 'Pribadi') ?></span>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-3 font-medium">Hari/ Tanggal</span>
                <span class="col-span-9">: <?= htmlspecialchars($formatted_date) ?></span>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-3 font-medium">Waktu</span>
                <span class="col-span-9">: <?= htmlspecialchars($data['waktu'] ?: '2 x 45 Menit') ?></span>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-3 font-medium">Kelas</span>
                <span class="col-span-9">: <?= htmlspecialchars($kelas_display) ?></span>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-3 font-medium">Tujuan</span>
                <div class="col-span-9 leading-relaxed">: <?= nl2br(htmlspecialchars($data['tujuan_umum'] ?: $data['topik_permasalahan'])) ?></div>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-3 font-medium">Guru BK</span>
                <div class="col-span-9">: 1. <?= htmlspecialchars($nama_guru) ?></div>
            </div>
        </div>

        <!-- Uraian Pelaksanaan -->
        <div class="pt-4 text-xs space-y-3">
            <h3 class="font-bold text-sm">Uraian Pelaksanaan</h3>

            <div class="space-y-1.5 ml-2">
                <p class="font-bold">1. Pembentukan</p>
                <div class="ml-4 space-y-1 leading-relaxed">
                    <?php if (!empty($pelaksanaan['pembentukan'])): ?>
                        <?= nl2br(htmlspecialchars($pelaksanaan['pembentukan'])) ?>
                    <?php else: ?>
                        <p>- <strong>Sambutan:</strong> Pemimpin kelompok menyambut anggota kelompok dan mengucapkan selamat datang serta ucapan terima kasih karena telah bersedia mengikuti kegiatan Bimbingan kelompok.</p>
                        <p>- <strong>Doa:</strong> Pemimpin kelompok mengajak anggota kelompok untuk berdoa bersama sebelum kegiatan dimulai.</p>
                        <p>- <strong>Pengertian Bimbingan Kelompok:</strong> Pemimpin kelompok menjelaskan pengertian Bimbingan kelompok dihadapan para anggota kelompok.</p>
                        <p>- <strong>Tujuan dan Azas Bimbingan kelompok:</strong> Pemimpin kelompok menjelaskan tujuan dan azas (kesukarelaan, keterbukaan, kemandirian, kenormatifan).</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="space-y-1.5 ml-2 pt-2">
                <p class="font-bold">2. Hasil yang diperoleh (Identifikasi) :</p>
                <div class="ml-4 space-y-1 leading-relaxed">
                    <?php if (!empty($data['evaluasi_hasil'])): ?>
                        <?= nl2br(htmlspecialchars($data['evaluasi_hasil'])) ?>
                    <?php else: ?>
                        <p>- Sebagian besar siswa antusias dengan aktivitas bimbingan kelompok.</p>
                        <p>- Permasalahan siswa didominasi oleh kebiasaan dan pola hidup sehari-hari.</p>
                        <p>- Siswa dapat memahami peran penting memperbaiki pola hidup sehat.</p>
                        <p>- Siswa dapat menghargai pendapat temannya dan pemimpin kelompok.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="space-y-1.5 ml-2 pt-2">
                <p class="font-bold">3. Kesimpulan yang didapat (Analisis)</p>
                <div class="ml-4 space-y-1 leading-relaxed">
                    <?php if (!empty($data['evaluasi_proses'])): ?>
                        <?= nl2br(htmlspecialchars($data['evaluasi_proses'])) ?>
                    <?php else: ?>
                        <p>- Siswa mampu memahami pentingnya merubah pola hidup untuk meningkatkan kualitas kesehatan.</p>
                        <p>- Siswa dapat memahami pentingnya Kerjasama dalam situasi kelompok dan belajar bertanggung jawab.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="space-y-1.5 ml-2 pt-2">
                <p class="font-bold">4. Tindak lanjut dari kegiatan ini (Generalisasi)</p>
                <div class="ml-4 space-y-1 leading-relaxed">
                    <?php if (!empty($data['tindak_lanjut'])): ?>
                        <?= nl2br(htmlspecialchars($data['tindak_lanjut'])) ?>
                    <?php else: ?>
                        <p>- Akan diadakan pemantauan siswa di sekolah terkait dengan kebiasaan/pola hidup.</p>
                        <p>- Siswa dipersilahkan jika ingin melanjutkan dengan sesi konseling pribadi.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Signature Block -->
        <div class="mt-12 pt-6 flex justify-between items-start text-xs leading-normal">
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
                    Bondowoso, <?= $formatted_date_short ?><br><br>
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
