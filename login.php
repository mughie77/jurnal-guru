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

// 2. Jumlah siswa per kelas (Laki-Laki & Perempuan)
$q_siswa_kelas = mysqli_query($conn, "
    SELECT k.nama_kelas,
           SUM(CASE WHEN LOWER(s.jk) = 'l' THEN 1 ELSE 0 END) as total_l,
           SUM(CASE WHEN LOWER(s.jk) = 'p' THEN 1 ELSE 0 END) as total_p,
           COUNT(s.id) as total_siswa
    FROM kelas k
    LEFT JOIN siswa_kelas sk ON k.id = sk.kelas_id
    LEFT JOIN siswa s ON sk.siswa_id = s.id
    LEFT JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id AND tp.status = 'aktif'
    GROUP BY k.id, k.nama_kelas
    ORDER BY k.nama_kelas ASC
");
$rekap_siswa_kelas = [];
$total_l_all = 0;
$total_p_all = 0;
$total_siswa_all = 0;
if ($q_siswa_kelas) {
    while ($r = mysqli_fetch_assoc($q_siswa_kelas)) {
        $rekap_siswa_kelas[] = $r;
        $total_l_all += (int)$r['total_l'];
        $total_p_all += (int)$r['total_p'];
        $total_siswa_all += (int)$r['total_siswa'];
    }
}

// 3. Rekap kehadiran siswa per kelas (Grafik Kehadiran Bulan Ini)
$q_absen_chart = mysqli_query($conn, "
    SELECT
        SUM(CASE WHEN status IN ('Hadir', 'H') THEN 1 ELSE 0 END) as total_hadir,
        SUM(CASE WHEN status IN ('Sakit', 'S') THEN 1 ELSE 0 END) as total_sakit,
        SUM(CASE WHEN status IN ('Izin', 'I') THEN 1 ELSE 0 END) as total_izin,
        SUM(CASE WHEN status IN ('Alfa', 'A', 'Terlambat') THEN 1 ELSE 0 END) as total_alfa
    FROM absensi_harian
    WHERE MONTH(tanggal) = MONTH(CURRENT_DATE()) AND YEAR(tanggal) = YEAR(CURRENT_DATE())
");
$rekap_absen = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alfa' => 0];
if ($q_absen_chart && $r = mysqli_fetch_assoc($q_absen_chart)) {
    $rekap_absen['hadir'] = (int)($r['total_hadir'] ?? 0);
    $rekap_absen['sakit'] = (int)($r['total_sakit'] ?? 0);
    $rekap_absen['izin'] = (int)($r['total_izin'] ?? 0);
    $rekap_absen['alfa'] = (int)($r['total_alfa'] ?? 0);
}

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
    <title>Login - CAKRA Analytics Portal</title>
    <?php if ($favicon): ?>
    <link rel="icon" type="image/png" href="<?= $favicon ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0f172a;
        }
        .bg-mesh-cakra {
            background-color: #0f172a;
            background-image:
                radial-gradient(at 0% 0%, rgba(14, 116, 144, 0.4) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(76, 29, 149, 0.4) 0px, transparent 50%),
                radial-gradient(at 50% 100%, rgba(15, 23, 42, 0.9) 0px, transparent 50%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
        }
        @keyframes pulseGlow {
            0%, 100% { opacity: 0.6; transform: scale(1); }
            50% { opacity: 0.9; transform: scale(1.05); }
        }
        .animate-pulse-glow { animation: pulseGlow 4s ease-in-out infinite; }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-6px); }
            40%, 80% { transform: translateX(6px); }
        }
        .animate-shake { animation: shake 0.4s ease-in-out; }
    </style>
</head>
<body class="bg-mesh-cakra min-h-screen flex items-center justify-center p-3 sm:p-6 lg:p-8 relative overflow-x-hidden selection:bg-cyan-500 selection:text-white">

    <!-- Ambient Glowing Orbs -->
    <div class="fixed top-1/4 -left-32 w-96 h-96 bg-cyan-500/20 rounded-full blur-[120px] pointer-events-none animate-pulse-glow"></div>
    <div class="fixed bottom-1/4 -right-32 w-96 h-96 bg-purple-600/20 rounded-full blur-[120px] pointer-events-none animate-pulse-glow" style="animation-delay: 2s;"></div>

    <div class="w-full max-w-[1280px] min-h-[720px] bg-slate-900/80 rounded-[32px] sm:rounded-[40px] border border-slate-800/80 shadow-2xl shadow-cyan-950/40 overflow-hidden grid grid-cols-1 lg:grid-cols-12 relative z-10 backdrop-blur-md">

        <!-- 1. LOGIN FORM CONTAINER (ORDER-1 on Mobile / TOP, ORDER-2 on Desktop lg:col-span-5 / RIGHT) -->
        <div class="order-1 lg:order-2 lg:col-span-5 p-6 sm:p-10 lg:p-12 flex flex-col justify-center glass-card relative">
            <div class="w-full max-w-sm mx-auto">

                <div class="mb-8 text-left">
                    <div class="inline-block px-3 py-1 rounded-full bg-cyan-50 text-cyan-700 font-bold text-[10px] uppercase tracking-wider mb-2">
                        Portal Otentikasi CAKRA
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

        <!-- 2. CAKRA ANALYTICS SHOWCASE CONTAINER (ORDER-2 on Mobile / BOTTOM, ORDER-1 on Desktop lg:col-span-7 / LEFT) -->
        <div class="order-2 lg:order-1 lg:col-span-7 bg-gradient-to-br from-slate-900 via-slate-900/95 to-cyan-950/80 p-6 sm:p-8 lg:p-12 flex flex-col justify-between relative overflow-hidden border-t lg:border-t-0 lg:border-r border-slate-800/60">
            <!-- Background Accent Blurs -->
            <div class="absolute -top-24 -right-24 w-80 h-80 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 space-y-6">
                <!-- Top Brand Header -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-cyan-500 to-teal-400 p-0.5 shadow-lg shadow-cyan-500/30 shrink-0">
                            <div class="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center">
                                <?php if ($favicon): ?>
                                    <img src="<?= $favicon ?>" class="w-7 h-7 object-contain">
                                <?php else: ?>
                                    <i class="fa fa-shield-halved text-cyan-400 text-xl"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xl font-black tracking-tight text-white">CAKRA</span>
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">v2.5</span>
                            </div>
                            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider mt-0.5"><?= htmlspecialchars($app_name) ?></p>
                        </div>
                    </div>

                    <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-800/80 border border-slate-700/80 text-cyan-300 text-xs font-semibold">
                        <i class="fa fa-circle text-[8px] text-cyan-400 animate-ping"></i>
                        <span><?= date('d M Y') ?></span>
                    </div>
                </div>

                <!-- Highlight Stat Cards Row (Jurnal Hari Ini & Total Siswa) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="p-4 rounded-2xl bg-gradient-to-br from-indigo-900/40 to-slate-900/90 border border-indigo-500/20 backdrop-blur-md flex items-center justify-between shadow-lg">
                        <div>
                            <p class="text-indigo-200/80 text-[10px] font-bold uppercase tracking-widest mb-1">Jurnal Terisi Hari Ini</p>
                            <h3 class="text-3xl font-black text-white tracking-tight"><?= number_format($jurnal_today_count) ?> <span class="text-xs font-normal text-indigo-300">Sesi</span></h3>
                        </div>
                        <div class="w-11 h-11 rounded-xl bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 flex items-center justify-center text-lg shadow-inner">
                            <i class="fa fa-book-open"></i>
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-gradient-to-br from-teal-900/40 to-slate-900/90 border border-teal-500/20 backdrop-blur-md flex items-center justify-between shadow-lg">
                        <div>
                            <p class="text-teal-200/80 text-[10px] font-bold uppercase tracking-widest mb-1">Total Siswa Terdaftar</p>
                            <h3 class="text-3xl font-black text-white tracking-tight"><?= number_format($total_siswa_all) ?> <span class="text-xs font-normal text-teal-300">Siswa</span></h3>
                        </div>
                        <div class="w-11 h-11 rounded-xl bg-teal-500/20 text-teal-400 border border-teal-500/30 flex items-center justify-center text-lg shadow-inner">
                            <i class="fa fa-users"></i>
                        </div>
                    </div>
                </div>

                <!-- Desktop Visual Charts & Breakdown Showcase -->
                <div class="hidden lg:grid grid-cols-1 md:grid-cols-12 gap-4">
                    <!-- Attendance Chart Card (5 cols) -->
                    <div class="md:col-span-5 p-4 rounded-2xl bg-slate-800/50 border border-slate-700/60 backdrop-blur-md flex flex-col justify-between">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-200 flex items-center gap-1.5">
                                <i class="fa fa-chart-pie text-cyan-400"></i> Kehadiran Bulan Ini
                            </h4>
                        </div>
                        <div class="relative h-44 flex items-center justify-center">
                            <canvas id="chartKehadiran"></canvas>
                        </div>
                    </div>

                    <!-- Gender & Class Breakdown Table Card (7 cols) -->
                    <div class="md:col-span-7 p-4 rounded-2xl bg-slate-800/50 border border-slate-700/60 backdrop-blur-md flex flex-col justify-between">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-200 flex items-center gap-1.5">
                                <i class="fa fa-users-rectangle text-teal-400"></i> Rekap Siswa Per Kelas
                            </h4>
                            <span class="text-[10px] font-bold text-slate-400">L: <?= $total_l_all ?> | P: <?= $total_p_all ?></span>
                        </div>

                        <div class="max-h-44 overflow-y-auto pr-1 space-y-1.5 custom-scrollbar">
                            <?php if (empty($rekap_siswa_kelas)): ?>
                                <p class="text-[10px] text-slate-400 italic text-center py-4">Belum ada data kelas.</p>
                            <?php else: ?>
                                <?php foreach ($rekap_siswa_kelas as $rk): ?>
                                <div class="p-2 bg-slate-900/60 border border-slate-700/40 rounded-xl flex items-center justify-between text-xs">
                                    <span class="font-bold text-slate-200 truncate max-w-[110px]"><?= htmlspecialchars($rk['nama_kelas']) ?></span>
                                    <div class="flex items-center gap-2 font-mono text-[11px]">
                                        <span class="px-1.5 py-0.5 rounded bg-blue-500/10 text-blue-400 border border-blue-500/20 font-bold" title="Laki-Laki">L: <?= $rk['total_l'] ?></span>
                                        <span class="px-1.5 py-0.5 rounded bg-pink-500/10 text-pink-400 border border-pink-500/20 font-bold" title="Perempuan">P: <?= $rk['total_p'] ?></span>
                                        <span class="px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-400 font-black" title="Total Siswa"><?= $rk['total_siswa'] ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Bottom User Role Indicator -->
            <div class="relative z-10 pt-6 mt-6 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400 font-medium">
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

        // Initialize Attendance Doughnut Chart
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('chartKehadiran');
            if (ctx) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Hadir', 'Sakit', 'Izin', 'Alfa/Telat'],
                        datasets: [{
                            data: [
                                <?= $rekap_absen['hadir'] ?>,
                                <?= $rekap_absen['sakit'] ?>,
                                <?= $rekap_absen['izin'] ?>,
                                <?= $rekap_absen['alfa'] ?>
                            ],
                            backgroundColor: [
                                '#10b981', // Emerald Hadir
                                '#3b82f6', // Blue Sakit
                                '#8b5cf6', // Violet Izin
                                '#f43f5e'  // Rose Alfa
                            ],
                            borderWidth: 2,
                            borderColor: '#0f172a'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: '#94a3b8',
                                    font: { family: 'Plus Jakarta Sans', size: 10, weight: 'bold' },
                                    boxWidth: 10,
                                    padding: 8
                                }
                            }
                        },
                        cutout: '70%'
                    }
                });
            }
        });
    </script>
</body>
</html>
