<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];

// Get Student Profile
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

// Check active PKL Mapping
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

$page_title = "Kumpulan Semua Menu Siswa";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-full w-full mx-auto pb-24">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <a href="index.php" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-indigo-600 mb-2 transition-colors">
                <i class="fa fa-arrow-left"></i> Kembali ke Beranda
            </a>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight italic">Kumpulan Semua Menu</h1>
            <p class="text-slate-500 text-xs">Akses seluruh layanan dan fitur portal siswa dalam satu halaman.</p>
        </div>
    </div>

    <!-- Group 1: Akademik & Pembelajaran -->
    <div class="mb-8">
        <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs mb-4 flex items-center gap-2">
            <i class="fa fa-book-reader text-indigo-600"></i> Akademik & Pembelajaran
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <a href="index.php" class="p-4 sm:p-5 rounded-2xl bg-indigo-600 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-indigo-700 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm">
                    <i class="fa fa-calendar-alt"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Jadwal Kelas</div>
                    <div class="text-[9px] font-normal text-indigo-100 mt-0.5">Jadwal pelajaran mingguan</div>
                </div>
            </a>

            <a href="media.php" class="p-4 sm:p-5 rounded-2xl bg-amber-500 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-amber-600 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm">
                    <i class="fa fa-book-reader"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Media & Buku</div>
                    <div class="text-[9px] font-normal text-amber-100 mt-0.5">Digital learning</div>
                </div>
            </a>

            <a href="tugas_guru.php" class="p-4 sm:p-5 rounded-2xl bg-amber-600 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-amber-700 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm">
                    <i class="fa fa-tasks"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Tugas Guru</div>
                    <div class="text-[9px] font-normal text-amber-100 mt-0.5">Guru tidak masuk</div>
                </div>
            </a>

            <a href="absensi.php" class="p-4 sm:p-5 rounded-2xl bg-emerald-600 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-emerald-700 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm">
                    <i class="fa fa-fingerprint"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Absen GPS</div>
                    <div class="text-[9px] font-normal text-emerald-100 mt-0.5">Presensi lokasi mandiri</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Group 2: Perizinan & Konsultasi BK -->
    <div class="mb-8">
        <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs mb-4 flex items-center gap-2">
            <i class="fa fa-envelope-open-text text-indigo-600"></i> Perizinan & Layanan BK
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <a href="izin.php" class="p-4 sm:p-5 rounded-2xl bg-violet-500 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-violet-600 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm">
                    <i class="fa fa-envelope-open-text"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Pengajuan Izin</div>
                    <div class="text-[9px] font-normal text-violet-100 mt-0.5">Sakit & Keperluan</div>
                </div>
            </a>

            <a href="konsultasi.php" class="p-4 sm:p-5 rounded-2xl bg-indigo-500 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-indigo-600 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm">
                    <i class="fa fa-comments"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Konsultasi BK</div>
                    <div class="text-[9px] font-normal text-indigo-100 mt-0.5">Bimbingan online</div>
                </div>
            </a>

            <a href="kontak.php" class="p-4 sm:p-5 rounded-2xl bg-teal-500 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-teal-600 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm">
                    <i class="fa fa-phone-alt"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Kontak BK & Wali</div>
                    <div class="text-[9px] font-normal text-teal-100 mt-0.5">Direktori kontak</div>
                </div>
            </a>

            <a href="pengaduan.php" class="p-4 sm:p-5 rounded-2xl bg-rose-600 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-rose-700 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm">
                    <i class="fa fa-bullhorn"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Pengaduan Siswa</div>
                    <div class="text-[9px] font-normal text-rose-100 mt-0.5">Laporkan perundungan</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Group 3: Profil & Informasi -->
    <div class="mb-8">
        <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs mb-4 flex items-center gap-2">
            <i class="fa fa-id-card text-indigo-600"></i> Profil, Berkas & Fitur Lainnya
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <a href="berkas.php" class="p-4 sm:p-5 rounded-2xl bg-cyan-500 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-cyan-600 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm">
                    <i class="fa fa-folder-open"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Berkas Saya</div>
                    <div class="text-[9px] font-normal text-cyan-100 mt-0.5">Upload KK & Ijazah</div>
                </div>
            </a>

            <a href="profil.php" class="p-4 sm:p-5 rounded-2xl bg-emerald-500 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-emerald-600 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm">
                    <i class="fa fa-user-circle"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Profil Saya</div>
                    <div class="text-[9px] font-normal text-emerald-100 mt-0.5">Informasi pribadi</div>
                </div>
            </a>

            <a href="kartu.php" class="p-4 sm:p-5 rounded-2xl bg-indigo-600 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-indigo-700 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm">
                    <i class="fa fa-id-card"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Kartu Pelajar</div>
                    <div class="text-[9px] font-normal text-indigo-100 mt-0.5">Digital ID card</div>
                </div>
            </a>

            <a href="barcode.php" class="p-4 sm:p-5 rounded-2xl bg-slate-800 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-slate-900 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm">
                    <i class="fa fa-qrcode"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Barcode QR</div>
                    <div class="text-[9px] font-normal text-slate-300 mt-0.5">Scan presensi</div>
                </div>
            </a>

            <a href="kritik_saran.php" class="p-4 sm:p-5 rounded-2xl bg-sky-500 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-sky-600 hover:shadow-lg">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-sm">
                    <i class="fa fa-comment-dots"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold tracking-tight">Kritik & Saran</div>
                    <div class="text-[9px] font-normal text-sky-100 mt-0.5">Umpan balik</div>
                </div>
            </a>

            <?php if ($pkl_active): ?>
            <a href="pkl_jurnal.php" class="p-4 sm:p-5 rounded-2xl bg-slate-900 text-white shadow-md flex flex-col justify-between gap-3 group transition-all hover:bg-slate-800 hover:shadow-lg border border-indigo-400/40">
                <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-sm">
                    <i class="fa fa-briefcase"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-sm font-bold text-indigo-400">Portal PKL</div>
                    <div class="text-[9px] font-normal text-slate-300 mt-0.5"><?= htmlspecialchars($pkl_active['nama_tempat']) ?></div>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
