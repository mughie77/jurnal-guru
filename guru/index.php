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
$recent_jurnals = mysqli_query($conn, "SELECT j.*, k.nama_kelas, mp.nama_mapel FROM jurnal j JOIN kelas k ON j.kelas_id = k.id JOIN mata_pelajaran mp ON j.mapel_id = mp.id WHERE j.guru_id = $guru_id ORDER BY j.tanggal DESC LIMIT 5");

// Check PKL Pembimbing Status
$q_pkl_guru = mysqli_query($conn, "SELECT COUNT(*) as count FROM tempat_pkl WHERE guru_pembimbing_id = $guru_id");
$is_pkl_pembimbing = (mysqli_fetch_assoc($q_pkl_guru)['count'] ?? 0) > 0;

$page_title = "Beranda Guru";

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
    body { background-color: #F8FAFC; }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <!-- Main Content (Full-width max-w-full) -->
    <div class="p-4 sm:p-6 lg:p-8 max-w-full w-full mx-auto">
        <div class="flex items-center justify-between mb-12">
            <div class="flex items-center gap-6">
                <a href="profil.php" class="w-20 h-20 rounded-full bg-indigo-600 border border-slate-200 overflow-hidden flex items-center justify-center block hover:scale-105 transition-transform">
                    <?php if(!empty($guru_foto)): ?>
                        <img src="<?= BASE_URL ?>uploads/guru/<?= $guru_foto ?>" class="w-full h-full object-cover rounded-none">
                    <?php else: ?>
                        <i class="fa fa-user-tie text-white text-3xl"></i>
                    <?php endif; ?>
                </a>
                <div>
                    <h1 class="text-3xl font-normal text-slate-800 tracking-tight italic leading-tight"><?= $greeting ?><br /><span class="text-indigo-600 text-xl font-normal leading-normal"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></span></h1>
                    <p class="text-slate-400 font-medium tracking-wide mt-1"><?= date('l, d F Y') ?></p>
                </div>
            </div>
        </div>

        <!-- Pengumuman Terbaru Widget -->
        <?php
        $q_announcements = mysqli_query($conn, "SELECT * FROM pengumuman WHERE target IN ('semua', 'guru') ORDER BY created_at DESC LIMIT 3");
        if (mysqli_num_rows($q_announcements) > 0):
        ?>
        <div class="mb-12">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-black text-slate-800 italic uppercase tracking-wider text-base flex items-center gap-2">
                    <i class="fa fa-bullhorn text-indigo-600"></i> Pengumuman Terbaru
                </h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php while ($ann = mysqli_fetch_assoc($q_announcements)): ?>
                <div class="lux-card p-6 bg-white border border-slate-100 shadow-xl rounded-3xl flex flex-col justify-between hover:border-indigo-200 transition-all">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-600 border border-indigo-100">
                                <?= $ann['target'] == 'guru' ? 'Khusus Guru' : 'Semua' ?>
                            </span>
                            <span class="text-[10px] font-bold text-slate-400">
                                <?= date('d M Y', strtotime($ann['created_at'])) ?>
                            </span>
                        </div>
                        <h4 class="font-black text-slate-800 text-base mb-2 line-clamp-1"><?= htmlspecialchars($ann['judul']) ?></h4>
                        <p class="text-xs text-slate-500 line-clamp-3 leading-relaxed mb-4"><?= htmlspecialchars($ann['isi']) ?></p>
                    </div>
                    <div class="pt-3 border-t border-slate-50 flex items-center justify-between">
                        <button type="button" onclick='showAnnouncementModal(<?= htmlspecialchars(json_encode($ann), ENT_QUOTES, 'UTF-8') ?>)' class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                            Baca Selengkapnya <i class="fa fa-arrow-right text-[10px]"></i>
                        </button>
                        <?php if (!empty($ann['file_lampiran'])): ?>
                        <a href="<?= BASE_URL ?>uploads/pengumuman/<?= $ann['file_lampiran'] ?>" target="_blank" class="text-[10px] font-black text-slate-500 bg-slate-100 hover:bg-slate-200 px-2.5 py-1 rounded-lg flex items-center gap-1 transition-colors">
                            <i class="fa fa-paperclip text-indigo-500"></i> Lampiran
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($is_pkl_pembimbing): ?>
        <!-- Menu Tile Khusus Guru Pembimbing PKL -->
        <div class="mb-12">
            <h3 class="font-black text-slate-800 italic uppercase tracking-wider text-base mb-4 flex items-center gap-2">
                <i class="fa fa-briefcase text-indigo-600"></i> Menu Pembimbing PKL
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                <a href="pkl_siswa.php" class="p-3.5 sm:p-5 rounded-2xl sm:rounded-[32px] bg-slate-900 text-white shadow-xl shadow-slate-200 flex flex-col gap-2 sm:gap-3 group transition-all hover:scale-[1.02] active:scale-95 border-2 border-indigo-500">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-sm sm:text-lg shadow-inner">
                        <i class="fa fa-users"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-base font-black italic tracking-tighter uppercase leading-none text-indigo-400">Daftar Siswa</div>
                        <div class="text-[7px] sm:text-[9px] font-bold text-slate-300 uppercase tracking-widest mt-1">Bimbingan PKL</div>
                    </div>
                </a>

                <a href="pkl_absensi.php" class="p-3.5 sm:p-5 rounded-2xl sm:rounded-[32px] bg-emerald-600 text-white shadow-xl shadow-emerald-200 flex flex-col gap-2 sm:gap-3 group transition-all hover:scale-[1.02] active:scale-95">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-lg shadow-inner">
                        <i class="fa fa-user-check"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-base font-black italic tracking-tighter uppercase leading-none">Rekap Absensi</div>
                        <div class="text-[7px] sm:text-[9px] font-bold text-emerald-100 uppercase tracking-widest mt-1 opacity-80">Presensi GPS PKL</div>
                    </div>
                </a>

                <a href="pkl_jurnal.php" class="p-3.5 sm:p-5 rounded-2xl sm:rounded-[32px] bg-indigo-600 text-white shadow-xl shadow-indigo-200 flex flex-col gap-2 sm:gap-3 group transition-all hover:scale-[1.02] active:scale-95">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-lg shadow-inner">
                        <i class="fa fa-book-open"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-base font-black italic tracking-tighter uppercase leading-none">Jurnal Siswa</div>
                        <div class="text-[7px] sm:text-[9px] font-bold text-indigo-100 uppercase tracking-widest mt-1 opacity-80">Laporan PKL Siswa</div>
                    </div>
                </a>

                <a href="pkl_lokasi.php" class="p-3.5 sm:p-5 rounded-2xl sm:rounded-[32px] bg-rose-600 text-white shadow-xl shadow-rose-200 flex flex-col gap-2 sm:gap-3 group transition-all hover:scale-[1.02] active:scale-95">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-lg shadow-inner">
                        <i class="fa fa-map-marker-alt"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-base font-black italic tracking-tighter uppercase leading-none">Lokasi PKL</div>
                        <div class="text-[7px] sm:text-[9px] font-bold text-rose-100 uppercase tracking-widest mt-1 opacity-80">Pointing GPS PKL</div>
                    </div>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            <div class="lux-card p-8 bg-indigo-600 text-white shadow-indigo-200 border-none relative overflow-hidden group">
                <div class="relative z-10">
                    <p class="text-indigo-100 text-xs font-black uppercase tracking-widest mb-4">Total Jurnal</p>
                    <h2 class="text-5xl font-black italic"><?= $total_jurnal ?></h2>
                    <p class="mt-4 text-xs font-bold bg-white/20 inline-block px-3 py-1 rounded-full">+12% dari bulan lalu</p>
                </div>
                <i class="fa fa-book-open absolute -bottom-4 -right-4 text-8xl opacity-10 group-hover:scale-110 transition-transform"></i>
            </div>
            <div class="lux-card p-8 bg-white border-slate-100">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-4">Rata-rata Kehadiran</p>
                <h2 class="text-5xl font-black italic text-slate-800"><?= round((float)($hadir_avg ?? 0), 1) ?></h2>
                <div class="mt-6 w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                    <div class="bg-emerald-500 h-full w-[85%] rounded-full"></div>
                </div>
            </div>
            <div class="lux-card p-8 bg-white border-slate-100 flex flex-col justify-center items-center text-center group cursor-pointer hover:border-indigo-500 transition-all">
                <a href="isi_absensi.php" class="contents">
                    <div class="w-16 h-16 rounded-2xl bg-slate-50 text-indigo-600 flex items-center justify-center mb-4 group-hover:bg-indigo-600 group-hover:text-white transition-all duration-500 shadow-inner">
                        <i class="fa fa-user-check text-2xl"></i>
                    </div>
                    <p class="font-black text-slate-800 italic uppercase tracking-tighter">Mulai Absensi & Jurnal</p>
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
            <!-- Quick Actions Grid Design -->
            <?php
            $wali_info = get_wali_kelas_info();
            $pending_permits_count = 0;
            if ($wali_info) {
                $w_kelas_id = $wali_info['kelas_id'];
                $q_perm = mysqli_query($conn, "SELECT COUNT(*) as pending_count FROM absensi_harian ah
                                                JOIN siswa_kelas sk ON ah.siswa_id = sk.siswa_id
                                                WHERE sk.kelas_id = $w_kelas_id AND ah.status_verifikasi = 'pending' AND ah.status IN ('Izin', 'Sakit')");
                if ($p_data = mysqli_fetch_assoc($q_perm)) {
                    $pending_permits_count = (int)$p_data['pending_count'];
                }
            }

            $bk_info = get_bk_info();
            $open_chats_count = 0;
            if ($bk_info) {
                $bk_guru_id = $bk_info['guru_id'];
                $q_chats = mysqli_query($conn, "SELECT COUNT(*) as open_count FROM konsultasi WHERE guru_id = $bk_guru_id AND status = 'open'");
                if ($c_data = mysqli_fetch_assoc($q_chats)) {
                    $open_chats_count = (int)$c_data['open_count'];
                }
            }

            $grid_cols_class = ($wali_info || $bk_info || $is_pkl_pembimbing) ? "grid-cols-2 lg:grid-cols-3" : "grid-cols-2";
            ?>
            <div class="grid <?= $grid_cols_class ?> gap-3 sm:gap-4 col-span-1 lg:col-span-2">
                <a href="isi_absensi.php" class="p-3.5 sm:p-6 rounded-2xl sm:rounded-[32px] bg-indigo-600 text-white shadow-xl shadow-indigo-200 flex flex-col gap-2.5 sm:gap-4 group transition-all hover:scale-[1.02] active:scale-95">
                    <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-xl shadow-inner group-hover:bg-white/30 transition-all">
                        <i class="fa fa-user-check"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-lg font-black italic tracking-tighter uppercase leading-none">Isi Jurnal</div>
                        <div class="text-[7px] sm:text-[9px] font-bold text-indigo-100 uppercase tracking-widest mt-1 opacity-70">Input Aktivitas</div>
                    </div>
                </a>

                <a href="rekap_absen.php" class="p-3.5 sm:p-6 rounded-2xl sm:rounded-[32px] bg-emerald-600 text-white shadow-xl shadow-emerald-200 flex flex-col gap-2.5 sm:gap-4 group transition-all hover:scale-[1.02] active:scale-95">
                    <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-xl shadow-inner group-hover:bg-white/30 transition-all">
                        <i class="fa fa-chart-line"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-lg font-black italic tracking-tighter uppercase leading-none">Rekap Absensi</div>
                        <div class="text-[7px] sm:text-[9px] font-bold text-emerald-100 uppercase tracking-widest mt-1 opacity-70">Laporan Kehadiran</div>
                    </div>
                </a>

                <a href="riwayat.php" class="p-3.5 sm:p-6 rounded-2xl sm:rounded-[32px] bg-amber-500 text-white shadow-xl shadow-amber-200 flex flex-col gap-2.5 sm:gap-4 group transition-all hover:scale-[1.02] active:scale-95">
                    <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-xl shadow-inner group-hover:bg-white/30 transition-all">
                        <i class="fa fa-history"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-lg font-black italic tracking-tighter uppercase leading-none">Riwayat Jurnal</div>
                        <div class="text-[7px] sm:text-[9px] font-bold text-amber-100 uppercase tracking-widest mt-1 opacity-70">Arsip Mengajar</div>
                    </div>
                </a>

                <a href="perangkat.php" class="p-3.5 sm:p-6 rounded-2xl sm:rounded-[32px] bg-rose-500 text-white shadow-xl shadow-rose-200 flex flex-col gap-2.5 sm:gap-4 group transition-all hover:scale-[1.02] active:scale-95">
                    <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-xl shadow-inner group-hover:bg-white/30 transition-all">
                        <i class="fa fa-folder-open"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-lg font-black italic tracking-tighter uppercase leading-none">Perangkat</div>
                        <div class="text-[7px] sm:text-[9px] font-bold text-rose-100 uppercase tracking-widest mt-1 opacity-70">Upload Media</div>
                    </div>
                </a>

                <a href="rekan.php" class="p-3.5 sm:p-6 rounded-2xl sm:rounded-[32px] bg-sky-500 text-white shadow-xl shadow-sky-200 flex flex-col gap-2.5 sm:gap-4 group transition-all hover:scale-[1.02] active:scale-95">
                    <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-xl shadow-inner group-hover:bg-white/30 transition-all">
                        <i class="fa fa-users"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-lg font-black italic tracking-tighter uppercase leading-none">Rekan Guru</div>
                        <div class="text-[7px] sm:text-[9px] font-bold text-sky-100 uppercase tracking-widest mt-1 opacity-70">Kontak Sejawat</div>
                    </div>
                </a>

                <a href="kritik_saran.php" class="p-3.5 sm:p-6 rounded-2xl sm:rounded-[32px] bg-indigo-500 text-white shadow-xl shadow-indigo-200 flex flex-col gap-2.5 sm:gap-4 group transition-all hover:scale-[1.02] active:scale-95">
                    <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-xl shadow-inner group-hover:bg-white/30 transition-all">
                        <i class="fa fa-comment-dots"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-lg font-black italic tracking-tighter uppercase leading-none">Kritik & Saran</div>
                        <div class="text-[7px] sm:text-[9px] font-bold text-indigo-100 uppercase tracking-widest mt-1 opacity-70">Umpan Balik</div>
                    </div>
                </a>

                <a href="tugas_tidak_masuk.php" class="p-3.5 sm:p-6 rounded-2xl sm:rounded-[32px] bg-rose-600 text-white shadow-xl shadow-rose-200 flex flex-col gap-2.5 sm:gap-4 group transition-all hover:scale-[1.02] active:scale-95">
                    <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-xl shadow-inner group-hover:bg-white/30 transition-all">
                        <i class="fa fa-clipboard-list"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-lg font-black italic tracking-tighter uppercase leading-none">Tugas Guru</div>
                        <div class="text-[7px] sm:text-[9px] font-bold text-rose-100 uppercase tracking-widest mt-1 opacity-70">Guru Tidak Masuk</div>
                    </div>
                </a>


                <?php if ($wali_info): ?>
                <a href="<?= BASE_URL ?>admin/rekap_persiswa.php" class="p-3.5 sm:p-6 rounded-2xl sm:rounded-[32px] bg-violet-600 text-white shadow-xl shadow-violet-200 flex flex-col gap-2.5 sm:gap-4 group transition-all hover:scale-[1.02] active:scale-95 col-span-1">
                    <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-xl shadow-inner group-hover:bg-white/30 transition-all">
                        <i class="fa fa-user-check"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-lg font-black italic tracking-tighter uppercase leading-none">Rekap Kelas</div>
                        <div class="text-[7px] sm:text-[9px] font-bold text-violet-100 uppercase tracking-widest mt-1 opacity-70">Wali: <?= htmlspecialchars($wali_info['nama_kelas']) ?></div>
                    </div>
                </a>

                <a href="rekap_izin.php" class="p-3.5 sm:p-6 rounded-2xl sm:rounded-[32px] bg-rose-600 text-white shadow-xl shadow-rose-200 flex flex-col gap-2.5 sm:gap-4 group transition-all hover:scale-[1.02] active:scale-95 col-span-1 relative">
                    <?php if ($pending_permits_count > 0): ?>
                        <span class="absolute top-2 right-2 sm:top-4 sm:right-4 bg-yellow-400 text-slate-950 font-black text-[7px] sm:text-[10px] uppercase px-1.5 py-0.5 sm:px-2 sm:py-0.5 rounded-full animate-bounce shadow-md">
                            <?= $pending_permits_count ?> PENDING
                        </span>
                    <?php endif; ?>
                    <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-xl shadow-inner group-hover:bg-white/30 transition-all">
                        <i class="fa fa-envelope-open-text"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-lg font-black italic tracking-tighter uppercase leading-none">Siswa Izin</div>
                        <div class="text-[7px] sm:text-[9px] font-bold text-rose-100 uppercase tracking-widest mt-1 opacity-70">Verifikasi Izin/Sakit</div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if ($bk_info): ?>
                <a href="rekap_pengaduan.php" class="p-3.5 sm:p-6 rounded-2xl sm:rounded-[32px] bg-rose-600 text-white shadow-xl shadow-rose-200 flex flex-col gap-2.5 sm:gap-4 group transition-all hover:scale-[1.02] active:scale-95 col-span-1">
                    <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-xl shadow-inner group-hover:bg-white/30 transition-all">
                        <i class="fa fa-bullhorn"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-lg font-black italic tracking-tighter uppercase leading-none">Daftar Pengaduan</div>
                        <div class="text-[7px] sm:text-[9px] font-bold text-rose-100 uppercase tracking-widest mt-1 opacity-70">Laporan Pengaduan Siswa</div>
                    </div>
                </a>

                <a href="konsultasi.php" class="p-3.5 sm:p-6 rounded-2xl sm:rounded-[32px] bg-indigo-500 text-white shadow-xl shadow-indigo-200 flex flex-col gap-2.5 sm:gap-4 group transition-all hover:scale-[1.02] active:scale-95 col-span-1 relative">
                    <?php if ($open_chats_count > 0): ?>
                        <span class="absolute top-2 right-2 sm:top-4 sm:right-4 bg-yellow-400 text-slate-950 font-black text-[7px] sm:text-[10px] uppercase px-1.5 py-0.5 sm:px-2 sm:py-0.5 rounded-full animate-bounce shadow-md">
                            <?= $open_chats_count ?> CHAT
                        </span>
                    <?php endif; ?>
                    <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-xl shadow-inner group-hover:bg-white/30 transition-all">
                        <i class="fa fa-comments"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-lg font-black italic tracking-tighter uppercase leading-none">Chat Konsultasi</div>
                        <div class="text-[7px] sm:text-[9px] font-bold text-indigo-100 uppercase tracking-widest mt-1 opacity-70">Konsultasi BK Online</div>
                    </div>
                </a>
                <?php endif; ?>
            </div>

            <!-- Activity Table -->
            <div class="lux-card overflow-hidden border-none shadow-2xl">
                <div class="p-6 border-b border-slate-50 flex items-center justify-between">
                    <h3 class="font-black text-slate-800 italic uppercase tracking-widest text-sm">Aktivitas Terakhir</h3>
                    <a href="riwayat.php" class="text-xs font-bold text-indigo-600 hover:underline">Lihat Semua</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50">
                                <th class="px-6 py-4">Tanggal</th>
                                <th class="px-6 py-4">Pelajaran</th>
                                <th class="px-6 py-4 text-center">Kelas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php mysqli_data_seek($recent_jurnals, 0); while($j = mysqli_fetch_assoc($recent_jurnals)): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4 text-xs font-bold text-slate-700"><?= date('d M', strtotime($j['tanggal'])) ?></td>
                                <td class="px-6 py-4 font-bold text-slate-800 text-sm italic"><?= htmlspecialchars($j['nama_mapel']) ?></td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-2 py-1 rounded bg-slate-100 text-slate-500 text-[10px] font-black uppercase border border-slate-200"><?= $j['nama_kelas'] ?></span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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
               <div class="text-left text-sm text-slate-700 leading-relaxed whitespace-pre-line">${ann.isi}</div>
               ${lampiranHtml}`,
        confirmButtonColor: '#4f46e5',
        confirmButtonText: 'Tutup',
        customClass: { popup: 'rounded-3xl', title: 'font-black italic text-left text-slate-800 text-xl' }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
