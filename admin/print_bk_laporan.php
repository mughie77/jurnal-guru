<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka', 'guru', 'guru_bk']);

$id = (int)($_GET['id'] ?? 0);
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

// Fetch school settings
$res_set = mysqli_query($conn, "SELECT nama_setting, nilai_setting FROM pengaturan");
$sets = [];
while ($r = mysqli_fetch_assoc($res_set)) {
    $sets[$r['nama_setting']] = $r['nilai_setting'];
}
$nama_sekolah = $sets['nama_sekolah'] ?? 'SMK NEGERI 2 BONDOWOSO';
$alamat_sekolah = $sets['alamat_sekolah'] ?? 'Jl. Nusantara No. 1, Bondowoso';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Bimbingan Konseling - <?= htmlspecialchars($data['nama_siswa']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #ffffff; color: #1e293b; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; }
        }
    </style>
</head>
<body class="p-8 max-w-4xl mx-auto">

    <!-- Action Bar -->
    <div class="no-print flex items-center justify-between mb-8 p-4 bg-slate-100 rounded-2xl">
        <span class="text-xs font-bold text-slate-600">Dokumen Laporan Resmi Bimbingan Konseling</span>
        <button onclick="window.print()" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Cetak Dokumen
        </button>
    </div>

    <!-- Kop Surat -->
    <div class="border-b-4 border-slate-900 pb-4 mb-6 text-center">
        <h2 class="text-xl font-black uppercase tracking-wider text-slate-900"><?= htmlspecialchars($nama_sekolah) ?></h2>
        <p class="text-xs text-slate-600 font-medium tracking-wide mt-1">LAYANAN BIMBINGAN DAN KONSELING (BK)</p>
        <p class="text-[10px] text-slate-500 italic mt-0.5"><?= htmlspecialchars($alamat_sekolah) ?></p>
    </div>

    <div class="text-center mb-6">
        <h1 class="text-lg font-black uppercase tracking-wider underline">LAPORAN HASIL BIMBINGAN KONSELING</h1>
        <p class="text-xs text-slate-500 font-bold mt-1">Jenis Bimbingan: Konseling <?= htmlspecialchars($data['jenis_bimbingan']) ?></p>
    </div>

    <!-- Identitas Siswa & Jadwal -->
    <div class="p-4 border border-slate-300 rounded-xl mb-6 bg-slate-50/50 space-y-2 text-xs">
        <div class="grid grid-cols-2 gap-4">
            <div><strong class="text-slate-500 uppercase text-[9px]">Nama Siswa:</strong> <span class="font-bold block text-sm text-slate-800"><?= htmlspecialchars($data['nama_siswa']) ?></span></div>
            <div><strong class="text-slate-500 uppercase text-[9px]">Kelas:</strong> <span class="font-bold block text-sm text-slate-800"><?= htmlspecialchars($data['nama_kelas'] ?? '-') ?></span></div>
            <div><strong class="text-slate-500 uppercase text-[9px]">NIS / NISN:</strong> <span class="font-mono block text-slate-700"><?= htmlspecialchars($data['nis']) ?> / <?= htmlspecialchars($data['nisn'] ?? '-') ?></span></div>
            <div><strong class="text-slate-500 uppercase text-[9px]">Tanggal Pelaksanaan:</strong> <span class="font-bold block text-slate-800"><?= date('d F Y', strtotime($data['tanggal'])) ?></span></div>
        </div>
    </div>

    <!-- Detail Laporan -->
    <div class="space-y-5 text-xs">
        <div class="border border-slate-200 p-4 rounded-xl">
            <h3 class="font-bold uppercase tracking-wider text-[10px] text-slate-500 mb-1.5">1. Topik / Permasalahan Utama</h3>
            <p class="font-bold text-slate-800 text-sm leading-relaxed"><?= htmlspecialchars($data['topik_permasalahan']) ?></p>
        </div>

        <div class="border border-slate-200 p-4 rounded-xl">
            <h3 class="font-bold uppercase tracking-wider text-[10px] text-slate-500 mb-1.5">2. Ringkasan Pembahasan & Hasil Konseling</h3>
            <p class="text-slate-700 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($data['ringkasan_pembahasan']) ?></p>
        </div>

        <div class="border border-slate-200 p-4 rounded-xl">
            <h3 class="font-bold uppercase tracking-wider text-[10px] text-slate-500 mb-1.5">3. Tindak Lanjut / Rekomendasi Konselor</h3>
            <p class="text-slate-700 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($data['tindak_lanjut'] ?: 'Pemantauan perkembangan berkala oleh Guru BK dan Wali Kelas.') ?></p>
        </div>

        <div class="p-3 bg-slate-100 rounded-xl flex items-center justify-between font-bold text-xs">
            <span>STATUS AKHIR KONSELING:</span>
            <span class="px-3 py-1 rounded-full uppercase tracking-wider text-[10px] bg-indigo-600 text-white"><?= htmlspecialchars($data['status']) ?></span>
        </div>
    </div>

    <!-- Tanda Tangan -->
    <div class="mt-12 pt-6 grid grid-cols-2 text-center text-xs gap-8">
        <div>
            <p class="mb-16">Siswa Terkait,</p>
            <p class="font-bold underline uppercase"><?= htmlspecialchars($data['nama_siswa']) ?></p>
            <p class="text-[10px] text-slate-500 font-mono">NIS: <?= htmlspecialchars($data['nis']) ?></p>
        </div>
        <div>
            <p class="mb-16">Guru Bimbingan Konseling / Konselor,</p>
            <p class="font-bold underline uppercase"><?= htmlspecialchars($data['nama_guru'] ?? 'Konselor BK') ?></p>
            <p class="text-[10px] text-slate-500 font-mono">NIP: <?= htmlspecialchars($data['nip_guru'] ?? '-') ?></p>
        </div>
    </div>

</body>
</html>
