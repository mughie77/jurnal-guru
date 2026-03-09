<?php
require_once 'config/database.php';

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
            $error_message = "Username atau Password salah.";
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
    <title>Login - JurnalApp</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .bg-pattern {
            background-color: #4f46e5;
            background-image: radial-gradient(#ffffff 1px, transparent 1px);
            background-size: 40px 40px;
            background-opacity: 0.1;
        }
    </style>
</head>
<body class="bg-slate-900 flex items-center justify-center min-h-screen px-4">
    <div class="max-w-md w-full">
        <div class="text-center mb-10">
            <h1 class="text-4xl font-extrabold text-white tracking-tight mb-2">
                Jurnal<span class="text-indigo-500">App</span>
            </h1>
            <p class="text-slate-400">Sistem Informasi Jurnal Mengajar Sekolah</p>
        </div>

        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden border border-white/10">
            <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 px-8 py-6">
                <h2 class="text-2xl font-bold text-white">Selamat Datang</h2>
                <p class="text-indigo-100/70 text-sm">Silakan login untuk mengakses dashboard</p>
            </div>

            <div class="p-8">
                <?php if (!empty($error_message)): ?>
                    <div class="bg-rose-50 text-rose-600 px-4 py-3 rounded-xl text-sm font-medium mb-6 flex items-center border border-rose-100">
                        <i class="fa fa-exclamation-circle mr-3"></i>
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Username</label>
                        <div class="relative">
                            <i class="fa fa-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" name="username" required
                                class="w-full pl-12 pr-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 outline-none transition-all"
                                placeholder="Masukkan username">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Password</label>
                        <div class="relative">
                            <i class="fa fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="password" name="password" required
                                class="w-full pl-12 pr-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 outline-none transition-all"
                                placeholder="••••••••">
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-indigo-200 transition-all transform hover:-translate-y-1 active:scale-95">
                        Masuk ke Akun
                    </button>
                </form>
            </div>
        </div>

        <p class="text-center text-slate-500 text-sm mt-8">
            &copy; <?= date('Y') ?> JurnalApp Team. All rights reserved.
        </p>
    </div>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>
