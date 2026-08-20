<?php
require_once 'config/database.php';

// Get Settings
$res_set = mysqli_query($conn, "SELECT * FROM pengaturan");
$app_sets = [];
while ($r = mysqli_fetch_assoc($res_set)) {
    $app_sets[$r['nama_setting']] = $r['nilai_setting'];
}
$app_name = $app_sets['nama_sekolah'] ?? 'Aplikasi Jurnal Mengajar';
$favicon = !empty($app_sets['favicon']) ? BASE_URL . 'uploads/' . $app_sets['favicon'] : null;

$error_message = '';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL);
    exit();
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

        $lifetime = 30 * 24 * 60 * 60; // 30 hari persistent login
        if ($user = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $user['password'])) {
                session_set_cookie_params([
                    'lifetime' => $lifetime,
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                session_regenerate_id(true);
                $_SESSION['remember_me'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header('Location: ' . BASE_URL . 'index.php');
                exit();
            } else {
                $error_message = "Username atau Password salah.";
            }
        } else {
            $sql_siswa = "SELECT id, nisn, nama_siswa FROM siswa WHERE nisn = ?";
            $stmt_s = mysqli_prepare($conn, $sql_siswa);
            mysqli_stmt_bind_param($stmt_s, "s", $username);
            mysqli_stmt_execute($stmt_s);
            $res_s = mysqli_stmt_get_result($stmt_s);

            if ($siswa = mysqli_fetch_assoc($res_s)) {
                if (!empty($siswa['nisn']) && $password === $siswa['nisn']) {
                    session_set_cookie_params([
                        'lifetime' => $lifetime,
                        'path' => '/',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                    session_regenerate_id(true);
                    $_SESSION['remember_me'] = true;
                    $_SESSION['user_id'] = $siswa['id'];
                    $_SESSION['nama_lengkap'] = $siswa['nama_siswa'];
                    $_SESSION['username'] = $siswa['nisn'];
                    $_SESSION['role'] = 'siswa';
                    header('Location: ' . BASE_URL . 'siswa/index.php');
                    exit();
                } else {
                    $error_message = "Username atau Password salah.";
                }
            } else {
                $error_message = "Username atau Password salah.";
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
        .glass-panel {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        .animate-float { animation: float 6s ease-in-out infinite; }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }
        .animate-shake { animation: shake 0.4s ease-in-out 0s 2; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center lg:p-6">
    <div class="flex flex-col lg:flex-row w-full max-w-[1100px] min-h-[600px] lg:min-h-[700px] bg-white lg:rounded-[40px] shadow-2xl overflow-hidden relative">

        <!-- Left Side: Visual/Branding (Hidden on small screens) -->
        <div class="hidden lg:flex lg:w-1/2 bg-mesh p-16 flex-col justify-between relative overflow-hidden">
            <!-- Decorative Abstract -->
            <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -mr-32 -mt-32 blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-64 h-64 bg-indigo-500/20 rounded-full -ml-32 -mb-32 blur-3xl"></div>

            <div class="relative z-10">
                <div class="flex items-center gap-4 mb-12">
                    <div class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center border border-white/20 shadow-xl">
                        <?php if ($favicon): ?>
                            <img src="<?= $favicon ?>" class="w-7 h-7 object-contain">
                        <?php else: ?>
                            <i class="fa fa-book-open text-white text-xl"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2 class="text-white font-black text-2xl tracking-tighter">CAKRA</h2>
                        <p class="text-white/50 text-[10px] font-bold uppercase tracking-[0.1em]">Central Academic Knowledge & Record Application</p>
                    </div>
                </div>

                <div class="animate-float">
                    <h1 class="text-5xl font-black text-white leading-[1.1] tracking-tight mb-6 italic">
                        Transformasi <br>
                        Digital <br>
                        <span class="text-indigo-400">Pendidikan.</span>
                    </h1>
                    <p class="text-indigo-100/70 font-medium leading-relaxed max-w-sm">
                        Kelola jurnal mengajar, absensi siswa, dan administrasi sekolah dalam satu platform terintegrasi yang modern dan responsif.
                    </p>
                </div>
            </div>

            <div class="relative z-10 flex items-center gap-4">
                <div class="flex -space-x-3">
                    <div class="w-10 h-10 rounded-full border-2 border-slate-900 bg-indigo-500 flex items-center justify-center text-[10px] font-bold text-white">G</div>
                    <div class="w-10 h-10 rounded-full border-2 border-slate-900 bg-emerald-500 flex items-center justify-center text-[10px] font-bold text-white">S</div>
                    <div class="w-10 h-10 rounded-full border-2 border-slate-900 bg-rose-500 flex items-center justify-center text-[10px] font-bold text-white">A</div>
                </div>
                <p class="text-indigo-200/50 text-xs font-bold uppercase tracking-widest italic">Terpercaya untuk Semua Peran</p>
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="w-full lg:w-1/2 p-8 sm:p-12 lg:p-20 flex flex-col justify-center bg-white relative">
            <!-- Mobile Header (Visible only on small screens) -->
            <div class="lg:hidden flex flex-col items-center mb-10 text-center">
                <div class="w-16 h-16 rounded-2xl bg-indigo-600 flex items-center justify-center shadow-2xl shadow-indigo-200 mb-6">
                    <?php if ($favicon): ?>
                        <img src="<?= $favicon ?>" class="w-9 h-9 object-contain">
                    <?php else: ?>
                        <i class="fa fa-book-open text-white text-3xl"></i>
                    <?php endif; ?>
                </div>
                <h2 class="text-3xl font-black text-slate-900 tracking-tighter italic">CAKRA</h2>
                <p class="text-slate-400 text-[8px] font-bold uppercase tracking-[0.1em] mb-1">Central Academic Knowledge & Record Application</p>
                <p class="text-indigo-600 text-[9px] font-black uppercase tracking-[0.3em]"><?= htmlspecialchars($app_name) ?></p>
            </div>

            <div class="max-w-md mx-auto w-full">
                <div class="mb-10 lg:block hidden">
                    <h3 class="text-4xl font-black text-slate-800 tracking-tighter mb-2 italic">Selamat Datang.</h3>
                    <p class="text-slate-500 font-medium italic">Masukkan kredensial Anda untuk melanjutkan.</p>
                </div>

                <?php if ($error_message): ?>
                <div class="mb-8 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-rose-600 text-sm font-bold flex items-center animate-shake">
                    <i class="fa fa-circle-exclamation mr-3 text-lg"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
                <?php endif; ?>

                <form action="" method="POST" class="space-y-6">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2 ml-1 italic">Username / NIP / NISN</label>
                        <div class="group relative">
                            <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none transition-colors group-focus-within:text-indigo-600 text-slate-300">
                                <i class="fa fa-user-circle"></i>
                            </div>
                            <input type="text" name="username" required autocomplete="username"
                                class="w-full pl-12 pr-5 py-4 rounded-2xl bg-slate-50 border-2 border-transparent focus:border-indigo-100 focus:bg-white focus:ring-4 focus:ring-indigo-50/50 outline-none transition-all font-bold text-slate-700 placeholder:text-slate-300 placeholder:italic"
                                placeholder="Masukkan username anda...">
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-2 ml-1">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] italic">Password</label>
                        </div>
                        <div class="group relative">
                            <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none transition-colors group-focus-within:text-indigo-600 text-slate-300">
                                <i class="fa fa-fingerprint"></i>
                            </div>
                            <input type="password" name="password" id="passwordInput" required autocomplete="current-password"
                                class="w-full pl-12 pr-12 py-4 rounded-2xl bg-slate-50 border-2 border-transparent focus:border-indigo-100 focus:bg-white focus:ring-4 focus:ring-indigo-50/50 outline-none transition-all font-bold text-slate-700 placeholder:text-slate-300 placeholder:italic"
                                placeholder="••••••••••••">
                            <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-5 flex items-center text-slate-300 hover:text-indigo-600 transition-colors">
                                <i class="fa fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-1">
                        <label class="flex items-center cursor-pointer group">
                            <div class="relative">
                                <input type="checkbox" name="remember_me" class="sr-only">
                                <div class="w-10 h-6 bg-slate-200 rounded-full transition-colors group-hover:bg-slate-300 peer-checked:bg-indigo-600"></div>
                                <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform"></div>
                            </div>
                            <span class="ml-3 text-xs font-bold text-slate-500 italic">Ingat Saya</span>
                        </label>
                        <a href="#" class="text-xs font-bold text-indigo-500 hover:text-indigo-700 italic transition-colors">Lupa Password?</a>
                    </div>

                    <div class="pt-4">
                        <button type="submit"
                            class="w-full bg-slate-900 hover:bg-indigo-600 text-white font-black py-4 rounded-2xl shadow-xl shadow-slate-200 hover:shadow-indigo-200 transition-all active:scale-[0.98] flex items-center justify-center gap-3 group tracking-widest italic">
                            <span>MASUK KE SISTEM</span>
                            <i class="fa fa-bolt transition-transform group-hover:scale-125"></i>
                        </button>
                    </div>
                </form>

                <div class="mt-12 text-center">
                    <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-4">Butuh bantuan akses?</p>
                    <div class="flex justify-center gap-4">
                        <a href="absen.php" class="px-6 py-2 rounded-full border border-slate-100 text-[10px] font-black text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-100 transition-all italic uppercase">Halaman Absen</a>
                        <a href="index.php" class="px-6 py-2 rounded-full border border-slate-100 text-[10px] font-black text-slate-600 hover:bg-slate-900 hover:text-white hover:border-slate-900 transition-all italic uppercase">Beranda</a>
                    </div>
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

        // Custom Toggle Style logic
        const toggleInput = document.querySelector('input[name="remember_me"]');
        const toggleDot = document.querySelector('.dot');
        const toggleBg = document.querySelector('.w-10');

        toggleInput.addEventListener('change', function() {
            if (this.checked) {
                toggleDot.style.transform = 'translateX(16px)';
                toggleBg.classList.replace('bg-slate-200', 'bg-indigo-600');
            } else {
                toggleDot.style.transform = 'translateX(0px)';
                toggleBg.classList.replace('bg-indigo-600', 'bg-slate-200');
            }
        });
    </script>
</body>
</html>
