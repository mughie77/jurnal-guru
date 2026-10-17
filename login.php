<?php
require_once 'config/database.php';

// Get Settings
$res_set = mysqli_query($conn, "SELECT * FROM pengaturan");
$app_sets = [];
while ($r = mysqli_fetch_assoc($res_set)) {
    $app_sets[$r['nama_setting']] = $r['nilai_setting'];
}
$app_name = $app_sets['nama_sekolah'] ?? 'SMK Negeri 1 Bondowoso';
$favicon = !empty($app_sets['favicon']) ? BASE_URL . 'uploads/' . $app_sets['favicon'] : null;

$error_message = '';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL);
    exit();
}

// 1. Jumlah pengisian jurnal per hari ini
$today = date('Y-m-d');
$q_jurnal_today = mysqli_query($conn, "SELECT COUNT(*) as total FROM jurnal WHERE tanggal = '$today'");
$jurnal_today_count = ($q_jurnal_today && $r = mysqli_fetch_assoc($q_jurnal_today)) ? (int)$r['total'] : 0;

// Dynamic check column name for gender (jenis_kelamin vs jk)
$chk_jk_col = mysqli_query($conn, "SHOW COLUMNS FROM siswa LIKE 'jenis_kelamin'");
$jk_col_name = ($chk_jk_col && mysqli_num_rows($chk_jk_col) > 0) ? 's.jenis_kelamin' : 's.jk';

// 2. Jumlah siswa per kelas & Total
$q_siswa_total = mysqli_query($conn, "SELECT COUNT(*) as total FROM siswa");
$total_siswa_all = ($q_siswa_total && $r = mysqli_fetch_assoc($q_siswa_total)) ? (int)$r['total'] : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error_message = "Username dan Password tidak boleh kosong!";
    } else {
        $sql = "SELECT id, nama_lengkap, username, password, role FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['remember_me'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header('Location: ' . BASE_URL . 'index.php');
                exit();
            } else {
                $error_message = "Username atau Password yang Anda masukkan salah.";
            }
        } else {
            $sql_siswa = "SELECT id, nisn, nama_siswa FROM siswa WHERE nisn = ?";
            $stmt_s = mysqli_prepare($conn, $sql_siswa);
            mysqli_stmt_bind_param($stmt_s, "s", $username);
            mysqli_stmt_execute($stmt_s);
            $res_s = mysqli_stmt_get_result($stmt_s);

            if ($siswa = mysqli_fetch_assoc($res_s)) {
                if (!empty($siswa['nisn']) && $password === $siswa['nisn']) {
                    session_regenerate_id(true);
                    $_SESSION['remember_me'] = true;
                    $_SESSION['user_id'] = $siswa['id'];
                    $_SESSION['nama_lengkap'] = $siswa['nama_siswa'];
                    $_SESSION['username'] = $siswa['nisn'];
                    $_SESSION['role'] = 'siswa';
                    header('Location: ' . BASE_URL . 'siswa/index.php');
                    exit();
                } else {
                    $error_message = "Username atau Password yang Anda masukkan salah.";
                }
            } else {
                $error_message = "Username atau Password yang Anda masukkan salah.";
            }
            mysqli_stmt_close($stmt_s);
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAKRA - Central Academic Knowledge & Record Application</title>
    <?php if ($favicon): ?>
    <link rel="icon" type="image/png" href="<?= $favicon ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0b1329;
        }
        .bg-mesh-cakra {
            background-color: #0b1329;
            background-image:
                radial-gradient(at 0% 0%, rgba(14, 116, 144, 0.35) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(88, 28, 135, 0.35) 0px, transparent 50%),
                radial-gradient(at 50% 100%, rgba(11, 19, 41, 0.95) 0px, transparent 50%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        @keyframes pulseGlow {
            0%, 100% { opacity: 0.5; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }
        .animate-pulse-glow { animation: pulseGlow 5s ease-in-out infinite; }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-6px); }
            40%, 80% { transform: translateX(6px); }
        }
        .animate-shake { animation: shake 0.4s ease-in-out; }
    </style>
</head>
<body class="bg-mesh-cakra min-h-screen flex items-center justify-center p-3 sm:p-6 lg:p-8 relative overflow-x-hidden selection:bg-cyan-500 selection:text-white">

    <!-- Ambient Background Lighting -->
    <div class="fixed top-1/4 -left-32 w-96 h-96 bg-cyan-500/15 rounded-full blur-[120px] pointer-events-none animate-pulse-glow"></div>
    <div class="fixed bottom-1/4 -right-32 w-96 h-96 bg-purple-600/15 rounded-full blur-[120px] pointer-events-none animate-pulse-glow" style="animation-delay: 2.5s;"></div>

    <div class="w-full max-w-[1240px] min-h-[680px] bg-slate-900/80 rounded-[32px] sm:rounded-[40px] border border-slate-800/80 shadow-2xl shadow-black/60 overflow-hidden grid grid-cols-1 lg:grid-cols-12 relative z-10 backdrop-blur-md">

        <!-- 1. LOGIN FORM CONTAINER (Appears FIRST on mobile order-1, SECOND on desktop lg:order-2) -->
        <div class="order-1 lg:order-2 lg:col-span-5 p-6 sm:p-10 lg:p-12 flex flex-col justify-center glass-card relative border-b lg:border-b-0 lg:border-l border-slate-200/80">
            <div class="w-full max-w-sm mx-auto">

                <div class="mb-8 text-left">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-50 border border-cyan-100 text-cyan-700 font-bold text-[10px] uppercase tracking-wider mb-3">
                        <span class="w-1.5 h-1.5 rounded-full bg-cyan-600"></span>
                        <span>Portal Otentikasi CAKRA</span>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Login Akun</h2>
                    <p class="text-slate-500 text-xs font-medium mt-1">Masukkan kredensial akun Anda untuk melanjutkan.</p>
                </div>

                <?php if ($error_message): ?>
                <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-start gap-3 animate-shake shadow-sm">
                    <i class="fa fa-circle-exclamation text-rose-500 text-base shrink-0 mt-0.5"></i>
                    <div><?= htmlspecialchars($error_message) ?></div>
                </div>
                <?php endif; ?>

                <form action="" method="POST" class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">
                            Username / NIP / NISN
                        </label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-cyan-600 transition-colors">
                                <i class="fa fa-user text-sm"></i>
                            </div>
                            <input type="text" name="username" required autocomplete="username"
                                class="w-full pl-11 pr-4 py-3.5 rounded-2xl bg-slate-50 border border-slate-200 focus:border-cyan-500 focus:bg-white focus:ring-4 focus:ring-cyan-500/10 outline-none transition-all font-bold text-slate-800 text-sm placeholder:text-slate-400 placeholder:font-normal"
                                placeholder="NIP / NISN / Username">
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-bold text-slate-700">
                                Password
                            </label>
                        </div>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-cyan-600 transition-colors">
                                <i class="fa fa-lock text-sm"></i>
                            </div>
                            <input type="password" name="password" id="passwordInput" required autocomplete="current-password"
                                class="w-full pl-11 pr-11 py-3.5 rounded-2xl bg-slate-50 border border-slate-200 focus:border-cyan-500 focus:bg-white focus:ring-4 focus:ring-cyan-500/10 outline-none transition-all font-bold text-slate-800 text-sm placeholder:text-slate-400 placeholder:font-normal"
                                placeholder="••••••••••••">
                            <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-cyan-600 transition-colors">
                                <i class="fa fa-eye text-sm" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs pt-1">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" name="remember_me" class="w-4 h-4 rounded text-cyan-600 focus:ring-cyan-500 border-slate-300">
                            <span class="font-bold text-slate-600">Ingat Saya</span>
                        </label>
                        <a href="#" onclick="alert('Silakan hubungi Administrator Sekolah untuk meriset password Anda.'); return false;" class="font-bold text-cyan-600 hover:text-cyan-800 transition-colors">
                            Lupa Password?
                        </a>
                    </div>

                    <div class="pt-2">
                        <button type="submit"
                            class="w-full py-4 rounded-2xl bg-gradient-to-r from-cyan-600 via-teal-600 to-indigo-600 hover:from-cyan-500 hover:to-indigo-500 text-white font-bold text-sm shadow-xl shadow-cyan-600/25 hover:shadow-cyan-600/40 transition-all transform active:scale-[0.99] flex items-center justify-center gap-2">
                            <span>MASUK KE SISTEM</span>
                            <i class="fa fa-arrow-right text-xs"></i>
                        </button>
                    </div>
                </form>

                <div class="mt-8 pt-6 border-t border-slate-200/80 text-center">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Akses Pintas Cepat</p>
                    <div class="flex items-center justify-center gap-3">
                        <a href="absen.php" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 font-bold text-xs transition-all border border-slate-200 flex items-center gap-1.5">
                            <i class="fa fa-qrcode text-cyan-600"></i> Absen GPS / QR
                        </a>
                    </div>
                </div>

            </div>
        </div>

        <!-- 2. MINIMALIST PORTAL WEBSITE OVERVIEW (Appears SECOND on mobile order-2, FIRST on desktop lg:order-1) -->
        <div class="order-2 lg:order-1 lg:col-span-7 bg-slate-900/90 p-6 sm:p-10 lg:p-12 flex flex-col justify-between relative overflow-y-auto max-h-[850px] custom-scrollbar">
            <!-- Background Soft Accent -->
            <div class="absolute top-0 right-0 w-80 h-80 bg-cyan-500/5 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 space-y-8">
                <!-- Portal Website Brand Header -->
                <div class="flex items-center justify-between border-b border-slate-800 pb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-cyan-500 to-teal-400 p-0.5 shadow-md shadow-cyan-500/20 shrink-0">
                            <div class="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center">
                                <?php if ($favicon): ?>
                                    <img src="<?= $favicon ?>" class="w-6 h-6 object-contain">
                                <?php else: ?>
                                    <i class="fa fa-shield-halved text-cyan-400 text-lg"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xl font-black tracking-tight text-white">CAKRA</span>
                                <span class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-widest bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">System</span>
                            </div>
                            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider"><?= htmlspecialchars($app_name) ?></p>
                        </div>
                    </div>

                    <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-800/80 border border-slate-700/80 text-cyan-300 text-xs font-semibold">
                        <i class="fa fa-circle text-[7px] text-cyan-400 animate-ping"></i>
                        <span><?= date('d M Y') ?></span>
                    </div>
                </div>

                <!-- Section: Penjelasan CAKRA -->
                <div class="space-y-3">
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-snug">
                        Portal Digital Akademik & <br>
                        <span class="bg-gradient-to-r from-cyan-400 via-teal-300 to-indigo-400 bg-clip-text text-transparent">Presensi Terpadu Sekolah</span>
                    </h1>
                    <p class="text-slate-300 text-xs sm:text-sm font-normal leading-relaxed">
                        <strong>CAKRA</strong> (Central Academic Knowledge & Record Application) menghadirkan efisiensi pengelolaan jurnal pembelajaran, presensi siswa berbasis lokasi GPS & Barcode QR, rekapitulasi nilai, serta kearsipan akademik secara real-time dan terintegrasi.
                    </p>
                </div>

                <!-- Minimal Live Stat Pills -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="p-3.5 rounded-2xl bg-slate-800/60 border border-slate-800 flex items-center justify-between">
                        <div>
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-0.5">Jurnal Terisi Hari Ini</span>
                            <span class="text-2xl font-black text-white"><?= number_format($jurnal_today_count) ?> <span class="text-xs font-normal text-slate-400">Sesi</span></span>
                        </div>
                        <i class="fa fa-book-open text-cyan-400 text-xl opacity-80"></i>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-800/60 border border-slate-800 flex items-center justify-between">
                        <div>
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-0.5">Total Siswa Terdaftar</span>
                            <span class="text-2xl font-black text-white"><?= number_format($total_siswa_all) ?> <span class="text-xs font-normal text-slate-400">Siswa</span></span>
                        </div>
                        <i class="fa fa-users text-teal-400 text-xl opacity-80"></i>
                    </div>
                </div>

                <!-- Section: Kelebihan CAKRA -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-widest text-cyan-400 flex items-center gap-1.5">
                        <i class="fa fa-circle-check"></i> Kelebihan Utama CAKRA
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="p-3.5 rounded-2xl bg-slate-800/40 border border-slate-800 flex items-start gap-3">
                            <div class="w-8 h-8 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center shrink-0 mt-0.5">
                                <i class="fa fa-bolt text-xs"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-200">Real-Time Synchronization</h4>
                                <p class="text-[10px] text-slate-400 leading-normal mt-0.5">Penilaian & jurnal otomatis tersimpan dan siap dipantau langsung.</p>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-slate-800/40 border border-slate-800 flex items-start gap-3">
                            <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center shrink-0 mt-0.5">
                                <i class="fa fa-location-dot text-xs"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-200">Presisi Geofencing GPS</h4>
                                <p class="text-[10px] text-slate-400 leading-normal mt-0.5">Akurasi lokasi presensi siswa & guru di area geofence sekolah.</p>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-slate-800/40 border border-slate-800 flex items-start gap-3">
                            <div class="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center shrink-0 mt-0.5">
                                <i class="fa fa-file-excel text-xs"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-200">Rekapitulasi Otomatis</h4>
                                <p class="text-[10px] text-slate-400 leading-normal mt-0.5">Laporan rekap kehadiran & agenda kelas ekspor Excel instan.</p>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-slate-800/40 border border-slate-800 flex items-start gap-3">
                            <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center shrink-0 mt-0.5">
                                <i class="fa fa-users-gear text-xs"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-200">Akses Multi-Portal</h4>
                                <p class="text-[10px] text-slate-400 leading-normal mt-0.5">Portal khusus untuk Guru, Siswa, Waka, Admin, dan Mitra PKL.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Fasilitas di CAKRA -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-widest text-teal-400 flex items-center gap-1.5">
                        <i class="fa fa-layer-group"></i> Fasilitas & Modul Layanan
                    </h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        <div class="p-3 rounded-2xl bg-slate-800/50 border border-slate-800 text-slate-200 font-bold space-y-1">
                            <i class="fa fa-book-open text-cyan-400 text-base"></i>
                            <div class="text-[11px]">Jurnal Class</div>
                        </div>

                        <div class="p-3 rounded-2xl bg-slate-800/50 border border-slate-800 text-slate-200 font-bold space-y-1">
                            <i class="fa fa-qrcode text-emerald-400 text-base"></i>
                            <div class="text-[11px]">Presensi QR/GPS</div>
                        </div>

                        <div class="p-3 rounded-2xl bg-slate-800/50 border border-slate-800 text-slate-200 font-bold space-y-1">
                            <i class="fa fa-comments text-indigo-400 text-base"></i>
                            <div class="text-[11px]">Konsultasi BK</div>
                        </div>

                        <div class="p-3 rounded-2xl bg-slate-800/50 border border-slate-800 text-slate-200 font-bold space-y-1">
                            <i class="fa fa-briefcase text-amber-400 text-base"></i>
                            <div class="text-[11px]">Portal PKL</div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Bottom User Role Indicator -->
            <div class="relative z-10 pt-6 mt-6 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400 font-medium">
                <span>Portal Resmi CAKRA Sekolah</span>
                <div class="flex items-center gap-1.5 font-bold text-[10px]">
                    <span class="px-2 py-0.5 rounded bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">Guru</span>
                    <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Siswa</span>
                    <span class="px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">Waka</span>
                    <span class="px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20">Admin</span>
                </div>
            </div>
        </div>

    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('passwordInput');
            const icon = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
