<?php
require_once __DIR__ . '/../config/database.php';

// Get Settings
$res_set = mysqli_query($conn, "SELECT * FROM pengaturan");
$app_sets = [];
while ($r = mysqli_fetch_assoc($res_set)) {
    $app_sets[$r['nama_setting']] = $r['nilai_setting'];
}
$app_name = $app_sets['nama_sekolah'] ?? 'Aplikasi Jurnal Mengajar';
$favicon = !empty($app_sets['favicon']) ? BASE_URL . 'uploads/' . $app_sets['favicon'] : BASE_URL . 'assets/img/favicon.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Beranda' ?> - <?= htmlspecialchars($app_name) ?></title>

    <?php if ($favicon): ?>
    <link rel="icon" type="image/png" href="<?= $favicon ?>">
    <?php endif; ?>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5',
                        secondary: '#10B981',
                        dark: '#1F2937',
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- App Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-800">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar-transition fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-white transform -translate-x-full lg:translate-x-0 lg:static lg:inset-0 shadow-2xl flex flex-col">
            <div class="flex flex-col items-center justify-center py-8 border-b border-slate-800 px-6 shrink-0">
                <div class="flex flex-col items-center mb-1">
                    <span class="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-indigo-400 to-emerald-400 tracking-tighter">CAKRA</span>
                    <p class="text-[7px] text-slate-500 font-bold uppercase tracking-[0.1em] text-center leading-none mt-1">Central Academic Knowledge & Record Application</p>
                </div>
                <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest text-center truncate w-full"><?= htmlspecialchars($app_name) ?></p>
            </div>

            <nav class="flex-1 mt-4 px-4 space-y-2 overflow-y-auto pb-8">
                <?php require_once __DIR__ . '/sidebar_content.php'; ?>
            </nav>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Topbar -->
            <header class="h-16 glass sticky top-0 z-40 flex items-center justify-between px-4 lg:px-8 border-b border-slate-200">
                <button id="sidebarToggle" class="p-2 rounded-lg hover:bg-slate-100 lg:hidden text-slate-600">
                    <i class="fa fa-bars text-xl"></i>
                </button>

                <div class="flex items-center space-x-4">
                    <div class="hidden md:flex flex-col text-right">
                        <span class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($_SESSION['nama_lengkap']); ?></span>
                        <span class="text-xs text-slate-500 uppercase tracking-wider font-bold"><?= htmlspecialchars($_SESSION['role']); ?></span>
                    </div>

                    <div class="relative group">
                        <button class="w-10 h-10 rounded-full bg-indigo-500 flex items-center justify-center text-white shadow-lg hover:ring-4 hover:ring-indigo-100 transition-all">
                            <i class="fa fa-user"></i>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-slate-100 py-2 hidden group-hover:block animate-in fade-in slide-in-from-top-2 duration-200">
                            <a href="<?= BASE_URL ?>logout.php" class="flex items-center px-4 py-2 text-sm text-rose-600 hover:bg-rose-50 font-medium">
                                <i class="fa fa-sign-out-alt mr-3"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Scrollable Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-50 p-4 lg:p-8">
