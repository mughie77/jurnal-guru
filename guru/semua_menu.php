<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id, foto FROM guru WHERE user_id = $user_id");
$g_data = mysqli_fetch_assoc($guru_res);
$guru_id = $g_data['id'];

// Check PKL Pembimbing Status
$is_pkl_pembimbing = false;
$chk_tpkl = mysqli_query($conn, "SHOW TABLES LIKE 'tempat_pkl'");
if ($chk_tpkl && mysqli_num_rows($chk_tpkl) > 0) {
    $q_pkl_guru = mysqli_query($conn, "SELECT COUNT(*) as count FROM tempat_pkl WHERE guru_pembimbing_id = $guru_id");
    if ($q_pkl_guru) {
        $is_pkl_pembimbing = (mysqli_fetch_assoc($q_pkl_guru)['count'] ?? 0) > 0;
    }
}

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

$page_title = "Kumpulan Semua Menu Guru";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-full w-full mx-auto pb-24">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <a href="index.php" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-indigo-600 mb-2 transition-colors">
                <i class="fa fa-arrow-left"></i> Kembali ke Beranda
            </a>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight italic">Kumpulan Semua Menu</h1>
            <p class="text-slate-500 text-xs">Akses seluruh layanan dan fitur portal guru dalam satu halaman.</p>
        </div>
    </div>

    <!-- Group 1: Utama & Pembelajaran -->
    <div class="mb-8">
        <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs mb-4 flex items-center gap-2">
            <i class="fa fa-book-open text-indigo-600"></i> Menu Utama & Pembelajaran
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
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

            <a href="perangkat.php" class="p-5 rounded-2xl bg-rose-500 text-white shadow-md flex flex-col justify-between gap-4 group transition-all hover:bg-rose-600 hover:shadow-lg">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-base">
                    <i class="fa fa-folder-open"></i>
                </div>
                <div>
                    <div class="text-sm font-bold tracking-tight">Perangkat Ajar</div>
                    <div class="text-[10px] text-rose-100 font-normal mt-0.5">Upload media & modul</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Group 2: Kejadian & Komunikasi -->
    <div class="mb-8">
        <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs mb-4 flex items-center gap-2">
            <i class="fa fa-comments text-indigo-600"></i> Catatan Kejadian & Komunikasi
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <a href="rekap_buku_kejadian.php" class="p-5 rounded-2xl bg-amber-600 text-white shadow-md flex flex-col justify-between gap-4 group transition-all hover:bg-amber-700 hover:shadow-lg">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-base">
                    <i class="fa fa-book-bookmark"></i>
                </div>
                <div>
                    <div class="text-sm font-bold tracking-tight">Buku Kejadian</div>
                    <div class="text-[10px] text-amber-100 font-normal mt-0.5">Catatan kejadian siswa</div>
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
        </div>
    </div>

    <!-- Group 3: Wali Kelas, BK & Pembimbing PKL -->
    <?php if ($wali_info || $bk_info || $is_pkl_pembimbing): ?>
    <div class="mb-8">
        <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs mb-4 flex items-center gap-2">
            <i class="fa fa-user-shield text-indigo-600"></i> Tugas Tambahan (Wali Kelas / BK / PKL)
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
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
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
