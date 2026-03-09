<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);
$page_title = "Dashboard Guru";
require_once __DIR__ . '/../includes/header.php';
?>

<style>#sidebar, header { display: none; } .lg\:ml-64 { margin-left: 0; }</style>

<div class="max-w-6xl mx-auto pb-12 px-4 md:px-0">
    <div class="bg-gradient-to-br from-indigo-600 via-purple-600 to-indigo-800 rounded-[2.5rem] p-10 md:p-16 text-white shadow-2xl mb-12 relative overflow-hidden">
        <div class="relative z-10">
            <h1 class="text-4xl md:text-5xl font-black mb-4 italic tracking-tighter">Halo, <?= explode(' ', $_SESSION['nama_lengkap'])[0] ?>!</h1>
            <p class="text-indigo-100 text-lg md:text-xl opacity-90 max-w-xl font-medium leading-relaxed">Selamat datang di portal mengajar Anda. Silakan kelola jurnal dan absensi siswa dengan mudah.</p>
        </div>
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-white/10 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -left-24 w-64 h-64 bg-indigo-400/20 rounded-full blur-3xl"></div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <?php
        $menus = [
            ['title' => 'Isi Jurnal & Absen', 'desc' => 'Catat materi & kehadiran siswa.', 'icon' => 'fa-edit', 'color' => 'indigo', 'link' => 'isi_jurnal.php'],
            ['title' => 'Riwayat Jurnal', 'desc' => 'Lihat arsip pengajaran Anda.', 'icon' => 'fa-history', 'color' => 'emerald', 'link' => 'riwayat.php'],
            ['title' => 'Rekap Absensi', 'desc' => 'Pantau kehadiran per kelas.', 'icon' => 'fa-chart-pie', 'color' => 'amber', 'link' => 'rekap_absen.php'],
        ];
        foreach ($menus as $m):
        ?>
        <a href="<?= $m['link'] ?>" class="lux-card group p-10 text-center hover:scale-[1.05] transition-all duration-500 border-b-8 border-<?= $m['color'] ?>-500">
            <div class="w-24 h-24 bg-<?= $m['color'] ?>-50 text-<?= $m['color'] ?>-600 rounded-[2rem] flex items-center justify-center mx-auto mb-8 group-hover:bg-<?= $m['color'] ?>-600 group-hover:text-white group-hover:rotate-12 transition-all duration-500 shadow-xl shadow-<?= $m['color'] ?>-100">
                <i class="fa <?= $m['icon'] ?> text-4xl"></i>
            </div>
            <h3 class="text-2xl font-black text-slate-800 mb-3 italic tracking-tight"><?= $m['title'] ?></h3>
            <p class="text-slate-500 font-medium"><?= $m['desc'] ?></p>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="mt-12 pt-12 border-t border-slate-200 flex justify-center">
        <a href="<?= BASE_URL ?>logout.php" class="inline-flex items-center px-10 py-4 bg-rose-600 hover:bg-rose-700 text-white rounded-2xl font-black shadow-xl shadow-rose-100 transition-all transform hover:-translate-y-1 active:scale-95 uppercase tracking-widest text-sm">
            <i class="fa fa-sign-out-alt mr-3 text-lg"></i> Keluar Sistem
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
