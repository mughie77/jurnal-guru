<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'];

function nav_link($url, $icon, $label, $active) {
    $base_url = BASE_URL;
    $class = $active
        ? 'bg-indigo-600 text-white'
        : 'text-slate-400 hover:bg-slate-800 hover:text-white';
    return "
        <a href='{$base_url}{$url}' class='flex items-center px-4 py-3 rounded-xl transition-all duration-200 group {$class}'>
            <i class='{$icon} w-6 text-center text-lg mr-3 ".($active ? "" : "group-hover:scale-110 transition-transform")."'></i>
            <span class='font-medium'>{$label}</span>
        </a>";
}

if ($role == 'admin') {
    echo nav_link('admin/index.php', 'fa fa-tachometer-alt', 'Dashboard', $current_page == 'index.php');
    echo nav_link('admin/jurnal.php', 'fa fa-book-open', 'Data Jurnal', $current_page == 'jurnal.php');
    echo nav_link('admin/users.php', 'fa fa-users', 'Manajemen User', $current_page == 'users.php');
    echo nav_link('admin/guru.php', 'fa fa-chalkboard-teacher', 'Manajemen Guru', $current_page == 'guru.php');
    echo nav_link('admin/kelas.php', 'fa fa-school', 'Manajemen Kelas', $current_page == 'kelas.php');
    echo nav_link('admin/naik_kelas.php', 'fa fa-level-up-alt', 'Kenaikan Kelas', $current_page == 'naik_kelas.php');
    echo nav_link('admin/siswa.php', 'fa fa-user-graduate', 'Manajemen Siswa', $current_page == 'siswa.php');
    echo nav_link('admin/mapel.php', 'fa fa-book', 'Mata Pelajaran', $current_page == 'mapel.php');
    echo nav_link('admin/tahun_pelajaran.php', 'fa fa-calendar-alt', 'Tahun Pelajaran', $current_page == 'tahun_pelajaran.php');
} elseif ($role == 'waka') {
    echo nav_link('waka/index.php', 'fa fa-tachometer-alt', 'Dashboard', $current_page == 'index.php');
    echo nav_link('waka/jurnal.php', 'fa fa-book-open', 'Laporan Jurnal', $current_page == 'jurnal.php');
}

echo "<div class='pt-4 mt-4 border-t border-slate-800'>";
echo nav_link('logout.php', 'fa fa-sign-out-alt text-rose-500', 'Keluar Sistem', false);
echo "</div>";
?>
