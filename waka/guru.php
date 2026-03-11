<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['waka', 'admin']);
$page_title = "Data Guru";

// Search Logic
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$where_sql = "";
if (!empty($search)) {
    $where_sql = " WHERE (users.nama_lengkap LIKE '%$search%' OR guru.nip LIKE '%$search%')";
}

$pagin = get_pagination_data($conn, "guru JOIN users ON guru.user_id = users.id", 15, $where_sql);

$query = "SELECT guru.*, users.nama_lengkap, GROUP_CONCAT(mata_pelajaran.nama_mapel SEPARATOR ', ') as mapel_diampu
          FROM guru JOIN users ON guru.user_id = users.id
          LEFT JOIN guru_mapel ON guru.id = guru_mapel.guru_id
          LEFT JOIN mata_pelajaran ON guru_mapel.mapel_id = mata_pelajaran.id
          $where_sql
          GROUP BY guru.id ORDER BY users.nama_lengkap ASC LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";
$result = mysqli_query($conn, $query);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Data Guru</h1>
        <p class="text-slate-500">Daftar tenaga pengajar aktif di sekolah.</p>
    </div>
</div>

<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cari Guru</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama atau NIP..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm">
        </div>
        <div class="lg:col-span-2 flex items-end gap-3">
            <button type="submit" class="px-8 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100">Cari</button>
            <a href="guru.php" class="px-6 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-300 transition-all">Reset</a>
        </div>
    </form>
</div>

<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">NIP</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Nama Lengkap</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Mata Pelajaran</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-mono text-sm text-indigo-600 font-bold"><?= htmlspecialchars($row['nip']) ?></td>
                    <td class="px-6 py-4 font-semibold text-slate-700"><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                    <td class="px-6 py-4 text-sm text-slate-500">
                        <?php if($row['mapel_diampu']): ?>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach(explode(', ', $row['mapel_diampu']) as $m): ?>
                                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[10px] font-bold border border-slate-200"><?= htmlspecialchars($m) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="italic text-slate-400">Belum ada mapel</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
