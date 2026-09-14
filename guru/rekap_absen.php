<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['guru', 'admin']);
$page_title = "Rekap Absensi Jurnal";

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
$tgl_mulai = $_GET['tanggal_mulai'] ?? date('Y-m-d');
$tgl_selesai = $_GET['tanggal_selesai'] ?? date('Y-m-d');

$jurnals = [];
$siswas = [];
$pagin = null;

if ($kelas_id > 0) {
    $tgl_m_escaped = mysqli_real_escape_string($conn, $tgl_mulai);
    $tgl_s_escaped = mysqli_real_escape_string($conn, $tgl_selesai);
    $where_guru = ($_SESSION['role'] === 'admin') ? "1=1" : "j.guru_id = $guru_id";
    $where_mapel = ($mapel_id > 0) ? " AND j.mapel_id = $mapel_id" : "";

    // 1. Fetch all journals for this teacher, class, date range (and optional mapel)
    $query_jurnal = "SELECT j.id, j.tanggal, j.jam_ke, mp.nama_mapel
                     FROM jurnal j
                     JOIN mata_pelajaran mp ON j.mapel_id = mp.id
                     WHERE j.kelas_id = $kelas_id AND $where_guru $where_mapel AND j.tanggal BETWEEN '$tgl_m_escaped' AND '$tgl_s_escaped'
                     ORDER BY j.tanggal ASC, j.jam_ke ASC";
    $res_jurnal = mysqli_query($conn, $query_jurnal);

    while ($j = mysqli_fetch_assoc($res_jurnal)) {
        $j['absensi'] = [];
        $res_abs = mysqli_query($conn, "SELECT siswa_id, status FROM absensi_jurnal WHERE jurnal_id = " . $j['id']);
        while ($a = mysqli_fetch_assoc($res_abs)) {
            $j['absensi'][$a['siswa_id']] = $a['status'];
        }
        $jurnals[] = $j;
    }

    // 2. Fetch paginated list of students in the selected class
    $where_siswa = " WHERE sk.kelas_id = $kelas_id AND sk.tahun_pelajaran_id = $active_tahun_id";
    $pagin = get_pagination_data($conn, "siswa s JOIN siswa_kelas sk ON s.id = sk.siswa_id", 30, $where_siswa);

    $query_siswa = "SELECT s.id, s.nama_siswa, s.nis
                    FROM siswa s
                    JOIN siswa_kelas sk ON s.id = sk.siswa_id
                    $where_siswa
                    ORDER BY s.nama_siswa ASC LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";
    $res_siswa = mysqli_query($conn, $query_siswa);

    while ($s = mysqli_fetch_assoc($res_siswa)) {
        $siswas[] = $s;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>#sidebar, header, nav.navbar { display: none !important; } .lg\:ml-64 { margin-left: 0 !important; } .main-content { margin-left: 0 !important; padding-top: 2rem !important; }</style>

<div class="max-w-7xl mx-auto pb-20 px-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Rekap Absensi</h1>
            <p class="text-slate-500 font-medium">Pantau kehadiran siswa per jam mata pelajaran yang Anda ampu.</p>
        </div>
        <div class="flex gap-3">
            <?php if ($kelas_id > 0): ?>
            <a href="export_rekap_absen_excel.php?<?= http_build_query($_GET) ?>" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition-all shadow-lg shadow-emerald-100 flex items-center text-xs">
                <i class="fa fa-file-excel mr-2"></i> Ekspor Excel
            </a>
            <?php endif; ?>
            <a href="index.php" class="w-10 h-10 flex items-center justify-center rounded-2xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
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
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Mata Pelajaran (Opsional)</label>
                <select name="mapel_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold text-xs text-slate-700">
                    <option value="">-- Semua Mapel --</option>
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
                <button type="submit" class="flex-1 py-2.5 bg-indigo-600 text-white font-bold rounded-xl text-xs hover:bg-indigo-700 transition-all shadow-md shadow-indigo-100">Tampilkan Rekap</button>
                <a href="rekap_absen.php" class="px-4 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl text-xs hover:bg-slate-300 transition-all">Reset</a>
            </div>
        </form>
    </div>

    <?php if ($kelas_id > 0): ?>
    <div class="lux-card overflow-hidden bg-white shadow-2xl rounded-3xl mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest sticky left-0 bg-slate-50 z-10 min-w-[200px]">Nama Siswa</th>
                        <?php foreach ($jurnals as $j): ?>
                        <th class="px-4 py-4 text-center border-l border-slate-100 min-w-[90px]">
                            <div class="text-[10px] font-black text-indigo-500 uppercase whitespace-nowrap"><?= date('d/m', strtotime($j['tanggal'])) ?></div>
                            <div class="text-[10px] font-black text-indigo-500 uppercase whitespace-nowrap">Jam: <?= htmlspecialchars($j['jam_ke']) ?></div>
                            <div class="text-[9px] text-slate-400 font-bold truncate w-24 mx-auto" title="<?= htmlspecialchars($j['nama_mapel']) ?>"><?= htmlspecialchars($j['nama_mapel']) ?></div>
                        </th>
                        <?php endforeach; ?>
                        <?php if (!empty($jurnals)): ?>
                        <th class="px-4 py-4 text-center border-l border-slate-100 bg-emerald-50 text-emerald-700 font-black text-[10px] uppercase">H</th>
                        <th class="px-4 py-4 text-center border-l border-slate-100 bg-amber-50 text-amber-700 font-black text-[10px] uppercase">S</th>
                        <th class="px-4 py-4 text-center border-l border-slate-100 bg-blue-50 text-blue-700 font-black text-[10px] uppercase">I</th>
                        <th class="px-4 py-4 text-center border-l border-slate-100 bg-rose-50 text-rose-700 font-black text-[10px] uppercase">A</th>
                        <?php endif; ?>
                        <?php if (empty($jurnals)): ?>
                            <th class="px-6 py-4 text-center text-slate-400 italic text-sm">Belum ada data jurnal untuk kelas dan rentang tanggal terpilih.</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($siswas as $s): ?>
                    <?php
                    $cnt_h = 0; $cnt_s = 0; $cnt_i = 0; $cnt_a = 0;
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 font-bold text-slate-700 text-sm sticky left-0 bg-white group-hover:bg-slate-50/50 z-10 border-r border-slate-50">
                            <?= htmlspecialchars($s['nama_siswa']) ?>
                            <div class="text-[9px] text-slate-400 font-mono tracking-tighter"><?= $s['nis'] ?></div>
                        </td>
                        <?php foreach ($jurnals as $j): ?>
                        <td class="px-4 py-4 text-center border-l border-slate-50">
                            <?php
                            $status = $j['absensi'][$s['id']] ?? '-';
                            if ($status == 'H') $cnt_h++;
                            elseif ($status == 'S') $cnt_s++;
                            elseif ($status == 'I') $cnt_i++;
                            elseif ($status == 'A') $cnt_a++;

                            $colors = [
                                'H' => 'bg-emerald-500 text-white',
                                'S' => 'bg-amber-500 text-white',
                                'I' => 'bg-blue-500 text-white',
                                'A' => 'bg-rose-500 text-white',
                                '-' => 'bg-slate-100 text-slate-300'
                            ];
                            ?>
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg font-black text-[10px] <?= $colors[$status] ?>"><?= $status ?></span>
                        </td>
                        <?php endforeach; ?>
                        <?php if (!empty($jurnals)): ?>
                        <td class="px-4 py-4 text-center border-l border-slate-50 font-black text-emerald-600 text-xs bg-emerald-50/20"><?= $cnt_h ?></td>
                        <td class="px-4 py-4 text-center border-l border-slate-50 font-black text-amber-600 text-xs bg-amber-50/20"><?= $cnt_s ?></td>
                        <td class="px-4 py-4 text-center border-l border-slate-50 font-black text-blue-600 text-xs bg-blue-50/20"><?= $cnt_i ?></td>
                        <td class="px-4 py-4 text-center border-l border-slate-50 font-black text-rose-600 text-xs bg-rose-50/20"><?= $cnt_a ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($siswas)): ?>
                        <tr><td colspan="<?= count($jurnals) + 5 ?>" class="px-6 py-12 text-center text-slate-400 italic">Tidak ada data siswa di kelas ini.</td></tr>
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
        <div class="w-20 h-20 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl"><i class="fa fa-search"></i></div>
        <p class="text-slate-400 font-medium italic">Silakan pilih kelas dan rentang tanggal untuk melihat rekap absensi.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
