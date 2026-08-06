<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin', 'waka']);
$page_title = "Rekap Absensi Jurnal";

$kelas_id = (int)($_GET['kelas_id'] ?? 0);
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");

$rekap_data = [];
if ($kelas_id > 0) {
    // Ambil semua jurnal untuk kelas dan tanggal tersebut
    $query_jurnal = "SELECT j.id, j.jam_ke, mp.nama_mapel, u.nama_lengkap as nama_guru
                     FROM jurnal j
                     JOIN mata_pelajaran mp ON j.mapel_id = mp.id
                     JOIN guru g ON j.guru_id = g.id
                     JOIN users u ON g.user_id = u.id
                     WHERE j.kelas_id = $kelas_id AND j.tanggal = '$tanggal'
                     ORDER BY j.jam_ke ASC";
    $res_jurnal = mysqli_query($conn, $query_jurnal);

    $where_siswa = " WHERE sk.kelas_id = $kelas_id AND sk.tahun_pelajaran_id = $active_tahun_id";
    $pagin = get_pagination_data($conn, "siswa s JOIN siswa_kelas sk ON s.id = sk.siswa_id", 30, $where_siswa);

    // Ambil daftar siswa di kelas tersebut
    $query_siswa = "SELECT s.id, s.nama_siswa, s.nis
                    FROM siswa s
                    JOIN siswa_kelas sk ON s.id = sk.siswa_id
                    $where_siswa
                    ORDER BY s.nama_siswa ASC LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";
    $res_siswa = mysqli_query($conn, $query_siswa);

    $jurnals = [];
    while ($j = mysqli_fetch_assoc($res_jurnal)) {
        // Ambil data absensi untuk jurnal ini
        $j['absensi'] = [];
        $res_abs = mysqli_query($conn, "SELECT siswa_id, status FROM absensi_jurnal WHERE jurnal_id = " . $j['id']);
        while ($a = mysqli_fetch_assoc($res_abs)) {
            $j['absensi'][$a['siswa_id']] = $a['status'];
        }
        $jurnals[] = $j;
    }

    $siswas = [];
    while ($s = mysqli_fetch_assoc($res_siswa)) {
        $siswas[] = $s;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Rekap Absensi</h1>
        <p class="text-slate-500">Pantau kehadiran siswa per jam mata pelajaran.</p>
    </div>
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
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Tanggal</label>
            <input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold">
        </div>
        <button type="submit" class="px-8 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100">Tampilkan Rekap</button>
    </form>
</div>

<?php if ($kelas_id > 0): ?>
<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest sticky left-0 bg-slate-50 z-10">Nama Siswa</th>
                    <?php foreach ($jurnals as $j): ?>
                    <th class="px-4 py-4 text-center border-l border-slate-100">
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
                    <?php if(empty($jurnals)): ?>
                        <th class="px-6 py-4 text-center text-slate-400 italic text-sm">Belum ada data jurnal untuk hari ini.</th>
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
                <?php if(empty($siswas)): ?>
                    <tr><td colspan="<?= count($jurnals) + 1 ?>" class="px-6 py-12 text-center text-slate-400 italic">Tidak ada data siswa di kelas ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>

<?php else: ?>
<div class="lux-card p-20 text-center">
    <div class="w-20 h-20 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl"><i class="fa fa-search"></i></div>
    <p class="text-slate-400 font-medium italic">Silakan pilih kelas dan tanggal untuk melihat rekap absensi.</p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
