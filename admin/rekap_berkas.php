<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin', 'waka']);

$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$kelas_id = mysqli_real_escape_string($conn, $_GET['kelas_id'] ?? '');

$where = " WHERE tp.status = 'aktif'";
if (!empty($search)) {
    $where .= " AND (s.nama_siswa LIKE '%$search%' OR s.nis LIKE '%$search%' OR s.nisn LIKE '%$search%')";
}
if (!empty($kelas_id)) {
    $where .= " AND sk.kelas_id = '$kelas_id'";
}

// Pagination
$table_join = "siswa s
                JOIN siswa_kelas sk ON s.id = sk.siswa_id
                JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id";
$pagin = get_pagination_data($conn, $table_join, 15, $where);

// Get Data
$query = "SELECT s.id, s.nis, s.nama_siswa, s.berkas_kk, s.berkas_ijazah, s.no_wa_ortu, k.nama_kelas
          FROM siswa s
          JOIN siswa_kelas sk ON s.id = sk.siswa_id
          JOIN kelas k ON sk.kelas_id = k.id
          JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id
          $where
          ORDER BY k.nama_kelas ASC, s.nama_siswa ASC
          LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";
$result = mysqli_query($conn, $query);

// Get Classes for filter
$classes = mysqli_query($conn, "SELECT * FROM kelas ORDER BY nama_kelas ASC");

// Calculate summary stats matching the active filter
$stats_query = "SELECT
    COUNT(*) as total_siswa,
    SUM(CASE WHEN (s.berkas_kk IS NOT NULL AND s.berkas_kk != '') AND (s.berkas_ijazah IS NOT NULL AND s.berkas_ijazah != '') THEN 1 ELSE 0 END) as lengkap,
    SUM(CASE WHEN (s.berkas_kk IS NULL OR s.berkas_kk = '') OR (s.berkas_ijazah IS NULL OR s.berkas_ijazah = '') THEN 1 ELSE 0 END) as belum_lengkap
FROM siswa s
JOIN siswa_kelas sk ON s.id = sk.siswa_id
JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id
$where";
$stats_res = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_res);
$total_siswa = (int)($stats['total_siswa'] ?? 0);
$total_lengkap = (int)($stats['lengkap'] ?? 0);
$total_belum_lengkap = (int)($stats['belum_lengkap'] ?? 0);

$page_title = "Rekap Berkas Siswa";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 lg:p-8">
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black italic text-slate-800 uppercase tracking-widest">Rekap Berkas Siswa</h1>
            <p class="text-slate-400 font-bold text-xs uppercase tracking-[0.3em] mt-1">Pemantauan Dokumen KK & Ijazah</p>
        </div>
    </div>

    <!-- Stats Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="lux-card p-6 flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Total Siswa</span>
                <span class="text-3xl font-black text-slate-800 italic"><?= $total_siswa ?></span>
            </div>
            <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center text-slate-500">
                <i class="fa fa-users text-xl"></i>
            </div>
        </div>
        <div class="lux-card p-6 flex items-center justify-between border-l-4 border-l-emerald-500">
            <div>
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Berkas Lengkap</span>
                <span class="text-3xl font-black text-emerald-600 italic"><?= $total_lengkap ?></span>
            </div>
            <div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-500">
                <i class="fa fa-check-circle text-xl"></i>
            </div>
        </div>
        <div class="lux-card p-6 flex items-center justify-between border-l-4 border-l-rose-500">
            <div>
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Belum Lengkap</span>
                <span class="text-3xl font-black text-rose-600 italic"><?= $total_belum_lengkap ?></span>
            </div>
            <div class="w-12 h-12 bg-rose-50 rounded-2xl flex items-center justify-center text-rose-500">
                <i class="fa fa-times-circle text-xl"></i>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="lux-card p-6 mb-8">
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="relative">
                <i class="fa fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari Nama / NIS / NISN..."
                    class="w-full pl-12 pr-4 py-3 rounded-2xl bg-slate-50 border-none focus:ring-2 focus:ring-indigo-500 font-bold text-slate-700 placeholder:text-slate-300">
            </div>
            <select name="kelas_id" class="w-full px-4 py-3 rounded-2xl bg-slate-50 border-none focus:ring-2 focus:ring-indigo-500 font-bold text-slate-700">
                <option value="">Semua Kelas</option>
                <?php while($k = mysqli_fetch_assoc($classes)): ?>
                    <option value="<?= $k['id'] ?>" <?= ($kelas_id == $k['id']) ? 'selected' : '' ?>><?= $k['nama_kelas'] ?></option>
                <?php endwhile; ?>
            </select>
            <button type="submit" class="w-full bg-slate-900 text-white font-black py-3 rounded-2xl hover:bg-indigo-600 transition-all uppercase tracking-widest italic text-xs">Filter Data</button>
        </form>
    </div>

    <div class="lux-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-900 text-white uppercase text-[10px] tracking-[0.2em]">
                        <th class="p-6 font-black">Siswa</th>
                        <th class="p-6 font-black text-center">Kelas</th>
                        <th class="p-6 font-black text-center">Kartu Keluarga</th>
                        <th class="p-6 font-black text-center">Ijazah</th>
                        <th class="p-6 font-black text-center">Status Berkas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="p-6">
                                    <div class="font-black text-slate-800 italic uppercase text-sm"><?= htmlspecialchars($row['nama_siswa']) ?></div>
                                    <div class="text-[10px] font-bold text-slate-400 mt-0.5 tracking-widest">NIS: <?= $row['nis'] ?></div>
                                    <?php if (!empty($row['no_wa_ortu'])): ?>
                                        <div class="mt-1 flex items-center gap-1.5 text-[10px] font-black text-emerald-600 uppercase tracking-wider">
                                            <i class="fab fa-whatsapp text-xs"></i> WA Ortu: <?= htmlspecialchars($row['no_wa_ortu']) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-1 flex items-center gap-1.5 text-[10px] font-bold text-rose-400 uppercase tracking-wider italic">
                                            <i class="fab fa-whatsapp text-xs"></i> WA Ortu: Belum Diisi
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-6 text-center">
                                    <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-[10px] font-black uppercase tracking-widest"><?= $row['nama_kelas'] ?></span>
                                </td>
                                <td class="p-6 text-center">
                                    <?php if ($row['berkas_kk']): ?>
                                        <a href="<?= BASE_URL ?>uploads/siswa/berkas/<?= $row['berkas_kk'] ?>" target="_blank"
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-600 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-600 hover:text-white transition-all">
                                            <i class="fa fa-eye"></i> Lihat
                                        </a>
                                    <?php else: ?>
                                        <span class="text-[10px] font-black text-rose-400 uppercase tracking-widest italic">Belum Upload</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-6 text-center">
                                    <?php if ($row['berkas_ijazah']): ?>
                                        <a href="<?= BASE_URL ?>uploads/siswa/berkas/<?= $row['berkas_ijazah'] ?>" target="_blank"
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-amber-50 text-amber-600 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-amber-600 hover:text-white transition-all">
                                            <i class="fa fa-eye"></i> Lihat
                                        </a>
                                    <?php else: ?>
                                        <span class="text-[10px] font-black text-rose-400 uppercase tracking-widest italic">Belum Upload</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-6 text-center">
                                    <?php
                                    $has_kk = !empty($row['berkas_kk']);
                                    $has_ijazah = !empty($row['berkas_ijazah']);
                                    if ($has_kk && $has_ijazah): ?>
                                        <span class="px-3 py-1 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-emerald-100">Lengkap</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-rose-50 text-rose-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-rose-100">Belum Lengkap</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="p-12 text-center">
                                <div class="flex flex-col items-center gap-4">
                                    <i class="fa fa-folder-open text-5xl text-slate-100"></i>
                                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest italic">Data tidak ditemukan</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagin['total_pages'] > 1): ?>
            <div class="p-6 bg-slate-50/50 border-t border-slate-100">
                <?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
