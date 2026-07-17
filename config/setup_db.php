<?php
// Prevent Setup Hijacking: Block setup if already installed (install.lock exists)
if (file_exists(__DIR__ . '/install.lock')) {
    // Database is already installed/configured but offline! Output a beautiful Offline error page instead of setup wizard
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Offline - CAKRA</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-[32px] shadow-2xl p-8 text-center border border-slate-100">
            <div class="w-20 h-20 bg-amber-50 text-amber-500 rounded-full flex items-center justify-center mx-auto text-4xl mb-6 shadow-inner animate-pulse">
                <i class="fa fa-exclamation-triangle"></i>
            </div>
            <h1 class="text-2xl font-black text-slate-800 italic">Koneksi Database Offline</h1>
            <p class="text-slate-500 text-sm mt-3 leading-relaxed">
                Sistem CAKRA tidak dapat terhubung ke database. Server database mungkin sedang mengalami pemeliharaan rutin atau offline sementara. Silakan hubungi Administrator Anda atau coba muat ulang halaman.
            </p>
            <button onclick="window.location.reload()" class="w-full py-4 mt-8 bg-slate-800 hover:bg-slate-900 text-white font-black rounded-2xl shadow-xl transition-all">
                Coba Hubungkan Kembali
            </button>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Secure Database Setup Bootstrap Installer
$install_error = '';
$install_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_database'])) {
    $host = trim($_POST['db_host']);
    $user = trim($_POST['db_user']);
    $pass = trim($_POST['db_pass']);
    $name = trim($_POST['db_name']);
    $base = trim($_POST['base_url'] ?? '');

    // 1. Attempt connection
    $test_conn = @mysqli_connect($host, $user, $pass);
    if (!$test_conn) {
        $install_error = "Koneksi Gagal: " . mysqli_connect_error();
    } else {
        // Attempt to create database if it doesn't exist
        $db_escaped = mysqli_real_escape_string($test_conn, $name);
        mysqli_query($test_conn, "CREATE DATABASE IF NOT EXISTS `$db_escaped` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

        // Re-connect to database
        if (!@mysqli_select_db($test_conn, $name)) {
            $install_error = "Gagal mengakses database \"$name\". Pastikan nama database benar.";
        } else {
            // Escape inputs strictly to prevent PHP Code Injection/RCE (CWE-94 / CWE-74)
            $host_esc = addslashes($host);
            $user_esc = addslashes($user);
            $pass_esc = addslashes($pass);
            $name_esc = addslashes($name);
            $base_esc = addslashes($base);

            // Connection is successful! Overwrite config/database.php securely
            $config_content = "<?php
// Set Timezone to UTC+7 (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

// --- Security Headers to Prevent Deface, Clickjacking & XSS ---
header(\"X-Frame-Options: SAMEORIGIN\");
header(\"X-Content-Type-Options: nosniff\");
header(\"X-XSS-Protection: 1; mode=block\");

// --- Mulai Session dengan Pengaturan Aman ---
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
    session_start();
}

// --- Anti DDOS / Rate Limiting (Maksimal 150 request per menit per session) ---
if (isset(\$_SESSION)) {
    if (!isset(\$_SESSION['req_count'])) {
        \$_SESSION['req_count'] = 0;
        \$_SESSION['req_start_time'] = time();
    }
    \$_SESSION['req_count']++;
    if (time() - \$_SESSION['req_start_time'] > 60) {
        \$_SESSION['req_count'] = 1;
        \$_SESSION['req_start_time'] = time();
    }
    if (\$_SESSION['req_count'] > 150) {
        http_response_code(429);
        die(\"<h1>429 Too Many Requests</h1><p>Terlalu banyak permintaan (Spam/DDOS Terdeteksi). Mohon tunggu beberapa saat sebelum mencoba kembali.</p>\");
    }
}

// --- Anti Session Hijacking (Kunci Session ke User Agent) ---
if (isset(\$_SESSION['user_id'])) {
    if (!isset(\$_SESSION['user_agent'])) {
        \$_SESSION['user_agent'] = \$_SERVER['HTTP_USER_AGENT'] ?? '';
    } elseif (\$_SESSION['user_agent'] !== (\$_SERVER['HTTP_USER_AGENT'] ?? '')) {
        session_unset();
        session_destroy();
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/login.php') . '?error=session_hijacked');
        exit();
    }
}

// --- Koneksi Database ---
\$db_host = '$host_esc';
\$db_user = '$user_esc';
\$db_pass = '$pass_esc';
\$db_name = '$name_esc';

\$conn = @mysqli_connect(\$db_host, \$db_user, \$db_pass, \$db_name);

if (!\$conn) {
    include __DIR__ . '/setup_db.php';
    exit();
}

// --- URL Konfigurasi ---
\$base_url_config = '$base_esc';

if (!empty(\$base_url_config)) {
    define('BASE_URL', \$base_url_config);
} else {
    \$protocol = (isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off' || \$_SERVER['SERVER_PORT'] == 443 || (isset(\$_SERVER['HTTP_X_FORWARDED_PROTO']) && \$_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? \"https://\" : \"http://\";
    \$domainName = \$_SERVER['HTTP_HOST'] ?? 'localhost';
    \$scriptName = \$_SERVER['SCRIPT_NAME'];

    \$app_root_path = str_replace(['/admin', '/guru', '/waka', '/api', '/siswa', '/error'], '/', dirname(\$scriptName));

    \$app_root_path = str_replace('\\\\', '/', \$app_root_path);
    \$app_root_path = rtrim(\$app_root_path, '/') . '/';

    define('BASE_URL', \$protocol . \$domainName . \$app_root_path);
}

function get_active_tahun_pelajaran_id(\$conn) {
    \$query = \"SELECT id FROM tahun_pelajaran WHERE status = 'aktif' LIMIT 1\";
    \$result = mysqli_query(\$conn, \$query);
    if (\$row = mysqli_fetch_assoc(\$result)) {
        return (int)\$row['id'];
    }
    return null;
}

\$active_tahun_id = get_active_tahun_pelajaran_id(\$conn);
?>";

            if (file_put_contents(__DIR__ . '/database.php', $config_content) !== FALSE) {
                // Connection successfully saved! Now, check if database tables exist
                $tables_res = mysqli_query($test_conn, "SHOW TABLES");
                if (mysqli_num_rows($tables_res) === 0) {
                    // Automatically bootstrap using jurnal_mengajar.sql if found
                    $sql_file = __DIR__ . '/../jurnal_mengajar.sql';
                    if (file_exists($sql_file)) {
                        $sql_content = file_get_contents($sql_file);
                        // Clean multiline comments
                        $sql_clean = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
                        $lines = explode("\n", $sql_clean);
                        $processed_lines = [];
                        foreach ($lines as $line) {
                            $trimmed = trim($line);
                            if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) {
                                continue;
                            }
                            $processed_lines[] = $line;
                        }
                        $sql_executable = implode("\n", $processed_lines);
                        $queries = preg_split('/;[ \t\r]*\n/', $sql_executable);

                        foreach ($queries as $query) {
                            $query = trim($query);
                            if ($query !== '') {
                                @mysqli_query($test_conn, $query);
                            }
                        }
                    }
                }

                // Write install.lock to prevent further setup hijacking permanently
                @file_put_contents(__DIR__ . '/install.lock', date('Y-m-d H:i:s'));
                $install_success = true;
            } else {
                $install_error = "Gagal menulis berkas konfigurasi. Harap periksa izin tulis folder config/.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - CAKRA Installer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-white rounded-[32px] shadow-2xl border border-slate-100 overflow-hidden relative">
        <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 p-8 text-white relative">
            <h1 class="text-3xl font-black italic tracking-tight">CAKRA Installer</h1>
            <p class="text-indigo-100 text-xs font-bold uppercase tracking-widest mt-1">Konfigurasi & Inisialisasi Database</p>
            <i class="fa fa-database absolute bottom-4 right-8 text-7xl opacity-10"></i>
        </div>

        <div class="p-8">
            <?php if ($install_success): ?>
                <div class="text-center py-6 space-y-6">
                    <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto text-4xl shadow-inner animate-bounce">
                        <i class="fa fa-check-circle"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-slate-800 italic">Koneksi Berhasil Disimpan!</h2>
                        <p class="text-slate-500 text-sm mt-2">Database Anda telah terhubung dan berkas konfigurasi berhasil diperbarui.</p>
                    </div>
                    <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-100">
                        <p class="text-xs text-emerald-800 font-bold leading-relaxed italic">
                            Harap jalankan kembali migrasi skema setelah ini jika Anda menggunakan fitur database lanjutan.
                        </p>
                    </div>
                    <button onclick="window.location.reload()" class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-2xl shadow-xl shadow-indigo-100 transition-all flex items-center justify-center gap-2">
                        MULAI APLIKASI CAKRA <i class="fa fa-arrow-right"></i>
                    </button>
                </div>
            <?php else: ?>
                <p class="text-sm text-slate-500 leading-relaxed mb-6">
                    Selamat datang di CAKRA! Sistem mendeteksi bahwa aplikasi Anda belum terhubung ke database aktif. Isi kredensial di bawah ini untuk menghubungkan database Anda secara otomatis.
                </p>

                <?php if ($install_error): ?>
                    <div class="p-4 bg-rose-50 border border-rose-100 text-rose-600 text-xs font-bold rounded-2xl mb-6 flex items-start gap-3">
                        <i class="fa fa-exclamation-circle text-base mt-0.5 shrink-0"></i>
                        <span><?= htmlspecialchars($install_error) ?></span>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="space-y-4">
                    <input type="hidden" name="setup_database" value="1">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Database Host</label>
                            <input type="text" name="db_host" required value="localhost" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-sm">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Database User</label>
                            <input type="text" name="db_user" required value="root" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Database Password</label>
                            <input type="password" name="db_pass" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-sm" placeholder="Kosongkan jika tidak ada">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Nama Database</label>
                            <input type="text" name="db_name" required value="jurnal_mengajar" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-sm">
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Override Base URL (Opsional)</label>
                        <input type="text" name="base_url" placeholder="Contoh: http://localhost/jurnal/" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-sm">
                        <p class="text-[9px] text-slate-400 font-bold italic">Biarkan kosong untuk auto-deteksi otomatis URL server.</p>
                    </div>

                    <button type="submit" class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-2xl shadow-xl shadow-indigo-100 transition-all flex items-center justify-center gap-2 text-base mt-6">
                        <i class="fa fa-plug"></i> VERIFIKASI & INSTALASI
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
