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

// Check PKL Pembimbing Status safely
$is_pkl_pembimbing = false;
$chk_tpkl = mysqli_query($conn, "SHOW TABLES LIKE 'tempat_pkl'");
if ($chk_tpkl && mysqli_num_rows($chk_tpkl) > 0) {
    $q_pkl_guru = mysqli_query($conn, "SELECT COUNT(*) as count FROM tempat_pkl WHERE guru_pembimbing_id = $guru_id");
    if ($q_pkl_guru) {
        $is_pkl_pembimbing = (mysqli_fetch_assoc($q_pkl_guru)['count'] ?? 0) > 0;
    }
}

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
        <div class="flex items-center justify-between mb-10">
            <div class="flex items-center gap-5">
                <a href="profil.php" class="w-16 h-16 rounded-full bg-indigo-600 border border-slate-200 overflow-hidden flex items-center justify-center block hover:scale-105 transition-transform shrink-0">
                    <?php if(!empty($guru_foto)): ?>
                        <img src="<?= BASE_URL ?>uploads/guru/<?= $guru_foto ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fa fa-user-tie text-white text-2xl"></i>
                    <?php endif; ?>
                </a>
                <div>
                    <h1 class="text-2xl font-normal text-slate-800 tracking-tight leading-tight"><?= $greeting ?>, <span class="text-indigo-600 font-semibold"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></span></h1>
                    <p class="text-slate-400 text-xs font-medium tracking-wide mt-0.5"><?= date('l, d F Y') ?></p>
                </div>
            </div>
        </div>

        <!-- Pengumuman Terbaru Widget -->
        <?php
        $q_announcements = mysqli_query($conn, "SELECT * FROM pengumuman WHERE target IN ('semua', 'guru') ORDER BY created_at DESC LIMIT 3");
        if (mysqli_num_rows($q_announcements) > 0):
        ?>
        <div class="mb-10">
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
                                <?= $ann['target'] == 'guru' ? 'Khusus Guru' : 'Semua' ?>
                            </span>
                            <span class="text-[10px] text-slate-400 font-medium">
                                <?= date('d M Y', strtotime($ann['created_at'])) ?>
                            </span>
                        </div>
                        <h4 class="font-bold text-slate-800 text-sm mb-1.5 line-clamp-1"><?= htmlspecialchars($ann['judul']) ?></h4>
                        <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed mb-3 font-normal"><?= htmlspecialchars($ann['isi']) ?></p>
                    </div>
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                        <button type="button" onclick='showAnnouncementModal(<?= htmlspecialchars(json_encode($ann), ENT_QUOTES, 'UTF-8') ?>)' class="font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                            Baca <i class="fa fa-arrow-right text-[9px]"></i>
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

        <!-- Minimal Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
            <div class="lux-card p-6 bg-gradient-to-br from-indigo-600 to-indigo-700 text-white border-none relative overflow-hidden rounded-2xl">
                <div class="relative z-10 flex items-center justify-between">
                    <div>
                        <p class="text-indigo-100/80 text-[10px] font-bold uppercase tracking-widest mb-1">Total Jurnal Mengajar</p>
                        <h2 class="text-4xl font-bold tracking-tight"><?= number_format($total_jurnal) ?> <span class="text-xs font-normal text-indigo-200">Sesi</span></h2>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center text-xl text-white">
                        <i class="fa fa-book-open"></i>
                    </div>
                </div>
            </div>

            <div class="lux-card p-6 bg-white border border-slate-200/80 rounded-2xl flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-1">Rata-rata Kehadiran Siswa</p>
                    <h2 class="text-4xl font-bold tracking-tight text-slate-800"><?= round((float)($hadir_avg ?? 0), 1) ?> <span class="text-xs font-normal text-slate-400">Siswa / Kelas</span></h2>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                    <i class="fa fa-user-check"></i>
                </div>
            </div>
        </div>

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

        $grid_cols_class = ($wali_info || $bk_info || $is_pkl_pembimbing) ? "grid-cols-2 lg:grid-cols-4" : "grid-cols-2 sm:grid-cols-3";
        ?>
        <div class="grid <?= $grid_cols_class ?> gap-4 mb-10">
            <a href="isi_absensi.php" class="p-5 rounded-2xl bg-indigo-600 text-white shadow-md flex flex-col justify-between gap-4 group transition-all hover:bg-indigo-700 hover:shadow-lg">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-base">
                    <i class="fa fa-user-check"></i>
                </div>
                <div>
                    <div class="text-sm font-bold tracking-tight">Isi Jurnal & Absensi</div>
                    <div class="text-[10px] text-indigo-100 font-normal mt-0.5">Input aktivitas mengajar</div>
                </div>
            </a>

            <a href="rekap_absen.php" class="p-5 rounded-2xl bg-emerald-600 text-white shadow-md flex flex-col justify-between gap-4 group transition-all hover:bg-emerald-700 hover:shadow-lg">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-base">
                    <i class="fa fa-chart-line"></i>
                </div>
                <div>
                    <div class="text-sm font-bold tracking-tight">Rekap Absensi</div>
                    <div class="text-[10px] text-emerald-100 font-normal mt-0.5">Laporan kehadiran siswa</div>
                </div>
            </a>

            <a href="riwayat.php" class="p-5 rounded-2xl bg-amber-500 text-white shadow-md flex flex-col justify-between gap-4 group transition-all hover:bg-amber-600 hover:shadow-lg">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-base">
                    <i class="fa fa-history"></i>
                </div>
                <div>
                    <div class="text-sm font-bold tracking-tight">Riwayat Jurnal</div>
                    <div class="text-[10px] text-amber-100 font-normal mt-0.5">Arsip agenda mengajar</div>
                </div>
            </a>

            <a href="rekap_buku_kejadian.php" class="p-5 rounded-2xl bg-amber-600 text-white shadow-md flex flex-col justify-between gap-4 group transition-all hover:bg-amber-700 hover:shadow-lg">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-base">
                    <i class="fa fa-book-bookmark"></i>
                </div>
                <div>
                    <div class="text-sm font-bold tracking-tight">Buku Kejadian</div>
                    <div class="text-[10px] text-amber-100 font-normal mt-0.5">Catatan kejadian siswa</div>
                </div>
            </a>

            <a href="perangkat.php" class="p-5 rounded-2xl bg-rose-500 text-white shadow-md flex flex-col justify-between gap-4 group transition-all hover:bg-rose-600 hover:shadow-lg">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-base">
                    <i class="fa fa-folder-open"></i>
                </div>
                <div>
                    <div class="text-sm font-bold tracking-tight">Perangkat Ajar</div>
                    <div class="text-[10px] text-rose-100 font-normal mt-0.5">Upload media & modul</div>
                </div>
            </a>

            <a href="rekan.php" class="p-5 rounded-2xl bg-sky-500 text-white shadow-md flex flex-col justify-between gap-4 group transition-all hover:bg-sky-600 hover:shadow-lg">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-base">
                    <i class="fa fa-users"></i>
                </div>
                <div>
                    <div class="text-sm font-bold tracking-tight">Rekan Guru</div>
                    <div class="text-[10px] text-sky-100 font-normal mt-0.5">Kontak sejawat</div>
                </div>
            </a>

            <a href="kritik_saran.php" class="p-5 rounded-2xl bg-indigo-500 text-white shadow-md flex flex-col justify-between gap-4 group transition-all hover:bg-indigo-600 hover:shadow-lg">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-base">
                    <i class="fa fa-comment-dots"></i>
                </div>
                <div>
                    <div class="text-sm font-bold tracking-tight">Kritik & Saran</div>
                    <div class="text-[10px] text-indigo-100 font-normal mt-0.5">Umpan balik</div>
                </div>
            </a>

            <a href="tugas_tidak_masuk.php" class="p-5 rounded-2xl bg-rose-600 text-white shadow-md flex flex-col justify-between gap-4 group transition-all hover:bg-rose-700 hover:shadow-lg">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-base">
                    <i class="fa fa-clipboard-list"></i>
                </div>
                <div>
                    <div class="text-sm font-bold tracking-tight">Tugas Guru</div>
                    <div class="text-[10px] text-rose-100 font-normal mt-0.5">Guru tidak masuk</div>
                </div>
            </a>

            <?php if ($is_pkl_pembimbing): ?>
            <a href="pkl_index.php" class="p-5 rounded-2xl bg-slate-900 text-white shadow-md flex flex-col justify-between gap-4 group transition-all hover:bg-slate-800 hover:shadow-lg border border-indigo-400/40">
                <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-base">
                    <i class="fa fa-briefcase"></i>
                </div>
                <div>
                    <div class="text-sm font-bold tracking-tight text-indigo-400">Pembimbing PKL</div>
                    <div class="text-[10px] text-slate-300 font-normal mt-0.5">Portal menu PKL</div>
                </div>
            </a>
            <?php endif; ?>

            <?php if ($wali_info): ?>
            <a href="<?= BASE_URL ?>admin/rekap_persiswa.php" class="p-5 rounded-2xl bg-violet-600 text-white shadow-md flex flex-col justify-between gap-4 group transition-all hover:bg-violet-700 hover:shadow-lg">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-base">
                    <i class="fa fa-user-check"></i>
                </div>
                <div>
                    <div class="text-sm font-bold tracking-tight">Rekap Kelas</div>
                    <div class="text-[10px] text-violet-100 font-normal mt-0.5">Wali: <?= htmlspecialchars($wali_info['nama_kelas']) ?></div>
                </div>
            </a>

            <a href="rekap_izin.php" class="p-5 rounded-2xl bg-rose-600 text-white shadow-md flex flex-col justify-between gap-4 group transition-all hover:bg-rose-700 hover:shadow-lg relative">
                <?php if ($pending_permits_count > 0): ?>
                    <span class="absolute top-3 right-3 bg-amber-400 text-slate-950 font-extrabold text-[8px] uppercase px-2 py-0.5 rounded-full shadow-sm">
                        <?= $pending_permits_count ?> PENDING
                    </span>
                <?php endif; ?>
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-base">
                    <i class="fa fa-envelope-open-text"></i>
                </div>
                <div>
                    <div class="text-sm font-bold tracking-tight">Siswa Izin</div>
                    <div class="text-[10px] text-rose-100 font-normal mt-0.5">Verifikasi Izin/Sakit</div>
                </div>
            </a>
            <?php endif; ?>

            <?php if ($bk_info): ?>
            <a href="rekap_pengaduan.php" class="p-5 rounded-2xl bg-rose-600 text-white shadow-md flex flex-col justify-between gap-4 group transition-all hover:bg-rose-700 hover:shadow-lg">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-base">
                    <i class="fa fa-bullhorn"></i>
                </div>
                <div>
                    <div class="text-sm font-bold tracking-tight">Daftar Pengaduan</div>
                    <div class="text-[10px] text-rose-100 font-normal mt-0.5">Laporan pengaduan siswa</div>
                </div>
            </a>

            <a href="konsultasi.php" class="p-5 rounded-2xl bg-indigo-500 text-white shadow-md flex flex-col justify-between gap-4 group transition-all hover:bg-indigo-600 hover:shadow-lg relative">
                <?php if ($open_chats_count > 0): ?>
                    <span class="absolute top-3 right-3 bg-amber-400 text-slate-950 font-extrabold text-[8px] uppercase px-2 py-0.5 rounded-full shadow-sm">
                        <?= $open_chats_count ?> CHAT
                    </span>
                <?php endif; ?>
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-base">
                    <i class="fa fa-comments"></i>
                </div>
                <div>
                    <div class="text-sm font-bold tracking-tight">Chat Konsultasi</div>
                    <div class="text-[10px] text-indigo-100 font-normal mt-0.5">Konsultasi BK online</div>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
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
