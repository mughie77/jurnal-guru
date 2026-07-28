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

// Get User Photo for Header
$user_photo = null;
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    if ($role == 'siswa') {
        $q = mysqli_query($conn, "SELECT foto FROM siswa WHERE id = $uid");
        $u_data = mysqli_fetch_assoc($q);
        $user_photo = !empty($u_data['foto']) ? BASE_URL . "uploads/siswa/" . $u_data['foto'] : null;
    } elseif ($role == 'guru' || $role == 'waka') {
        $q = mysqli_query($conn, "SELECT foto FROM guru WHERE user_id = $uid");
        $u_data = mysqli_fetch_assoc($q);
        $user_photo = (!empty($u_data) && !empty($u_data['foto'])) ? BASE_URL . "uploads/guru/" . $u_data['foto'] : null;
    }
}
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

            <nav id="sidebar-nav" class="flex-1 mt-4 px-4 space-y-2 overflow-y-auto pb-8">
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
                    <div class="flex items-center bg-slate-50 border border-slate-100 px-3 py-1.5 rounded-xl mr-2">
                        <div class="flex items-center border-r border-slate-200 pr-3 mr-3">
                            <i class="fa fa-calendar-day text-indigo-500 mr-2 text-[10px]"></i>
                            <span id="header-date" class="text-[11px] font-black text-slate-500 uppercase tracking-widest"><?= date('d M Y') ?></span>
                        </div>
                        <i class="fa fa-clock text-indigo-500 mr-2 text-xs"></i>
                        <span id="digital-clock" class="text-sm font-black text-slate-700 italic tracking-tighter"><?= date('H:i:s') ?></span>
                    </div>

                    <div class="hidden md:flex flex-col text-right">
                        <span class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($_SESSION['nama_lengkap']); ?></span>
                        <span class="text-xs text-slate-500 uppercase tracking-wider font-bold"><?= htmlspecialchars($_SESSION['role']); ?></span>
                    </div>

                    <div class="relative group">
                        <?php if ($_SESSION['role'] == 'siswa' || $_SESSION['role'] == 'guru'): ?>
                            <a href="<?= BASE_URL . $_SESSION['role'] ?>/profil.php" class="w-10 h-10 rounded-full bg-indigo-500 flex items-center justify-center text-white shadow-lg hover:ring-4 hover:ring-indigo-100 transition-all overflow-hidden block">
                                <?php if ($user_photo): ?>
                                    <img src="<?= $user_photo ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fa fa-user"></i>
                                <?php endif; ?>
                            </a>
                        <?php else: ?>
                            <button class="w-10 h-10 rounded-full bg-indigo-500 flex items-center justify-center text-white shadow-lg hover:ring-4 hover:ring-indigo-100 transition-all overflow-hidden">
                                <?php if ($user_photo): ?>
                                    <img src="<?= $user_photo ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fa fa-user"></i>
                                <?php endif; ?>
                            </button>
                        <?php endif; ?>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-slate-100 py-2 hidden group-hover:block animate-in fade-in slide-in-from-top-2 duration-200">
                            <?php if ($_SESSION['role'] == 'siswa' || $_SESSION['role'] == 'guru'): ?>
                                <a href="<?= BASE_URL . $_SESSION['role'] ?>/profil.php" class="flex items-center px-4 py-2 text-sm text-slate-600 hover:bg-indigo-50 font-medium">
                                    <i class="fa fa-user-circle mr-3 text-indigo-500"></i> Profil Saya
                                </a>
                                <div class="h-px bg-slate-50 my-1"></div>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>logout.php" class="flex items-center px-4 py-2 text-sm text-rose-600 hover:bg-rose-50 font-medium">
                                <i class="fa fa-sign-out-alt mr-3"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <?php
            $show_mood_survey = false;
            if (isset($_SESSION['user_id']) && in_array($_SESSION['role'], ['siswa', 'guru'])) {
                $current_date = date('Y-m-d');
                $uid = (int)$_SESSION['user_id'];
                $role = mysqli_real_escape_string($conn, $_SESSION['role']);

                // Check if entry exists
                $check_survey = mysqli_query($conn, "SELECT 1 FROM mood_survey WHERE user_id = $uid AND role = '$role' AND tanggal = '$current_date'");
                if (mysqli_num_rows($check_survey) == 0) {
                    $show_mood_survey = true;
                }
            }
            ?>

            <?php if ($show_mood_survey): ?>
            <div id="moodSurveyOverlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-xl flex items-center justify-center p-4 z-[9999]">
                <div class="max-w-xl w-full bg-white rounded-[32px] shadow-2xl overflow-y-auto max-h-[90vh] relative border border-slate-100 flex flex-col animate-in fade-in zoom-in-95 duration-300">
                    <!-- Top decorative mesh banner -->
                    <div class="bg-gradient-to-r from-indigo-600 via-purple-600 to-indigo-700 p-8 text-center text-white relative">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -mr-16 -mt-16 blur-xl"></div>
                        <div class="absolute bottom-0 left-0 w-32 h-32 bg-indigo-500/20 rounded-full -ml-16 -mb-16 blur-xl"></div>

                        <div class="w-16 h-16 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center border border-white/20 mx-auto mb-4 shadow-xl">
                            <i class="fa fa-heart-pulse text-white text-3xl animate-pulse"></i>
                        </div>
                        <h2 class="text-2xl font-black italic tracking-tight">MOOD SURVEY HARIAN</h2>
                        <p class="text-indigo-100/80 text-xs font-bold uppercase tracking-[0.15em] mt-1">Bagaimana kabar & mood Anda hari ini?</p>
                    </div>

                    <div class="p-8 flex-1">
                        <p class="text-slate-500 text-sm text-center font-semibold mb-6">
                            Halo <span class="text-indigo-600 font-bold"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></span>, silakan pilih salah satu emoji mood yang menggambarkan perasaan Anda hari ini sebelum melanjutkan aktivitas di CAKRA.
                        </p>

                        <form id="moodSurveyForm">
                            <input type="hidden" name="mood_value" id="selectedMoodValue" value="">

                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                <!-- Sangat Baik -->
                                <button type="button" onclick="selectMoodCard('sangat_baik', this)" class="mood-card p-4 rounded-2xl border-2 border-slate-100 hover:border-indigo-100 hover:bg-indigo-50/20 flex flex-col items-center justify-center gap-2 transition-all group focus:outline-none">
                                    <span class="text-4xl group-hover:scale-110 transition-transform">😃</span>
                                    <span class="text-xs font-black text-slate-700 uppercase tracking-wider">Sangat Baik</span>
                                </button>
                                <!-- Bersemangat -->
                                <button type="button" onclick="selectMoodCard('bersemangat', this)" class="mood-card p-4 rounded-2xl border-2 border-slate-100 hover:border-indigo-100 hover:bg-indigo-50/20 flex flex-col items-center justify-center gap-2 transition-all group focus:outline-none">
                                    <span class="text-4xl group-hover:scale-110 transition-transform">💪</span>
                                    <span class="text-xs font-black text-slate-700 uppercase tracking-wider">Bersemangat</span>
                                </button>
                                <!-- Biasa Saja -->
                                <button type="button" onclick="selectMoodCard('biasa_saja', this)" class="mood-card p-4 rounded-2xl border-2 border-slate-100 hover:border-indigo-100 hover:bg-indigo-50/20 flex flex-col items-center justify-center gap-2 transition-all group focus:outline-none">
                                    <span class="text-4xl group-hover:scale-110 transition-transform">😐</span>
                                    <span class="text-xs font-black text-slate-700 uppercase tracking-wider">Biasa Saja</span>
                                </button>
                                <!-- Lelah -->
                                <button type="button" onclick="selectMoodCard('lelah', this)" class="mood-card p-4 rounded-2xl border-2 border-slate-100 hover:border-indigo-100 hover:bg-indigo-50/20 flex flex-col items-center justify-center gap-2 transition-all group focus:outline-none">
                                    <span class="text-4xl group-hover:scale-110 transition-transform">😴</span>
                                    <span class="text-xs font-black text-slate-700 uppercase tracking-wider">Lelah</span>
                                </button>
                                <!-- Stres -->
                                <button type="button" onclick="selectMoodCard('stres', this)" class="mood-card p-4 rounded-2xl border-2 border-slate-100 hover:border-indigo-100 hover:bg-indigo-50/20 flex flex-col items-center justify-center gap-2 transition-all group focus:outline-none">
                                    <span class="text-4xl group-hover:scale-110 transition-transform">😔</span>
                                    <span class="text-xs font-black text-slate-700 uppercase tracking-wider">Stres</span>
                                </button>
                                <!-- Sedih -->
                                <button type="button" onclick="selectMoodCard('sedih', this)" class="mood-card p-4 rounded-2xl border-2 border-slate-100 hover:border-indigo-100 hover:bg-indigo-50/20 flex flex-col items-center justify-center gap-2 transition-all group focus:outline-none">
                                    <span class="text-4xl group-hover:scale-110 transition-transform">😢</span>
                                    <span class="text-xs font-black text-slate-700 uppercase tracking-wider">Sedih</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <script>
            function selectMoodCard(mood, element) {
                // Set hidden input value
                document.getElementById('selectedMoodValue').value = mood;

                // Clear selection on other cards
                document.querySelectorAll('.mood-card').forEach(card => {
                    card.classList.remove('border-indigo-500', 'bg-indigo-50/50', 'ring-4', 'ring-indigo-100');
                    card.classList.add('border-slate-100');
                });

                // Highlight selected card
                element.classList.remove('border-slate-100');
                element.classList.add('border-indigo-500', 'bg-indigo-50/50', 'ring-4', 'ring-indigo-100');

                // Instant direct submit upon selection
                submitMoodSurveyDirect(mood);
            }

            function submitMoodSurveyDirect(mood) {
                if (!mood) return;

                // Disable all cards to prevent double submissions
                document.querySelectorAll('.mood-card').forEach(card => {
                    card.disabled = true;
                    card.style.opacity = '0.6';
                    card.style.pointerEvents = 'none';
                });

                fetch('<?= BASE_URL ?>api/submit_mood.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'mood=' + encodeURIComponent(mood)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Mood Berhasil Disimpan!',
                            text: data.message || 'Terima kasih, semoga hari Anda menyenangkan!',
                            confirmButtonColor: '#4F46E5',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            // Fade out and remove overlay
                            const overlay = document.getElementById('moodSurveyOverlay');
                            overlay.classList.add('opacity-0', 'transition-opacity', 'duration-300');
                            setTimeout(() => {
                                overlay.remove();
                            }, 300);
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Menyimpan Mood',
                            text: data.error || 'Terjadi kesalahan sistem, silakan coba lagi.',
                            confirmButtonColor: '#4F46E5'
                        });
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<span>SIMPAN MOOD SAYA</span> <i class="fa fa-paper-plane"></i>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Koneksi Bermasalah',
                        text: 'Silakan periksa koneksi internet Anda.',
                        confirmButtonColor: '#4F46E5'
                    });
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span>SIMPAN MOOD SAYA</span> <i class="fa fa-paper-plane"></i>';
                });
            }
            </script>
            <?php endif; ?>

            <!-- Main Scrollable Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-50 p-4 lg:p-8">
