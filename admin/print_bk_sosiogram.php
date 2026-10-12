<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka', 'guru', 'guru_bk']);

$sesi_id = (int)($_GET['sesi_id'] ?? 0);
if ($sesi_id <= 0) die("ID Sesi Sosiogram tidak valid.");

$q_sesi = mysqli_query($conn, "SELECT ss.*, k.nama_kelas FROM bk_sosiogram_sesi ss JOIN kelas k ON ss.kelas_id = k.id WHERE ss.id = $sesi_id LIMIT 1");
$sesi = mysqli_fetch_assoc($q_sesi);
if (!$sesi) die("Data sesi sosiogram tidak ditemukan.");

$kid = (int)$sesi['kelas_id'];
$class_students = [];
$incoming_votes = [];
$choices_matrix = [];

$res_st = mysqli_query($conn, "SELECT s.id, s.nama_siswa, s.nis
                               FROM siswa s
                               JOIN siswa_kelas sk ON s.id = sk.siswa_id
                               WHERE sk.kelas_id = $kid AND sk.tahun_pelajaran_id = $active_tahun_id
                               ORDER BY s.nama_siswa ASC");
while ($st = mysqli_fetch_assoc($res_st)) {
    $class_students[$st['id']] = $st;
    $incoming_votes[$st['id']] = 0;
}

$res_ch = mysqli_query($conn, "SELECT * FROM bk_sosiogram_pilihan WHERE sesi_id = $sesi_id ORDER BY urutan ASC");
while ($ch = mysqli_fetch_assoc($res_ch)) {
    $pem = (int)$ch['pemilih_siswa_id'];
    $dip = (int)$ch['dipilih_siswa_id'];
    $choices_matrix[$pem][$dip] = $ch['urutan'];
    $incoming_votes[$dip] = ($incoming_votes[$dip] ?? 0) + 1;
}

// Fetch school settings
$res_set = mysqli_query($conn, "SELECT nama_setting, nilai_setting FROM pengaturan");
$sets = [];
while ($r = mysqli_fetch_assoc($res_set)) {
    $sets[$r['nama_setting']] = $r['nilai_setting'];
}
$nama_sekolah = $sets['nama_sekolah'] ?? 'SMK NEGERI 2 BONDOWOSO';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sosiogram Kelas <?= htmlspecialchars($sesi['nama_kelas']) ?> - <?= htmlspecialchars($sesi['judul']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #ffffff; color: #1e293b; }
        @media print { .no-print { display: none !important; } body { padding: 0; margin: 0; } }
    </style>
</head>
<body class="p-8 max-w-5xl mx-auto">

    <div class="no-print flex items-center justify-between mb-8 p-4 bg-slate-100 rounded-2xl">
        <span class="text-xs font-bold text-slate-600">Dokumen Cetak Hasil Sosiogram Kelas</span>
        <button onclick="window.print()" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center gap-2">
            Cetak Sosiogram
        </button>
    </div>

    <div class="border-b-4 border-slate-900 pb-4 mb-6 text-center">
        <h2 class="text-xl font-black uppercase tracking-wider text-slate-900"><?= htmlspecialchars($nama_sekolah) ?></h2>
        <p class="text-xs text-slate-600 font-bold uppercase tracking-widest mt-1">LAPORAN ANALISIS SOSIOGRAM KELAS <?= htmlspecialchars($sesi['nama_kelas']) ?></p>
        <p class="text-xs text-indigo-700 font-extrabold italic mt-0.5">Topik: <?= htmlspecialchars($sesi['judul']) ?> (<?= date('d F Y', strtotime($sesi['tanggal'])) ?>)</p>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-3 gap-4 mb-6 text-xs">
        <div class="p-3 border border-slate-200 rounded-xl">
            <span class="text-slate-400 font-bold block uppercase text-[9px]">Total Siswa</span>
            <span class="text-base font-black text-slate-800"><?= count($class_students) ?> Siswa</span>
        </div>
        <div class="p-3 border border-slate-200 rounded-xl">
            <span class="text-slate-400 font-bold block uppercase text-[9px]">Siswa Partisipan</span>
            <span class="text-base font-black text-indigo-600"><?= count($choices_matrix) ?> Siswa</span>
        </div>
        <div class="p-3 border border-slate-200 rounded-xl">
            <span class="text-slate-400 font-bold block uppercase text-[9px]">Nilai Tertinggi Dipilih</span>
            <span class="text-base font-black text-emerald-600"><?= !empty($incoming_votes) ? max($incoming_votes) : 0 ?> Suara</span>
        </div>
    </div>

    <!-- Matriks Pilihan -->
    <div class="mb-8">
        <h3 class="font-bold text-slate-800 text-sm mb-2 uppercase tracking-wider">Matriks Hubungan Sosiometri</h3>
        <table class="w-full text-center border-collapse text-xs border border-slate-300">
            <thead>
                <tr class="bg-slate-100 font-bold">
                    <th class="p-2 border border-slate-300 text-left">Nama Siswa</th>
                    <?php foreach ($class_students as $st): ?>
                        <th class="p-1 border border-slate-300 text-[10px]" title="<?= htmlspecialchars($st['nama_siswa']) ?>">
                            <?= strtoupper(substr($st['nama_siswa'], 0, 3)) ?>
                        </th>
                    <?php endforeach; ?>
                    <th class="p-2 border border-slate-300 bg-indigo-50 font-black">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($class_students as $pem_id => $pem_st): ?>
                <tr class="border-b border-slate-200">
                    <td class="p-2 border border-slate-300 text-left font-bold"><?= htmlspecialchars($pem_st['nama_siswa']) ?></td>
                    <?php foreach ($class_students as $dip_id => $dip_st): ?>
                        <td class="p-1 border border-slate-300 font-bold <?= $pem_id === $dip_id ? 'bg-slate-200' : '' ?>">
                            <?= $pem_id === $dip_id ? '-' : ($choices_matrix[$pem_id][$dip_id] ?? '') ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="p-2 border border-slate-300 font-black bg-indigo-50/50"><?= $incoming_votes[$pem_id] ?? 0 ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</body>
</html>
