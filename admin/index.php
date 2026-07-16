<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);

$today = date('Y-m-d');

// KPIs
$total_guru = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM guru"))['total'] ?? 0;
$total_siswa = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM siswa"))['total'] ?? 0;
$total_kelas = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM kelas"))['total'] ?? 0;
$total_jurnal_hari_ini = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM jurnal WHERE tanggal = '$today'"))['total'] ?? 0;

// Student Daily Attendance Stats (Hadir, Sakit, Izin, Alfa, Terlambat)
$atten_stats = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Alfa' => 0, 'Terlambat' => 0];
$att_q = mysqli_query($conn, "SELECT status, COUNT(*) as count FROM absensi_harian WHERE tanggal = '$today' GROUP BY status");
if ($att_q) {
    while ($r = mysqli_fetch_assoc($att_q)) {
        if (isset($atten_stats[$r['status']])) {
            $atten_stats[$r['status']] = (int)$r['count'];
        }
    }
}

// 7 Days Journal Trend
$journal_trend = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('d M', strtotime($date));
    $journal_trend[$date] = ['label' => $label, 'count' => 0];
}
$trend_q = mysqli_query($conn, "SELECT tanggal, COUNT(*) as count FROM jurnal WHERE tanggal >= DATE_SUB('$today', INTERVAL 6 DAY) GROUP BY tanggal");
if ($trend_q) {
    while ($r = mysqli_fetch_assoc($trend_q)) {
        if (isset($journal_trend[$r['tanggal']])) {
            $journal_trend[$r['tanggal']]['count'] = (int)$r['count'];
        }
    }
}

// Recent Jurnals List
$recent_jurnals = mysqli_query($conn, "SELECT j.*, u.nama_lengkap as nama_guru, k.nama_kelas, mp.nama_mapel
    FROM jurnal j
    JOIN guru g ON j.guru_id = g.id
    JOIN users u ON g.user_id = u.id
    JOIN kelas k ON j.kelas_id = k.id
    JOIN mata_pelajaran mp ON j.mapel_id = mp.id
    ORDER BY j.tanggal DESC, j.created_at DESC LIMIT 5");

// Latest Active Mood or Panic logs to show on the side
$recent_moods = mysqli_query($conn, "SELECT ms.*,
       IF(ms.role = 'siswa', s.nama_siswa, u.nama_lengkap) AS nama_user
    FROM mood_survey ms
    LEFT JOIN siswa s ON ms.role = 'siswa' AND ms.user_id = s.id
    LEFT JOIN users u ON ms.role = 'guru' AND ms.user_id = u.id
    ORDER BY ms.created_at DESC LIMIT 5");

$mood_details = [
    'sangat_baik' => ['label' => 'Sangat Baik', 'emoji' => '😃', 'color' => 'bg-emerald-50 text-emerald-600 border-emerald-100'],
    'bersemangat' => ['label' => 'Bersemangat', 'emoji' => '💪', 'color' => 'bg-indigo-50 text-indigo-600 border-indigo-100'],
    'biasa_saja' => ['label' => 'Biasa Saja', 'emoji' => '😐', 'color' => 'bg-slate-50 text-slate-600 border-slate-100'],
    'lelah' => ['label' => 'Lelah', 'emoji' => '😴', 'color' => 'bg-amber-50 text-amber-600 border-amber-100'],
    'stres' => ['label' => 'Stres', 'emoji' => '😔', 'color' => 'bg-rose-50 text-rose-600 border-rose-100'],
    'sedih' => ['label' => 'Sedih', 'emoji' => '😢', 'color' => 'bg-blue-50 text-blue-600 border-blue-100'],
];

$page_title = "Beranda Admin";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Beranda Admin</h1>
    <p class="text-slate-500">Pemantauan dan statistik aktivitas akademik real-time.</p>
</div>

<!-- KPIs Section -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="lux-card bg-gradient-to-br from-indigo-50 to-white p-6 relative overflow-hidden group hover:scale-[1.02] transition-transform duration-300">
        <div class="absolute right-0 bottom-0 translate-x-4 translate-y-4 text-indigo-500/5 text-8xl font-black">
            <i class="fa fa-chalkboard-teacher"></i>
        </div>
        <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Total Tenaga Pendidik</p>
        <div class="text-3xl font-black text-slate-800 italic"><?= number_format($total_guru) ?> Guru</div>
        <div class="text-xs text-indigo-600 font-bold mt-2">Dapodik Terintegrasi</div>
    </div>

    <div class="lux-card bg-gradient-to-br from-emerald-50 to-white p-6 relative overflow-hidden group hover:scale-[1.02] transition-transform duration-300">
        <div class="absolute right-0 bottom-0 translate-x-4 translate-y-4 text-emerald-500/5 text-8xl font-black">
            <i class="fa fa-user-graduate"></i>
        </div>
        <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Total Peserta Didik</p>
        <div class="text-3xl font-black text-slate-800 italic"><?= number_format($total_siswa) ?> Siswa</div>
        <div class="text-xs text-emerald-600 font-bold mt-2">Partisipan Aktif Kelas</div>
    </div>

    <div class="lux-card bg-gradient-to-br from-sky-50 to-white p-6 relative overflow-hidden group hover:scale-[1.02] transition-transform duration-300">
        <div class="absolute right-0 bottom-0 translate-x-4 translate-y-4 text-sky-500/5 text-8xl font-black">
            <i class="fa fa-school"></i>
        </div>
        <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Manajemen Kelas</p>
        <div class="text-3xl font-black text-slate-800 italic"><?= number_format($total_kelas) ?> Ruang</div>
        <div class="text-xs text-sky-600 font-bold mt-2">Tersebar di Berbagai Jenjang</div>
    </div>

    <div class="lux-card bg-gradient-to-br from-amber-50 to-white p-6 relative overflow-hidden group hover:scale-[1.02] transition-transform duration-300">
        <div class="absolute right-0 bottom-0 translate-x-4 translate-y-4 text-amber-500/5 text-8xl font-black">
            <i class="fa fa-calendar-check"></i>
        </div>
        <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Jurnal Hari Ini</p>
        <div class="text-3xl font-black text-slate-800 italic"><?= number_format($total_jurnal_hari_ini) ?> Agenda</div>
        <div class="text-xs text-amber-600 font-bold mt-2">Tercatat di Jurnal Mengajar</div>
    </div>
</div>

<!-- Charts Section -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    <!-- Today Attendance Pie Card -->
    <div class="lux-card p-6 lg:col-span-1 bg-white flex flex-col justify-between">
        <div>
            <h3 class="text-lg font-bold text-slate-800 mb-1">Presensi Hari Ini</h3>
            <p class="text-xs text-slate-400 font-medium mb-6">Distribusi absensi harian siswa hari ini</p>
        </div>

        <?php
        $total_today_attendance = array_sum($atten_stats);
        ?>
        <div class="relative w-full max-w-[200px] mx-auto mb-6">
            <?php if ($total_today_attendance > 0): ?>
                <canvas id="todayAttendanceChart"></canvas>
            <?php else: ?>
                <div class="h-[200px] flex flex-col items-center justify-center text-center text-slate-400 italic gap-2 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                    <i class="fa fa-user-clock text-4xl text-slate-300"></i>
                    <span class="text-xs font-semibold px-4">Belum ada siswa melakukan absensi hari ini.</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="space-y-2">
            <?php foreach ($atten_stats as $status => $count):
                $percent = $total_today_attendance > 0 ? round(($count / $total_today_attendance) * 100, 1) : 0;
                $status_emoji = '✔️';
                if ($status == 'Sakit') $status_emoji = '🤒';
                if ($status == 'Izin') $status_emoji = '✉️';
                if ($status == 'Alfa') $status_emoji = '❌';
                if ($status == 'Terlambat') $status_emoji = '⏰';
            ?>
            <div class="flex items-center justify-between text-xs font-semibold">
                <div class="flex items-center gap-2">
                    <span><?= $status_emoji ?></span>
                    <span class="text-slate-600"><?= $status ?></span>
                </div>
                <div class="text-right flex items-center gap-2">
                    <span class="text-slate-400"><?= $count ?> Siswa</span>
                    <span class="text-slate-800 font-bold"><?= $percent ?>%</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 7 Days Journal Stats Line Card -->
    <div class="lux-card p-6 lg:col-span-2 bg-white flex flex-col justify-between">
        <div>
            <h3 class="text-lg font-bold text-slate-800 mb-1">Aktivitas Mengajar</h3>
            <p class="text-xs text-slate-400 font-medium mb-6">Intensitas pengisian agenda jurnal guru 7 hari terakhir</p>
        </div>

        <div class="relative w-full h-[300px]">
            <canvas id="journalTrendChart"></canvas>
        </div>
    </div>
</div>

<!-- Logs & Recent Submissions Section -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    <!-- Recent Jurnals (2/3 width) -->
    <div class="lux-card p-6 lg:col-span-2 bg-white">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-bold text-slate-800 mb-1">Agenda Mengajar Terbaru</h3>
                <p class="text-xs text-slate-400 font-medium">Data input jurnal mengajar guru terakhir</p>
            </div>
            <a href="jurnal.php" class="text-xs font-black text-indigo-600 hover:underline">Lihat Semua</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-widest">Guru / Kelas</th>
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-widest">Mata Pelajaran</th>
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Kehadiran</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (mysqli_num_rows($recent_jurnals) > 0): ?>
                        <?php while ($rj = mysqli_fetch_assoc($recent_jurnals)): ?>
                        <tr class="hover:bg-slate-50/50 transition-all">
                            <td class="px-4 py-3">
                                <div class="text-sm font-bold text-slate-800 leading-tight"><?= htmlspecialchars($rj['nama_guru']) ?></div>
                                <div class="text-[10px] font-black uppercase text-indigo-500 mt-0.5 tracking-tight">Kelas: <?= htmlspecialchars($rj['nama_kelas']) ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-bold text-slate-700 leading-tight"><?= htmlspecialchars($rj['nama_mapel']) ?></div>
                                <div class="text-[10px] text-slate-400 font-bold italic">Jam Ke: <?= htmlspecialchars($rj['jam_ke']) ?></div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-3 py-1 bg-emerald-50 text-emerald-600 text-xs font-black rounded-xl border border-emerald-100">
                                    <?= $rj['jml_hadir'] ?>/<?= $rj['jml_hadir'] + $rj['jml_sakit'] + $rj['jml_izin'] + $rj['jml_alfa'] ?> Hadir
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="px-4 py-12 text-center text-slate-400 font-medium italic">
                                Belum ada pengisian agenda jurnal hari ini.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Mood Logs (1/3 width) -->
    <div class="lux-card p-6 lg:col-span-1 bg-white">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-bold text-slate-800 mb-1">Mood Survey Terakhir</h3>
                <p class="text-xs text-slate-400 font-medium">Log kondisi emosional terbaru</p>
            </div>
            <a href="rekap_mood.php" class="text-xs font-black text-indigo-600 hover:underline">Lihat Semua</a>
        </div>

        <div class="space-y-4">
            <?php if (mysqli_num_rows($recent_moods) > 0): ?>
                <?php while ($rm = mysqli_fetch_assoc($recent_moods)):
                    $md = $mood_details[$rm['mood']] ?? ['label' => 'Unknown', 'emoji' => '❓', 'color' => 'bg-slate-50 text-slate-400 border-slate-100'];
                ?>
                <div class="flex items-center justify-between p-3 rounded-2xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition-all">
                    <div>
                        <div class="text-xs font-black text-slate-800 leading-tight"><?= htmlspecialchars($rm['nama_user']) ?></div>
                        <div class="text-[9px] font-bold text-slate-400 mt-1 uppercase tracking-wider"><?= $rm['role'] ?> • <?= date('d M H:i', strtotime($rm['created_at'])) ?></div>
                    </div>
                    <span class="w-10 h-10 rounded-full flex items-center justify-center text-xl bg-white shadow-sm border border-slate-100" title="<?= $md['label'] ?>">
                        <?= $md['emoji'] ?>
                    </span>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="py-12 text-center text-slate-400 font-medium italic">
                    Belum ada survey mood yang diisi hari ini.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    <?php if ($total_today_attendance > 0): ?>
    // Today Attendance Doughnut Chart
    const todayAttCtx = document.getElementById('todayAttendanceChart').getContext('2d');
    new Chart(todayAttCtx, {
        type: 'doughnut',
        data: {
            labels: ['Hadir', 'Sakit', 'Izin', 'Alfa', 'Terlambat'],
            datasets: [{
                data: [
                    <?= (int)$atten_stats['Hadir'] ?>,
                    <?= (int)$atten_stats['Sakit'] ?>,
                    <?= (int)$atten_stats['Izin'] ?>,
                    <?= (int)$atten_stats['Alfa'] ?>,
                    <?= (int)$atten_stats['Terlambat'] ?>
                ],
                backgroundColor: ['#10B981', '#F59E0B', '#3B82F6', '#EF4444', '#8B5CF6'],
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            cutout: '75%'
        }
    });
    <?php endif; ?>

    // 7 Days Journal Trend Bar/Line Chart
    const journalLabels = [
        <?php foreach ($journal_trend as $trend) { echo "'" . $trend['label'] . "',"; } ?>
    ];
    const journalData = [
        <?php foreach ($journal_trend as $trend) { echo $trend['count'] . ","; } ?>
    ];

    const trendCtx = document.getElementById('journalTrendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'bar',
        data: {
            labels: journalLabels,
            datasets: [{
                label: 'Agenda Jurnal Terisi',
                data: journalData,
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                borderColor: '#4F46E5',
                borderWidth: 2,
                borderRadius: 8,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
