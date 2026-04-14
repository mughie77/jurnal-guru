<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];

// Get Student Profile for class context
$query_profile = "SELECT s.*, sk.kelas_id, k.nama_kelas
                  FROM siswa s
                  JOIN siswa_kelas sk ON s.id = sk.siswa_id
                  JOIN kelas k ON sk.kelas_id = k.id
                  JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id
                  WHERE s.id = ? AND tp.status = 'aktif'";
$stmt = mysqli_prepare($conn, $query_profile);
mysqli_stmt_bind_param($stmt, "i", $siswa_id);
mysqli_stmt_execute($stmt);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Search and Pagination
$media_search = mysqli_real_escape_string($conn, $_GET['media_search'] ?? '');
$media_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$where_media = " WHERE sk.siswa_id = $siswa_id AND sk.tahun_pelajaran_id = (SELECT id FROM tahun_pelajaran WHERE status = 'aktif' LIMIT 1)";
if ($media_search) {
    $where_media .= " AND (p.nama_perangkat LIKE '%$media_search%' OR u.nama_lengkap LIKE '%$media_search%')";
}

$query_base_media = "perangkat p
                     JOIN guru g ON p.guru_id = g.id
                     JOIN users u ON g.user_id = u.id
                     JOIN perangkat_kelas pk ON p.id = pk.perangkat_id
                     JOIN siswa_kelas sk ON pk.kelas_id = sk.kelas_id";

$pagin_media = get_pagination_data($conn, $query_base_media, 10, $where_media, "DISTINCT p.id");

$query_media = "SELECT p.*, u.nama_lengkap as nama_guru
                FROM $query_base_media
                $where_media
                GROUP BY p.id
                ORDER BY p.created_at DESC
                LIMIT {$pagin_media['limit']} OFFSET {$pagin_media['offset']}";
$media_list = mysqli_query($conn, $query_media);

$page_title = "Media & Buku Digital";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 lg:p-8 max-w-4xl mx-auto">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-black italic text-slate-800 uppercase tracking-widest">Media & Buku Digital</h1>
            <p class="text-slate-400 font-bold text-[10px] uppercase tracking-[0.3em] mt-1">Materi Pembelajaran Kelas <?= htmlspecialchars($siswa['nama_kelas']) ?></p>
        </div>

        <form action="" method="GET" class="relative group max-w-xs w-full">
            <input type="text" name="media_search" value="<?= htmlspecialchars($media_search) ?>" placeholder="Cari materi..."
                class="w-full pl-10 pr-4 py-3 bg-white rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 transition-all text-xs font-bold shadow-sm">
            <i class="fa fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-indigo-500 transition-colors"></i>
            <?php if ($media_search): ?>
                <a href="media.php" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 hover:text-rose-500"><i class="fa fa-times-circle"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Media List -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <?php if (mysqli_num_rows($media_list) > 0): ?>
            <?php while ($m = mysqli_fetch_assoc($media_list)): ?>
                <div class="lux-card p-5 bg-white border-none shadow-xl flex items-center gap-4 hover:scale-[1.02] transition-all group">
                    <div class="w-16 h-16 rounded-2xl bg-slate-50 text-slate-400 flex items-center justify-center text-3xl group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-all duration-500 shrink-0 shadow-inner">
                        <?php
                        $icon = 'fa-file-alt';
                        if (strpos($m['file_path'], '.pdf') !== false) $icon = 'fa-file-pdf';
                        elseif (strpos($m['file_path'], '.mp4') !== false) $icon = 'fa-file-video';
                        ?>
                        <i class="fa <?= $icon ?>"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-black text-slate-800 text-sm truncate italic uppercase"><?= htmlspecialchars($m['nama_perangkat']) ?></h4>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1">Guru: <?= htmlspecialchars($m['nama_guru']) ?></p>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-600 text-[8px] font-black uppercase"><?= $m['jenis_perangkat'] ?></span>
                            <span class="text-[8px] text-slate-300 font-bold uppercase"><?= date('d M Y', strtotime($m['created_at'])) ?></span>
                        </div>
                    </div>
                    <a href="<?= BASE_URL . $m['file_path'] ?>" target="_blank" class="w-12 h-12 rounded-2xl bg-slate-900 text-white flex items-center justify-center hover:bg-indigo-600 transition-all shadow-lg active:scale-90">
                        <i class="fa <?= strpos($m['file_path'], '.mp4') !== false ? 'fa-play' : 'fa-download' ?> text-sm"></i>
                    </a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-span-full py-20 text-center lux-card bg-white/50 border-dashed border-2 border-slate-200 shadow-none rounded-[40px]">
                <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fa fa-folder-open text-4xl text-slate-300"></i>
                </div>
                <h3 class="text-slate-800 font-black italic text-lg uppercase tracking-widest">Tidak Ditemukan</h3>
                <p class="text-slate-400 font-bold italic tracking-widest text-[10px] uppercase mt-2">Belum ada media dibagikan ke kelas Anda</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <div class="no-print">
        <?= render_pagination($pagin_media['page'], $pagin_media['total_pages'], $_GET) ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
