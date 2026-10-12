<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka', 'guru_bk']);

$page_title = "Portal Layanan Bimbingan Konseling";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-5xl mx-auto pb-24 px-4">
    <div class="flex items-center justify-between mb-8 pt-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Layanan Bimbingan Konseling (BK)</h1>
            <p class="text-slate-500 font-medium">Portal terpadu rekapitulasi konseling, sosiogram kelas, dan poin kedisiplinan siswa.</p>
        </div>
        <a href="index.php" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
            <i class="fa fa-arrow-left"></i>
        </a>
    </div>

    <!-- Main Tile Menu Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Card 1: Laporan Bimbingan Konseling -->
        <a href="bk_laporan.php" class="p-8 rounded-3xl bg-gradient-to-br from-indigo-600 to-indigo-800 text-white shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-1 group flex flex-col justify-between space-y-6">
            <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-2xl shadow-inner">
                <i class="fa fa-file-signature"></i>
            </div>
            <div>
                <h3 class="text-xl font-black italic tracking-tight">Laporan Bimbingan Konseling</h3>
                <p class="text-xs text-indigo-100 font-medium mt-1 leading-relaxed">Catatan rekapitulasi sesi konseling individu dan kelompok siswa serta opsi cetak laporan resmi.</p>
            </div>
            <div class="flex items-center text-xs font-bold text-white/90 group-hover:text-white pt-2">
                <span>Buka Laporan Konseling</span>
                <i class="fa fa-arrow-right ml-2 text-xs group-hover:translate-x-1 transition-transform"></i>
            </div>
        </a>

        <!-- Card 2: Sosiogram Aplikasi -->
        <a href="bk_sosiogram.php" class="p-8 rounded-3xl bg-gradient-to-br from-emerald-600 to-teal-700 text-white shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-1 group flex flex-col justify-between space-y-6">
            <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-2xl shadow-inner">
                <i class="fa fa-diagram-project"></i>
            </div>
            <div>
                <h3 class="text-xl font-black italic tracking-tight">Sistem Aplikasi Sosiogram</h3>
                <p class="text-xs text-emerald-100 font-medium mt-1 leading-relaxed">Pemetaan dinamika sosial kelas, analisis siswa terpopuler/star, dan deteksi siswa terisolasi.</p>
            </div>
            <div class="flex items-center text-xs font-bold text-white/90 group-hover:text-white pt-2">
                <span>Kelola Sosiogram</span>
                <i class="fa fa-arrow-right ml-2 text-xs group-hover:translate-x-1 transition-transform"></i>
            </div>
        </a>

        <!-- Card 3: Point Pelanggaran / Apresiasi -->
        <a href="bk_pelanggaran.php" class="p-8 rounded-3xl bg-gradient-to-br from-rose-600 to-amber-600 text-white shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-1 group flex flex-col justify-between space-y-6">
            <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-2xl shadow-inner">
                <i class="fa fa-award"></i>
            </div>
            <div>
                <h3 class="text-xl font-black italic tracking-tight">Poin Pelanggaran & Apresiasi</h3>
                <p class="text-xs text-rose-100 font-medium mt-1 leading-relaxed">Manajemen poin kedisiplinan, penetapan status peringatan SP1/SP2/SP3, dan pencetakan kartu poin.</p>
            </div>
            <div class="flex items-center text-xs font-bold text-white/90 group-hover:text-white pt-2">
                <span>Manajemen Poin Siswa</span>
                <i class="fa fa-arrow-right ml-2 text-xs group-hover:translate-x-1 transition-transform"></i>
            </div>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
