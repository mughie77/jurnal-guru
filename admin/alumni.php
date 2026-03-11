<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin', 'waka']);
$page_title = "Data Alumni";

$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
// Alumni are students who do NOT have an entry in siswa_kelas for the active year
$where_sql = " WHERE s.id NOT IN (SELECT siswa_id FROM siswa_kelas WHERE tahun_pelajaran_id = '$active_tahun_id')";
if (!empty($search)) {
    $where_sql .= " AND (s.nama_siswa LIKE '%$search%' OR s.nis LIKE '%$search%')";
}

$pagin = get_pagination_data($conn, "siswa s", 15, $where_sql);

$query = "SELECT s.* FROM siswa s $where_sql ORDER BY s.nama_siswa ASC LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";
$result = mysqli_query($conn, $query);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Database Alumni</h1>
        <p class="text-slate-500">Siswa yang telah lulus atau tidak aktif di tahun ajaran ini.</p>
    </div>
</div>

<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
    <form action="" method="GET" class="flex gap-4">
        <div class="flex-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cari Alumni</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama atau NIS..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm">
        </div>
        <div class="flex items-end gap-3">
            <button type="submit" class="px-8 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100">Cari</button>
            <a href="alumni.php" class="px-6 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-300 transition-all">Reset</a>
        </div>
    </form>
</div>

<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">NIS / NISN</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Nama Lengkap</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">JK</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Alamat</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-mono text-sm">
                        <div class="text-indigo-600 font-bold"><?= htmlspecialchars($row['nis']) ?></div>
                        <div class="text-slate-400 text-[10px]"><?= htmlspecialchars($row['nisn'] ?? '-') ?></div>
                    </td>
                    <td class="px-6 py-4 font-semibold text-slate-700"><?= htmlspecialchars($row['nama_siswa']) ?></td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase <?= $row['jenis_kelamin'] == 'L' ? 'bg-blue-50 text-blue-600' : 'bg-pink-50 text-pink-600' ?>">
                            <?= $row['jenis_kelamin'] ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-500 italic max-w-xs truncate"><?= htmlspecialchars($row['alamat'] ?? '-') ?></td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-500 text-[10px] font-black uppercase tracking-widest">ALUMNI</span>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if(mysqli_num_rows($result) == 0): ?>
                    <tr><td colspan="5" class="px-6 py-20 text-center text-slate-400 italic font-medium">Tidak ada data alumni yang ditemukan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
