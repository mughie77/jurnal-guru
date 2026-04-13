<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];

// Get Student Profile and Active Class
$query_profile = "SELECT s.*, k.nama_kelas, tp.tahun as tahun_pelajaran
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

// Digital Media for this student's class
$query_media = "SELECT p.*, u.nama_lengkap as nama_guru
                FROM perangkat p
                JOIN guru g ON p.guru_id = g.id
                JOIN users u ON g.user_id = u.id
                JOIN perangkat_kelas pk ON p.id = pk.perangkat_id
                JOIN siswa_kelas sk ON pk.kelas_id = sk.kelas_id
                WHERE sk.siswa_id = ? AND sk.tahun_pelajaran_id = (SELECT id FROM tahun_pelajaran WHERE status = 'aktif' LIMIT 1)
                ORDER BY p.created_at DESC";
$stmt_media = mysqli_prepare($conn, $query_media);
mysqli_stmt_bind_param($stmt_media, "i", $siswa_id);
mysqli_stmt_execute($stmt_media);
$media_list = mysqli_stmt_get_result($stmt_media);

$page_title = "Dashboard Siswa";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    body { background-color: #F8FAFC; }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 lg:p-8 max-w-4xl mx-auto">

        <div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-600 to-indigo-800 text-white relative overflow-hidden">
            <div class="relative z-10 flex items-center gap-6">
                <div class="w-24 h-24 rounded-2xl bg-white/20 backdrop-blur-md border border-white/30 flex items-center justify-center text-3xl font-black italic shadow-inner overflow-hidden">
                    <?php if(!empty($siswa['foto'])): ?>
                        <img src="<?= BASE_URL ?>uploads/siswa/<?= $siswa['foto'] ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?= strtoupper(substr($siswa['nama_siswa'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="text-2xl font-black italic tracking-tight leading-tight"><?= htmlspecialchars($siswa['nama_siswa']) ?></h1>
                    <p class="text-indigo-100 font-bold text-sm tracking-widest mt-1"><?= htmlspecialchars($siswa['nis']) ?> / <?= htmlspecialchars($siswa['nisn'] ?? '-') ?></p>
                    <div class="flex items-center gap-3 mt-3">
                        <span class="px-3 py-1 bg-white/10 rounded-full text-[10px] font-black uppercase tracking-widest border border-white/20"><?= htmlspecialchars($siswa['nama_kelas']) ?></span>
                        <span class="px-3 py-1 bg-emerald-400 text-emerald-950 rounded-full text-[10px] font-black uppercase tracking-widest italic">Aktif</span>
                    </div>
                </div>
            </div>
            <i class="fa fa-user-graduate absolute -bottom-6 -right-6 text-9xl opacity-10"></i>
        </div>

        <!-- Detail Data -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="lux-card p-5">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Jenis Kelamin</p>
                <p class="font-bold text-slate-800"><?= $siswa['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan' ?></p>
            </div>
            <div class="lux-card p-5">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">No. Telp/HP</p>
                <p class="font-bold text-slate-800"><?= htmlspecialchars($siswa['no_telp'] ?? '-') ?></p>
            </div>
            <div class="lux-card p-5">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Tahun Pelajaran</p>
                <p class="font-bold text-slate-800 italic"><?= htmlspecialchars($siswa['tahun_pelajaran']) ?></p>
            </div>
        </div>

        <div class="lux-card p-6 mb-8">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Alamat Lengkap</p>
            <p class="text-slate-700 italic"><?= nl2br(htmlspecialchars($siswa['alamat'] ?? 'Belum diisi')) ?></p>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-2 gap-4 mb-8 no-print">
            <a href="absensi.php" class="lux-card p-6 flex flex-col items-center justify-center text-center gap-3 group hover:bg-indigo-600 transition-all duration-500">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl group-hover:bg-white/20 group-hover:text-white transition-all duration-500 shadow-sm">
                    <i class="fa fa-fingerprint"></i>
                </div>
                <div>
                    <div class="text-sm font-black text-slate-800 italic uppercase tracking-tighter group-hover:text-white transition-colors">Absen GPS</div>
                    <div class="text-[8px] font-bold text-slate-400 uppercase tracking-widest group-hover:text-indigo-200 transition-colors">Presensi Lokasi</div>
                </div>
            </a>
            <a href="izin.php" class="lux-card p-6 flex flex-col items-center justify-center text-center gap-3 group hover:bg-emerald-600 transition-all duration-500">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl group-hover:bg-white/20 group-hover:text-white transition-all duration-500 shadow-sm">
                    <i class="fa fa-envelope-open-text"></i>
                </div>
                <div>
                    <div class="text-sm font-black text-slate-800 italic uppercase tracking-tighter group-hover:text-white transition-colors">Pengajuan Izin</div>
                    <div class="text-[8px] font-bold text-slate-400 uppercase tracking-widest group-hover:text-emerald-100 transition-colors">Sakit / Keperluan</div>
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

        <!-- Digital Media Section -->
        <h3 class="text-lg font-black text-slate-800 italic uppercase tracking-widest mb-4 flex items-center">
            <i class="fa fa-book-reader mr-2 text-indigo-500"></i> Media & Buku Digital
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php if (mysqli_num_rows($media_list) > 0): ?>
                <?php while ($m = mysqli_fetch_assoc($media_list)): ?>
                    <div class="lux-card p-5 bg-white border-none shadow-xl flex items-center gap-4 hover:scale-[1.02] transition-all group">
                        <div class="w-14 h-14 rounded-2xl bg-slate-50 text-slate-400 flex items-center justify-center text-2xl group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-all duration-500 shrink-0">
                            <?php
                            $icon = 'fa-file-alt';
                            if (strpos($m['file_path'], '.pdf') !== false) $icon = 'fa-file-pdf';
                            elseif (strpos($m['file_path'], '.mp4') !== false) $icon = 'fa-file-video';
                            ?>
                            <i class="fa <?= $icon ?>"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-black text-slate-800 text-sm truncate italic"><?= htmlspecialchars($m['nama_perangkat']) ?></h4>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1"><?= htmlspecialchars($m['nama_guru']) ?></p>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-600 text-[8px] font-black uppercase"><?= $m['jenis_perangkat'] ?></span>
                                <span class="text-[8px] text-slate-300 font-bold uppercase"><?= date('d M Y', strtotime($m['created_at'])) ?></span>
                            </div>
                        </div>
                        <a href="<?= BASE_URL . $m['file_path'] ?>" target="_blank" class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center hover:bg-indigo-600 transition-all shadow-lg">
                            <i class="fa <?= strpos($m['file_path'], '.mp4') !== false ? 'fa-play' : 'fa-download' ?> text-xs"></i>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full py-12 text-center lux-card bg-slate-100/50 border-dashed border-2 border-slate-200 shadow-none">
                    <i class="fa fa-folder-open text-3xl text-slate-200 mb-3"></i>
                    <p class="text-slate-400 font-bold italic tracking-widest text-[10px] uppercase">Belum ada media dibagikan ke kelas Anda</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
