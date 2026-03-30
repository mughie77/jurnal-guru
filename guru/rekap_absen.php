<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);
$page_title = "Rekap Absensi Saya";

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
$guru_id = mysqli_fetch_assoc($guru_res)['id'];

$kelas_id = (int)($_GET['kelas_id'] ?? 0);
$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");

require_once __DIR__ . '/../includes/header.php';
?>

<style>#sidebar, header, nav.navbar { display: none !important; } .lg\:ml-64 { margin-left: 0 !important; } .main-content { margin-left: 0 !important; padding-top: 2rem !important; }</style>

<div class="max-w-6xl mx-auto pb-20 px-4">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Rekap Absensi Jurnal</h1>
            <p class="text-slate-500 font-medium">Tinjau kehadiran siswa pada jam mengajar Anda.</p>
        </div>
        <a href="index.php" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
            <i class="fa fa-arrow-left"></i>
        </a>
    </div>

    <div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
        <form action="" method="GET" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Pilih Kelas</label>
                <select name="kelas_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold">
                    <option value="">-- Pilih Kelas --</option>
                    <?php mysqli_data_seek($kelases, 0); while($k = mysqli_fetch_assoc($kelases)): ?>
                        <option value="<?= $k['id'] ?>" <?= $k['id'] == $kelas_id ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="px-8 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100">Cari Data</button>
        </form>
    </div>

    <?php if ($kelas_id > 0):
        // Ambil data absensi agregat per siswa untuk guru yang login di kelas terpilih
        $sql = "SELECT s.nama_siswa, s.nis,
                COUNT(CASE WHEN aj.status = 'H' THEN 1 END) as hadir,
                COUNT(CASE WHEN aj.status = 'S' THEN 1 END) as sakit,
                COUNT(CASE WHEN aj.status = 'I' THEN 1 END) as izin,
                COUNT(CASE WHEN aj.status = 'A' THEN 1 END) as alfa,
                COUNT(aj.id) as total
                FROM siswa s
                JOIN siswa_kelas sk ON s.id = sk.siswa_id
                LEFT JOIN absensi_jurnal aj ON s.id = aj.siswa_id
                LEFT JOIN jurnal j ON aj.jurnal_id = j.id AND j.guru_id = $guru_id
                WHERE sk.kelas_id = $kelas_id AND sk.tahun_pelajaran_id = $active_tahun_id
                GROUP BY s.id ORDER BY s.nama_siswa ASC";
        $res = mysqli_query($conn, $sql);
    ?>
    <div class="lux-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Nama Siswa</th>
                        <th class="px-4 py-4 text-center text-[10px] font-black text-emerald-600 uppercase tracking-widest">Hadir</th>
                        <th class="px-4 py-4 text-center text-[10px] font-black text-amber-600 uppercase tracking-widest">Sakit</th>
                        <th class="px-4 py-4 text-center text-[10px] font-black text-blue-600 uppercase tracking-widest">Izin</th>
                        <th class="px-4 py-4 text-center text-[10px] font-black text-rose-600 uppercase tracking-widest">Alfa</th>
                        <th class="px-6 py-4 text-center text-[10px] font-black text-indigo-600 uppercase tracking-widest">Persentase</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php while($row = mysqli_fetch_assoc($res)):
                        $pct = $row['total'] > 0 ? round(($row['hadir'] / $row['total']) * 100) : 0;
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-700 text-sm"><?= htmlspecialchars($row['nama_siswa']) ?></div>
                            <div class="text-[9px] text-slate-400 font-mono"><?= $row['nis'] ?></div>
                        </td>
                        <td class="px-4 py-4 text-center font-black text-emerald-600 text-sm"><?= $row['hadir'] ?></td>
                        <td class="px-4 py-4 text-center font-black text-amber-600 text-sm"><?= $row['sakit'] ?></td>
                        <td class="px-4 py-4 text-center font-black text-blue-600 text-sm"><?= $row['izin'] ?></td>
                        <td class="px-4 py-4 text-center font-black text-rose-600 text-sm"><?= $row['alfa'] ?></td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center gap-3">
                                <div class="w-24 bg-slate-100 h-2 rounded-full overflow-hidden">
                                    <div class="bg-indigo-500 h-full transition-all" style="width: <?= $pct ?>%"></div>
                                </div>
                                <span class="text-xs font-black text-slate-700"><?= $pct ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
