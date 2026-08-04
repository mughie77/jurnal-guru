<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka']);

$page_title = "Peta Kehadiran & Status Kelas";
$date_filter = $_GET['tanggal'] ?? date('Y-m-d');

// Fetch all classes
$classes_res = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
$classes = [];
while ($row = mysqli_fetch_assoc($classes_res)) {
    $classes[] = $row;
}

// Fetch all journals for selected date
$jurnals_by_class = [];
$res_j = mysqli_query($conn, "SELECT j.kelas_id, j.jam_ke, u.nama_lengkap, u.username, mp.nama_mapel
                             FROM jurnal j
                             JOIN guru g ON j.guru_id = g.id
                             JOIN users u ON g.user_id = u.id
                             JOIN mata_pelajaran mp ON j.mapel_id = mp.id
                             WHERE j.tanggal = '" . mysqli_real_escape_string($conn, $date_filter) . "'");
while ($row = mysqli_fetch_assoc($res_j)) {
    $jurnals_by_class[$row['kelas_id']][] = $row;
}

// Calculate Rekap
$kelas_ada_pengajar = 0;
$kelas_kosong = 0;
foreach ($classes as $c) {
    if (isset($jurnals_by_class[$c['id']])) {
        $kelas_ada_pengajar++;
    } else {
        $kelas_kosong++;
    }
}
$total_kelas = count($classes);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* CSS Tooltip styling for perfect cursor hover interactions */
.tooltip-trigger {
    position: relative;
}
.tooltip-content {
    visibility: hidden;
    position: absolute;
    bottom: 125%;
    left: 50%;
    transform: translateX(-50%);
    background-color: #1E293B;
    color: #F8FAFC;
    text-align: left;
    padding: 12px;
    border-radius: 12px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    z-index: 50;
    width: 250px;
    opacity: 0;
    transition: opacity 0.25s, visibility 0.25s;
    font-size: 11px;
    line-height: 1.5;
}
.tooltip-content::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -6px;
    border-width: 6px;
    border-style: solid;
    border-color: #1E293B transparent transparent transparent;
}
.tooltip-trigger:hover .tooltip-content {
    visibility: visible;
    opacity: 1;
}
</style>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Status Kelas Hari Ini</h1>
        <p class="text-slate-500">Peta visual status pengajaran dan aktivitas di seluruh kelas.</p>
    </div>
    <form action="" method="GET" class="flex items-center gap-3">
        <label class="text-xs font-bold text-slate-400 uppercase tracking-widest hidden sm:block">Pilih Tanggal:</label>
        <input type="date" name="tanggal" value="<?= htmlspecialchars($date_filter) ?>" onchange="this.form.submit()" class="px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700">
    </form>
</div>

<!-- Rekap Kelas Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="lux-card p-6 bg-gradient-to-br from-indigo-500 to-indigo-700 text-white border-none shadow-lg shadow-indigo-100 flex items-center justify-between">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.2em] opacity-80 mb-1">Total Kelas Terdaftar</p>
            <h3 class="text-3xl font-black italic tracking-tighter"><?= $total_kelas ?> Kelas</h3>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center text-2xl shadow-inner"><i class="fa fa-school"></i></div>
    </div>
    <div class="lux-card p-6 bg-white border-none shadow-lg shadow-emerald-100 flex items-center justify-between border-l-4 border-emerald-500">
        <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Kelas Ada Pengajar</p>
            <h3 class="text-3xl font-black italic text-emerald-600 tracking-tighter"><?= $kelas_ada_pengajar ?> Kelas</h3>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-500 flex items-center justify-center text-2xl"><i class="fa fa-user-tie"></i></div>
    </div>
    <div class="lux-card p-6 bg-white border-none shadow-lg shadow-rose-100 flex items-center justify-between border-l-4 border-rose-500">
        <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Kelas Kosong (Tanpa Pengajar)</p>
            <h3 class="text-3xl font-black italic text-rose-500 tracking-tighter"><?= $kelas_kosong ?> Kelas</h3>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center text-2xl"><i class="fa fa-user-times"></i></div>
    </div>
</div>

<!-- Kotak-kotak Besar (Peta Kelas Grid) -->
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-6">
    <?php foreach ($classes as $c): ?>
        <?php
        $has_teacher = isset($jurnals_by_class[$c['id']]);
        $teacher_details = $has_teacher ? $jurnals_by_class[$c['id']] : [];
        ?>
        <div class="tooltip-trigger">
            <?php if ($has_teacher): ?>
                <!-- Cell Hijau (Ada Pengajar) -->
                <div class="p-6 rounded-[24px] bg-emerald-500 text-white shadow-xl shadow-emerald-100 hover:shadow-emerald-200 transition-all hover:scale-[1.03] active:scale-95 flex flex-col justify-between h-40 border border-emerald-400 cursor-default">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase tracking-widest bg-white/20 px-2 py-0.5 rounded-full border border-white/10">TERISI</span>
                        <i class="fa fa-chalkboard-teacher text-lg opacity-85"></i>
                    </div>
                    <div>
                        <h4 class="text-xl font-black tracking-tight italic"><?= htmlspecialchars($c['nama_kelas']) ?></h4>
                        <p class="text-xs font-bold text-emerald-100 truncate mt-1">
                            Guru: <?= htmlspecialchars($teacher_details[0]['username'] ?? $teacher_details[0]['nama_lengkap']) ?>
                        </p>
                    </div>
                </div>

                <!-- Hover Details Tooltip -->
                <div class="tooltip-content space-y-3">
                    <div class="border-b border-slate-700/60 pb-1.5 mb-1.5 flex items-center justify-between">
                        <span class="font-black text-[9px] text-emerald-400 uppercase tracking-widest">Detail Aktif</span>
                        <span class="text-[9px] bg-emerald-500/20 text-emerald-300 px-1.5 py-0.5 rounded">Hari Ini</span>
                    </div>
                    <?php foreach ($teacher_details as $td): ?>
                        <div class="space-y-1">
                            <p class="font-bold text-slate-200 text-xs"><i class="fa fa-user-circle mr-1 text-slate-400"></i> <?= htmlspecialchars($td['nama_lengkap']) ?></p>
                            <p class="text-[10px] text-slate-300"><i class="fa fa-book mr-1 text-slate-400"></i> Mapel: <span class="font-semibold text-slate-100"><?= htmlspecialchars($td['nama_mapel']) ?></span></p>
                            <p class="text-[10px] text-slate-300"><i class="fa fa-clock mr-1 text-slate-400"></i> Jam ke: <span class="font-semibold text-slate-100"><?= htmlspecialchars($td['jam_ke']) ?></span></p>
                        </div>
                        <?php if (count($teacher_details) > 1): ?><hr class="border-slate-800 my-2"><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Cell Merah (Tidak Ada Pengajar) -->
                <div class="p-6 rounded-[24px] bg-rose-500 text-white shadow-xl shadow-rose-100 hover:shadow-rose-200 transition-all hover:scale-[1.03] active:scale-95 flex flex-col justify-between h-40 border border-rose-400 cursor-default">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase tracking-widest bg-white/20 px-2 py-0.5 rounded-full border border-white/10">KOSONG</span>
                        <i class="fa fa-exclamation-triangle text-lg opacity-85"></i>
                    </div>
                    <div>
                        <h4 class="text-xl font-black tracking-tight italic"><?= htmlspecialchars($c['nama_kelas']) ?></h4>
                        <p class="text-xs font-bold text-rose-100 truncate mt-1">
                            Tidak Ada Pengajar
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
