<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id, foto FROM guru WHERE user_id = $user_id");
$g_data = mysqli_fetch_assoc($guru_res);
$guru_id = $g_data['id'];
$guru_foto = $g_data['foto'];

// Stats for Dashboard
$total_jurnal = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM jurnal WHERE guru_id = $guru_id"))['total'];
$hadir_avg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(jml_hadir) as avg FROM jurnal WHERE guru_id = $guru_id"))['avg'];

// Fetch today's schedule for teacher
$days_map = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];
$hari_ini = $days_map[date('l')];
$today_date = date('Y-m-d');

$q_today_sched = mysqli_query($conn, "SELECT jp.*, k.nama_kelas, m.nama_mapel,
                    (SELECT COUNT(*) FROM jurnal j WHERE j.guru_id = jp.guru_id AND j.kelas_id = jp.kelas_id AND j.tanggal = '$today_date' AND (j.jadwal_id = jp.id OR (j.jadwal_id IS NULL AND j.mapel_id = jp.mapel_id))) as total_jurnal
                  FROM jadwal_pelajaran jp
                  JOIN kelas k ON jp.kelas_id = k.id
                  JOIN mata_pelajaran m ON jp.mapel_id = m.id
                  WHERE jp.guru_id = $guru_id AND jp.hari = '$hari_ini' AND (jp.tahun_pelajaran_id = $active_tahun_id OR jp.tahun_pelajaran_id IS NULL)
                  ORDER BY jp.jam_mulai ASC, jp.jam_ke ASC");

$today_schedules = [];
if ($q_today_sched) {
    while ($sched = mysqli_fetch_assoc($q_today_sched)) {
        $today_schedules[] = $sched;
    }
}

// Check PKL Pembimbing Status safely
$is_pkl_pembimbing = false;
$chk_tpkl = mysqli_query($conn, "SHOW TABLES LIKE 'tempat_pkl'");
if ($chk_tpkl && mysqli_num_rows($chk_tpkl) > 0) {
    $q_pkl_guru = mysqli_query($conn, "SELECT COUNT(*) as count FROM tempat_pkl WHERE guru_pembimbing_id = $guru_id");
    if ($q_pkl_guru) {
        $is_pkl_pembimbing = (mysqli_fetch_assoc($q_pkl_guru)['count'] ?? 0) > 0;
    }
}

$page_title = "Dashboard Guru";

// Compute time greeting
$hour = (int)date('H');
$greeting = "Selamat Malam";
if ($hour >= 5 && $hour < 11) {
    $greeting = "Selamat Pagi";
} elseif ($hour >= 11 && $hour < 15) {
    $greeting = "Selamat Siang";
} elseif ($hour >= 15 && $hour < 18) {
    $greeting = "Selamat Sore";
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    body { background-color: #F8FAFC; font-family: 'Plus Jakarta Sans', sans-serif; }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 sm:p-6 lg:p-8 max-w-xl sm:max-w-4xl lg:max-w-6xl mx-auto space-y-6">

        <!-- Top Greeting & Profile Header -->
        <div class="flex items-center justify-between pt-2">
            <div>
                <p class="text-slate-400 text-sm font-medium tracking-wide"><?= $greeting ?>,</p>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight leading-tight">
                    <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>
                </h1>
            </div>
            <a href="profil.php" class="w-14 h-14 rounded-full bg-indigo-600 border-2 border-white shadow-md overflow-hidden flex items-center justify-center shrink-0 hover:scale-105 transition-transform">
                <?php if(!empty($guru_foto)): ?>
                    <img src="<?= BASE_URL ?>uploads/guru/<?= $guru_foto ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <i class="fa fa-user-tie text-white text-xl"></i>
                <?php endif; ?>
            </a>
        </div>

        <!-- Search Bar -->
        <div class="relative">
            <input type="text" id="menuSearchInput" onkeyup="filterMenus()" placeholder="Search menu, jadwal, rekap..."
                   class="w-full pl-5 pr-14 py-3.5 bg-white rounded-2xl border border-slate-200 shadow-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 text-sm font-medium text-slate-700 placeholder-slate-400 transition-all">
            <button type="button" class="absolute right-2 top-2 bottom-2 w-10 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl flex items-center justify-center transition-colors shadow-sm">
                <i class="fa fa-search text-xs"></i>
            </button>
        </div>

        <!-- Menu Categories (Tile Menu) -->
        <div>
            <div class="flex items-center justify-between mb-3.5">
                <h3 class="font-bold text-slate-800 text-base tracking-tight">Kategori Menu</h3>
                <a href="semua_menu.php" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">Lihat Semua</a>
            </div>

            <div class="grid grid-cols-4 sm:grid-cols-4 md:grid-cols-8 gap-3 sm:gap-4" id="menuGrid">
                <!-- Tile 1 -->
                <a href="isi_absensi.php" class="menu-item flex flex-col items-center group text-center" data-title="isi jurnal absensi agenda mengajar">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-indigo-500 text-white flex items-center justify-center text-xl shadow-md group-hover:scale-105 transition-transform">
                        <i class="fa fa-edit"></i>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 mt-2 line-clamp-1">Isi Jurnal</span>
                </a>

                <!-- Tile 2 -->
                <a href="rekap_absen.php" class="menu-item flex flex-col items-center group text-center" data-title="rekap absensi kehadiran siswa">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-amber-500 text-white flex items-center justify-center text-xl shadow-md group-hover:scale-105 transition-transform">
                        <i class="fa fa-chart-line"></i>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 mt-2 line-clamp-1">Rekap Absen</span>
                </a>

                <!-- Tile 3 -->
                <a href="riwayat.php" class="menu-item flex flex-col items-center group text-center" data-title="riwayat jurnal arsip agenda">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-teal-500 text-white flex items-center justify-center text-xl shadow-md group-hover:scale-105 transition-transform">
                        <i class="fa fa-history"></i>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 mt-2 line-clamp-1">Riwayat</span>
                </a>

                <!-- Tile 4 -->
                <a href="perangkat.php" class="menu-item flex flex-col items-center group text-center" data-title="perangkat ajar modul buku rpp">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-rose-500 text-white flex items-center justify-center text-xl shadow-md group-hover:scale-105 transition-transform">
                        <i class="fa fa-folder-open"></i>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 mt-2 line-clamp-1">Perangkat</span>
                </a>

                <!-- Tile 5 -->
                <a href="tugas_tidak_masuk.php" class="menu-item flex flex-col items-center group text-center" data-title="piket tugas kelas tidak masuk">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-sky-500 text-white flex items-center justify-center text-xl shadow-md group-hover:scale-105 transition-transform">
                        <i class="fa fa-clipboard-list"></i>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 mt-2 line-clamp-1">Tugas Piket</span>
                </a>

                <!-- Tile 6 -->
                <a href="rekap_buku_kejadian.php" class="menu-item flex flex-col items-center group text-center" data-title="buku kejadian insiden siswa">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-emerald-500 text-white flex items-center justify-center text-xl shadow-md group-hover:scale-105 transition-transform">
                        <i class="fa fa-book-medical"></i>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 mt-2 line-clamp-1">Kejadian</span>
                </a>

                <!-- Tile 7 -->
                <a href="rekap_pengaduan.php" class="menu-item flex flex-col items-center group text-center" data-title="pengaduan siswa konseling">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-purple-500 text-white flex items-center justify-center text-xl shadow-md group-hover:scale-105 transition-transform">
                        <i class="fa fa-comments"></i>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 mt-2 line-clamp-1">Pengaduan</span>
                </a>

                <!-- Tile 8 -->
                <a href="semua_menu.php" class="menu-item flex flex-col items-center group text-center" data-title="semua menu fitur lengkap">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-slate-800 text-white flex items-center justify-center text-xl shadow-md group-hover:scale-105 transition-transform">
                        <i class="fa fa-th-large"></i>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 mt-2 line-clamp-1">Lainnya</span>
                </a>
            </div>
        </div>

        <!-- Jadwal Pelajaran Hari Ini (Dibawah Tile Menu) -->
        <div id="jadwalSection">
            <div class="flex items-center justify-between mb-3.5">
                <h3 class="font-bold text-slate-800 text-base tracking-tight flex items-center gap-2">
                    Jadwal Pelajaran Hari Ini <span class="text-xs px-2.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700 font-bold"><?= $hari_ini ?></span>
                </h3>
                <span class="text-xs font-bold text-slate-400"><?= count($today_schedules) ?> Sesi</span>
            </div>

            <?php if (!empty($today_schedules)): ?>
                <div class="space-y-3.5" id="scheduleList">
                    <?php foreach ($today_schedules as $sched):
                        $is_filled = ((int)$sched['total_jurnal']) > 0;
                    ?>
                        <div class="schedule-item bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm space-y-3 hover:border-indigo-300 transition-all" data-title="<?= strtolower($sched['nama_kelas'] . ' ' . $sched['nama_mapel']) ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-base shrink-0 font-bold">
                                        <i class="fa fa-chalkboard-teacher"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-extrabold text-slate-800 text-base leading-tight"><?= htmlspecialchars($sched['nama_kelas']) ?></h4>
                                        <p class="text-xs text-slate-500 font-medium mt-0.5"><?= htmlspecialchars($sched['nama_mapel']) ?></p>
                                    </div>
                                </div>

                                <div>
                                    <?php if ($is_filled): ?>
                                        <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 flex items-center gap-1">
                                            <i class="fa fa-check-circle"></i> Selesai
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800 flex items-center gap-1">
                                            <i class="fa fa-clock"></i> Belum Diisi
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <div class="p-3 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-500 text-white flex flex-col justify-between">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-white/80">Waktu / Jam</span>
                                    <span class="text-sm font-extrabold mt-0.5">
                                        <?php if (!empty($sched['jam_mulai'])): ?>
                                            <?= date('H:i', strtotime($sched['jam_mulai'])) ?> WIB
                                        <?php else: ?>
                                            Jam Ke-<?= htmlspecialchars($sched['jam_ke']) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="p-3 rounded-xl bg-gradient-to-r from-pink-500 to-rose-500 text-white flex flex-col justify-between">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-white/80">Aksi Jurnal</span>
                                    <?php if ($is_filled): ?>
                                        <span class="text-xs font-bold mt-0.5 text-white/90">Terisi</span>
                                    <?php else: ?>
                                        <a href="isi_absensi.php" class="text-xs font-extrabold mt-0.5 underline hover:text-white flex items-center gap-1">
                                            Isi Sekarang <i class="fa fa-arrow-right text-[10px]"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-6 bg-white border border-slate-200/80 rounded-2xl text-center shadow-sm">
                    <i class="fa fa-calendar-check text-slate-300 text-3xl mb-2"></i>
                    <p class="text-xs text-slate-500 font-medium">Tidak ada jadwal mengajar terdaftar untuk hari <?= $hari_ini ?>.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pengumuman Terbaru -->
        <?php
        $q_announcements = mysqli_query($conn, "SELECT * FROM pengumuman WHERE target IN ('semua', 'guru') ORDER BY created_at DESC LIMIT 3");
        if (mysqli_num_rows($q_announcements) > 0):
        ?>
        <div id="pengumumanSection">
            <h3 class="font-bold text-slate-800 text-base tracking-tight mb-3 flex items-center gap-2">
                <i class="fa fa-bullhorn text-indigo-600"></i> Pengumuman Terbaru
            </h3>
            <div class="space-y-3">
                <?php while ($ann = mysqli_fetch_assoc($q_announcements)): ?>
                <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm hover:border-indigo-300 transition-all">
                    <div class="flex items-center justify-between gap-2 mb-1.5">
                        <span class="px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider bg-indigo-50 text-indigo-600 border border-indigo-100">
                            <?= $ann['target'] == 'guru' ? 'Khusus Guru' : 'Semua' ?>
                        </span>
                        <span class="text-[10px] text-slate-400 font-medium">
                            <?= date('d M Y', strtotime($ann['created_at'])) ?>
                        </span>
                    </div>
                    <h4 class="font-bold text-slate-800 text-sm mb-1"><?= htmlspecialchars($ann['judul']) ?></h4>
                    <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed mb-2"><?= htmlspecialchars($ann['isi']) ?></p>
                    <button type="button" onclick='showAnnouncementModal(<?= htmlspecialchars(json_encode($ann), ENT_QUOTES, 'UTF-8') ?>)' class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                        Baca Selengkapnya <i class="fa fa-arrow-right text-[9px]"></i>
                    </button>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Summary Stats -->
        <div class="grid grid-cols-2 gap-3 pt-2">
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm text-center">
                <p class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Total Jurnal</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1"><?= number_format($total_jurnal) ?></h3>
            </div>

            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm text-center">
                <p class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Rata Kehadiran</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1"><?= round((float)($hadir_avg ?? 0), 1) ?></h3>
            </div>
        </div>

    </div>
</div>

<script>
function filterMenus() {
    const query = document.getElementById('menuSearchInput').value.toLowerCase().trim();
    const menuItems = document.querySelectorAll('.menu-item');
    const scheduleItems = document.querySelectorAll('.schedule-item');

    menuItems.forEach(item => {
        const title = item.getAttribute('data-title') || '';
        if (title.includes(query)) {
            item.classList.remove('hidden');
        } else {
            item.classList.add('hidden');
        }
    });

    scheduleItems.forEach(item => {
        const title = item.getAttribute('data-title') || '';
        if (title.includes(query)) {
            item.classList.remove('hidden');
        } else {
            item.classList.add('hidden');
        }
    });
}

function showAnnouncementModal(ann) {
    let lampiranHtml = '';
    if (ann.file_lampiran) {
        lampiranHtml = `<div class="mt-4 pt-4 border-t border-slate-100 text-left">
            <a href="<?= BASE_URL ?>uploads/pengumuman/${ann.file_lampiran}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-600 font-semibold rounded-xl text-xs hover:bg-indigo-100 transition-colors">
                <i class="fa fa-paperclip"></i> Unduh Lampiran Berkas
            </a>
        </div>`;
    }

    Swal.fire({
        title: ann.judul,
        html: `<div class="text-left text-xs font-semibold text-slate-400 uppercase tracking-widest mb-3">${new Date(ann.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</div>
               <div class="text-left text-sm text-slate-700 leading-relaxed whitespace-pre-line max-h-[60vh] overflow-y-auto pr-1">${ann.isi}</div>
               ${lampiranHtml}`,
        confirmButtonColor: '#4f46e5',
        confirmButtonText: 'Tutup',
        customClass: { popup: 'rounded-3xl max-w-[95vw] sm:max-w-xl w-full p-4 sm:p-6', title: 'font-bold text-left text-slate-800 text-lg' }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
