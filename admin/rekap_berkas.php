<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin', 'waka']);

$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$kelas_id = mysqli_real_escape_string($conn, $_GET['kelas_id'] ?? '');

$where = " WHERE tp.status = 'aktif'";
if (!empty($search)) {
    $where .= " AND (s.nama_siswa LIKE '%$search%' OR s.nis LIKE '%$search%')";
}
if (!empty($kelas_id)) {
    $where .= " AND sk.kelas_id = '$kelas_id'";
}

// Pagination
$count_query = "SELECT COUNT(*) as total FROM siswa s
                JOIN siswa_kelas sk ON s.id = sk.siswa_id
                JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id
                $where";
$pagin = get_pagination_data($conn, $count_query, 15);

// Get Data
$query = "SELECT s.id, s.nis, s.nama_siswa, s.berkas_kk, s.berkas_ijazah, k.nama_kelas
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

    <!-- Filter Card -->
    <div class="lux-card p-6 mb-8">
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="relative">
                <i class="fa fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari Nama / NIS..."
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
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="p-6">
                                    <div class="font-black text-slate-800 italic uppercase text-sm"><?= htmlspecialchars($row['nama_siswa']) ?></div>
                                    <div class="text-[10px] font-bold text-slate-400 mt-0.5 tracking-widest"><?= $row['nis'] ?></div>
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
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="p-12 text-center">
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
