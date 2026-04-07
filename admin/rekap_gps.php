<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin', 'waka']);
$page_title = "Rekap Absensi GPS";

$tanggal = mysqli_real_escape_string($conn, $_GET['tanggal'] ?? date('Y-m-d'));
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$kelas_id = (int)($_GET['kelas_id'] ?? 0);

$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");

$where = " WHERE ah.tanggal = '$tanggal'";
if ($search) {
    $where .= " AND (s.nama_siswa LIKE '%$search%' OR s.nis LIKE '%$search%')";
}
if ($kelas_id > 0) {
    $where .= " AND sk.kelas_id = $kelas_id";
}

$query_base = "absensi_harian ah
               JOIN siswa s ON ah.siswa_id = s.id
               LEFT JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = $active_tahun_id
               LEFT JOIN kelas k ON sk.kelas_id = k.id";

$pagin = get_pagination_data($conn, $query_base, 15, $where);

$query = "SELECT ah.*, s.nama_siswa, s.nis, k.nama_kelas
          FROM $query_base
          $where
          ORDER BY ah.waktu_masuk DESC
          LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";
$res = mysqli_query($conn, $query);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Rekap Absensi GPS</h1>
        <p class="text-slate-500">Log kehadiran harian siswa berbasis lokasi.</p>
    </div>
</div>

<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Tanggal</label>
            <input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold">
        </div>
        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Filter Kelas</label>
            <select name="kelas_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold">
                <option value="0">Semua Kelas</option>
                <?php mysqli_data_seek($kelases, 0); while($k = mysqli_fetch_assoc($kelases)): ?>
                    <option value="<?= $k['id'] ?>" <?= $k['id'] == $kelas_id ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Cari Siswa</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama / NIS..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100">Filter</button>
            <a href="rekap_gps.php" class="px-4 py-2.5 bg-slate-100 text-slate-500 font-bold rounded-xl hover:bg-slate-200 transition-all flex items-center justify-center"><i class="fa fa-sync-alt"></i></a>
        </div>
    </form>
</div>

<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Siswa</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest text-center">Waktu</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest text-center">Status</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php while ($r = mysqli_fetch_assoc($res)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="font-bold text-slate-700"><?= htmlspecialchars($r['nama_siswa']) ?></div>
                        <div class="text-[10px] text-slate-400 font-mono"><?= $r['nis'] ?> • <?= htmlspecialchars($r['nama_kelas'] ?? 'Tanpa Kelas') ?></div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-lg text-xs font-bold font-mono"><?= date('H:i', strtotime($r['waktu_masuk'])) ?></span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <?php
                        $color = 'bg-emerald-100 text-emerald-600';
                        if ($r['status'] == 'Terlambat') $color = 'bg-amber-100 text-amber-600';
                        if (in_array($r['status'], ['Sakit', 'Izin'])) $color = 'bg-blue-100 text-blue-600';
                        if ($r['status'] == 'Alfa') $color = 'bg-rose-100 text-rose-600';
                        ?>
                        <span class="px-3 py-1 <?= $color ?> rounded-full text-[10px] font-black uppercase tracking-widest border border-current opacity-80"><?= $r['status'] ?></span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-xs text-slate-500 italic truncate max-w-xs" title="<?= htmlspecialchars($r['keterangan']) ?>">
                            <?= htmlspecialchars($r['keterangan']) ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($res) == 0): ?>
                <tr>
                    <td colspan="4" class="px-6 py-12 text-center text-slate-400 italic">Data absensi tidak ditemukan untuk kriteria ini.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
