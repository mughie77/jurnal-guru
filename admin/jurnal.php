<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);
$page_title = "Data Jurnal Mengajar";

// Filter Logic
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$guru_id = (int)($_GET['guru_id'] ?? 0);
$kelas_id = (int)($_GET['kelas_id'] ?? 0);
$mapel_id = (int)($_GET['mapel_id'] ?? 0);
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_clauses = [];
if (!empty($start_date)) $where_clauses[] = "jurnal.tanggal >= '$start_date'";
if (!empty($end_date)) $where_clauses[] = "jurnal.tanggal <= '$end_date'";
if ($guru_id > 0) $where_clauses[] = "jurnal.guru_id = $guru_id";
if ($kelas_id > 0) $where_clauses[] = "jurnal.kelas_id = $kelas_id";
if ($mapel_id > 0) $where_clauses[] = "jurnal.mapel_id = $mapel_id";
if (!empty($search)) $where_clauses[] = "(jurnal.materi LIKE '%$search%' OR users.nama_lengkap LIKE '%$search%')";

$sql = "SELECT jurnal.*, users.nama_lengkap, mata_pelajaran.nama_mapel, kelas.nama_kelas
        FROM jurnal JOIN guru ON jurnal.guru_id = guru.id
        JOIN users ON guru.user_id = users.id
        JOIN mata_pelajaran ON jurnal.mapel_id = mata_pelajaran.id
        JOIN kelas ON jurnal.kelas_id = kelas.id";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY jurnal.tanggal DESC, jurnal.created_at DESC";

$guru_list = mysqli_query($conn, "SELECT g.id, u.nama_lengkap FROM guru g JOIN users u ON g.user_id = u.id ORDER BY u.nama_lengkap ASC");
$kelas_list = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
$mapel_list = mysqli_query($conn, "SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel ASC");
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

<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cari Materi/Guru</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Materi atau Guru..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm">
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Pilih Guru</label>
            <select name="guru_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700">
                <option value="">-- Semua Guru --</option>
                <?php mysqli_data_seek($guru_list, 0); while($g = mysqli_fetch_assoc($guru_list)): ?>
                    <option value="<?= $g['id'] ?>" <?= $g['id'] == $guru_id ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_lengkap']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Pilih Kelas</label>
            <select name="kelas_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700">
                <option value="">-- Semua Kelas --</option>
                <?php mysqli_data_seek($kelas_list, 0); while($k = mysqli_fetch_assoc($kelas_list)): ?>
                    <option value="<?= $k['id'] ?>" <?= $k['id'] == $kelas_id ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Pilih Mapel</label>
            <select name="mapel_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700">
                <option value="">-- Semua Mapel --</option>
                <?php mysqli_data_seek($mapel_list, 0); while($m = mysqli_fetch_assoc($mapel_list)): ?>
                    <option value="<?= $m['id'] ?>" <?= $m['id'] == $mapel_id ? 'selected' : '' ?>><?= htmlspecialchars($m['nama_mapel']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Dari Tanggal</label>
            <input type="date" name="start_date" value="<?= $start_date ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white">
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Sampai Tanggal</label>
            <input type="date" name="end_date" value="<?= $end_date ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white">
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
