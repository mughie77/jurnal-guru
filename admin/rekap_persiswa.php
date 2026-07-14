<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin', 'waka']);
$page_title = "Rekap Kehadiran Siswa";

$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");
$tahun_pelajarans = mysqli_query($conn, "SELECT id, tahun FROM tahun_pelajaran ORDER BY tahun DESC");

$kelas_id = (int)($_GET['kelas_id'] ?? 0);
$filter_tipe = $_GET['filter_tipe'] ?? 'hari';

$start_date = '';
$end_date = '';

// Daily Range
$hari_mulai = $_GET['hari_mulai'] ?? date('Y-m-d');
$hari_selesai = $_GET['hari_selesai'] ?? date('Y-m-d');

// Monthly
$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun_select = (int)($_GET['tahun_select'] ?? date('Y'));

// Semester
$semester = $_GET['semester'] ?? 'ganjil';
$tahun_ajaran_id = (int)($_GET['tahun_ajaran_id'] ?? $active_tahun_id);

if ($kelas_id > 0) {
    if ($filter_tipe === 'hari') {
        $start_date = $hari_mulai;
        $end_date = $hari_selesai;
    } elseif ($filter_tipe === 'bulan') {
        $start_date = "$tahun_select-" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "-01";
        $end_date = date("Y-m-t", strtotime($start_date));
    } elseif ($filter_tipe === 'semester') {
        // Fetch selected school year string
        $q_th = mysqli_query($conn, "SELECT tahun FROM tahun_pelajaran WHERE id = $tahun_ajaran_id");
        $th_data = mysqli_fetch_assoc($q_th);
        $th_str = $th_data['tahun'] ?? ''; // e.g. "2025/2026"

        if (preg_match('/^(\d{4})\/(\d{4})$/', $th_str, $matches)) {
            $year_start = $matches[1];
            $year_end = $matches[2];

            if ($semester === 'ganjil') {
                $start_date = "$year_start-07-01";
                $end_date = "$year_start-12-31";
            } else {
                $start_date = "$year_end-01-01";
                $end_date = "$year_end-06-30";
            }
        } else {
            // Fallback to active year or current year
            $start_date = date('Y') . "-01-01";
            $end_date = date('Y') . "-12-31";
        }
    }
}

$rekap = [];
$pagin = null;
if ($kelas_id > 0 && $start_date && $end_date) {
    $where_siswa = " WHERE sk.kelas_id = $kelas_id AND sk.tahun_pelajaran_id = $active_tahun_id";
    $pagin = get_pagination_data($conn, "siswa s JOIN siswa_kelas sk ON s.id = sk.siswa_id", 50, $where_siswa);

    $query = "SELECT s.id, s.nis, s.nama_siswa, s.jenis_kelamin,
                     COUNT(CASE WHEN aj.status = 'H' THEN 1 END) as count_h,
                     COUNT(CASE WHEN aj.status = 'S' THEN 1 END) as count_s,
                     COUNT(CASE WHEN aj.status = 'I' THEN 1 END) as count_i,
                     COUNT(CASE WHEN aj.status = 'A' THEN 1 END) as count_a
              FROM siswa s
              JOIN siswa_kelas sk ON s.id = sk.siswa_id
              LEFT JOIN (
                  SELECT aj.siswa_id, aj.status
                  FROM absensi_jurnal aj
                  JOIN jurnal j ON aj.jurnal_id = j.id
                  WHERE j.kelas_id = $kelas_id AND j.tanggal BETWEEN '$start_date' AND '$end_date'
              ) aj ON s.id = aj.siswa_id
              $where_siswa
              GROUP BY s.id, s.nis, s.nama_siswa, s.jenis_kelamin
              ORDER BY s.nama_siswa ASC LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";

    $res = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($res)) {
        $rekap[] = $row;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Rekap Kehadiran per Siswa</h1>
    <p class="text-slate-500">Laporan total kehadiran per siswa berdasarkan rentang waktu pilihan.</p>
</div>

<div class="lux-card p-6 mb-8">
    <form action="" method="GET" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Pilih Kelas</label>
                <select name="kelas_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold">
                    <option value="">-- Pilih Kelas --</option>
                    <?php mysqli_data_seek($kelases, 0); while($k = mysqli_fetch_assoc($kelases)): ?>
                        <option value="<?= $k['id'] ?>" <?= $k['id'] == $kelas_id ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Tipe Filter</label>
                <select name="filter_tipe" id="filter_tipe" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold">
                    <option value="hari" <?= $filter_tipe === 'hari' ? 'selected' : '' ?>>Per Hari / Rentang Tanggal</option>
                    <option value="bulan" <?= $filter_tipe === 'bulan' ? 'selected' : '' ?>>Per Bulan</option>
                    <option value="semester" <?= $filter_tipe === 'semester' ? 'selected' : '' ?>>Per Semester</option>
                </select>
            </div>

            <!-- Dynamic Section For Daily -->
            <div id="section_hari" class="filter-section grid grid-cols-2 gap-4 <?= $filter_tipe !== 'hari' ? 'hidden' : '' ?>">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Mulai Tanggal</label>
                    <input type="date" name="hari_mulai" value="<?= htmlspecialchars($hari_mulai) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Selesai Tanggal</label>
                    <input type="date" name="hari_selesai" value="<?= htmlspecialchars($hari_selesai) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold">
                </div>
            </div>

            <!-- Dynamic Section For Monthly -->
            <div id="section_bulan" class="filter-section grid grid-cols-2 gap-4 <?= $filter_tipe !== 'bulan' ? 'hidden' : '' ?>">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Pilih Bulan</label>
                    <select name="bulan" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold">
                        <?php
                        $indonesian_months = [
                            1 => "Januari", 2 => "Februari", 3 => "Maret", 4 => "April", 5 => "Mei", 6 => "Juni",
                            7 => "Juli", 8 => "Agustus", 9 => "September", 10 => "Oktober", 11 => "November", 12 => "Desember"
                        ];
                        foreach ($indonesian_months as $m_num => $m_name): ?>
                            <option value="<?= $m_num ?>" <?= $m_num == $bulan ? 'selected' : '' ?>><?= $m_name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Pilih Tahun</label>
                    <select name="tahun_select" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold">
                        <?php for($y = date('Y') - 5; $y <= date('Y') + 2; $y++): ?>
                            <option value="<?= $y ?>" <?= $y == $tahun_select ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <!-- Dynamic Section For Semester -->
            <div id="section_semester" class="filter-section grid grid-cols-2 gap-4 <?= $filter_tipe !== 'semester' ? 'hidden' : '' ?>">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Pilih Semester</label>
                    <select name="semester" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold">
                        <option value="ganjil" <?= $semester === 'ganjil' ? 'selected' : '' ?>>Ganjil (Jul - Des)</option>
                        <option value="genap" <?= $semester === 'genap' ? 'selected' : '' ?>>Genap (Jan - Jun)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Tahun Ajaran</label>
                    <select name="tahun_ajaran_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold">
                        <?php mysqli_data_seek($tahun_pelajarans, 0); while($tp = mysqli_fetch_assoc($tahun_pelajarans)): ?>
                            <option value="<?= $tp['id'] ?>" <?= $tp['id'] == $tahun_ajaran_id ? 'selected' : '' ?>><?= htmlspecialchars($tp['tahun']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="px-8 py-3 bg-indigo-600 text-white font-bold rounded-2xl hover:bg-indigo-700 transition-all shadow-xl shadow-indigo-100">
                <i class="fa fa-filter mr-2"></i> Tampilkan Rekap
            </button>
        </div>
    </form>
</div>

<?php if ($kelas_id > 0 && $start_date && $end_date): ?>
<div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 px-2">
    <div>
        <p class="text-sm font-bold text-slate-500">Rentang Waktu Laporan: <span class="text-slate-800 underline font-black"><?= date('d F Y', strtotime($start_date)) ?></span> s/d <span class="text-slate-800 underline font-black"><?= date('d F Y', strtotime($end_date)) ?></span></p>
    </div>
</div>

<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest z-10">Nama Siswa</th>
                    <th class="px-6 py-4 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">NIS</th>
                    <th class="px-6 py-4 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">L/P</th>
                    <th class="px-4 py-4 text-center bg-emerald-50 text-emerald-700 font-black text-[10px] uppercase border-l border-slate-100">Hadir (H)</th>
                    <th class="px-4 py-4 text-center bg-amber-50 text-amber-700 font-black text-[10px] uppercase border-l border-slate-100">Sakit (S)</th>
                    <th class="px-4 py-4 text-center bg-blue-50 text-blue-700 font-black text-[10px] uppercase border-l border-slate-100">Izin (I)</th>
                    <th class="px-4 py-4 text-center bg-rose-50 text-rose-700 font-black text-[10px] uppercase border-l border-slate-100">Alfa (A)</th>
                    <th class="px-6 py-4 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest border-l border-slate-100">Total Jam</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($rekap as $r): ?>
                <?php
                $total_jam = $r['count_h'] + $r['count_s'] + $r['count_i'] + $r['count_a'];
                ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-bold text-slate-700 text-sm">
                        <?= htmlspecialchars($r['nama_siswa']) ?>
                    </td>
                    <td class="px-6 py-4 text-center font-mono text-xs text-slate-500 font-bold"><?= htmlspecialchars($r['nis']) ?></td>
                    <td class="px-6 py-4 text-center text-xs font-bold text-slate-500"><?= $r['jenis_kelamin'] ?></td>
                    <td class="px-4 py-4 text-center border-l border-slate-50 font-black text-emerald-600 text-sm bg-emerald-50/10"><?= $r['count_h'] ?></td>
                    <td class="px-4 py-4 text-center border-l border-slate-50 font-black text-amber-600 text-sm bg-amber-50/10"><?= $r['count_s'] ?></td>
                    <td class="px-4 py-4 text-center border-l border-slate-50 font-black text-blue-600 text-sm bg-blue-50/10"><?= $r['count_i'] ?></td>
                    <td class="px-4 py-4 text-center border-l border-slate-50 font-black text-rose-600 text-sm bg-rose-50/10"><?= $r['count_a'] ?></td>
                    <td class="px-6 py-4 text-center border-l border-slate-50 font-bold text-slate-700 text-sm bg-slate-50/30"><?= $total_jam ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($rekap)): ?>
                    <tr><td colspan="8" class="px-6 py-12 text-center text-slate-400 italic">Tidak ada data rekap siswa di kelas ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pagin): ?>
<?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>
<?php endif; ?>

<?php else: ?>
<div class="lux-card p-20 text-center">
    <div class="w-20 h-20 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl"><i class="fa fa-chart-pie"></i></div>
    <p class="text-slate-400 font-medium italic">Silakan pilih kelas dan atur parameter rentang waktu filter untuk menampilkan rekap.</p>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fTipe = document.getElementById('filter_tipe');

    function toggleSections() {
        const val = fTipe.value;
        document.querySelectorAll('.filter-section').forEach(sec => {
            sec.classList.add('hidden');
        });

        const targetSec = document.getElementById('section_' + val);
        if (targetSec) {
            targetSec.classList.remove('hidden');
        }
    }

    fTipe.addEventListener('change', toggleSections);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
