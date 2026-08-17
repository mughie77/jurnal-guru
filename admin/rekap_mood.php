<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

// Authorize role (accessible by admin and waka)
authorize_role(['admin', 'waka']);
$page_title = "Rekap & Analisis Mood Harian";

// Filter Parameters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$role_filter = $_GET['role'] ?? '';
$mood_filter = $_GET['mood'] ?? '';
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');

$where_clauses = [];
if (!empty($start_date)) {
    $where_clauses[] = "ms.tanggal >= '" . mysqli_real_escape_string($conn, $start_date) . "'";
}
if (!empty($end_date)) {
    $where_clauses[] = "ms.tanggal <= '" . mysqli_real_escape_string($conn, $end_date) . "'";
}
if (!empty($role_filter)) {
    $where_clauses[] = "ms.role = '" . mysqli_real_escape_string($conn, $role_filter) . "'";
}
if (!empty($mood_filter)) {
    $where_clauses[] = "ms.mood = '" . mysqli_real_escape_string($conn, $mood_filter) . "'";
}
if (!empty($search)) {
    $where_clauses[] = "(s.nama_siswa LIKE '%$search%' OR u.nama_lengkap LIKE '%$search%')";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(' AND ', $where_clauses);
}

// Year Active
$active_tahun_id = (int)($active_tahun_id ?? 0);

// Base count queries for analytics (independent of list pagination)
// 1. Total mood submissions with date filters
$total_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM mood_survey ms " .
    "LEFT JOIN siswa s ON ms.role = 'siswa' AND ms.user_id = s.id " .
    "LEFT JOIN users u ON ms.role = 'guru' AND ms.user_id = u.id " . $where_sql);
$total_count = mysqli_fetch_assoc($total_q)['total'] ?? 0;

// 2. Count by Role
$siswa_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM mood_survey ms " .
    "LEFT JOIN siswa s ON ms.role = 'siswa' AND ms.user_id = s.id " .
    "LEFT JOIN users u ON ms.role = 'guru' AND ms.user_id = u.id " .
    (!empty($where_sql) ? $where_sql . " AND ms.role = 'siswa'" : " WHERE ms.role = 'siswa'"));
$siswa_count = mysqli_fetch_assoc($siswa_q)['total'] ?? 0;

$guru_count = $total_count - $siswa_count;

// 3. Count by each Mood for chart
$mood_counts = [
    'sangat_baik' => 0,
    'bersemangat' => 0,
    'biasa_saja' => 0,
    'lelah' => 0,
    'stres' => 0,
    'sedih' => 0
];

$mood_chart_q = mysqli_query($conn, "SELECT ms.mood, COUNT(*) as count FROM mood_survey ms " .
    "LEFT JOIN siswa s ON ms.role = 'siswa' AND ms.user_id = s.id " .
    "LEFT JOIN users u ON ms.role = 'guru' AND ms.user_id = u.id " .
    $where_sql . " GROUP BY ms.mood");

while ($r = mysqli_fetch_assoc($mood_chart_q)) {
    if (isset($mood_counts[$r['mood']])) {
        $mood_counts[$r['mood']] = (int)$r['count'];
    }
}

// Find dominant/most common mood
$dominant_mood = '-';
$dominant_emoji = '❓';
$max_val = -1;
$mood_details = [
    'sangat_baik' => ['label' => 'Sangat Baik', 'emoji' => '😃', 'color' => 'bg-emerald-50 text-emerald-600 border-emerald-100'],
    'bersemangat' => ['label' => 'Bersemangat', 'emoji' => '💪', 'color' => 'bg-indigo-50 text-indigo-600 border-indigo-100'],
    'biasa_saja' => ['label' => 'Biasa Saja', 'emoji' => '😐', 'color' => 'bg-slate-50 text-slate-600 border-slate-100'],
    'lelah' => ['label' => 'Lelah', 'emoji' => '😴', 'color' => 'bg-amber-50 text-amber-600 border-amber-100'],
    'stres' => ['label' => 'Stres', 'emoji' => '😔', 'color' => 'bg-rose-50 text-rose-600 border-rose-100'],
    'sedih' => ['label' => 'Sedih', 'emoji' => '😢', 'color' => 'bg-blue-50 text-blue-600 border-blue-100'],
];

foreach ($mood_counts as $m => $val) {
    if ($val > $max_val && $val > 0) {
        $max_val = $val;
        $dominant_mood = $mood_details[$m]['label'];
        $dominant_emoji = $mood_details[$m]['emoji'];
    }
}

// Pagination setup for list view
$from_tables = "mood_survey ms
                LEFT JOIN siswa s ON ms.role = 'siswa' AND ms.user_id = s.id
                LEFT JOIN users u ON ms.role = 'guru' AND ms.user_id = u.id";

$pagin = get_pagination_data($conn, $from_tables, 15, $where_sql);

// Main query to load data
$sql = "SELECT ms.*,
               IF(ms.role = 'siswa', s.nama_siswa, u.nama_lengkap) AS nama_user,
               IF(ms.role = 'siswa', k.nama_kelas, '-') AS nama_kelas
        FROM mood_survey ms
        LEFT JOIN siswa s ON ms.role = 'siswa' AND ms.user_id = s.id
        LEFT JOIN siswa_kelas sk ON ms.role = 'siswa' AND s.id = sk.siswa_id AND sk.tahun_pelajaran_id = $active_tahun_id
        LEFT JOIN kelas k ON sk.kelas_id = k.id
        LEFT JOIN users u ON ms.role = 'guru' AND ms.user_id = u.id
        $where_sql
        ORDER BY ms.tanggal DESC, ms.created_at DESC
        LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";

$result = mysqli_query($conn, $sql);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Rekap & Analisis Mood</h1>
        <p class="text-slate-500">Analisis statistik kondisi mental dan mood siswa serta guru secara realtime.</p>
    </div>
    <div>
        <a href="export_mood.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-emerald-100 transition-all flex items-center">
            <i class="fa fa-file-excel mr-2"></i> Ekspor Excel
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="lux-card bg-gradient-to-br from-indigo-50 to-white p-6 relative overflow-hidden">
        <div class="absolute right-0 bottom-0 translate-x-4 translate-y-4 text-indigo-500/5 text-8xl font-black">
            <i class="fa fa-heart-pulse"></i>
        </div>
        <div class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Total Mood Terisi</div>
        <div class="text-3xl font-black text-slate-800 italic"><?= number_format($total_count) ?></div>
        <div class="text-xs text-indigo-600 font-bold mt-2">Partisipasi harian aktif</div>
    </div>

    <div class="lux-card bg-gradient-to-br from-emerald-50 to-white p-6 relative overflow-hidden">
        <div class="absolute right-0 bottom-0 translate-x-4 translate-y-4 text-emerald-500/5 text-8xl font-black">
            <i class="fa fa-user-graduate"></i>
        </div>
        <div class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Mood Siswa</div>
        <div class="text-3xl font-black text-slate-800 italic"><?= number_format($siswa_count) ?></div>
        <div class="text-xs text-emerald-600 font-bold mt-2">Kondisi mental murid</div>
    </div>

    <div class="lux-card bg-gradient-to-br from-amber-50 to-white p-6 relative overflow-hidden">
        <div class="absolute right-0 bottom-0 translate-x-4 translate-y-4 text-amber-500/5 text-8xl font-black">
            <i class="fa fa-chalkboard-teacher"></i>
        </div>
        <div class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Mood Guru</div>
        <div class="text-3xl font-black text-slate-800 italic"><?= number_format($guru_count) ?></div>
        <div class="text-xs text-amber-600 font-bold mt-2">Kondisi mental pengajar</div>
    </div>

    <div class="lux-card bg-gradient-to-br from-purple-50 to-white p-6 relative overflow-hidden">
        <div class="absolute right-0 bottom-0 translate-x-4 translate-y-4 text-purple-500/5 text-8xl font-black">
            <i class="fa fa-face-smile"></i>
        </div>
        <div class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Mood Terbanyak</div>
        <div class="text-3xl font-black text-slate-800 italic flex items-center gap-2">
            <span><?= $dominant_emoji ?></span>
            <span class="text-xl font-bold truncate max-w-[150px]"><?= $dominant_mood ?></span>
        </div>
        <div class="text-xs text-purple-600 font-bold mt-2">Dominasi psikologis saat ini</div>
    </div>
</div>

<!-- Filter Hari / Tanggal Spesifik untuk Grafik -->
<div class="mb-6 flex flex-wrap items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
    <div class="flex items-center gap-2">
        <i class="fa fa-calendar-alt text-indigo-500"></i>
        <h3 class="text-sm font-bold text-slate-800">Filter Tanggal Grafik & Distribusi Mood</h3>
    </div>
    <div class="flex items-center gap-3">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Pilih Hari:</span>
        <input type="date" id="chart-single-date" value="<?= ($start_date === $end_date && !empty($start_date)) ? htmlspecialchars($start_date) : '' ?>" onchange="applySingleDateFilter(this.value)" class="px-4 py-2 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700 text-xs">
        <?php if (!empty($start_date) || !empty($end_date)): ?>
            <a href="rekap_mood.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl text-xs transition-all">Clear Filter</a>
        <?php endif; ?>
    </div>
</div>

<script>
function applySingleDateFilter(val) {
    if (val) {
        window.location.href = '?start_date=' + val + '&end_date=' + val;
    } else {
        window.location.href = 'rekap_mood.php';
    }
}
</script>

<!-- Visual Charts & Analytics Layout -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    <!-- Chart.js Doughnut Section -->
    <div class="lux-card p-6 lg:col-span-1 bg-white flex flex-col justify-between">
        <div>
            <h3 class="text-lg font-bold text-slate-800 mb-1">Distribusi Mood</h3>
            <p class="text-xs text-slate-400 font-medium mb-6">Persentase persebaran perasaan hari ini</p>
        </div>

        <div class="relative w-full max-w-[220px] mx-auto mb-6">
            <canvas id="moodDoughnutChart"></canvas>
        </div>

        <div class="space-y-2">
            <?php foreach ($mood_counts as $m => $count):
                $percent = $total_count > 0 ? round(($count / $total_count) * 100, 1) : 0;
            ?>
            <div class="flex items-center justify-between text-xs font-semibold">
                <div class="flex items-center gap-2">
                    <span class="text-lg"><?= $mood_details[$m]['emoji'] ?></span>
                    <span class="text-slate-600"><?= $mood_details[$m]['label'] ?></span>
                </div>
                <div class="text-right flex items-center gap-2">
                    <span class="text-slate-400"><?= $count ?>x</span>
                    <span class="text-slate-800 font-bold"><?= $percent ?>%</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Chart.js Comparison Bar Section -->
    <div class="lux-card p-6 lg:col-span-2 bg-white flex flex-col justify-between">
        <div>
            <h3 class="text-lg font-bold text-slate-800 mb-1">Perbandingan Mood: Siswa vs Guru</h3>
            <p class="text-xs text-slate-400 font-medium mb-6">Analisis kontras kondisi emosional antara murid dan pendidik</p>
        </div>

        <div class="relative w-full h-[300px]">
            <canvas id="moodBarChart"></canvas>
        </div>
    </div>
</div>

<!-- Search & Filtering Panel -->
<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <!-- If accessed inside waka directory, retain file location context -->
        <input type="hidden" name="role_current" value="<?= htmlspecialchars($_SESSION['role']) ?>">

        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cari Nama</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama siswa/guru..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-semibold text-slate-700">
        </div>

        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Filter Peran</label>
            <select name="role" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700">
                <option value="">-- Semua Peran --</option>
                <option value="siswa" <?= $role_filter == 'siswa' ? 'selected' : '' ?>>Siswa</option>
                <option value="guru" <?= $role_filter == 'guru' ? 'selected' : '' ?>>Guru</option>
            </select>
        </div>

        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Filter Mood</label>
            <select name="mood" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700">
                <option value="">-- Semua Mood --</option>
                <?php foreach ($mood_details as $key => $dt): ?>
                    <option value="<?= $key ?>" <?= $mood_filter == $key ? 'selected' : '' ?>><?= $dt['emoji'] ?> <?= $dt['label'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Dari Tanggal</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm text-slate-700 font-semibold">
        </div>

        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Sampai Tanggal</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm text-slate-700 font-semibold">
        </div>

        <div class="lg:col-span-5 flex justify-end gap-3 pt-2">
            <a href="rekap_mood.php" class="px-6 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-300 transition-all text-sm flex items-center justify-center">Reset Filter</a>
            <button type="submit" class="px-8 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100 text-sm flex items-center justify-center">Terapkan Filter</button>
        </div>
    </form>
</div>

<!-- Detailed Records Table -->
<div class="lux-card overflow-hidden mb-6 bg-white">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="text-base font-bold text-slate-800">Rincian Log Mood Harian</h3>
        <span class="px-3 py-1 bg-indigo-50 text-indigo-600 text-xs font-black rounded-full"><?= number_format($pagin['total_data']) ?> Catatan</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Tanggal / Waktu</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Nama / Kelas</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Peran</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Kondisi Mood</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)):
                        $dt = $mood_details[$row['mood']] ?? ['label' => 'Unknown', 'emoji' => '❓', 'color' => 'bg-slate-50 text-slate-400 border-slate-100'];
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-all">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-bold text-slate-800"><?= date('d M Y', strtotime($row['tanggal'])) ?></div>
                            <div class="text-[10px] text-slate-400 font-bold italic"><?= date('H:i:s', strtotime($row['created_at'])) ?> WIB</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-slate-800 leading-tight"><?= htmlspecialchars($row['nama_user'] ?? 'No Name') ?></div>
                            <?php if ($row['role'] == 'siswa'): ?>
                                <div class="text-[10px] font-black uppercase text-indigo-500 mt-0.5 tracking-tight">Kelas: <?= htmlspecialchars($row['nama_kelas']) ?></div>
                            <?php else: ?>
                                <div class="text-[10px] font-black uppercase text-slate-400 mt-0.5 tracking-tight">-</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($row['role'] == 'siswa'): ?>
                                <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase tracking-wider border border-emerald-100">Siswa</span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 rounded-full bg-amber-50 text-amber-600 text-[10px] font-black uppercase tracking-wider border border-amber-100">Guru</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-3 py-1.5 rounded-2xl text-xs font-black border flex items-center gap-1.5 w-fit <?= $dt['color'] ?>">
                                <span><?= $dt['emoji'] ?></span>
                                <span class="uppercase tracking-wide text-[10px]"><?= $dt['label'] ?></span>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-20 text-center text-slate-400 font-medium italic">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <i class="fa fa-folder-open text-4xl text-slate-200"></i>
                                <span>Tidak ditemukan data log mood survey harian.</span>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>

<!-- Chart.js Rendering logic -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    // 1. Doughnut Chart Data
    const moodDoughnutCtx = document.getElementById('moodDoughnutChart').getContext('2d');
    const moodLabels = [
        'Sangat Baik 😃',
        'Bersemangat 💪',
        'Biasa Saja 😐',
        'Lelah 😴',
        'Stres 😔',
        'Sedih 😢'
    ];
    const moodData = [
        <?= (int)$mood_counts['sangat_baik'] ?>,
        <?= (int)$mood_counts['bersemangat'] ?>,
        <?= (int)$mood_counts['biasa_saja'] ?>,
        <?= (int)$mood_counts['lelah'] ?>,
        <?= (int)$mood_counts['stres'] ?>,
        <?= (int)$mood_counts['sedih'] ?>
    ];
    const moodColors = [
        '#10B981', // emerald-500
        '#4F46E5', // indigo-600
        '#64748B', // slate-500
        '#F59E0B', // amber-500
        '#F43F5E', // rose-500
        '#3B82F6'  // blue-500
    ];

    new Chart(moodDoughnutCtx, {
        type: 'doughnut',
        data: {
            labels: moodLabels,
            datasets: [{
                data: moodData,
                backgroundColor: moodColors,
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const val = context.raw || 0;
                            const total = moodData.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                            return ` ${label}: ${val} (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '70%'
        }
    });

    // 2. Dual Bar Comparison Chart Data
    <?php
    // Group count query for comparative bar chart (Siswa vs Guru)
    $siswa_moods = ['sangat_baik' => 0, 'bersemangat' => 0, 'biasa_saja' => 0, 'lelah' => 0, 'stres' => 0, 'sedih' => 0];
    $guru_moods = ['sangat_baik' => 0, 'bersemangat' => 0, 'biasa_saja' => 0, 'lelah' => 0, 'stres' => 0, 'sedih' => 0];

    $comp_q = mysqli_query($conn, "SELECT ms.role, ms.mood, COUNT(*) as count FROM mood_survey ms " .
        "LEFT JOIN siswa s ON ms.role = 'siswa' AND ms.user_id = s.id " .
        "LEFT JOIN users u ON ms.role = 'guru' AND ms.user_id = u.id " .
        $where_sql . " GROUP BY ms.role, ms.mood");

    while ($r = mysqli_fetch_assoc($comp_q)) {
        if ($r['role'] == 'siswa' && isset($siswa_moods[$r['mood']])) {
            $siswa_moods[$r['mood']] = (int)$r['count'];
        } elseif ($r['role'] == 'guru' && isset($guru_moods[$r['mood']])) {
            $guru_moods[$r['mood']] = (int)$r['count'];
        }
    }
    ?>

    const moodBarCtx = document.getElementById('moodBarChart').getContext('2d');
    new Chart(moodBarCtx, {
        type: 'bar',
        data: {
            labels: ['Sangat Baik 😃', 'Bersemangat 💪', 'Biasa Saja 😐', 'Lelah 😴', 'Stres 😔', 'Sedih 😢'],
            datasets: [
                {
                    label: 'Siswa',
                    data: [
                        <?= $siswa_moods['sangat_baik'] ?>,
                        <?= $siswa_moods['bersemangat'] ?>,
                        <?= $siswa_moods['biasa_saja'] ?>,
                        <?= $siswa_moods['lelah'] ?>,
                        <?= $siswa_moods['stres'] ?>,
                        <?= $siswa_moods['sedih'] ?>
                    ],
                    backgroundColor: 'rgba(16, 185, 129, 0.85)', // emerald
                    borderColor: '#10B981',
                    borderWidth: 1,
                    borderRadius: 8
                },
                {
                    label: 'Guru',
                    data: [
                        <?= $guru_moods['sangat_baik'] ?>,
                        <?= $guru_moods['bersemangat'] ?>,
                        <?= $guru_moods['biasa_saja'] ?>,
                        <?= $guru_moods['lelah'] ?>,
                        <?= $guru_moods['stres'] ?>,
                        <?= $guru_moods['sedih'] ?>
                    ],
                    backgroundColor: 'rgba(79, 70, 229, 0.85)', // indigo
                    borderColor: '#4F46E5',
                    borderWidth: 1,
                    borderRadius: 8
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: {
                            weight: 'bold',
                            family: 'Plus Jakarta Sans'
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
