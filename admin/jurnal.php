<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);
$page_title = "Data Jurnal Mengajar";

// Filter Logic ... (Keep existing logic but update UI)
$where_clauses = [];
if (!empty($_GET['start_date'])) $where_clauses[] = "jurnal.tanggal >= '" . mysqli_real_escape_string($conn, $_GET['start_date']) . "'";
if (!empty($_GET['end_date'])) $where_clauses[] = "jurnal.tanggal <= '" . mysqli_real_escape_string($conn, $_GET['end_date']) . "'";
// ... other filters ...

$sql = "SELECT jurnal.*, users.nama_lengkap, mata_pelajaran.nama_mapel, kelas.nama_kelas
        FROM jurnal JOIN guru ON jurnal.guru_id = guru.id
        JOIN users ON guru.user_id = users.id
        JOIN mata_pelajaran ON jurnal.mapel_id = mata_pelajaran.id
        JOIN kelas ON jurnal.kelas_id = kelas.id";
if (!empty($where_clauses)) $sql .= " WHERE " . implode(' AND ', $where_clauses);
$sql .= " ORDER BY jurnal.tanggal DESC LIMIT 100";
$result = mysqli_query($conn, $sql);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Data Jurnal</h1>
        <p class="text-slate-500">Pantau aktivitas mengajar guru di seluruh kelas.</p>
    </div>
    <a href="export_csv.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-emerald-200 transition-all flex items-center">
        <i class="fa fa-file-excel mr-2"></i> Ekspor CSV
    </a>
</div>

<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50 to-white">
    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Mulai Tanggal</label>
            <input type="date" name="start_date" class="w-full px-4 py-2 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Sampai Tanggal</label>
            <input type="date" name="end_date" class="w-full px-4 py-2 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50">
        </div>
        <div class="md:col-span-2 flex items-end gap-3">
            <button type="submit" class="flex-1 bg-indigo-600 text-white font-bold py-2 rounded-xl hover:bg-indigo-700 transition-all">Filter Data</button>
            <a href="jurnal.php" class="px-6 py-2 bg-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-300 transition-all text-center">Reset</a>
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
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 text-sm font-medium text-slate-700 whitespace-nowrap"><?= date('d M Y', strtotime($row['tanggal'])) ?></td>
                    <td class="px-6 py-4">
                        <div class="font-bold text-slate-800"><?= htmlspecialchars($row['nama_lengkap']) ?></div>
                        <div class="text-xs text-indigo-500 font-bold tracking-tight"><?= htmlspecialchars($row['nama_mapel']) ?></div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-bold border border-slate-200 uppercase"><?= htmlspecialchars($row['nama_kelas']) ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 max-w-xs truncate"><?= htmlspecialchars($row['materi']) ?></td>
                    <td class="px-6 py-4">
                        <div class="flex justify-center gap-1">
                            <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 text-xs font-bold border border-emerald-100" title="Hadir"><?= $row['jml_hadir'] ?></span>
                            <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-amber-50 text-amber-600 text-xs font-bold border border-amber-100" title="Sakit"><?= $row['jml_sakit'] ?></span>
                            <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 text-xs font-bold border border-blue-100" title="Izin"><?= $row['jml_izin'] ?></span>
                            <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-rose-50 text-rose-600 text-xs font-bold border border-rose-100" title="Alfa"><?= $row['jml_alfa'] ?></span>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
