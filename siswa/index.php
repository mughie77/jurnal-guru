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

// Attendance Recap per Subject
$query_absensi = "SELECT mp.nama_mapel,
                  SUM(CASE WHEN aj.status = 'H' THEN 1 ELSE 0 END) as hadir,
                  SUM(CASE WHEN aj.status = 'S' THEN 1 ELSE 0 END) as sakit,
                  SUM(CASE WHEN aj.status = 'I' THEN 1 ELSE 0 END) as izin,
                  SUM(CASE WHEN aj.status = 'A' THEN 1 ELSE 0 END) as alfa,
                  (SELECT COUNT(*) FROM absensi_harian ah WHERE ah.siswa_id = ? AND ah.status = 'Terlambat' AND ah.tanggal IN (SELECT tanggal FROM jurnal WHERE mapel_id = mp.id)) as terlambat
                  FROM absensi_jurnal aj
                  JOIN jurnal j ON aj.jurnal_id = j.id
                  JOIN mata_pelajaran mp ON j.mapel_id = mp.id
                  WHERE aj.siswa_id = ? AND j.tahun_pelajaran_id = ?
                  GROUP BY mp.id";
$stmt_abs = mysqli_prepare($conn, $query_absensi);
mysqli_stmt_bind_param($stmt_abs, "iii", $siswa_id, $siswa_id, $active_tahun_id);
mysqli_stmt_execute($stmt_abs);
$absensi_recap = mysqli_stmt_get_result($stmt_abs);

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
        <!-- Header Profil -->
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

        <!-- Rekap Absensi -->
        <h3 class="text-lg font-black text-slate-800 italic uppercase tracking-widest mb-4 flex items-center">
            <i class="fa fa-chart-pie mr-2 text-indigo-500"></i> Rekap Kehadiran
        </h3>

        <div class="space-y-4">
            <?php if (mysqli_num_rows($absensi_recap) > 0): ?>
                <?php while ($abs = mysqli_fetch_assoc($absensi_recap)):
                    $total_per_mapel = $abs['hadir'] + $abs['sakit'] + $abs['izin'] + $abs['alfa'] + $abs['terlambat'];
                    $persentase = $total_per_mapel > 0 ? (($abs['hadir'] + $abs['terlambat']) / $total_per_mapel) * 100 : 0;
                ?>
                <div class="lux-card p-6 hover:shadow-2xl transition-all group">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex-1">
                            <h4 class="font-black text-slate-800 uppercase tracking-tighter italic text-base group-hover:text-indigo-600 transition-colors"><?= htmlspecialchars($abs['nama_mapel']) ?></h4>
                            <div class="flex flex-wrap gap-2 mt-3">
                                <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded text-[10px] font-black uppercase border border-emerald-100">Hadir: <?= $abs['hadir'] ?></span>
                                <span class="px-2 py-0.5 bg-amber-50 text-amber-600 rounded text-[10px] font-black uppercase border border-amber-100">Telat: <?= $abs['terlambat'] ?></span>
                                <span class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] font-black uppercase border border-blue-100">S/I: <?= $abs['sakit'] + $abs['izin'] ?></span>
                                <span class="px-2 py-0.5 bg-rose-50 text-rose-600 rounded text-[10px] font-black uppercase border border-rose-100">Alfa: <?= $abs['alfa'] ?></span>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-2xl font-black italic <?= $persentase >= 80 ? 'text-emerald-500' : 'text-amber-500' ?>"><?= round($persentase) ?>%</div>
                            <p class="text-[8px] font-black text-slate-400 uppercase tracking-[0.2em]">Kehadiran</p>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="lux-card p-12 text-center">
                    <div class="w-16 h-16 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fa fa-calendar-times text-2xl"></i></div>
                    <p class="text-slate-400 italic font-medium">Belum ada data absensi yang terekam.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
