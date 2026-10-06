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
    body { background-color: #F8FAFC; }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 sm:p-6 lg:p-8 max-w-full w-full mx-auto">

        <!-- Pengumuman Terbaru Widget -->
        <?php
        $q_announcements = mysqli_query($conn, "SELECT * FROM pengumuman WHERE target IN ('semua', 'siswa') ORDER BY created_at DESC LIMIT 3");
        if (mysqli_num_rows($q_announcements) > 0):
        ?>
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs flex items-center gap-2">
                    <i class="fa fa-bullhorn text-indigo-600"></i> Pengumuman Terbaru
                </h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php while ($ann = mysqli_fetch_assoc($q_announcements)): ?>
                <div class="lux-card p-5 bg-white border border-slate-200/80 rounded-2xl flex flex-col justify-between hover:border-indigo-300 transition-all">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider bg-indigo-50 text-indigo-600 border border-indigo-100">
                                <?= $ann['target'] == 'siswa' ? 'Khusus Siswa' : 'Semua' ?>
                            </span>
                            <span class="text-[10px] text-slate-400 font-medium">
                                <?= date('d M Y', strtotime($ann['created_at'])) ?>
                            </span>
                        </div>
                        <h4 class="font-bold text-slate-800 text-sm mb-1 line-clamp-1"><?= htmlspecialchars($ann['judul']) ?></h4>
                        <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed mb-3 font-normal"><?= htmlspecialchars($ann['isi']) ?></p>
                    </div>
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                        <button type="button" onclick='showAnnouncementModal(<?= htmlspecialchars(json_encode($ann), ENT_QUOTES, 'UTF-8') ?>)' class="font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                            Baca Selengkapnya <i class="fa fa-arrow-right text-[9px]"></i>
                        </button>
                        <?php if (!empty($ann['file_lampiran'])): ?>
                        <a href="<?= BASE_URL ?>uploads/pengumuman/<?= $ann['file_lampiran'] ?>" target="_blank" class="text-[10px] font-bold text-slate-500 bg-slate-100 hover:bg-slate-200 px-2 py-1 rounded-md flex items-center gap-1 transition-colors">
                            <i class="fa fa-paperclip text-indigo-500"></i> Lampiran
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-600 to-indigo-800 text-white relative overflow-hidden rounded-2xl">
            <div class="relative z-10 flex items-center gap-6">
                <div class="w-20 h-20 rounded-xl bg-white/20 backdrop-blur-md border border-white/30 flex items-center justify-center text-3xl font-bold shadow-inner overflow-hidden shrink-0">
                    <?php if(!empty($siswa['foto'])): ?>
                        <img src="<?= BASE_URL ?>uploads/siswa/<?= $siswa['foto'] ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?= strtoupper(substr($siswa['nama_siswa'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight leading-tight"><?= htmlspecialchars($siswa['nama_siswa']) ?></h1>
                    <p class="text-indigo-100 font-semibold text-xs tracking-widest mt-0.5"><?= htmlspecialchars($siswa['nis']) ?> / <?= htmlspecialchars($siswa['nisn'] ?? '-') ?></p>
                    <div class="flex items-center gap-2 mt-3">
                        <span class="px-3 py-1 bg-white/10 rounded-full text-[10px] font-bold uppercase tracking-widest border border-white/20"><?= htmlspecialchars($siswa['nama_kelas']) ?></span>
                        <span class="px-3 py-1 bg-emerald-400 text-emerald-950 rounded-full text-[10px] font-bold uppercase tracking-widest">Aktif</span>
                    </div>
                </div>
            </div>
            <i class="fa fa-user-graduate absolute -bottom-6 -right-6 text-9xl opacity-10"></i>
        </div>

        <!-- Today's Schedule Card Section for Student -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs flex items-center gap-2">
                    <i class="fa fa-calendar-day text-indigo-600"></i> Jadwal Pelajaran Hari Ini (<?= $hari_ini ?>)
                </h3>
                <span class="text-xs text-slate-500 font-semibold"><?= count($today_class_schedules) ?> Mapel</span>
            </div>

            <?php if (count($today_class_schedules) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($today_class_schedules as $item): ?>
                        <div class="lux-card p-4 bg-white border border-indigo-100/80 rounded-2xl flex flex-col justify-between space-y-3 hover:border-indigo-300 transition-all">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-1.5">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 flex items-center gap-1">
                                        <?php if (!empty($item['jam_mulai'])): ?>
                                            <i class="fa fa-clock text-[9px] text-indigo-500"></i> Pukul <?= date('H:i', strtotime($item['jam_mulai'])) ?> WIB |
                                        <?php endif; ?>
                                        Jam Ke: <?= htmlspecialchars($item['jam_ke']) ?>
                                    </span>
                                    <span class="text-[10px] font-mono text-slate-400 font-semibold"><?= htmlspecialchars($item['kode_mapel']) ?></span>
                                </div>
                                <h4 class="font-bold text-slate-800 text-sm leading-snug mb-1"><?= htmlspecialchars($item['nama_mapel']) ?></h4>
                                <p class="text-xs text-slate-500 font-medium flex items-center gap-1.5">
                                    <i class="fa fa-user-tie text-indigo-500 text-[10px]"></i> <?= htmlspecialchars($item['nama_guru']) ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-5 bg-white border border-slate-200/80 rounded-2xl text-center">
                    <i class="fa fa-calendar-check text-slate-300 text-2xl mb-1.5"></i>
                    <p class="text-xs text-slate-500 font-semibold">Tidak ada jadwal pelajaran untuk hari <?= $hari_ini ?>.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions Grid Design -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4 mb-10 no-print">
            <!-- Row 1 -->
            <button onclick="openModal('jadwalKelasModal')" class="p-4 sm:p-5 rounded-2xl bg-indigo-600 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-indigo-700 hover:shadow-lg text-left">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm shadow-inner">
                    <i class="fa fa-calendar-alt"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Jadwal Kelas</div>
                    <div class="text-[9px] font-normal text-indigo-100 mt-0.5">Jadwal pelajaran mingguan</div>
                </div>
            </button>

            <a href="izin.php" class="p-4 sm:p-5 rounded-2xl bg-violet-500 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-violet-600 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm shadow-inner">
                    <i class="fa fa-envelope-open-text"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Pengajuan Izin</div>
                    <div class="text-[9px] font-normal text-violet-100 mt-0.5">Sakit & Keperluan</div>
                </div>
            </a>

            <!-- Row 2 -->
            <a href="media.php" class="p-4 sm:p-5 rounded-2xl bg-amber-500 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-amber-600 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm shadow-inner">
                    <i class="fa fa-book-reader"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Media & Buku</div>
                    <div class="text-[9px] font-normal text-amber-100 mt-0.5">Digital learning</div>
                </div>
            </a>

            <a href="berkas.php" class="p-4 sm:p-5 rounded-2xl bg-cyan-500 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-cyan-600 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm shadow-inner">
                    <i class="fa fa-folder-open"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Berkas Saya</div>
                    <div class="text-[9px] font-normal text-cyan-100 mt-0.5">Upload KK & Ijazah</div>
                </div>
            </a>

            <!-- Row 3 -->
            <a href="profil.php" class="p-4 sm:p-5 rounded-2xl bg-emerald-500 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-emerald-600 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm shadow-inner">
                    <i class="fa fa-user-circle"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Profil Saya</div>
                    <div class="text-[9px] font-normal text-emerald-100 mt-0.5">Informasi pribadi</div>
                </div>
            </a>

            <a href="kritik_saran.php" class="p-4 sm:p-5 rounded-2xl bg-sky-500 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-sky-600 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm shadow-inner">
                    <i class="fa fa-comment-dots"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Kritik & Saran</div>
                    <div class="text-[9px] font-normal text-sky-100 mt-0.5">Umpan balik</div>
                </div>
            </a>

            <!-- Row 4 -->
            <a href="konsultasi.php" class="p-4 sm:p-5 rounded-2xl bg-indigo-500 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-indigo-600 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm shadow-inner">
                    <i class="fa fa-comments"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Konsultasi BK</div>
                    <div class="text-[9px] font-normal text-indigo-100 mt-0.5">Bimbingan online</div>
                </div>
            </a>

            <a href="kontak.php" class="p-4 sm:p-5 rounded-2xl bg-teal-500 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-teal-600 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm shadow-inner">
                    <i class="fa fa-phone-alt"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Kontak BK & Wali</div>
                    <div class="text-[9px] font-normal text-teal-100 mt-0.5">Direktori kontak</div>
                </div>
            </a>

            <a href="tugas_guru.php" class="p-4 sm:p-5 rounded-2xl bg-amber-600 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-amber-700 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm shadow-inner">
                    <i class="fa fa-tasks"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Tugas Guru</div>
                    <div class="text-[9px] font-normal text-amber-100 mt-0.5">Guru tidak masuk</div>
                </div>
            </a>

            <!-- Row 5 (Pengaduan Siswa) -->
            <a href="pengaduan.php" class="p-4 sm:p-5 rounded-2xl bg-rose-600 text-white shadow-md flex flex-col justify-between gap-2 group transition-all hover:bg-rose-700 hover:shadow-lg col-span-2 sm:col-span-3 relative overflow-hidden">
                <div class="relative z-10 flex items-center justify-between w-full">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-white text-rose-600 flex items-center justify-center text-base shadow-inner">
                            <i class="fa fa-bullhorn"></i>
                        </div>
                        <div>
                            <div class="text-xs sm:text-sm font-bold tracking-tight">Pengaduan Siswa</div>
                            <div class="text-[9px] font-normal text-rose-100 mt-0.5">Laporkan perundungan / masalah keamanan</div>
                        </div>
                    </div>
                    <i class="fa fa-shield-alt text-2xl opacity-20 mr-2"></i>
                </div>
            </a>

            <?php if ($pkl_active): ?>
            <!-- Row PKL -->
            <a href="pkl_jurnal.php" class="p-4 sm:p-5 rounded-2xl bg-slate-900 text-white shadow-md flex flex-col justify-between gap-2 group transition-all hover:bg-slate-800 hover:shadow-lg col-span-2 sm:col-span-3 relative overflow-hidden border border-indigo-400/40">
                <div class="relative z-10 flex items-center justify-between w-full">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-base shadow-inner">
                            <i class="fa fa-briefcase"></i>
                        </div>
                        <div>
                            <div class="text-xs sm:text-sm font-bold text-indigo-400">Portal Praktik Kerja Lapangan (PKL)</div>
                            <div class="text-[9px] font-normal text-slate-300 mt-0.5"><?= htmlspecialchars($pkl_active['nama_tempat']) ?></div>
                        </div>
                    </div>
                    <i class="fa fa-arrow-right text-lg text-indigo-400 group-hover:translate-x-1 transition-transform mr-2"></i>
                </div>
            </a>
            <?php endif; ?>
        </div>

        <!-- Rekap Absensi GPS (Monthly) -->
        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4 flex items-center">
            <i class="fa fa-chart-pie mr-2 text-indigo-500"></i> Rekap Kehadiran GPS Bulan Ini
        </h3>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div class="lux-card p-4 text-center">
                <div class="text-2xl font-bold text-emerald-500"><?= $gps_recap['hadir'] ?? 0 ?></div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Hadir</p>
            </div>
            <div class="lux-card p-4 text-center">
                <div class="text-2xl font-bold text-amber-500"><?= $gps_recap['terlambat'] ?? 0 ?></div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Telat</p>
            </div>
            <div class="lux-card p-4 text-center">
                <div class="text-2xl font-bold text-blue-500"><?= $gps_recap['sakit'] ?? 0 ?></div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Sakit</p>
            </div>
            <div class="lux-card p-4 text-center">
                <div class="text-2xl font-bold text-indigo-500"><?= $gps_recap['izin'] ?? 0 ?></div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Izin</p>
            </div>
            <div class="lux-card p-4 text-center">
                <div class="text-2xl font-bold text-rose-500"><?= $gps_recap['alfa'] ?? 0 ?></div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Alfa</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal Jadwal Pelajaran Kelas -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<div id="jadwalKelasModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-3xl max-h-[85vh] overflow-y-auto bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="bg-indigo-600 px-6 py-5 text-white font-bold italic text-lg sticky top-0 z-10 flex justify-between items-center">
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
        customClass: { popup: 'rounded-3xl max-w-[95vw] sm:max-w-xl w-full p-4 sm:p-6', title: 'font-black italic text-left text-slate-800 text-xl' }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
