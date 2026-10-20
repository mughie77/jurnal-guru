<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka', 'guru', 'guru_bk']);

$page_title = "Layanan Bimbingan Konseling";
require_once __DIR__ . '/../includes/header.php';

// Fetch quick stats for BK dashboard header
$total_laporan = 0;
$total_konsultasi_unread = 0;
$total_sosiogram = 0;

$q_lap = mysqli_query($conn, "SELECT COUNT(*) as total FROM bk_laporan");
if ($q_lap && $r = mysqli_fetch_assoc($q_lap)) $total_laporan = (int)$r['total'];

$q_chat = mysqli_query($conn, "SELECT COUNT(*) as total FROM pesan_konsultasi WHERE dibaca = 0 AND pengirim = 'siswa'");
if ($q_chat && $r = mysqli_fetch_assoc($q_chat)) $total_konsultasi_unread = (int)$r['total'];

$q_sos = mysqli_query($conn, "SELECT COUNT(*) as total FROM bk_sosiogram_sesi");
if ($q_sos && $r = mysqli_fetch_assoc($q_sos)) $total_sosiogram = (int)$r['total'];
?>

<div class="max-w-6xl mx-auto pb-24 px-4 sm:px-6">
    <!-- Header Title -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8 gap-4 pt-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight italic">Layanan Bimbingan Konseling</h1>
            <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">Pusat pengelolaan layanan BK, konseling siswa, sosiogram, dan kedisiplinan.</p>
        </div>
        <a href="<?= BASE_URL ?>guru/index.php" class="self-start sm:self-center px-4 py-2.5 rounded-2xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 font-bold text-xs shadow-sm transition-all flex items-center gap-2">
            <i class="fa fa-arrow-left text-slate-400"></i> Kembali ke Dashboard
        </a>
    </div>

    <!-- Quick Stats Minimal Banner -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="p-5 rounded-3xl bg-white border border-slate-100 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Total Konseling</span>
                <span class="text-2xl font-black text-slate-800 tracking-tight mt-0.5 block"><?= $total_laporan ?> <span class="text-xs font-normal text-slate-400">sesi</span></span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                <i class="fa fa-file-signature"></i>
            </div>
        </div>

        <div class="p-5 rounded-3xl bg-white border border-slate-100 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Pesan Konsultasi</span>
                <span class="text-2xl font-black text-slate-800 tracking-tight mt-0.5 block">
                    <?= $total_konsultasi_unread ?>
                    <span class="text-xs font-semibold text-rose-500"><?= $total_konsultasi_unread > 0 ? 'belum dibaca' : 'terbaca' ?></span>
                </span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                <i class="fa fa-comments"></i>
            </div>
        </div>

        <div class="p-5 rounded-3xl bg-white border border-slate-100 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Sesi Sosiogram</span>
                <span class="text-2xl font-black text-slate-800 tracking-tight mt-0.5 block"><?= $total_sosiogram ?> <span class="text-xs font-normal text-slate-400">kelas</span></span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                <i class="fa fa-diagram-project"></i>
            </div>
        </div>
    </div>

    <!-- Minimalist Menu Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

        <!-- 1. Konseling Individu -->
        <a href="<?= BASE_URL ?>admin/bk_laporan.php?jenis=Individu" class="p-6 rounded-3xl bg-white border border-slate-200/80 hover:border-indigo-500 shadow-sm hover:shadow-xl transition-all transform hover:-translate-y-1 group flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                    <i class="fa fa-user"></i>
                </div>
                <span class="text-[10px] font-extrabold uppercase tracking-widest px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700">RPL BK</span>
            </div>
            <div>
                <h3 class="text-lg font-black text-slate-800 tracking-tight group-hover:text-indigo-600 transition-colors">Konseling Individu</h3>
                <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed">Pengisian dan rekapitulasi RPL konseling individu siswa dengan opsi penyamaran nama (Kode Konseli).</p>
            </div>
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-600 group-hover:text-indigo-600">
                <span>Kelola Konseling Individu</span>
                <i class="fa fa-arrow-right text-slate-300 group-hover:text-indigo-600 group-hover:translate-x-1 transition-all"></i>
            </div>
        </a>

        <!-- 2. Konseling Kelompok -->
        <a href="<?= BASE_URL ?>admin/bk_laporan.php?jenis=Kelompok" class="p-6 rounded-3xl bg-white border border-slate-200/80 hover:border-sky-500 shadow-sm hover:shadow-xl transition-all transform hover:-translate-y-1 group flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between">
                <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center text-xl group-hover:bg-sky-600 group-hover:text-white transition-colors">
                    <i class="fa fa-users"></i>
                </div>
                <span class="text-[10px] font-extrabold uppercase tracking-widest px-2.5 py-1 rounded-full bg-sky-50 text-sky-700">Kelompok</span>
            </div>
            <div>
                <h3 class="text-lg font-black text-slate-800 tracking-tight group-hover:text-sky-600 transition-colors">Konseling Kelompok</h3>
                <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed">Pencatatan layanan konseling yang melibatkan sekelompok siswa dalam pembahasan masalah bersama.</p>
            </div>
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-600 group-hover:text-sky-600">
                <span>Kelola Konseling Kelompok</span>
                <i class="fa fa-arrow-right text-slate-300 group-hover:text-sky-600 group-hover:translate-x-1 transition-all"></i>
            </div>
        </a>

        <!-- 3. Kunjungan Rumah (Home Visit) -->
        <a href="<?= BASE_URL ?>admin/bk_laporan.php?jenis=Kunjungan+Rumah" class="p-6 rounded-3xl bg-white border border-slate-200/80 hover:border-amber-500 shadow-sm hover:shadow-xl transition-all transform hover:-translate-y-1 group flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl group-hover:bg-amber-600 group-hover:text-white transition-colors">
                    <i class="fa fa-house-user"></i>
                </div>
                <span class="text-[10px] font-extrabold uppercase tracking-widest px-2.5 py-1 rounded-full bg-amber-50 text-amber-700">Home Visit</span>
            </div>
            <div>
                <h3 class="text-lg font-black text-slate-800 tracking-tight group-hover:text-amber-600 transition-colors">Kunjungan Rumah</h3>
                <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed">Dokumentasi hasil kunjungan ke rumah siswa untuk koordinasi intensif bersama orang tua / wali.</p>
            </div>
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-600 group-hover:text-amber-600">
                <span>Kelola Kunjungan Rumah</span>
                <i class="fa fa-arrow-right text-slate-300 group-hover:text-amber-600 group-hover:translate-x-1 transition-all"></i>
            </div>
        </a>

        <!-- 4. Konferensi Kasus -->
        <a href="<?= BASE_URL ?>admin/bk_laporan.php?jenis=Konferensi+Kasus" class="p-6 rounded-3xl bg-white border border-slate-200/80 hover:border-purple-500 shadow-sm hover:shadow-xl transition-all transform hover:-translate-y-1 group flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between">
                <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl group-hover:bg-purple-600 group-hover:text-white transition-colors">
                    <i class="fa fa-handshake font-black"></i>
                </div>
                <span class="text-[10px] font-extrabold uppercase tracking-widest px-2.5 py-1 rounded-full bg-purple-50 text-purple-700">Kasus Khusus</span>
            </div>
            <div>
                <h3 class="text-lg font-black text-slate-800 tracking-tight group-hover:text-purple-600 transition-colors">Konferensi Kasus</h3>
                <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed">Pencatatan rapat pembahasan kasus siswa bersama kepala sekolah, waka, wali kelas, dan guru terkait.</p>
            </div>
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-600 group-hover:text-purple-600">
                <span>Kelola Konferensi Kasus</span>
                <i class="fa fa-arrow-right text-slate-300 group-hover:text-purple-600 group-hover:translate-x-1 transition-all"></i>
            </div>
        </a>

        <!-- 5. Layanan Chat Konsultasi -->
        <a href="<?= BASE_URL ?>guru/konsultasi.php" class="p-6 rounded-3xl bg-white border border-slate-200/80 hover:border-emerald-500 shadow-sm hover:shadow-xl transition-all transform hover:-translate-y-1 group flex flex-col justify-between space-y-4 relative overflow-hidden">
            <?php if ($total_konsultasi_unread > 0): ?>
                <span class="absolute top-4 right-4 bg-rose-500 text-white text-[10px] font-black px-2 py-0.5 rounded-full animate-pulse">
                    <?= $total_konsultasi_unread ?> Baru
                </span>
            <?php endif; ?>
            <div class="flex items-center justify-between">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                    <i class="fa fa-comments"></i>
                </div>
                <span class="text-[10px] font-extrabold uppercase tracking-widest px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700">Live Chat</span>
            </div>
            <div>
                <h3 class="text-lg font-black text-slate-800 tracking-tight group-hover:text-emerald-600 transition-colors">Layanan Chat Konsultasi</h3>
                <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed">Fitur pesan interaktif real-time antara siswa dan Guru BK untuk konsultasi bimbingan privat.</p>
            </div>
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-600 group-hover:text-emerald-600">
                <span>Buka Chat Konsultasi</span>
                <i class="fa fa-arrow-right text-slate-300 group-hover:text-emerald-600 group-hover:translate-x-1 transition-all"></i>
            </div>
        </a>

        <!-- 6. Sosiogram -->
        <a href="<?= BASE_URL ?>admin/bk_sosiogram.php" class="p-6 rounded-3xl bg-white border border-slate-200/80 hover:border-teal-500 shadow-sm hover:shadow-xl transition-all transform hover:-translate-y-1 group flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between">
                <div class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center text-xl group-hover:bg-teal-600 group-hover:text-white transition-colors">
                    <i class="fa fa-diagram-project"></i>
                </div>
                <span class="text-[10px] font-extrabold uppercase tracking-widest px-2.5 py-1 rounded-full bg-teal-50 text-teal-700">Aplikasi Social</span>
            </div>
            <div>
                <h3 class="text-lg font-black text-slate-800 tracking-tight group-hover:text-teal-600 transition-colors">Sosiogram</h3>
                <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed">Pemetaan jejaring sosial kelas, analisis siswa terpopuler/bintang, dan deteksi siswa terisolasi.</p>
            </div>
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-600 group-hover:text-teal-600">
                <span>Kelola Sosiogram</span>
                <i class="fa fa-arrow-right text-slate-300 group-hover:text-teal-600 group-hover:translate-x-1 transition-all"></i>
            </div>
        </a>

        <!-- 7. Point Pelanggaran -->
        <a href="<?= BASE_URL ?>admin/bk_pelanggaran.php" class="p-6 rounded-3xl bg-white border border-slate-200/80 hover:border-rose-500 shadow-sm hover:shadow-xl transition-all transform hover:-translate-y-1 group flex flex-col justify-between space-y-4 sm:col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between">
                <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl group-hover:bg-rose-600 group-hover:text-white transition-colors">
                    <i class="fa fa-award"></i>
                </div>
                <span class="text-[10px] font-extrabold uppercase tracking-widest px-2.5 py-1 rounded-full bg-rose-50 text-rose-700">Kedisiplinan</span>
            </div>
            <div>
                <h3 class="text-lg font-black text-slate-800 tracking-tight group-hover:text-rose-600 transition-colors">Point Pelanggaran</h3>
                <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed">Pencatatan poin pelanggaran & apresiasi siswa, pemantauan batas SP, serta pencetakan surat poin.</p>
            </div>
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-600 group-hover:text-rose-600">
                <span>Manajemen Poin Siswa</span>
                <i class="fa fa-arrow-right text-slate-300 group-hover:text-rose-600 group-hover:translate-x-1 transition-all"></i>
            </div>
        </a>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
