<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka', 'guru', 'guru_bk']);

$siswa_id = (int)($_GET['siswa_id'] ?? 0);
if ($siswa_id <= 0) die("ID Siswa tidak valid.");

$q_s = mysqli_query($conn, "SELECT s.*, k.nama_kelas
                            FROM siswa s
                            LEFT JOIN siswa_kelas sk ON (s.id = sk.siswa_id AND sk.tahun_pelajaran_id = $active_tahun_id)
                            LEFT JOIN kelas k ON sk.kelas_id = k.id
                            WHERE s.id = $siswa_id LIMIT 1");
$siswa = mysqli_fetch_assoc($q_s);
if (!$siswa) die("Data siswa tidak ditemukan.");

$res_ps = mysqli_query($conn, "SELECT ps.*, u.nama_lengkap as nama_guru
                               FROM bk_pelanggaran_siswa ps
                               LEFT JOIN guru g ON ps.guru_id = g.id
                               LEFT JOIN users u ON g.user_id = u.id
                               WHERE ps.siswa_id = $siswa_id
                               ORDER BY ps.tanggal DESC");
$records = [];
$tot_pelanggaran = 0;
$tot_apresiasi = 0;
while ($r = mysqli_fetch_assoc($res_ps)) {
    $records[] = $r;
    if ($r['tipe'] === 'Pelanggaran') {
        $tot_pelanggaran += (int)$r['poin'];
    } else {
        $tot_apresiasi += (int)$r['poin'];
    }
}
$net_poin = max(0, $tot_pelanggaran - $tot_apresiasi);

// SP Status
$status_sp = "AMAN (TANPA PERINGATAN)";
if ($net_poin >= 20 && $net_poin < 40) $status_sp = "PERINGATAN RINGAN";
elseif ($net_poin >= 40 && $net_poin < 60) $status_sp = "SURAT PERINGATAN 1 (SP 1)";
elseif ($net_poin >= 60 && $net_poin < 85) $status_sp = "SURAT PERINGATAN 2 (SP 2)";
elseif ($net_poin >= 85) $status_sp = "SURAT PERINGATAN 3 / RUJUKAN (SP 3)";

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
    <title>Kartu Poin Kedisiplinan Siswa - <?= htmlspecialchars($siswa['nama_siswa']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #ffffff; color: #1e293b; }
        @media print { .no-print { display: none !important; } body { padding: 0; margin: 0; } }
    </style>
</head>
<body class="p-8 max-w-4xl mx-auto">

    <div class="no-print flex items-center justify-between mb-8 p-4 bg-slate-100 rounded-2xl">
        <span class="text-xs font-bold text-slate-600">Kartu Catatan Poin Kedisiplinan Resmi</span>
        <button onclick="window.print()" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center gap-2">
            Cetak Kartu Poin
        </button>
    </div>

    <!-- Kop Surat -->
    <div class="border-b-4 border-slate-900 pb-4 mb-6 text-center">
        <h2 class="text-xl font-black uppercase tracking-wider text-slate-900"><?= htmlspecialchars($nama_sekolah) ?></h2>
        <p class="text-xs text-slate-600 font-bold uppercase tracking-widest mt-1">KARTU CATATAN POIN KEDISIPLINAN & APRESIASI SISWA</p>
    </div>

    <!-- Identitas Siswa -->
    <div class="p-4 border border-slate-300 rounded-xl mb-6 bg-slate-50/50 text-xs grid grid-cols-3 gap-4">
        <div><strong class="text-slate-500 uppercase text-[9px]">Nama Siswa:</strong> <span class="font-black block text-sm text-slate-800"><?= htmlspecialchars($siswa['nama_siswa']) ?></span></div>
        <div><strong class="text-slate-500 uppercase text-[9px]">NIS / NISN:</strong> <span class="font-mono block text-slate-800"><?= htmlspecialchars($siswa['nis']) ?> / <?= htmlspecialchars($siswa['nisn'] ?? '-') ?></span></div>
        <div><strong class="text-slate-500 uppercase text-[9px]">Kelas:</strong> <span class="font-bold block text-slate-800"><?= htmlspecialchars($siswa['nama_kelas'] ?? '-') ?></span></div>
    </div>

    <!-- Akumulasi Box -->
    <div class="grid grid-cols-4 gap-3 mb-6 text-center text-xs">
        <div class="p-3 border border-slate-200 rounded-xl">
            <span class="text-[9px] font-bold uppercase text-slate-400">Total Pelanggaran</span>
            <span class="font-black text-rose-600 block text-lg mt-0.5">+<?= $tot_pelanggaran ?></span>
        </div>
        <div class="p-3 border border-slate-200 rounded-xl">
            <span class="text-[9px] font-bold uppercase text-slate-400">Total Apresiasi</span>
            <span class="font-black text-emerald-600 block text-lg mt-0.5">-<?= $tot_apresiasi ?></span>
        </div>
        <div class="p-3 border border-slate-200 rounded-xl bg-slate-900 text-white">
            <span class="text-[9px] font-bold uppercase text-slate-300">Sisa Net Poin</span>
            <span class="font-black block text-lg mt-0.5"><?= $net_poin ?> Poin</span>
        </div>
        <div class="p-3 border border-slate-200 rounded-xl bg-indigo-50">
            <span class="text-[9px] font-bold uppercase text-indigo-400">Status Kedisiplinan</span>
            <span class="font-black text-indigo-700 block text-xs mt-1 uppercase"><?= $status_sp ?></span>
        </div>
    </div>

    <!-- Table -->
    <div class="mb-8">
        <h3 class="font-bold text-slate-800 text-xs uppercase tracking-wider mb-2">Riwayat Catatan Kejadian</h3>
        <table class="w-full text-left border-collapse text-xs border border-slate-300">
            <thead>
                <tr class="bg-slate-100 font-bold border-b border-slate-300 text-slate-700">
                    <th class="p-2 border border-slate-300">Tanggal</th>
                    <th class="p-2 border border-slate-300">Tipe</th>
                    <th class="p-2 border border-slate-300">Nama Kejadian / Pelanggaran</th>
                    <th class="p-2 border border-slate-300 text-center">Poin</th>
                    <th class="p-2 border border-slate-300">Pencatat</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                <tr>
                    <td colspan="5" class="p-6 text-center text-slate-400 italic">Siswa belum memiliki catatan pelanggaran.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($records as $r): ?>
                    <tr class="border-b border-slate-200">
                        <td class="p-2 border border-slate-300 font-bold"><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
                        <td class="p-2 border border-slate-300 font-bold <?= $r['tipe'] === 'Apresiasi' ? 'text-emerald-600' : 'text-rose-600' ?>"><?= $r['tipe'] ?></td>
                        <td class="p-2 border border-slate-300">
                            <span class="font-bold block"><?= htmlspecialchars($r['nama_kejadian']) ?></span>
                            <?php if (!empty($r['catatan'])): ?>
                                <span class="text-[10px] text-slate-500 italic block"><?= htmlspecialchars($r['catatan']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="p-2 border border-slate-300 text-center font-black <?= $r['tipe'] === 'Apresiasi' ? 'text-emerald-600' : 'text-rose-600' ?>">
                            <?= $r['tipe'] === 'Apresiasi' ? '-' : '+' ?><?= $r['poin'] ?>
                        </td>
                        <td class="p-2 border border-slate-300 text-slate-600"><?= htmlspecialchars($r['nama_guru'] ?? 'Admin') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>
