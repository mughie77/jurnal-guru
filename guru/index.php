<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);
$page_title = "Dashboard Guru";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Hide sidebar and topbar for Guru focus mode */
#sidebar { display: none; }
.lg\:ml-64 { margin-left: 0; }
header { display: none; }
</style>

<div class="max-w-5xl mx-auto">
    <!-- Header Guru -->
    <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 rounded-3xl p-8 md:p-12 text-white shadow-2xl mb-10 relative overflow-hidden">
        <div class="relative z-10">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h1 class="text-3xl md:text-4xl font-extrabold mb-2">Selamat Datang,</h1>
                    <p class="text-indigo-100 text-xl md:text-2xl opacity-90"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?>!</p>
                </div>
                <a href="<?= BASE_URL ?>logout.php" class="inline-flex items-center px-6 py-3 bg-white/10 hover:bg-white/20 backdrop-blur-md rounded-2xl border border-white/20 transition-all font-bold group">
                    <i class="fa fa-sign-out-alt mr-3 group-hover:translate-x-1 transition-transform"></i> Keluar Sistem
                </a>
            </div>
        </div>
        <!-- Decorative blobs -->
        <div class="absolute -top-12 -right-12 w-64 h-64 bg-indigo-500 rounded-full blur-3xl opacity-20"></div>
        <div class="absolute -bottom-12 -left-12 w-64 h-64 bg-indigo-400 rounded-full blur-3xl opacity-10"></div>
    </div>

    <!-- Menu Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
        <a href="<?= BASE_URL ?>guru/isi_jurnal.php" class="lux-card group p-8 text-center hover:scale-[1.03] transition-all duration-300">
            <div class="w-24 h-24 bg-indigo-50 text-indigo-600 rounded-3xl flex items-center justify-center mx-auto mb-6 group-hover:bg-indigo-600 group-hover:text-white transition-all duration-300 shadow-inner">
                <i class="fa fa-edit text-4xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-slate-800 mb-2">Isi Jurnal</h3>
            <p class="text-slate-500">Catat aktivitas pembelajaran hari ini dengan cepat dan mudah.</p>
        </a>

        <a href="<?= BASE_URL ?>guru/riwayat.php" class="lux-card group p-8 text-center hover:scale-[1.03] transition-all duration-300">
            <div class="w-24 h-24 bg-emerald-50 text-emerald-600 rounded-3xl flex items-center justify-center mx-auto mb-6 group-hover:bg-emerald-600 group-hover:text-white transition-all duration-300 shadow-inner">
                <i class="fa fa-history text-4xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-slate-800 mb-2">Riwayat Jurnal</h3>
            <p class="text-slate-500">Lihat dan tinjau kembali catatan mengajar Anda sebelumnya.</p>
        </a>
    </div>

    <!-- Quick Stats or Info -->
    <div class="lux-card p-6 bg-slate-50/50 border-slate-200/60">
        <div class="flex items-center text-slate-500">
            <i class="fa fa-calendar-alt mr-3"></i>
            <span class="font-medium"><?= date('l, d F Y') ?></span>
            <span class="mx-3 text-slate-300">|</span>
            <i class="fa fa-clock mr-3"></i>
            <span class="font-medium" id="liveClock">00:00:00</span>
        </div>
    </div>
</div>

<script>
function updateClock() {
    const now = new Date();
    document.getElementById('liveClock').textContent = now.toLocaleTimeString('id-ID');
}
setInterval(updateClock, 1000);
updateClock();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
