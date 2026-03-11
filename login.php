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
    $username = $_POST['username'];
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
                // If "Remember Me" is checked, set cookie for 30 days
                if (isset($_POST['remember_me'])) {
                    $lifetime = 30 * 24 * 60 * 60; // 30 days
                    ini_set('session.gc_maxlifetime', $lifetime);
                    session_set_cookie_params($lifetime, '/');
                    session_regenerate_id(true);
                    $_SESSION['remember_me'] = true;
                }

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
            // Check student table
            $sql_siswa = "SELECT id, nis, nama_siswa FROM siswa WHERE nis = ?";
            $stmt_s = mysqli_prepare($conn, $sql_siswa);
            mysqli_stmt_bind_param($stmt_s, "s", $username);
            mysqli_stmt_execute($stmt_s);
            $res_s = mysqli_stmt_get_result($stmt_s);

            if ($siswa = mysqli_fetch_assoc($res_s)) {
                // Student password is their NIS
                if ($password === $siswa['nis']) {
                    $_SESSION['user_id'] = $siswa['id'];
                    $_SESSION['nama_lengkap'] = $siswa['nama_siswa'];
                    $_SESSION['username'] = $siswa['nis'];
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
    <title>Login - <?= htmlspecialchars($app_name) ?></title>
    <?php if ($favicon): ?>
    <link rel="icon" type="image/png" href="<?= $favicon ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-bg {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
    </style>
</head>
<body class="bg-[#F8FAFC] min-h-screen flex items-center justify-center p-6 relative overflow-hidden">
    <!-- Decorative Elements -->
    <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-indigo-100/50 rounded-full blur-[120px]"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-emerald-100/50 rounded-full blur-[120px]"></div>

    <div class="max-w-[420px] w-full relative">
        <div class="text-center mb-10 animate-in fade-in slide-in-from-top-4 duration-700">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white shadow-xl shadow-indigo-100 mb-6 group transition-transform hover:scale-110 overflow-hidden">
                <?php if ($favicon): ?>
                    <img src="<?= $favicon ?>" class="max-w-full max-h-full object-contain p-2">
                <?php else: ?>
                    <i class="fa fa-book-open text-3xl text-indigo-600"></i>
                <?php endif; ?>
            </div>
            <h1 class="text-4xl font-black text-slate-900 tracking-tighter">CAKRA</h1>
            <p class="text-[8px] text-slate-400 font-bold uppercase tracking-[0.2em] mb-4">Central Academic Knowledge & Record Application</p>
            <p class="text-[10px] font-black text-indigo-600 uppercase tracking-[0.2em] mb-1"><?= htmlspecialchars($app_name) ?></p>
            <p class="text-slate-500 mt-2 font-medium">Selamat datang kembali, silakan login.</p>
        </div>

        <div class="bg-white rounded-[32px] shadow-[0_20px_50px_rgba(0,0,0,0.04)] border border-slate-100 p-10 animate-in fade-in zoom-in duration-500">
            <?php if ($error_message): ?>
                <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-rose-600 text-sm font-bold flex items-center animate-shake">
                    <i class="fa fa-circle-exclamation mr-3 text-lg"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-6">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2 ml-1">Username / NIP</label>
                    <div class="group relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-indigo-600 text-slate-400">
                            <i class="fa fa-user"></i>
                        </div>
                        <input type="text" name="username" required
                            class="w-full pl-11 pr-4 py-4 rounded-2xl bg-slate-50 border-2 border-transparent focus:border-indigo-100 focus:bg-white focus:ring-4 focus:ring-indigo-50 outline-none transition-all font-semibold text-slate-700 placeholder:text-slate-300"
                            placeholder="Username Anda">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2 ml-1">Password</label>
                    <div class="group relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-indigo-600 text-slate-400">
                            <i class="fa fa-lock"></i>
                        </div>
                        <input type="password" name="password" required
                            class="w-full pl-11 pr-4 py-4 rounded-2xl bg-slate-50 border-2 border-transparent focus:border-indigo-100 focus:bg-white focus:ring-4 focus:ring-indigo-50 outline-none transition-all font-semibold text-slate-700 placeholder:text-slate-300"
                            placeholder="••••••••">
                    </div>
                </div>

                <div class="flex items-center ml-1">
                    <input type="checkbox" name="remember_me" id="remember_me" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500 cursor-pointer">
                    <label for="remember_me" class="ml-2 text-xs font-bold text-slate-500 cursor-pointer hover:text-indigo-600 transition-colors">Ingat Saya (Terus Login)</label>
                </div>

                <div class="pt-2">
                    <button type="submit"
                        class="w-full bg-slate-900 hover:bg-indigo-600 text-white font-bold py-4 rounded-2xl shadow-xl shadow-slate-200 hover:shadow-indigo-100 transition-all active:scale-[0.98] flex items-center justify-center gap-3 group">
                        <span>Masuk Sekarang</span>
                        <i class="fa fa-arrow-right transition-transform group-hover:translate-x-1"></i>
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-8 text-center">
            <a href="index.php" class="text-slate-400 hover:text-indigo-600 font-bold text-sm transition-colors flex items-center justify-center gap-2 group">
                <i class="fa fa-arrow-left text-xs transition-transform group-hover:-translate-x-1"></i>
                Kembali ke Halaman Utama
            </a>
        </div>
    </div>

    <style>
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }
        .animate-shake { animation: shake 0.4s ease-in-out 0s 2; }
    </style>
</body>
</html>
