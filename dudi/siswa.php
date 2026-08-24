<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['dudi', 'admin']);

$user_id = $_SESSION['user_id'];

// Get Tempat PKL assigned to this DU/DI user
$res_tp = mysqli_query($conn, "SELECT id, nama_tempat FROM tempat_pkl WHERE user_dudi_id = $user_id LIMIT 1");
$tempat_pkl = mysqli_fetch_assoc($res_tp);
$tempat_pkl_id = $tempat_pkl['id'] ?? 0;

$page_title = "Siswa PKL Murid";

// Search
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$where_spkl = " WHERE sp.tempat_pkl_id = $tempat_pkl_id AND sp.tahun_pelajaran_id = $active_tahun_id AND sp.status = 'aktif'";
if (!empty($search)) {
    $where_spkl .= " AND (s.nama_siswa LIKE '%$search%' OR s.nis LIKE '%$search%' OR k.nama_kelas LIKE '%$search%')";
}

$pagin = get_pagination_data($conn, "siswa_pkl sp JOIN siswa s ON sp.siswa_id = s.id JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = sp.tahun_pelajaran_id JOIN kelas k ON sk.kelas_id = k.id", 10, $where_spkl);

$q_siswa = "SELECT sp.id as siswa_pkl_id, s.id as siswa_id, s.nis, s.nama_siswa, s.no_telp, s.foto, k.nama_kelas,
            (SELECT COUNT(*) FROM jurnal_pkl jp WHERE jp.siswa_id = s.id AND jp.tempat_pkl_id = $tempat_pkl_id) as total_jurnal
            FROM siswa_pkl sp
            JOIN siswa s ON sp.siswa_id = s.id
            JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = sp.tahun_pelajaran_id
            JOIN kelas k ON sk.kelas_id = k.id
            $where_spkl
            ORDER BY k.nama_kelas ASC, s.nama_siswa ASC
            LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";
$res_siswa = mysqli_query($conn, $q_siswa);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto pb-24">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Daftar Siswa PKL Murid</h1>
            <p class="text-slate-500 font-medium">Siswa yang sedang melaksanakan Praktik Kerja Lapangan di perusahaan Anda.</p>
        </div>
    </div>

    <div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
        <form action="" method="GET" class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama siswa, NIS, kelas..." class="w-full sm:w-80 px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white text-xs font-semibold">
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl text-xs hover:bg-indigo-700 transition-all shadow-md shadow-indigo-100">Cari</button>
            <a href="<?= BASE_URL ?>dudi/siswa.php" class="px-5 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl text-xs hover:bg-slate-300 transition-all">Reset</a>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <?php if (mysqli_num_rows($res_siswa) == 0): ?>
        <div class="col-span-full lux-card p-12 text-center text-slate-400 italic">Belum ada siswa PKL terdaftar di industri Anda.</div>
        <?php else: ?>
            <?php while ($s = mysqli_fetch_assoc($res_siswa)): ?>
            <div class="lux-card p-6 bg-white shadow-xl rounded-3xl border border-slate-100 flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-14 h-14 rounded-2xl bg-indigo-600 text-white font-black text-xl flex items-center justify-center overflow-hidden shrink-0 shadow-md">
                            <?php if (!empty($s['foto'])): ?>
                                <img src="<?= BASE_URL ?>uploads/siswa/<?= $s['foto'] ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <?= strtoupper(substr($s['nama_siswa'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-600 border border-indigo-100"><?= htmlspecialchars($s['nama_kelas']) ?></span>
                            <h3 class="font-black text-slate-800 text-base mt-1 line-clamp-1"><?= htmlspecialchars($s['nama_siswa']) ?></h3>
                            <p class="text-[10px] text-slate-400 font-bold">NIS: <?= htmlspecialchars($s['nis']) ?></p>
                        </div>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400 font-bold uppercase text-[10px]">Total Jurnal Dibuat:</span>
                            <span class="font-bold text-indigo-600"><?= $s['total_jurnal'] ?> Laporan</span>
                        </div>
                        <?php if (!empty($s['no_telp'])): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400 font-bold uppercase text-[10px]">No. HP / WA:</span>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $s['no_telp']) ?>" target="_blank" class="text-emerald-600 font-bold hover:underline inline-flex items-center gap-1">
                                <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($s['no_telp']) ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-50 flex gap-2">
                    <a href="<?= BASE_URL ?>dudi/nilai.php?siswa_id=<?= $s['siswa_id'] ?>" class="w-full py-2.5 bg-emerald-600 text-white font-bold text-xs rounded-xl hover:bg-emerald-700 text-center transition-all shadow-md shadow-emerald-100">
                        <i class="fa fa-star mr-1"></i> Beri Nilai PKL
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
