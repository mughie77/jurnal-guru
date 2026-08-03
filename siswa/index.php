<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];

// Get Student Profile and Active Class
$query_profile = "SELECT s.*, k.nama_kelas, k.jadwal_pdf, tp.tahun as tahun_pelajaran
                  FROM siswa s
                  JOIN siswa_kelas sk ON s.id = sk.siswa_id
                  JOIN kelas k ON sk.kelas_id = k.id
                  JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id
                  WHERE s.id = ? AND tp.status = 'aktif'";
$stmt = mysqli_prepare($conn, $query_profile);
mysqli_stmt_bind_param($stmt, "i", $siswa_id);
mysqli_stmt_execute($stmt);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

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
    <div class="p-4 lg:p-8 max-w-full w-full mx-auto">

        <div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-600 to-indigo-800 text-white relative overflow-hidden">
            <div class="relative z-10 flex items-center gap-6">
                <div class="w-24 h-24 rounded-xl bg-white/20 backdrop-blur-md border border-white/30 flex items-center justify-center text-3xl font-black italic shadow-inner overflow-hidden">
                    <?php if(!empty($siswa['foto'])): ?>
                        <img src="<?= BASE_URL ?>uploads/siswa/<?= $siswa['foto'] ?>" class="w-full h-full object-cover rounded-none">
                    <?php else: ?>
                        <?= strtoupper(substr($siswa['nama_siswa'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="text-2xl font-normal italic tracking-tight leading-tight"><?= htmlspecialchars($siswa['nama_siswa']) ?></h1>
                    <p class="text-indigo-100 font-bold text-sm tracking-widest mt-1"><?= htmlspecialchars($siswa['nis']) ?> / <?= htmlspecialchars($siswa['nisn'] ?? '-') ?></p>
                    <div class="flex items-center gap-3 mt-3">
                        <span class="px-3 py-1 bg-white/10 rounded-full text-[10px] font-black uppercase tracking-widest border border-white/20"><?= htmlspecialchars($siswa['nama_kelas']) ?></span>
                        <span class="px-3 py-1 bg-emerald-400 text-emerald-950 rounded-full text-[10px] font-black uppercase tracking-widest italic">Aktif</span>
                    </div>
                </div>
            </div>
            <i class="fa fa-user-graduate absolute -bottom-6 -right-6 text-9xl opacity-10"></i>
        </div>

        <!-- Quick Actions Grid Design (3x2) -->
        <div class="grid grid-cols-2 gap-3 sm:gap-4 mb-10 no-print">
            <!-- Row 1 -->
            <?php if(!empty($siswa['jadwal_pdf'])): ?>
                <a href="<?= BASE_URL ?>uploads/jadwal/<?= $siswa['jadwal_pdf'] ?>" target="_blank" class="p-3.5 sm:p-5 rounded-2xl sm:rounded-[32px] bg-indigo-600 text-white shadow-xl shadow-indigo-100 flex flex-col gap-2.5 sm:gap-3 group transition-all hover:scale-[1.02] active:scale-95">
            <?php else: ?>
                <a href="javascript:void(0)" onclick="Swal.fire({icon: 'info', title: 'Belum Ada Jadwal', text: 'Jadwal pelajaran kelas belum diunggah oleh administrator.', confirmButtonColor: '#4F46E5'})" class="p-3.5 sm:p-5 rounded-2xl sm:rounded-[32px] bg-indigo-600 text-white shadow-xl shadow-indigo-100 flex flex-col gap-2.5 sm:gap-3 group transition-all hover:scale-[1.02] active:scale-95">
            <?php endif; ?>
                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-lg shadow-inner group-hover:bg-white/30 transition-all">
                    <i class="fa fa-calendar-alt"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-base font-black italic tracking-tighter uppercase leading-none">Jadwal Kelas</div>
                    <div class="text-[7px] sm:text-[9px] font-bold text-indigo-100 uppercase tracking-widest mt-1 opacity-80">Jadwal Pelajaran PDF</div>
                </div>
            </a>

            <a href="izin.php" class="p-3.5 sm:p-5 rounded-2xl sm:rounded-[32px] bg-violet-500 text-white shadow-xl shadow-violet-100 flex flex-col gap-2.5 sm:gap-3 group transition-all hover:scale-[1.02] active:scale-95">
                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-lg shadow-inner group-hover:bg-white/30 transition-all">
                    <i class="fa fa-envelope-open-text"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-base font-black italic tracking-tighter uppercase leading-none">Pengajuan Izin</div>
                    <div class="text-[7px] sm:text-[9px] font-bold text-violet-100 uppercase tracking-widest mt-1 opacity-80">Sakit & Keperluan</div>
                </div>
            </a>

            <!-- Row 2 -->
            <a href="media.php" class="p-3.5 sm:p-5 rounded-2xl sm:rounded-[32px] bg-amber-500 text-white shadow-xl shadow-amber-100 flex flex-col gap-2.5 sm:gap-3 group transition-all hover:scale-[1.02] active:scale-95">
                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-lg shadow-inner group-hover:bg-white/30 transition-all">
                    <i class="fa fa-book-reader"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-base font-black italic tracking-tighter uppercase leading-none">Media & Buku</div>
                    <div class="text-[7px] sm:text-[9px] font-bold text-amber-100 uppercase tracking-widest mt-1 opacity-80">Digital Learning</div>
                </div>
            </a>

            <a href="berkas.php" class="p-3.5 sm:p-5 rounded-2xl sm:rounded-[32px] bg-cyan-500 text-white shadow-xl shadow-cyan-100 flex flex-col gap-2.5 sm:gap-3 group transition-all hover:scale-[1.02] active:scale-95">
                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-lg shadow-inner group-hover:bg-white/30 transition-all">
                    <i class="fa fa-folder-open"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-base font-black italic tracking-tighter uppercase leading-none">Berkas Saya</div>
                    <div class="text-[7px] sm:text-[9px] font-bold text-cyan-100 uppercase tracking-widest mt-1 opacity-80">Upload KK & Ijazah</div>
                </div>
            </a>

            <!-- Row 3 -->
            <a href="profil.php" class="p-3.5 sm:p-5 rounded-2xl sm:rounded-[32px] bg-emerald-500 text-white shadow-xl shadow-emerald-100 flex flex-col gap-2.5 sm:gap-3 group transition-all hover:scale-[1.02] active:scale-95">
                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-lg shadow-inner group-hover:bg-white/30 transition-all">
                    <i class="fa fa-user-circle"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-base font-black italic tracking-tighter uppercase leading-none">Profil Saya</div>
                    <div class="text-[7px] sm:text-[9px] font-bold text-emerald-100 uppercase tracking-widest mt-1 opacity-80">Informasi Pribadi</div>
                </div>
            </a>

            <a href="kritik_saran.php" class="p-3.5 sm:p-5 rounded-2xl sm:rounded-[32px] bg-sky-500 text-white shadow-xl shadow-sky-100 flex flex-col gap-2.5 sm:gap-3 group transition-all hover:scale-[1.02] active:scale-95">
                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-lg shadow-inner group-hover:bg-white/30 transition-all">
                    <i class="fa fa-comment-dots"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-base font-black italic tracking-tighter uppercase leading-none">Kritik & Saran</div>
                    <div class="text-[7px] sm:text-[9px] font-bold text-sky-100 uppercase tracking-widest mt-1 opacity-80">Umpan Balik</div>
                </div>
            </a>

            <!-- Row 4 (New!) -->
            <a href="konsultasi.php" class="p-3.5 sm:p-5 rounded-2xl sm:rounded-[32px] bg-indigo-500 text-white shadow-xl shadow-indigo-100 flex flex-col gap-2.5 sm:gap-3 group transition-all hover:scale-[1.02] active:scale-95">
                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-lg shadow-inner group-hover:bg-white/30 transition-all">
                    <i class="fa fa-comments"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-base font-black italic tracking-tighter uppercase leading-none">Konsultasi BK</div>
                    <div class="text-[7px] sm:text-[9px] font-bold text-indigo-100 uppercase tracking-widest mt-1 opacity-80">Bimbingan Online</div>
                </div>
            </a>

            <a href="kontak.php" class="p-3.5 sm:p-5 rounded-2xl sm:rounded-[32px] bg-teal-500 text-white shadow-xl shadow-teal-100 flex flex-col gap-2.5 sm:gap-3 group transition-all hover:scale-[1.02] active:scale-95">
                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-white/20 flex items-center justify-center text-sm sm:text-lg shadow-inner group-hover:bg-white/30 transition-all">
                    <i class="fa fa-phone-alt"></i>
                </div>
                <div>
                    <div class="text-xs sm:text-base font-black italic tracking-tighter uppercase leading-none">Kontak BK & Wali</div>
                    <div class="text-[7px] sm:text-[9px] font-bold text-teal-100 uppercase tracking-widest mt-1 opacity-80">Direktori Kontak</div>
                </div>
            </a>

            <!-- Row 5 (Pengaduan Siswa, formerly Panic Button) -->
            <a href="pengaduan.php" class="p-3.5 sm:p-5 rounded-2xl sm:rounded-[32px] bg-rose-600 text-white shadow-xl shadow-rose-200 flex flex-col gap-2 sm:gap-3 group transition-all hover:scale-[1.02] active:scale-95 col-span-1 sm:col-span-2 relative overflow-hidden">
                <div class="absolute inset-0 bg-red-700/10 animate-pulse pointer-events-none"></div>
                <div class="relative z-10 flex items-center justify-between w-full">
                    <div class="flex items-center gap-4">
                        <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-2xl bg-white text-rose-600 flex items-center justify-center text-sm sm:text-xl shadow-inner animate-bounce">
                            <i class="fa fa-bullhorn"></i>
                        </div>
                        <div>
                            <div class="text-xs sm:text-lg font-black italic tracking-tighter uppercase leading-none">Pengaduan Siswa</div>
                            <div class="text-[7px] sm:text-[9px] font-bold text-rose-100 uppercase tracking-widest mt-1 opacity-90">Laporkan Perundungan / Masalah Keamanan</div>
                        </div>
                    </div>
                    <i class="fa fa-shield-alt text-xl sm:text-4xl opacity-20 mr-2"></i>
                </div>
            </a>
        </div>

        <!-- Rekap Absensi GPS (Monthly) -->
        <h3 class="text-lg font-black text-slate-800 italic uppercase tracking-widest mb-4 flex items-center">
            <i class="fa fa-chart-pie mr-2 text-indigo-500"></i> Rekap Kehadiran GPS Bulan Ini
        </h3>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div class="lux-card p-4 text-center">
                <div class="text-2xl font-black text-emerald-500 italic"><?= $gps_recap['hadir'] ?? 0 ?></div>
                <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Hadir</p>
            </div>
            <div class="lux-card p-4 text-center">
                <div class="text-2xl font-black text-amber-500 italic"><?= $gps_recap['terlambat'] ?? 0 ?></div>
                <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Telat</p>
            </div>
            <div class="lux-card p-4 text-center">
                <div class="text-2xl font-black text-blue-500 italic"><?= $gps_recap['sakit'] ?? 0 ?></div>
                <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Sakit</p>
            </div>
            <div class="lux-card p-4 text-center">
                <div class="text-2xl font-black text-indigo-500 italic"><?= $gps_recap['izin'] ?? 0 ?></div>
                <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Izin</p>
            </div>
            <div class="lux-card p-4 text-center">
                <div class="text-2xl font-black text-rose-500 italic"><?= $gps_recap['alfa'] ?? 0 ?></div>
                <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Alfa</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
