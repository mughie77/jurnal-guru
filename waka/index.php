<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['waka', 'admin']);

$total_guru = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM guru"))['total'];
$total_mapel = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM mata_pelajaran"))['total'];
$total_kelas = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM kelas"))['total'];
$today = date('Y-m-d');
$total_jurnal_hari_ini = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM jurnal WHERE tanggal = '$today'"))['total'];

$page_title = "Dashboard Waka Kurikulum";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Dashboard Waka</h1>
    <p class="text-slate-500">Pemantauan aktivitas akademik dan kurikulum sekolah.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <?php
    $stats = [
        ['label' => 'Total Guru', 'value' => $total_guru, 'icon' => 'fa-chalkboard-teacher', 'color' => 'indigo'],
        ['label' => 'Mata Pelajaran', 'value' => $total_mapel, 'icon' => 'fa-book', 'color' => 'emerald'],
        ['label' => 'Total Kelas', 'value' => $total_kelas, 'icon' => 'fa-school', 'color' => 'sky'],
        ['label' => 'Jurnal Hari Ini', 'value' => $total_jurnal_hari_ini, 'icon' => 'fa-calendar-check', 'color' => 'amber'],
    ];
    foreach ($stats as $stat):
    ?>
    <div class="lux-card p-6 flex items-center group transition-all duration-300 hover:shadow-2xl">
        <div class="p-4 rounded-2xl bg-<?= $stat['color'] ?>-50 text-<?= $stat['color'] ?>-600 mr-5 transition-all duration-300 group-hover:scale-110">
            <i class="fas <?= $stat['icon'] ?> text-2xl"></i>
        </div>
        <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1"><?= $stat['label'] ?></p>
            <p class="text-2xl font-extrabold text-slate-800"><?= $stat['value'] ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="lux-card p-10 bg-white relative overflow-hidden">
    <div class="relative z-10">
        <h2 class="text-2xl font-bold text-slate-800 mb-4 italic">Ringkasan Laporan</h2>
        <p class="text-slate-600 leading-relaxed max-w-2xl mb-8">Anda memiliki akses penuh untuk meninjau laporan jurnal mengajar di seluruh unit pendidikan. Gunakan fitur filter pada menu laporan untuk mendapatkan data spesifik berdasarkan tanggal, kelas, maupun mata pelajaran.</p>
        <a href="jurnal.php" class="inline-flex items-center px-6 py-3 bg-slate-900 text-white rounded-xl font-bold hover:bg-slate-800 transition-all shadow-xl">
            Lihat Semua Jurnal <i class="fa fa-arrow-right ml-3"></i>
        </a>
    </div>
    <i class="fa fa-chart-line absolute -bottom-10 -right-10 text-[200px] text-slate-50"></i>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
