<?php
require_once __DIR__ . '/../config/database.php';

// Get Settings
$res_set = mysqli_query($conn, "SELECT * FROM pengaturan");
$app_sets = [];
while ($r = mysqli_fetch_assoc($res_set)) {
    $app_sets[$r['nama_setting']] = $r['nilai_setting'];
}
$app_name = $app_sets['nama_sekolah'] ?? 'CAKRA';
$favicon = !empty($app_sets['favicon']) ? BASE_URL . 'uploads/' . $app_sets['favicon'] : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halaman Tidak Ditemukan - <?= htmlspecialchars($app_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .bg-mesh {
            background-color: #4f46e5;
            background-image:
                radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
        }
        @keyframes float {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
            100% { transform: translateY(0px) rotate(0deg); }
        }
        .animate-float { animation: float 6s ease-in-out infinite; }
        .text-glow { text-shadow: 0 0 20px rgba(79, 70, 229, 0.4); }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-2xl w-full text-center relative">
        <!-- Background Elements -->
        <div class="absolute -top-24 -left-24 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-24 -right-24 w-64 h-64 bg-rose-500/10 rounded-full blur-3xl"></div>

        <div class="relative z-10">
            <div class="mb-8 inline-block">
                <div class="w-32 h-32 rounded-[40px] bg-white shadow-2xl flex items-center justify-center animate-float relative overflow-hidden group">
                    <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <?php if ($favicon): ?>
                        <img src="<?= $favicon ?>" class="w-16 h-16 object-contain">
                    <?php else: ?>
                        <i class="fa fa-map-signs text-5xl text-indigo-600"></i>
                    <?php endif; ?>
                </div>
            </div>

            <h1 class="text-[120px] font-black text-slate-900 leading-none tracking-tighter mb-4 italic text-glow">
                404
            </h1>

            <h2 class="text-3xl font-black text-slate-800 uppercase tracking-widest italic mb-6">
                Tersesat di <span class="text-indigo-600">Luar Jangkauan.</span>
            </h2>

            <p class="text-slate-500 font-bold italic leading-relaxed max-w-md mx-auto mb-12 uppercase tracking-widest text-xs">
                Halaman yang Anda cari tidak dapat ditemukan atau telah dipindahkan ke dimensi lain.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="<?= BASE_URL ?>" class="w-full sm:w-auto px-10 py-4 bg-slate-900 text-white font-black rounded-2xl shadow-xl shadow-slate-200 hover:bg-indigo-600 hover:shadow-indigo-200 transition-all active:scale-95 flex items-center justify-center gap-3 uppercase tracking-widest italic text-xs">
                    <i class="fa fa-home"></i>
                    Kembali ke Beranda
                </a>
                <button onclick="history.back()" class="w-full sm:w-auto px-10 py-4 bg-white border-2 border-slate-100 text-slate-600 font-black rounded-2xl hover:bg-slate-50 transition-all active:scale-95 flex items-center justify-center gap-3 uppercase tracking-widest italic text-xs">
                    <i class="fa fa-arrow-left"></i>
                    Halaman Sebelumnya
                </button>
            </div>

            <div class="mt-20 flex items-center justify-center gap-8 opacity-30">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-slate-400"></div>
                    <span class="text-[8px] font-black uppercase tracking-[0.3em] text-slate-500">CAKRA CORE</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-slate-400"></div>
                    <span class="text-[8px] font-black uppercase tracking-[0.3em] text-slate-500">VER 2.0</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
