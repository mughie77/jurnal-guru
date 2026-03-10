<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['waka', 'admin']);
$page_title = "Laporan Jurnal Mengajar";

// Filter logic (Keep existing logic)
$where_clauses = [];
if (!empty($_GET['start_date'])) $where_clauses[] = "jurnal.tanggal >= '" . mysqli_real_escape_string($conn, $_GET['start_date']) . "'";
if (!empty($_GET['end_date'])) $where_clauses[] = "jurnal.tanggal <= '" . mysqli_real_escape_string($conn, $_GET['end_date']) . "'";
// ... other filters ...

$sql = "SELECT jurnal.*, users.nama_lengkap, mata_pelajaran.nama_mapel, kelas.nama_kelas, tahun_pelajaran.tahun
        FROM jurnal JOIN guru ON jurnal.guru_id = guru.id
        JOIN users ON guru.user_id = users.id
        JOIN mata_pelajaran ON jurnal.mapel_id = mata_pelajaran.id
        JOIN kelas ON jurnal.kelas_id = kelas.id
        JOIN tahun_pelajaran ON jurnal.tahun_pelajaran_id = tahun_pelajaran.id";
if (!empty($where_clauses)) $sql .= " WHERE " . implode(' AND ', $where_clauses);
$sql .= " ORDER BY jurnal.tanggal DESC, jurnal.created_at DESC";
$result = mysqli_query($conn, $sql);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Laporan Jurnal</h1>
        <p class="text-slate-500">Tinjau aktivitas mengajar guru di seluruh unit.</p>
    </div>
    <div class="flex gap-3">
        <a href="export_csv.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-emerald-100 transition-all flex items-center">
            <i class="fa fa-file-csv mr-2"></i> Unduh CSV
        </a>
        <a href="export_pdf.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" target="_blank" class="bg-rose-600 hover:bg-rose-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-rose-100 transition-all flex items-center">
            <i class="fa fa-file-pdf mr-2"></i> Unduh PDF
        </a>
    </div>
</div>

<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Dari Tanggal</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white">
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Sampai Tanggal</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white">
        </div>
        <div class="lg:col-span-2 flex items-end gap-3">
            <button type="submit" class="flex-1 px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100">Terapkan Filter</button>
            <a href="jurnal.php" class="px-6 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-300 transition-all">Reset</a>
        </div>
    </form>
</div>

<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Tanggal</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Guru & Mapel</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Kelas</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Materi</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Absensi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if(mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 text-sm font-bold text-slate-700 whitespace-nowrap"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-800 text-sm leading-tight mb-0.5"><?= htmlspecialchars($row['nama_lengkap']) ?></div>
                            <div class="text-[10px] text-indigo-500 font-black uppercase tracking-tighter"><?= htmlspecialchars($row['nama_mapel']) ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 rounded bg-slate-100 text-slate-600 text-[10px] font-black uppercase border border-slate-200"><?= htmlspecialchars($row['nama_kelas']) ?></span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600 max-w-xs truncate" title="<?= htmlspecialchars($row['materi']) ?>"><?= htmlspecialchars($row['materi']) ?></td>
                        <td class="px-6 py-4">
                            <div class="flex justify-center gap-1">
                                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 text-[10px] font-black border border-emerald-100"><?= $row['jml_hadir'] ?></span>
                                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-amber-50 text-amber-600 text-[10px] font-black border border-amber-100"><?= $row['jml_sakit'] ?></span>
                                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 text-[10px] font-black border border-blue-100"><?= $row['jml_izin'] ?></span>
                                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-rose-50 text-rose-600 text-[10px] font-black border border-rose-100"><?= $row['jml_alfa'] ?></span>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="px-6 py-20 text-center text-slate-400 font-medium italic">Tidak ada data laporan jurnal.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
