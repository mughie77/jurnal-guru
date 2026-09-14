<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);
$page_title = "Rekap Absensi Saya";

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
if (mysqli_num_rows($guru_res) == 0) die("Error: Data guru tidak ditemukan.");
$guru_id = mysqli_fetch_assoc($guru_res)['id'];

// Get classes
$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");

// Get subjects assigned to this teacher (or all mapels if admin)
if ($_SESSION['role'] === 'admin') {
    $mapels = mysqli_query($conn, "SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel ASC");
} else {
    $mapels = mysqli_query($conn, "SELECT mp.id, mp.nama_mapel FROM mata_pelajaran mp JOIN guru_mapel gm ON mp.id = gm.mapel_id WHERE gm.guru_id = $guru_id ORDER BY mp.nama_mapel ASC");
}

$kelas_id = (int)($_GET['kelas_id'] ?? 0);
$mapel_id = (int)($_GET['mapel_id'] ?? 0);
$tgl_mulai = $_GET['tanggal_mulai'] ?? date('Y-m-01');
$tgl_selesai = $_GET['tanggal_selesai'] ?? date('Y-m-d');

require_once __DIR__ . '/../includes/header.php';
?>

<style>#sidebar, header, nav.navbar { display: none !important; } .lg\:ml-64 { margin-left: 0 !important; } .main-content { margin-left: 0 !important; padding-top: 2rem !important; }</style>

<div class="max-w-6xl mx-auto pb-20 px-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Rekap Absensi Siswa Per Mapel</h1>
            <p class="text-slate-500 font-medium">Monitoring rekapitulasi kehadiran per-siswa pada mata pelajaran yang Anda ampu.</p>
        </div>
        <div class="flex gap-3">
            <?php if ($kelas_id > 0 && $mapel_id > 0): ?>
            <a href="export_rekap_absen_excel.php?<?= http_build_query($_GET) ?>" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition-all shadow-lg shadow-emerald-100 flex items-center">
                <i class="fa fa-file-excel mr-2"></i> Ekspor Excel
            </a>
            <?php endif; ?>
            <a href="index.php" class="w-11 h-11 flex items-center justify-center rounded-2xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
                <i class="fa fa-arrow-left"></i>
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
        <form action="" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Pilih Kelas</label>
                <select name="kelas_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold text-xs text-slate-700">
                    <option value="">-- Pilih Kelas --</option>
                    <?php mysqli_data_seek($kelases, 0); while($k = mysqli_fetch_assoc($kelases)): ?>
                        <option value="<?= $k['id'] ?>" <?= $k['id'] == $kelas_id ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Mata Pelajaran</label>
                <select name="mapel_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold text-xs text-slate-700">
                    <option value="">-- Pilih Mapel --</option>
                    <?php mysqli_data_seek($mapels, 0); while($m = mysqli_fetch_assoc($mapels)): ?>
                        <option value="<?= $m['id'] ?>" <?= $m['id'] == $mapel_id ? 'selected' : '' ?>><?= htmlspecialchars($m['nama_mapel']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Rentang Tanggal</label>
                <div class="flex items-center gap-1.5">
                    <input type="date" name="tanggal_mulai" value="<?= htmlspecialchars($tgl_mulai) ?>" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700">
                    <span class="text-slate-400 font-bold">-</span>
                    <input type="date" name="tanggal_selesai" value="<?= htmlspecialchars($tgl_selesai) ?>" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700">
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="flex-1 py-2.5 bg-indigo-600 text-white font-bold rounded-xl text-xs hover:bg-indigo-700 transition-all shadow-md shadow-indigo-100">Tampilkan</button>
                <a href="rekap_absen.php" class="px-4 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl text-xs hover:bg-slate-300 transition-all">Reset</a>
            </div>
        </form>
    </div>

    <?php if ($kelas_id > 0 && $mapel_id > 0):
        $tgl_m_escaped = mysqli_real_escape_string($conn, $tgl_mulai);
        $tgl_s_escaped = mysqli_real_escape_string($conn, $tgl_selesai);

        // Fetch student aggregated attendance for this specific subject and teacher
        $where_guru = ($_SESSION['role'] === 'admin') ? "1=1" : "j.guru_id = $guru_id";

        $sql = "SELECT s.id as siswa_id, s.nama_siswa, s.nis, s.nisn,
                COUNT(CASE WHEN aj.status = 'H' THEN 1 END) as hadir,
                COUNT(CASE WHEN aj.status = 'S' THEN 1 END) as sakit,
                COUNT(CASE WHEN aj.status = 'I' THEN 1 END) as izin,
                COUNT(CASE WHEN aj.status = 'A' THEN 1 END) as alfa,
                COUNT(aj.id) as total_pertemuan
                FROM siswa s
                JOIN siswa_kelas sk ON s.id = sk.siswa_id
                LEFT JOIN absensi_jurnal aj ON s.id = aj.siswa_id
                LEFT JOIN jurnal j ON aj.jurnal_id = j.id AND j.mapel_id = $mapel_id AND j.kelas_id = $kelas_id AND $where_guru AND j.tanggal BETWEEN '$tgl_m_escaped' AND '$tgl_s_escaped'
                WHERE sk.kelas_id = $kelas_id AND sk.tahun_pelajaran_id = $active_tahun_id
                GROUP BY s.id
                ORDER BY s.nama_siswa ASC";
        $res = mysqli_query($conn, $sql);
    ?>
    <div class="lux-card overflow-hidden bg-white shadow-2xl rounded-3xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Siswa</th>
                        <th class="px-4 py-4 text-center text-[10px] font-black text-emerald-600 uppercase tracking-widest">Hadir (H)</th>
                        <th class="px-4 py-4 text-center text-[10px] font-black text-amber-600 uppercase tracking-widest">Sakit (S)</th>
                        <th class="px-4 py-4 text-center text-[10px] font-black text-blue-600 uppercase tracking-widest">Izin (I)</th>
                        <th class="px-4 py-4 text-center text-[10px] font-black text-rose-600 uppercase tracking-widest">Alfa (A)</th>
                        <th class="px-4 py-4 text-center text-[10px] font-black text-slate-600 uppercase tracking-widest">Total Sesi</th>
                        <th class="px-6 py-4 text-center text-[10px] font-black text-indigo-600 uppercase tracking-widest">Persentase</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (mysqli_num_rows($res) == 0): ?>
                        <tr><td colspan="7" class="px-6 py-12 text-center text-slate-400 font-bold italic">Tidak ada siswa terdaftar di kelas ini.</td></tr>
                    <?php else: ?>
                        <?php while($row = mysqli_fetch_assoc($res)):
                            $total = (int)$row['total_pertemuan'];
                            $hadir = (int)$row['hadir'];
                            $pct = $total > 0 ? round(($hadir / $total) * 100) : 0;
                        ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($row['nama_siswa']) ?></div>
                                <div class="text-[10px] text-slate-400 font-bold">NIS: <?= htmlspecialchars($row['nis']) ?> <?= !empty($row['nisn']) ? "| NISN: " . htmlspecialchars($row['nisn']) : '' ?></div>
                            </td>
                            <td class="px-4 py-4 text-center font-black text-emerald-600 text-sm bg-emerald-50/20"><?= $row['hadir'] ?></td>
                            <td class="px-4 py-4 text-center font-black text-amber-600 text-sm bg-amber-50/20"><?= $row['sakit'] ?></td>
                            <td class="px-4 py-4 text-center font-black text-blue-600 text-sm bg-blue-50/20"><?= $row['izin'] ?></td>
                            <td class="px-4 py-4 text-center font-black text-rose-600 text-sm bg-rose-50/20"><?= $row['alfa'] ?></td>
                            <td class="px-4 py-4 text-center font-black text-slate-700 text-sm"><?= $total ?></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center gap-3">
                                    <div class="w-20 bg-slate-100 h-2 rounded-full overflow-hidden">
                                        <div class="bg-indigo-600 h-full transition-all" style="width: <?= $pct ?>%"></div>
                                    </div>
                                    <span class="text-xs font-black text-slate-700"><?= $pct ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="lux-card p-16 text-center">
        <div class="w-16 h-16 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl"><i class="fa fa-filter"></i></div>
        <p class="text-slate-400 font-medium italic text-sm">Silakan pilih kelas dan mata pelajaran untuk menampilkan rekap absensi siswa per-mapel.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
