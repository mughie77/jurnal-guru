<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
$guru_data = mysqli_fetch_assoc($guru_res);
$guru_id = $guru_data['id'] ?? 0;

$page_title = "Jurnal PKL Siswa Bimbingan";

// Search & Filter
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$filter_status = mysqli_real_escape_string($conn, $_GET['status'] ?? '');

$where_clauses = [
    "tp.guru_pembimbing_id = $guru_id",
    "sp.tahun_pelajaran_id = $active_tahun_id",
    "sp.status = 'aktif'"
];

if (!empty($search)) {
    $where_clauses[] = "(s.nama_siswa LIKE '%$search%' OR s.nis LIKE '%$search%' OR jp.kegiatan LIKE '%$search%' OR tp.nama_tempat LIKE '%$search%')";
}
if (!empty($filter_status) && in_array($filter_status, ['pending', 'disetujui', 'ditolak'])) {
    $where_clauses[] = "jp.status_verifikasi = '$filter_status'";
}

$where_sql = " WHERE " . implode(" AND ", $where_clauses);

$pagin = get_pagination_data($conn, "jurnal_pkl jp JOIN siswa s ON jp.siswa_id = s.id JOIN siswa_pkl sp ON s.id = sp.siswa_id JOIN tempat_pkl tp ON sp.tempat_pkl_id = tp.id JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = sp.tahun_pelajaran_id JOIN kelas k ON sk.kelas_id = k.id", 10, $where_sql);

$q_jurnal = "SELECT jp.*, s.nama_siswa, s.nis, k.nama_kelas, tp.nama_tempat, tp.pembimbing_dudi
             FROM jurnal_pkl jp
             JOIN siswa s ON jp.siswa_id = s.id
             JOIN siswa_pkl sp ON s.id = sp.siswa_id
             JOIN tempat_pkl tp ON sp.tempat_pkl_id = tp.id
             JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = sp.tahun_pelajaran_id
             JOIN kelas k ON sk.kelas_id = k.id
             $where_sql
             ORDER BY jp.tanggal DESC, jp.created_at DESC
             LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";
$res_jurnal = mysqli_query($conn, $q_jurnal);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto pb-24">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Jurnal PKL Siswa Bimbingan</h1>
            <p class="text-slate-500 font-medium">Monitoring aktivitas laporan harian kegiatan PKL siswa bimbingan Anda.</p>
        </div>
    </div>

    <div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cari Jurnal / Siswa</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama, NIS, kegiatan, tempat..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white text-xs font-semibold">
            </div>
            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Status Verifikasi DU/DI</label>
                <select name="status" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white text-xs font-semibold">
                    <option value="">Semua Status</option>
                    <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Pending (Menunggu)</option>
                    <option value="disetujui" <?= $filter_status == 'disetujui' ? 'selected' : '' ?>>Disetujui DU/DI</option>
                    <option value="ditolak" <?= $filter_status == 'ditolak' ? 'selected' : '' ?>>Ditolak DU/DI</option>
                </select>
            </div>
            <div class="flex items-end gap-3">
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl text-xs hover:bg-indigo-700 transition-all shadow-md shadow-indigo-100">Filter</button>
                <a href="pkl_jurnal.php" class="px-5 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl text-xs hover:bg-slate-300 transition-all">Reset</a>
            </div>
        </form>
    </div>

    <div class="lux-card overflow-hidden bg-white shadow-2xl rounded-3xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Siswa & Tempat PKL</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Tanggal</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Aktivitas Kegiatan</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Foto</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Status DU/DI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (mysqli_num_rows($res_jurnal) == 0): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-bold italic">Belum ada laporan kegiatan PKL dari siswa bimbingan Anda.</td>
                    </tr>
                    <?php else: ?>
                        <?php while ($j = mysqli_fetch_assoc($res_jurnal)): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($j['nama_siswa']) ?></div>
                                <div class="text-[10px] text-slate-400 font-bold">NIS: <?= htmlspecialchars($j['nis']) ?> | <span class="text-indigo-600"><?= htmlspecialchars($j['nama_kelas']) ?></span></div>
                                <div class="text-[10px] text-slate-500 font-bold mt-1 italic"><i class="fa fa-building text-indigo-400"></i> <?= htmlspecialchars($j['nama_tempat']) ?></div>
                            </td>
                            <td class="px-6 py-4 text-xs font-bold text-slate-600 whitespace-nowrap">
                                <?= date('d M Y', strtotime($j['tanggal'])) ?>
                            </td>
                            <td class="px-6 py-4 max-w-md">
                                <div class="text-xs text-slate-700 leading-relaxed font-medium line-clamp-3"><?= htmlspecialchars($j['kegiatan']) ?></div>
                                <?php if (!empty($j['catatan_dudi'])): ?>
                                <div class="mt-2 p-2 bg-amber-50 rounded-xl border border-amber-100 text-[10px] font-semibold text-amber-800 italic">
                                    Catatan DU/DI: <?= htmlspecialchars($j['catatan_dudi']) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if (!empty($j['foto_kegiatan'])): ?>
                                    <a href="<?= BASE_URL ?>uploads/pkl/<?= $j['foto_kegiatan'] ?>" target="_blank" class="inline-block w-12 h-12 rounded-xl overflow-hidden border border-slate-200 hover:scale-105 transition-transform shadow-sm">
                                        <img src="<?= BASE_URL ?>uploads/pkl/<?= $j['foto_kegiatan'] ?>" class="w-full h-full object-cover">
                                    </a>
                                <?php else: ?>
                                    <span class="text-slate-300 text-xs italic">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($j['status_verifikasi'] == 'disetujui'): ?>
                                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-100">Disetujui</span>
                                <?php elseif ($j['status_verifikasi'] == 'ditolak'): ?>
                                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider bg-rose-50 text-rose-600 border border-rose-100">Ditolak</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-100 animate-pulse">Pending</span>
                                <?php endif; ?>
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
