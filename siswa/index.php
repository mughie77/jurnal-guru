<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];

// Get Student Profile and Active Class
$query_profile = "SELECT s.*, k.id as kelas_id, k.nama_kelas, tp.tahun as tahun_pelajaran
                  FROM siswa s
                  JOIN siswa_kelas sk ON s.id = sk.siswa_id
                  JOIN kelas k ON sk.kelas_id = k.id
                  JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id
                  WHERE s.id = ? AND tp.status = 'aktif'";
$stmt = mysqli_prepare($conn, $query_profile);
mysqli_stmt_bind_param($stmt, "i", $siswa_id);
mysqli_stmt_execute($stmt);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Fetch active class schedule from jadwal_pelajaran
$kid = (int)($siswa['kelas_id'] ?? 0);
$schedules_by_day = [
    'Senin' => [],
    'Selasa' => [],
    'Rabu' => [],
    'Kamis' => [],
    'Jumat' => [],
    'Sabtu' => []
];

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

$today_class_schedules = [];

if ($kid > 0) {
    $q_s = "SELECT jp.*, mp.nama_mapel, mp.kode_mapel, u.nama_lengkap as nama_guru
            FROM jadwal_pelajaran jp
            JOIN mata_pelajaran mp ON jp.mapel_id = mp.id
            JOIN guru g ON jp.guru_id = g.id
            JOIN users u ON g.user_id = u.id
            WHERE jp.kelas_id = $kid
            ORDER BY FIELD(jp.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'), jp.jam_ke ASC";
    $res_s = mysqli_query($conn, $q_s);
    if ($res_s) {
        while ($r_s = mysqli_fetch_assoc($res_s)) {
            if (isset($schedules_by_day[$r_s['hari']])) {
                $schedules_by_day[$r_s['hari']][] = $r_s;
            } else {
                $schedules_by_day[$r_s['hari']] = [$r_s];
            }
        }
    }

    $q_today_siswa = "SELECT jp.*, mp.nama_mapel, mp.kode_mapel, u.nama_lengkap as nama_guru
                      FROM jadwal_pelajaran jp
                      JOIN mata_pelajaran mp ON jp.mapel_id = mp.id
                      JOIN guru g ON jp.guru_id = g.id
                      JOIN users u ON g.user_id = u.id
                      WHERE jp.kelas_id = $kid AND jp.hari = '$hari_ini'
                      ORDER BY jp.jam_mulai ASC, jp.jam_ke ASC";
    $res_today_siswa = mysqli_query($conn, $q_today_siswa);
    if ($res_today_siswa) {
        while ($r = mysqli_fetch_assoc($res_today_siswa)) {
            $today_class_schedules[] = $r;
        }
    }
}

// Check active PKL Mapping safely
$pkl_active = null;
$chk_spkl = mysqli_query($conn, "SHOW TABLES LIKE 'siswa_pkl'");
if ($chk_spkl && mysqli_num_rows($chk_spkl) > 0) {
    $q_pkl_check = "SELECT sp.*, tp.nama_tempat, tp.pembimbing_dudi, u.nama_lengkap as nama_guru_pembimbing
                    FROM siswa_pkl sp
                    JOIN tempat_pkl tp ON sp.tempat_pkl_id = tp.id
                    LEFT JOIN guru g ON tp.guru_pembimbing_id = g.id
                    LEFT JOIN users u ON g.user_id = u.id
                    WHERE sp.siswa_id = $siswa_id AND sp.tahun_pelajaran_id = $active_tahun_id AND sp.status = 'aktif'
                    LIMIT 1";
    $res_pkl = mysqli_query($conn, $q_pkl_check);
    if ($res_pkl && mysqli_num_rows($res_pkl) > 0) {
        $pkl_active = mysqli_fetch_assoc($res_pkl);
    }
}

// GPS Attendance Recap (Harian)
$query_gps = "SELECT
              SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
              SUM(CASE WHEN status = 'Terlambat' THEN 1 ELSE 0 END) as terlambat,
              SUM(CASE WHEN status = 'Sakit' THEN 1 ELSE 0 END) as sakit,
              SUM(CASE WHEN status = 'Izin' THEN 1 ELSE 0 END) as izin,
              SUM(CASE WHEN status = 'Alfa' THEN 1 ELSE 0 END) as alfa
              FROM absensi_harian
              WHERE siswa_id = ? AND (MONTH(tanggal) = MONTH(CURRENT_DATE()) AND YEAR(tanggal) = YEAR(CURRENT_DATE()))";
$stmt_gps = mysqli_prepare($conn, $query_gps);
mysqli_stmt_bind_param($stmt_gps, "i", $siswa_id);
mysqli_stmt_execute($stmt_gps);
$gps_recap = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_gps));

$page_title = "Dashboard Siswa";
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
                <p class="text-slate-400 text-sm font-medium tracking-wide">Howdy,</p>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight leading-tight">
                    <?= htmlspecialchars($siswa['nama_siswa']) ?>
                </h1>
                <p class="text-xs font-bold text-indigo-600 mt-0.5">Kelas <?= htmlspecialchars($siswa['nama_kelas']) ?></p>
            </div>
            <a href="profil.php" class="w-14 h-14 rounded-2xl bg-indigo-600 border-2 border-white shadow-md overflow-hidden flex items-center justify-center shrink-0 hover:scale-105 transition-transform">
                <?php if(!empty($siswa['foto'])): ?>
                    <img src="<?= BASE_URL ?>uploads/siswa/<?= $siswa['foto'] ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <span class="text-white text-xl font-extrabold"><?= strtoupper(substr($siswa['nama_siswa'], 0, 1)) ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Search Bar -->
        <div class="relative">
            <input type="text" id="menuSearchInput" onkeyup="filterMenus()" placeholder="Search menu, mapel, izin..."
                   class="w-full pl-5 pr-14 py-3.5 bg-white rounded-2xl border border-slate-200 shadow-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 text-sm font-medium text-slate-700 placeholder-slate-400 transition-all">
            <button type="button" class="absolute right-2 top-2 bottom-2 w-10 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl flex items-center justify-center transition-colors shadow-sm">
                <i class="fa fa-search text-xs"></i>
            </button>
        </div>

        <!-- Menu Categories (Tile Menu Grid) -->
        <div>
            <div class="flex items-center justify-between mb-3.5">
                <h3 class="font-bold text-slate-800 text-base tracking-tight">Kategori Menu</h3>
                <a href="semua_menu.php" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">Lihat Semua</a>
            </div>

            <div class="grid grid-cols-4 sm:grid-cols-4 md:grid-cols-8 gap-3 sm:gap-4" id="menuGrid">
                <!-- Tile 1 -->
                <button type="button" onclick="openModal('jadwalKelasModal')" class="menu-item flex flex-col items-center group text-center" data-title="jadwal pelajaran kelas">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-indigo-500 text-white flex items-center justify-center text-xl shadow-md group-hover:scale-105 transition-transform">
                        <i class="fa fa-calendar-alt"></i>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 mt-2 line-clamp-1">Jadwal</span>
                </button>

                <!-- Tile 2 -->
                <a href="izin.php" class="menu-item flex flex-col items-center group text-center" data-title="pengajuan izin sakit keperluan">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-purple-500 text-white flex items-center justify-center text-xl shadow-md group-hover:scale-105 transition-transform">
                        <i class="fa fa-envelope-open-text"></i>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 mt-2 line-clamp-1">Izin</span>
                </a>

                <!-- Tile 3 -->
                <a href="media.php" class="menu-item flex flex-col items-center group text-center" data-title="media pembelajaran buku modul">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-amber-500 text-white flex items-center justify-center text-xl shadow-md group-hover:scale-105 transition-transform">
                        <i class="fa fa-book-reader"></i>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 mt-2 line-clamp-1">Media</span>
                </a>

                <!-- Tile 4 -->
                <a href="berkas.php" class="menu-item flex flex-col items-center group text-center" data-title="berkas kk ijazah wa ortu">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-emerald-500 text-white flex items-center justify-center text-xl shadow-md group-hover:scale-105 transition-transform">
                        <i class="fa fa-file-upload"></i>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 mt-2 line-clamp-1">Berkas</span>
                </a>

                <!-- Tile 5 -->
                <a href="absensi.php" class="menu-item flex flex-col items-center group text-center" data-title="presensi absensi gps kehadiran">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-rose-500 text-white flex items-center justify-center text-xl shadow-md group-hover:scale-105 transition-transform">
                        <i class="fa fa-map-marker-alt"></i>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 mt-2 line-clamp-1">Presensi</span>
                </a>

                <!-- Tile 6 -->
                <a href="konsultasi.php" class="menu-item flex flex-col items-center group text-center" data-title="konsultasi bk bimbingan konseling">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-teal-500 text-white flex items-center justify-center text-xl shadow-md group-hover:scale-105 transition-transform">
                        <i class="fa fa-user-friends"></i>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 mt-2 line-clamp-1">Konsultasi</span>
                </a>

                <!-- Tile 7 -->
                <a href="kontak.php" class="menu-item flex flex-col items-center group text-center" data-title="kontak guru wali kelas bk">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-sky-500 text-white flex items-center justify-center text-xl shadow-md group-hover:scale-105 transition-transform">
                        <i class="fa fa-phone-alt"></i>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 mt-2 line-clamp-1">Kontak</span>
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
                <span class="text-xs font-bold text-slate-400"><?= count($today_class_schedules) ?> Mapel</span>
            </div>

            <?php if (count($today_class_schedules) > 0): ?>
                <div class="space-y-3.5" id="scheduleList">
                    <?php foreach ($today_class_schedules as $item): ?>
                        <div class="schedule-item bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm space-y-3 hover:border-indigo-300 transition-all" data-title="<?= strtolower($item['nama_mapel'] . ' ' . $item['nama_guru']) ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-base shrink-0 font-bold">
                                        <i class="fa fa-book"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-extrabold text-slate-800 text-base leading-tight"><?= htmlspecialchars($item['nama_mapel']) ?></h4>
                                        <p class="text-xs text-slate-500 font-medium mt-0.5 flex items-center gap-1">
                                            <i class="fa fa-user-tie text-indigo-500 text-[10px]"></i> <?= htmlspecialchars($item['nama_guru']) ?>
                                        </p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-mono font-bold bg-slate-100 text-slate-600">
                                    <?= htmlspecialchars($item['kode_mapel']) ?>
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-3 pt-1">
                                <div class="p-3 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-500 text-white flex flex-col justify-between">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-white/80">Waktu Pelajaran</span>
                                    <span class="text-sm font-extrabold mt-0.5">
                                        <?php if (!empty($item['jam_mulai'])): ?>
                                            <?= date('H:i', strtotime($item['jam_mulai'])) ?> WIB
                                        <?php else: ?>
                                            Jam Ke-<?= htmlspecialchars($item['jam_ke']) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="p-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white flex flex-col justify-between">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-white/80">Status Hari Ini</span>
                                    <span class="text-xs font-bold mt-0.5 flex items-center gap-1">
                                        <i class="fa fa-check-circle text-[10px]"></i> Sesuai Jadwal
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-6 bg-white border border-slate-200/80 rounded-2xl text-center shadow-sm">
                    <i class="fa fa-calendar-check text-slate-300 text-3xl mb-2"></i>
                    <p class="text-xs text-slate-500 font-medium">Tidak ada jadwal pelajaran terdaftar untuk hari <?= $hari_ini ?>.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pengumuman Terbaru -->
        <?php
        $q_announcements = mysqli_query($conn, "SELECT * FROM pengumuman WHERE target IN ('semua', 'siswa') ORDER BY created_at DESC LIMIT 3");
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
                            <?= $ann['target'] == 'siswa' ? 'Khusus Siswa' : 'Semua' ?>
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

        <!-- Rekap Kehadiran Bulan Ini -->
        <div>
            <h3 class="font-bold text-slate-800 text-base tracking-tight mb-3">Kehadiran Bulan Ini</h3>
            <div class="grid grid-cols-5 gap-2">
                <div class="bg-white border border-slate-200/80 rounded-xl p-2.5 text-center shadow-sm">
                    <div class="text-lg font-extrabold text-emerald-600"><?= $gps_recap['hadir'] ?? 0 ?></div>
                    <p class="text-[9px] font-bold text-slate-400 uppercase mt-0.5">Hadir</p>
                </div>
                <div class="bg-white border border-slate-200/80 rounded-xl p-2.5 text-center shadow-sm">
                    <div class="text-lg font-extrabold text-amber-500"><?= $gps_recap['terlambat'] ?? 0 ?></div>
                    <p class="text-[9px] font-bold text-slate-400 uppercase mt-0.5">Telat</p>
                </div>
                <div class="bg-white border border-slate-200/80 rounded-xl p-2.5 text-center shadow-sm">
                    <div class="text-lg font-extrabold text-blue-500"><?= $gps_recap['sakit'] ?? 0 ?></div>
                    <p class="text-[9px] font-bold text-slate-400 uppercase mt-0.5">Sakit</p>
                </div>
                <div class="bg-white border border-slate-200/80 rounded-xl p-2.5 text-center shadow-sm">
                    <div class="text-lg font-extrabold text-indigo-500"><?= $gps_recap['izin'] ?? 0 ?></div>
                    <p class="text-[9px] font-bold text-slate-400 uppercase mt-0.5">Izin</p>
                </div>
                <div class="bg-white border border-slate-200/80 rounded-xl p-2.5 text-center shadow-sm">
                    <div class="text-lg font-extrabold text-rose-500"><?= $gps_recap['alfa'] ?? 0 ?></div>
                    <p class="text-[9px] font-bold text-slate-400 uppercase mt-0.5">Alfa</p>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Jadwal Pelajaran Kelas -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<div id="jadwalKelasModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-3xl max-h-[85vh] overflow-y-auto bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="bg-indigo-600 px-6 py-5 text-white font-bold text-lg sticky top-0 z-10 flex justify-between items-center">
        <div class="flex items-center gap-2">
            <i class="fa fa-calendar-alt"></i>
            <span>Jadwal Pelajaran Kelas <?= htmlspecialchars($siswa['nama_kelas']) ?></span>
        </div>
        <button type="button" onclick="closeModal('jadwalKelasModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>

    <div class="p-6 space-y-6">
        <?php
        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $day_badges = [
            'Senin' => 'bg-blue-600 text-white',
            'Selasa' => 'bg-indigo-600 text-white',
            'Rabu' => 'bg-purple-600 text-white',
            'Kamis' => 'bg-pink-600 text-white',
            'Jumat' => 'bg-emerald-600 text-white',
            'Sabtu' => 'bg-amber-600 text-white'
        ];
        $total_schedules = 0;
        foreach ($days as $d) {
            $total_schedules += count($schedules_by_day[$d] ?? []);
        }
        ?>

        <?php if ($total_schedules === 0): ?>
            <div class="p-8 text-center bg-slate-50 border border-slate-200 rounded-2xl text-slate-400 font-medium italic text-xs">
                Belum ada jadwal pelajaran terdaftar untuk kelas <?= htmlspecialchars($siswa['nama_kelas']) ?>.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($days as $day):
                    $day_list = $schedules_by_day[$day] ?? [];
                ?>
                    <div class="border border-slate-200 rounded-2xl overflow-hidden bg-slate-50/50">
                        <div class="px-4 py-2.5 bg-slate-900 text-white font-bold text-xs flex items-center justify-between">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $day_badges[$day] ?>"><?= $day ?></span>
                            <span class="text-[10px] text-slate-400 font-normal"><?= count($day_list) ?> Mapel</span>
                        </div>
                        <div class="p-3 space-y-2">
                            <?php if (empty($day_list)): ?>
                                <p class="text-[10px] text-slate-400 italic text-center py-2">Tidak ada jadwal.</p>
                            <?php else: ?>
                                <?php foreach ($day_list as $item): ?>
                                    <div class="p-2.5 bg-white border border-slate-200/80 rounded-xl space-y-1">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[9px] font-bold uppercase text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">Jam Ke: <?= htmlspecialchars($item['jam_ke']) ?></span>
                                            <span class="text-[10px] font-mono text-slate-400"><?= htmlspecialchars($item['kode_mapel']) ?></span>
                                        </div>
                                        <div class="text-xs font-bold text-slate-800 leading-tight">
                                            <?= htmlspecialchars($item['nama_mapel']) ?>
                                        </div>
                                        <div class="text-[10px] text-slate-500 font-medium flex items-center gap-1">
                                            <i class="fa fa-user-tie text-indigo-400 text-[9px]"></i>
                                            <span><?= htmlspecialchars($item['nama_guru']) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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

function openModal(id) {
    const modal = document.getElementById(id);
    const overlay = document.getElementById('modalOverlay');
    overlay.classList.remove('hidden');
    modal.classList.remove('hidden');
    setTimeout(() => {
        overlay.classList.add('opacity-100');
        modal.classList.remove('scale-95', 'opacity-0');
    }, 10);
}

function closeModal(id) {
    const modal = document.getElementById(id);
    const overlay = document.getElementById('modalOverlay');
    overlay.classList.remove('opacity-100');
    modal.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        overlay.classList.add('hidden');
        modal.classList.add('hidden');
    }, 300);
}

function closeAllModals() {
    document.querySelectorAll('.modal-content').forEach(m => {
        if (!m.classList.contains('hidden')) closeModal(m.id);
    });
}

function showAnnouncementModal(ann) {
    let lampiranHtml = '';
    if (ann.file_lampiran) {
        lampiranHtml = `<div class="mt-4 pt-4 border-t border-slate-100 text-left">
            <a href="<?= BASE_URL ?>uploads/pengumuman/${ann.file_lampiran}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-600 font-bold rounded-xl text-xs hover:bg-indigo-100 transition-colors">
                <i class="fa fa-paperclip"></i> Unduh Lampiran Berkas
            </a>
        </div>`;
    }

    Swal.fire({
        title: ann.judul,
        html: `<div class="text-left text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">${new Date(ann.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</div>
               <div class="text-left text-sm text-slate-700 leading-relaxed whitespace-pre-line max-h-[60vh] overflow-y-auto pr-1">${ann.isi}</div>
               ${lampiranHtml}`,
        confirmButtonColor: '#4f46e5',
        confirmButtonText: 'Tutup',
        customClass: { popup: 'rounded-3xl max-w-[95vw] sm:max-w-xl w-full p-4 sm:p-6', title: 'font-bold text-left text-slate-800 text-lg' }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
