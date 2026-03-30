<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);

$total_guru = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM guru"))['total'];
$total_mapel = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM mata_pelajaran"))['total'];
$total_kelas = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM kelas"))['total'];
$today = date('Y-m-d');
$total_jurnal_hari_ini = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM jurnal WHERE tanggal = '$today'"))['total'];

$page_title = "Beranda Admin";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Beranda Admin</h1>
    <p class="text-slate-500">Ringkasan statistik sistem saat ini.</p>
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
    <div class="lux-card p-6 flex items-center group hover:scale-[1.02] transition-transform duration-300">
        <div class="p-4 rounded-2xl bg-<?= $stat['color'] ?>-50 text-<?= $stat['color'] ?>-600 mr-5 group-hover:bg-<?= $stat['color'] ?>-600 group-hover:text-white transition-colors duration-300">
            <i class="fas <?= $stat['icon'] ?> text-2xl"></i>
        </div>
        <div>
            <p class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-1"><?= $stat['label'] ?></p>
            <p class="text-2xl font-extrabold text-slate-800"><?= $stat['value'] ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="lux-card">
    <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-gradient-to-r from-indigo-50/50 to-transparent">
        <h2 class="text-xl font-bold text-slate-800">Selamat Datang!</h2>
        <span class="px-4 py-1.5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold uppercase tracking-widest">Sistem Aktif</span>
    </div>
    <div class="p-8 leading-relaxed text-slate-600">
        <p class="mb-4">Selamat datang di Panel Administrasi <span class="font-bold text-slate-800 uppercase tracking-tight italic text-indigo-600">CAKRA</span> - <?= htmlspecialchars($app_name) ?>.</p>
        <p>Gunakan menu navigasi di sisi kiri untuk mengelola infrastruktur data sekolah Anda. Anda memiliki kendali penuh atas manajemen guru, siswa, kelas, serta pemantauan jurnal mengajar secara real-time.</p>

        <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 flex items-start">
                <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center mr-4 mt-1">
                    <i class="fa fa-shield-alt"></i>
                </div>
                <div>
                    <h4 class="font-bold text-slate-800">Data Aman</h4>
                    <p class="text-sm">Semua interaksi database dienkripsi dan diproteksi.</p>
                </div>
            </div>
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 flex items-start">
                <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center mr-4 mt-1">
                    <i class="fa fa-bolt"></i>
                </div>
                <div>
                    <h4 class="font-bold text-slate-800">Akses Cepat</h4>
                    <p class="text-sm">Antarmuka mewah dioptimalkan untuk performa tinggi.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
