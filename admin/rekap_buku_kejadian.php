<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin', 'waka']);
$page_title = "Rekap Buku Kejadian";

// Filters
$kelas_id = (int)($_GET['kelas_id'] ?? 0);
$tgl_mulai = $_GET['tanggal_mulai'] ?? '';
$tgl_selesai = $_GET['tanggal_selesai'] ?? '';
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');

$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");

$where_clauses = [];
if ($kelas_id > 0) {
    $where_clauses[] = "bk.kelas_id = $kelas_id";
}
if (!empty($tgl_mulai)) {
    $where_clauses[] = "bk.tanggal >= '" . mysqli_real_escape_string($conn, $tgl_mulai) . "'";
}
if (!empty($tgl_selesai)) {
    $where_clauses[] = "bk.tanggal <= '" . mysqli_real_escape_string($conn, $tgl_selesai) . "'";
}
if (!empty($search)) {
    $where_clauses[] = "(bk.nama_siswa_list LIKE '%$search%' OR bk.uraian_kejadian LIKE '%$search%' OR bk.tindak_lanjut LIKE '%$search%' OR u.nama_lengkap LIKE '%$search%')";
}

$where_sql = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : "";

$pagin = get_pagination_data($conn, "buku_kejadian bk JOIN kelas k ON bk.kelas_id = k.id LEFT JOIN guru g ON bk.guru_id = g.id LEFT JOIN users u ON g.user_id = u.id", 15, $where_sql);

$query = "SELECT bk.*, k.nama_kelas, mp.nama_mapel, u.nama_lengkap as nama_guru
          FROM buku_kejadian bk
          JOIN kelas k ON bk.kelas_id = k.id
          LEFT JOIN mata_pelajaran mp ON bk.mapel_id = mp.id
          LEFT JOIN guru g ON bk.guru_id = g.id
          LEFT JOIN users u ON g.user_id = u.id
          $where_sql
          ORDER BY bk.tanggal DESC, bk.created_at DESC
          LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";
$res_bk = mysqli_query($conn, $query);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Rekap Buku Kejadian</h1>
        <p class="text-slate-500 font-medium">Monitoring seluruh catatan kejadian dan pembinaan siswa dari guru pengajar.</p>
    </div>
    <div>
        <a href="../guru/export_buku_kejadian_excel.php?<?= http_build_query($_GET) ?>" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition-all shadow-lg shadow-emerald-100 flex items-center text-xs">
            <i class="fa fa-file-excel mr-2"></i> Ekspor Excel
        </a>
    </div>
</div>

<!-- Filter Form -->
<div class="lux-card p-6 mb-8 bg-gradient-to-br from-amber-50/50 to-white border border-amber-100/50">
    <form action="" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Filter Kelas</label>
            <select name="kelas_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 bg-white font-bold text-xs text-slate-700">
                <option value="">-- Semua Kelas --</option>
                <?php mysqli_data_seek($kelases, 0); while($k = mysqli_fetch_assoc($kelases)): ?>
                    <option value="<?= $k['id'] ?>" <?= $k['id'] == $kelas_id ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Cari Siswa / Guru / Uraian</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Siswa, guru, uraian, tindak lanjut..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 bg-white font-semibold text-xs text-slate-700">
        </div>

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Rentang Tanggal</label>
            <div class="flex items-center gap-1.5">
                <input type="date" name="tanggal_mulai" value="<?= htmlspecialchars($tgl_mulai) ?>" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700">
                <span class="text-slate-400 font-bold">-</span>
                <input type="date" name="tanggal_selesai" value="<?= htmlspecialchars($tgl_selesai) ?>" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700">
            </div>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 py-2.5 bg-amber-500 text-white font-bold rounded-xl text-xs hover:bg-amber-600 transition-all shadow-md shadow-amber-100">Cari Data</button>
            <a href="rekap_buku_kejadian.php" class="px-4 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl text-xs hover:bg-slate-300 transition-all">Reset</a>
        </div>
    </form>
</div>

<!-- Data Table -->
<div class="lux-card overflow-hidden bg-white shadow-2xl rounded-3xl mb-6">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Hari & Tanggal</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Guru Pelapor</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Kelas & Mapel</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Siswa Terlibat</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Uraian Kejadian</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Tindak Lanjut / Pembinaan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (mysqli_num_rows($res_bk) == 0): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-slate-400 font-bold italic">Belum ada catatan buku kejadian terdaftar.</td>
                </tr>
                <?php else: ?>
                    <?php while ($r = mysqli_fetch_assoc($res_bk)): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($r['hari'] ?? date('l', strtotime($r['tanggal']))) ?></div>
                            <div class="text-[10px] text-slate-400 font-bold"><?= date('d M Y', strtotime($r['tanggal'])) ?></div>
                        </td>
                        <td class="px-6 py-4 font-bold text-slate-800 text-sm italic">
                            <?= htmlspecialchars($r['nama_guru'] ?? '-') ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-100"><?= htmlspecialchars($r['nama_kelas']) ?></span>
                            <div class="text-xs font-bold text-slate-700 mt-1 italic"><?= htmlspecialchars($r['nama_mapel'] ?? '-') ?></div>
                        </td>
                        <td class="px-6 py-4 max-w-xs">
                            <div class="text-xs font-bold text-slate-800 leading-relaxed"><?= htmlspecialchars($r['nama_siswa_list'] ?? 'Seluruh Siswa') ?></div>
                        </td>
                        <td class="px-6 py-4 max-w-md">
                            <p class="text-xs text-slate-600 leading-relaxed whitespace-pre-line font-medium"><?= htmlspecialchars($r['uraian_kejadian']) ?></p>
                        </td>
                        <td class="px-6 py-4 max-w-md">
                            <p class="text-xs text-indigo-600 leading-relaxed whitespace-pre-line font-semibold"><?= htmlspecialchars($r['tindak_lanjut'] ?? '-') ?></p>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
