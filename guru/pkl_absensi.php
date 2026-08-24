<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
$guru_data = mysqli_fetch_assoc($guru_res);
$guru_id = $guru_data['id'] ?? 0;

$page_title = "Rekap Absensi PKL Siswa";

// Filters
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$filter_status = mysqli_real_escape_string($conn, $_GET['status'] ?? '');
$tgl_mulai = $_GET['tanggal_mulai'] ?? date('Y-m-01');
$tgl_selesai = $_GET['tanggal_selesai'] ?? date('Y-m-d');

$where_clauses = [
    "tp.guru_pembimbing_id = $guru_id",
    "sp.tahun_pelajaran_id = $active_tahun_id",
    "sp.status = 'aktif'"
];

if (!empty($search)) {
    $where_clauses[] = "(s.nama_siswa LIKE '%$search%' OR s.nis LIKE '%$search%' OR k.nama_kelas LIKE '%$search%' OR tp.nama_tempat LIKE '%$search%')";
}
if (!empty($filter_status) && in_array($filter_status, ['Hadir', 'Terlambat', 'Sakit', 'Izin', 'Alfa'])) {
    $where_clauses[] = "ah.status = '$filter_status'";
}
if (!empty($tgl_mulai)) {
    $where_clauses[] = "ah.tanggal >= '$tgl_mulai'";
}
if (!empty($tgl_selesai)) {
    $where_clauses[] = "ah.tanggal <= '$tgl_selesai'";
}

$where_sql = " WHERE " . implode(" AND ", $where_clauses);

$pagin = get_pagination_data($conn, "absensi_harian ah JOIN siswa s ON ah.siswa_id = s.id JOIN siswa_pkl sp ON s.id = sp.siswa_id JOIN tempat_pkl tp ON sp.tempat_pkl_id = tp.id JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = sp.tahun_pelajaran_id JOIN kelas k ON sk.kelas_id = k.id", 15, $where_sql);

$q_abs = "SELECT ah.*, s.nama_siswa, s.nis, k.nama_kelas, tp.nama_tempat
          FROM absensi_harian ah
          JOIN siswa s ON ah.siswa_id = s.id
          JOIN siswa_pkl sp ON s.id = sp.siswa_id
          JOIN tempat_pkl tp ON sp.tempat_pkl_id = tp.id
          JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = sp.tahun_pelajaran_id
          JOIN kelas k ON sk.kelas_id = k.id
          $where_sql
          ORDER BY ah.tanggal DESC, ah.waktu_masuk DESC
          LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";
$res_abs = mysqli_query($conn, $q_abs);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto pb-24">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Rekap Absensi PKL Siswa</h1>
            <p class="text-slate-500 font-medium">Monitoring kehadiran presensi lokasi GPS siswa bimbingan PKL Anda.</p>
        </div>
    </div>

    <div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cari Siswa / Tempat</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama, NIS, kelas, industri..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white text-xs font-semibold">
            </div>
            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Status Kehadiran</label>
                <select name="status" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white text-xs font-semibold">
                    <option value="">Semua Status</option>
                    <option value="Hadir" <?= $filter_status == 'Hadir' ? 'selected' : '' ?>>Hadir</option>
                    <option value="Terlambat" <?= $filter_status == 'Terlambat' ? 'selected' : '' ?>>Terlambat</option>
                    <option value="Sakit" <?= $filter_status == 'Sakit' ? 'selected' : '' ?>>Sakit</option>
                    <option value="Izin" <?= $filter_status == 'Izin' ? 'selected' : '' ?>>Izin</option>
                    <option value="Alfa" <?= $filter_status == 'Alfa' ? 'selected' : '' ?>>Alfa</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Periode Tanggal</label>
                <div class="flex items-center gap-2">
                    <input type="date" name="tanggal_mulai" value="<?= htmlspecialchars($tgl_mulai) ?>" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold">
                    <span class="text-slate-400 font-bold">-</span>
                    <input type="date" name="tanggal_selesai" value="<?= htmlspecialchars($tgl_selesai) ?>" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold">
                </div>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2.5 bg-indigo-600 text-white font-bold rounded-xl text-xs hover:bg-indigo-700 transition-all shadow-md shadow-indigo-100">Filter</button>
                <a href="pkl_absensi.php" class="px-4 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl text-xs hover:bg-slate-300 transition-all">Reset</a>
            </div>
        </form>
    </div>

    <div class="lux-card overflow-hidden bg-white shadow-2xl rounded-3xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Siswa & Kelas</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Tempat PKL</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Waktu Presensi</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Status</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Keterangan GPS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (mysqli_num_rows($res_abs) == 0): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-bold italic">Belum ada catatan presensi GPS untuk siswa bimbingan PKL Anda.</td>
                    </tr>
                    <?php else: ?>
                        <?php while ($a = mysqli_fetch_assoc($res_abs)): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($a['nama_siswa']) ?></div>
                                <div class="text-[10px] text-slate-400 font-bold">NIS: <?= htmlspecialchars($a['nis']) ?> | <span class="text-indigo-600"><?= htmlspecialchars($a['nama_kelas']) ?></span></div>
                            </td>
                            <td class="px-6 py-4 font-bold text-slate-700 text-xs"><?= htmlspecialchars($a['nama_tempat']) ?></td>
                            <td class="px-6 py-4 text-center">
                                <div class="text-xs font-bold text-slate-700"><?= date('d M Y', strtotime($a['tanggal'])) ?></div>
                                <div class="text-[10px] font-bold text-slate-400"><?= htmlspecialchars($a['waktu_masuk'] ?? '-') ?> WIB</div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($a['status'] == 'Hadir'): ?>
                                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-100">Hadir</span>
                                <?php elseif ($a['status'] == 'Terlambat'): ?>
                                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-100">Terlambat</span>
                                <?php elseif ($a['status'] == 'Sakit'): ?>
                                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider bg-sky-50 text-sky-600 border border-sky-100">Sakit</span>
                                <?php elseif ($a['status'] == 'Izin'): ?>
                                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-600 border border-indigo-100">Izin</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider bg-rose-50 text-rose-600 border border-rose-100">Alfa</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500 italic max-w-xs leading-relaxed">
                                <?= htmlspecialchars($a['keterangan'] ?? '-') ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
