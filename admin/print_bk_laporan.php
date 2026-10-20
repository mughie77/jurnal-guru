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
$display_nama_siswa = $samarkan ? (!empty($data['kode_konseli']) ? $data['kode_konseli'] : 'Siswa Anonim (' . $data['siswa_id'] . ')') : $data['nama_siswa'];

// Decode JSON fields with fallbacks
$langkah_kegiatan = [];
if (!empty($data['langkah_kegiatan_json'])) {
    $langkah_kegiatan = json_decode($data['langkah_kegiatan_json'], true);
}
if (!is_array($langkah_kegiatan) || empty($langkah_kegiatan)) {
    $langkah_kegiatan = [
        ['langkah' => 'Langkah 1 : Rasional strategi', 'isi' => "• Konselor memberikan rasional/menjelaskan maksud penggunaan strategi\n• Konselor memberikan overview tahapan-tahapan implementasi strategi"],
        ['langkah' => 'Langkah 2 : Identifikasi keadaan yang menimbulkan persoalan', 'isi' => "• Konselor meminta konseli menceritakan secara terbuka permasalahan yang dihadapi"],
        ['langkah' => 'Langkah 3 : Pemahaman penerapan pola hidup sehat', 'isi' => "• Konselor memberi pemahaman tentang bagaimana mengukur kondisi ideal\n• Penerapan pola hidup harus memenuhi kebutuhan gizi dan nutrisi"],
        ['langkah' => 'Langkah 4 : Bermain peran & umpan balik', 'isi' => "• Konseli bermain peran sesuai permasalahan\n• Konselor memberi umpan balik secara verbal & penguatan positif"],
        ['langkah' => 'Langkah 5 : Latihan dan praktik', 'isi' => "• Konseli mengisi instrumen evaluasi sesuai target perilaku"],
        ['langkah' => 'Langkah 6 : Mengulang latihan', 'isi' => "• Konseli mengulang latihan kembali tanpa bantuan pembimbing"],
        ['langkah' => 'Langkah 7 : Tugas rumah dan tindak lanjut', 'isi' => "• Konselor memberi tugas rumah pada konseli untuk mempraktikkan perilaku target"],
        ['langkah' => 'Langkah 8 : Terminasi', 'isi' => "• Konselor menghentikan program bantuan"]
    ];
}

$rencana_evaluasi = [];
if (!empty($data['rencana_evaluasi_json'])) {
    $rencana_evaluasi = json_decode($data['rencana_evaluasi_json'], true);
}
if (!is_array($rencana_evaluasi)) {
    $rencana_evaluasi = [
        'tujuan_evaluasi' => 'Untuk mengetahui sejauh mana konseli merubah pola hidup dalam kehidupan sehari-hari',
        'jenis_evaluasi' => [
            'proses' => 'yang dievaluasi antara lain performen konselor, jalannya proses konseling, media/alat yang digunakan, keaktifan/keterbukaan konseli, ruang konseling',
            'hasil' => 'hasil dari sesi pertemuan dicatat sebagai acuan untuk laporan'
        ],
        'teknik_instrumen' => 'Instrumen yang digunakan adalah Data hasil wawancara'
    ];
}

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
$formatted_date = $day_name . ', ' . date('j', $time_stamp) . ' ' . $months[(int)date('n', $time_stamp)] . ' ' . date('Y', $time_stamp);

$m_num = (int)date('n', $time_stamp);
$semester = ($m_num >= 7) ? 'GANJIL' : 'GENAP';
$tahun_pelajaran = ($m_num >= 7) ? date('Y', $time_stamp) . ' – ' . (date('Y', $time_stamp) + 1) : (date('Y', $time_stamp) - 1) . ' – ' . date('Y', $time_stamp);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPL Konseling Individual - <?= htmlspecialchars($display_nama_siswa) ?></title>
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
            <span class="text-xs font-bold text-slate-700 block">Dokumen RPL Konseling Individual</span>
            <span class="text-[11px] text-slate-500 font-mono">Status Penyamaran Nama: <strong><?= $samarkan ? 'AKTIF (Gunakan Kode)' : 'NONAKTIF (Nama Asli)' ?></strong></span>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>admin/print_bk_laporan.php?id=<?= $id ?>&samarkan=<?= $samarkan ? '0' : '1' ?>" class="px-3 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs rounded-xl transition-all">
                Ganti ke <?= $samarkan ? 'Nama Asli' : 'Kode Konseli' ?>
            </a>
            <button onclick="window.print()" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center gap-2">
                <i class="fa fa-print"></i> Cetak Dokumen RPL
            </button>
        </div>
    </div>

    <!-- HALAMAN 1: RENCANA PELAKSANAAN LAYANAN -->
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
            <h2 class="text-base font-bold uppercase tracking-wider">RENCANA PELAKSANAAN LAYANAN</h2>
            <h3 class="text-sm font-bold uppercase tracking-wider">KONSELING INDIVIDUAL</h3>
            <p class="text-xs font-bold uppercase">SEMESTER <?= $semester ?> TAHUN PELAJARAN <?= $tahun_pelajaran ?></p>
        </div>

        <!-- Tabel Identitas & Parameter (List Style) -->
        <div class="text-xs space-y-2.5 pt-2">
            <div class="grid grid-cols-12">
                <span class="col-span-4">1. Nama Konseli</span>
                <span class="col-span-8">: <?= htmlspecialchars($display_nama_siswa) ?></span>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-4">2. Kelas/ Semester</span>
                <span class="col-span-8">: <?= htmlspecialchars($data['nama_kelas'] ?? '-') ?></span>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-4">3. Hari, tanggal</span>
                <span class="col-span-8">: <?= htmlspecialchars($formatted_date) ?></span>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-4">4. Pertemuan ke</span>
                <span class="col-span-8">: <?= htmlspecialchars($data['pertemuan_ke'] ?: '1') ?></span>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-4">5. Waktu</span>
                <span class="col-span-8">: <?= htmlspecialchars($data['waktu'] ?: '45 menit') ?></span>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-4">6. Tempat</span>
                <span class="col-span-8">: <?= htmlspecialchars($data['tempat'] ?: 'Ruang BK') ?></span>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-4">7. Deskripsi kasus/gejala yang nampak keluhan</span>
                <div class="col-span-8 leading-relaxed">
                    : <?= nl2br(htmlspecialchars($data['deskripsi_kasus'] ?: $data['topik_permasalahan'])) ?>
                </div>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-4">8. Tujuan konseling</span>
                <div class="col-span-8 leading-relaxed">
                    : <?= nl2br(htmlspecialchars($data['tujuan_konseling'] ?: $data['ringkasan_pembahasan'])) ?>
                </div>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-4">9. Tahapan konseling</span>
                <div class="col-span-8 leading-relaxed">
                    : <?= nl2br(htmlspecialchars($data['tahapan_konseling'] ?: "1. Rasional strategi\n2. Identifikasi keadaan\n3. Memahami pentingnya penerapan pola hidup sehat\n4. Melaksanakan latihan/praktik\n5. Mengulang latihan\n6. Tugas rumah dan tindak lanjut\n7. Terminasi")) ?>
                </div>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-4">10. Jumlah pertemuan</span>
                <span class="col-span-8">: <?= htmlspecialchars($data['jumlah_pertemuan'] ?: '1 kali pertemuan') ?></span>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-4">11. Durasi per pertemuan</span>
                <span class="col-span-8">: <?= htmlspecialchars($data['durasi_pertemuan'] ?: '45 menit') ?></span>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-4">12. Media dan sumber bantuan</span>
                <span class="col-span-8">: <?= htmlspecialchars($data['media_sumber'] ?: '-') ?></span>
            </div>
            <div class="grid grid-cols-12">
                <span class="col-span-4">13. Pihak lain yang dilibatkan</span>
                <span class="col-span-8">: <?= htmlspecialchars($data['pihak_dilibatkan'] ?: 'Wali Kelas') ?></span>
            </div>
        </div>

        <!-- Tanda Tangan Guru BK -->
        <div class="mt-12 pt-4 flex justify-end text-xs">
            <div class="text-left w-64 space-y-16">
                <p>Guru BK,</p>
                <div>
                    <p class="font-bold underline uppercase"><?= htmlspecialchars($data['nama_guru'] ?? 'Laili Anggraini, S.Pd') ?></p>
                    <p class="text-xs">NIP/NIPPPK. <?= htmlspecialchars($data['nip_guru'] ?? '-') ?></p>
                </div>
            </div>
        </div>

        <div class="text-[11px] italic mt-8 pt-4 border-t border-slate-200">
            Keterangan :<br>
            Dokumen ini bersifat rahasia
        </div>
    </div>

    <!-- HALAMAN 2: LAMPIRAN -->
    <div class="page-break pt-6 space-y-4">
        <div class="text-center font-bold text-sm tracking-wider uppercase mb-6">
            LAMPIRAN
        </div>

        <div class="text-xs space-y-3">
            <div class="grid grid-cols-12">
                <span class="col-span-3 font-bold">1. Rumusan masalah</span>
                <span class="col-span-9">: <?= htmlspecialchars($data['rumusan_masalah'] ?: $data['topik_permasalahan']) ?></span>
            </div>

            <div class="grid grid-cols-12">
                <span class="col-span-3 font-bold">2. Masalah dan analisis</span>
                <div class="col-span-9 text-justify leading-relaxed">
                    : <?= nl2br(htmlspecialchars($data['masalah_analisis'] ?: $data['ringkasan_pembahasan'])) ?>
                </div>
            </div>

            <div class="grid grid-cols-12">
                <span class="col-span-3 font-bold">3. Pendekatan/ teknik pemecahan</span>
                <span class="col-span-9">: <?= htmlspecialchars($data['pendekatan_teknik'] ?: 'Teori Behavioral dengan teknik konseling Eksplorasi') ?></span>
            </div>

            <div>
                <span class="font-bold block mb-2">4. Tahap/ proses konseling :</span>
                <table class="w-full border-collapse border border-black text-xs">
                    <thead>
                        <tr class="bg-slate-100 border-b border-black">
                            <th class="border border-black p-2 text-center w-5/12 font-bold">Langkah</th>
                            <th class="border border-black p-2 text-center w-7/12 font-bold">Isi kegiatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($langkah_kegiatan as $lk): ?>
                        <tr class="align-top">
                            <td class="border border-black p-2.5 font-bold">
                                <?= htmlspecialchars($lk['langkah'] ?? '') ?>
                            </td>
                            <td class="border border-black p-2.5 leading-relaxed whitespace-pre-line">
                                <?= htmlspecialchars($lk['isi'] ?? '') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pt-4 space-y-2">
                <span class="font-bold block">5. Rencana evaluasi :</span>

                <div class="ml-4 space-y-2">
                    <div class="grid grid-cols-12">
                        <span class="col-span-4 font-bold">• Tujuan evaluasi</span>
                        <span class="col-span-8">: <?= htmlspecialchars($rencana_evaluasi['tujuan_evaluasi'] ?? '') ?></span>
                    </div>

                    <div>
                        <span class="font-bold block mb-1">• Jenis evaluasi :</span>
                        <div class="ml-4 space-y-1">
                            <div class="grid grid-cols-12">
                                <span class="col-span-4">Evaluasi proses</span>
                                <span class="col-span-8">: <?= htmlspecialchars($rencana_evaluasi['jenis_evaluasi']['proses'] ?? '') ?></span>
                            </div>
                            <div class="grid grid-cols-12">
                                <span class="col-span-4">Evaluasi hasil</span>
                                <span class="col-span-8">: <?= htmlspecialchars($rencana_evaluasi['jenis_evaluasi']['hasil'] ?? '') ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-12 pt-1">
                        <span class="col-span-4 font-bold">• Teknik dan instrumen</span>
                        <span class="col-span-8">: <?= htmlspecialchars($rencana_evaluasi['teknik_instrumen'] ?? '') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
