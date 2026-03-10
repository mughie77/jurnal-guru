<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['waka', 'admin']);
$page_title = "Data Kelas";

// Search Logic
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$where_sql = "";
if (!empty($search)) {
    $where_sql = " WHERE (kelas.nama_kelas LIKE '%$search%' OR users.nama_lengkap LIKE '%$search%')";
}

$query = "SELECT kelas.*, users.nama_lengkap as nama_wali_kelas
          FROM kelas LEFT JOIN guru ON kelas.wali_kelas_id = guru.id
          LEFT JOIN users ON guru.user_id = users.id
          $where_sql
          ORDER BY kelas.nama_kelas ASC";
$result = mysqli_query($conn, $query);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Data Kelas</h1>
        <p class="text-slate-500">Informasi kelas dan kapasitas siswa.</p>
    </div>
</div>

<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cari Kelas</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama Kelas atau Wali..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm">
        </div>
        <div class="lg:col-span-2 flex items-end gap-3">
            <button type="submit" class="px-8 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100">Cari</button>
            <a href="kelas.php" class="px-6 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-300 transition-all">Reset</a>
        </div>
    </form>
</div>

<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Nama Kelas</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Wali Kelas</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Laki-laki</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Perempuan</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-bold text-slate-700"><?= htmlspecialchars($row['nama_kelas']) ?></td>
                    <td class="px-6 py-4 text-sm text-slate-600"><?= htmlspecialchars($row['nama_wali_kelas'] ?? 'Belum Diatur') ?></td>
                    <td class="px-6 py-4 text-center font-bold text-blue-600"><?= $row['jumlah_siswa_L'] ?></td>
                    <td class="px-6 py-4 text-center font-bold text-pink-600"><?= $row['jumlah_siswa_P'] ?></td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-3 py-1 rounded-lg bg-slate-100 text-slate-800 font-extrabold text-sm border border-slate-200">
                            <?= $row['jumlah_siswa_L'] + $row['jumlah_siswa_P'] ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
