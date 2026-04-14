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

// Search, Filter and Pagination
$media_search = mysqli_real_escape_string($conn, $_GET['media_search'] ?? '');
$media_type = mysqli_real_escape_string($conn, $_GET['media_type'] ?? '');
$media_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$where_media = " WHERE sk.siswa_id = $siswa_id AND sk.tahun_pelajaran_id = (SELECT id FROM tahun_pelajaran WHERE status = 'aktif' LIMIT 1)";
if ($media_search) {
    $where_media .= " AND (p.nama_perangkat LIKE '%$media_search%' OR u.nama_lengkap LIKE '%$media_search%')";
}
if ($media_type) {
    $where_media .= " AND p.jenis_perangkat = '$media_type'";
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

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    body { background-color: #F8FAFC; }
</style>

<div class="p-4 lg:p-8 max-w-4xl mx-auto pb-24">
    <!-- Header Section -->
    <div class="mb-8">
        <h1 class="text-3xl font-black italic text-slate-800 uppercase tracking-tighter">Media & Buku Digital</h1>
        <p class="text-slate-400 font-bold text-[10px] uppercase tracking-[0.3em] mt-2">Pusat Belajar Kelas <?= htmlspecialchars($siswa['nama_kelas']) ?></p>
    </div>

    <!-- Enhanced Filter Form -->
    <form action="" method="GET" class="mb-10 space-y-4">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="relative group flex-1">
                <input type="text" name="media_search" value="<?= htmlspecialchars($media_search) ?>" placeholder="Cari materi atau nama guru..."
                    class="w-full pl-12 pr-4 py-4 bg-white rounded-[24px] border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 transition-all text-sm font-bold shadow-sm">
                <i class="fa fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-indigo-500 transition-colors"></i>
            </div>

            <div class="flex gap-2">
                <select name="media_type" onchange="this.form.submit()"
                    class="px-6 py-4 bg-white rounded-[24px] border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 transition-all text-xs font-black uppercase tracking-widest shadow-sm appearance-none cursor-pointer pr-12 relative">
                    <option value="">Semua Jenis</option>
                    <option value="Buku Digital" <?= $media_type == 'Buku Digital' ? 'selected' : '' ?>>Buku Digital</option>
                    <option value="Media Pembelajaran" <?= $media_type == 'Media Pembelajaran' ? 'selected' : '' ?>>Media Pembelajaran</option>
                </select>

                <?php if ($media_search || $media_type): ?>
                    <a href="media.php" class="w-14 h-14 bg-rose-50 text-rose-500 rounded-[20px] flex items-center justify-center hover:bg-rose-100 transition-all shadow-sm">
                        <i class="fa fa-times-circle text-xl"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 px-2">
            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic mr-2">Filter Populer:</span>
            <button type="button" onclick="document.querySelector('select[name=media_type]').value='Buku Digital'; this.form.submit();"
                class="px-4 py-1.5 rounded-full border border-slate-200 text-[9px] font-black uppercase tracking-widest text-slate-600 hover:bg-slate-900 hover:text-white transition-all">PDF Books</button>
            <button type="button" onclick="document.querySelector('select[name=media_type]').value='Media Pembelajaran'; this.form.submit();"
                class="px-4 py-1.5 rounded-full border border-slate-200 text-[9px] font-black uppercase tracking-widest text-slate-600 hover:bg-slate-900 hover:text-white transition-all">Video MP4</button>
        </div>
    </form>

    <!-- Media List -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
        <?php if (mysqli_num_rows($media_list) > 0): ?>
            <?php while ($m = mysqli_fetch_assoc($media_list)): ?>
                <div class="lux-card p-6 bg-white border-none shadow-2xl shadow-slate-100/50 flex flex-col gap-5 hover:translate-y-[-4px] transition-all group rounded-[32px]">
                    <div class="flex items-start justify-between">
                        <div class="w-16 h-16 rounded-[24px] bg-slate-50 text-slate-400 flex items-center justify-center text-3xl group-hover:bg-indigo-600 group-hover:text-white transition-all duration-500 shrink-0 shadow-inner">
                            <?php
                            $icon = 'fa-file-alt';
                            if (strpos($m['file_path'], '.pdf') !== false) $icon = 'fa-file-pdf';
                            elseif (strpos($m['file_path'], '.mp4') !== false) $icon = 'fa-file-video';
                            ?>
                            <i class="fa <?= $icon ?>"></i>
                        </div>
                        <div class="text-right">
                            <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-[8px] font-black uppercase tracking-widest"><?= date('d M Y', strtotime($m['created_at'])) ?></span>
                        </div>
                    </div>

                    <div class="flex-1">
                        <h4 class="font-black text-slate-800 text-lg leading-tight italic uppercase tracking-tighter"><?= htmlspecialchars($m['nama_perangkat']) ?></h4>
                        <div class="flex items-center gap-2 mt-3">
                            <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-[10px] font-black italic">G</div>
                            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest"><?= htmlspecialchars($m['nama_guru']) ?></p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                        <span class="px-4 py-1.5 rounded-xl bg-indigo-50 text-indigo-600 text-[9px] font-black uppercase tracking-widest"><?= $m['jenis_perangkat'] ?></span>
                        <a href="<?= BASE_URL . $m['file_path'] ?>" target="_blank"
                           class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-900 text-white hover:bg-indigo-600 transition-all shadow-xl active:scale-90">
                            <span class="text-[10px] font-black uppercase tracking-widest">Buka</span>
                            <i class="fa <?= strpos($m['file_path'], '.mp4') !== false ? 'fa-play' : 'fa-download' ?> text-[10px]"></i>
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-span-full py-24 text-center lux-card bg-white border-dashed border-2 border-slate-200 shadow-none rounded-[40px]">
                <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
                    <i class="fa fa-folder-open text-5xl text-slate-200"></i>
                </div>
                <h3 class="text-slate-800 font-black italic text-xl uppercase tracking-widest">Kosong</h3>
                <p class="text-slate-400 font-bold italic tracking-widest text-[10px] uppercase mt-2">Tidak ada materi yang sesuai dengan filter Anda</p>
                <a href="media.php" class="inline-block mt-6 px-8 py-3 bg-slate-900 text-white rounded-full text-[10px] font-black uppercase tracking-widest">Reset Filter</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <div class="no-print flex justify-center mt-12">
        <?= render_pagination($pagin_media['page'], $pagin_media['total_pages'], $_GET) ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
