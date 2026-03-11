<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'];

function nav_link($url, $icon, $label, $active) {
    $base_url = BASE_URL;
    $class = $active
        ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/30'
        : 'text-slate-400 hover:bg-slate-800 hover:text-white';
    return "
        <a href='{$base_url}{$url}' class='flex items-center px-4 py-3 rounded-xl transition-all duration-300 group {$class}'>
            <i class='{$icon} w-6 text-center text-lg mr-3 ".($active ? "" : "group-hover:scale-110 group-hover:text-indigo-400 transition-all")."'></i>
            <span class='font-medium'>{$label}</span>
        </a>";
}

function sub_nav_link($url, $label, $active) {
    $base_url = BASE_URL;
    $class = $active ? 'text-indigo-400 font-bold' : 'text-slate-500 hover:text-slate-300';
    $bullet = $active ? 'bg-indigo-400' : 'bg-slate-700';
    return "
        <a href='{$base_url}{$url}' class='flex items-center py-2 pl-12 pr-4 transition-colors group {$class}'>
            <div class='w-1.5 h-1.5 rounded-full mr-3 {$bullet} group-hover:bg-indigo-400 transition-colors'></div>
            <span class='text-sm'>{$label}</span>
        </a>";
}

if ($role == 'admin') {
    echo nav_link('admin/index.php', 'fa fa-tachometer-alt', 'Beranda', $current_page == 'index.php');

    // Group: Data Master
    echo "
    <div class='pt-4'>
        <p class='px-4 text-[10px] font-black text-slate-600 uppercase tracking-[0.2em] mb-2'>Data Master</p>
        ".nav_link('admin/users.php', 'fa fa-users-cog', 'Manajemen User', $current_page == 'users.php')."
        ".nav_link('admin/guru.php', 'fa fa-chalkboard-teacher', 'Data Guru', $current_page == 'guru.php')."
        ".nav_link('admin/mapel.php', 'fa fa-book', 'Mata Pelajaran', $current_page == 'mapel.php')."
        ".nav_link('admin/tahun_pelajaran.php', 'fa fa-calendar-alt', 'Tahun Pelajaran', $current_page == 'tahun_pelajaran.php')."
    </div>";

    // Group: Akademik
    echo "
    <div class='pt-6'>
        <p class='px-4 text-[10px] font-black text-slate-600 uppercase tracking-[0.2em] mb-2'>Data Akademik</p>
        ".nav_link('admin/kelas.php', 'fa fa-school', 'Manajemen Kelas', $current_page == 'kelas.php')."
        ".nav_link('admin/siswa.php', 'fa fa-user-graduate', 'Data Siswa', $current_page == 'siswa.php')."
        ".nav_link('admin/import_foto_zip.php', 'fa fa-images', 'Import Foto ZIP', $current_page == 'import_foto_zip.php')."
        ".nav_link('admin/alumni.php', 'fa fa-user-tag', 'Data Alumni', $current_page == 'alumni.php')."
        ".nav_link('admin/mapping_siswa.php', 'fa fa-project-diagram', 'Mapping Kelas', $current_page == 'mapping_siswa.php')."
        ".nav_link('admin/naik_kelas.php', 'fa fa-level-up-alt', 'Kenaikan Kelas', $current_page == 'naik_kelas.php')."
    </div>";

    // Group: Laporan
    echo "
    <div class='pt-6'>
        <p class='px-4 text-[10px] font-black text-slate-600 uppercase tracking-[0.2em] mb-2'>Laporan & Rekap</p>
        ".nav_link('admin/jurnal.php', 'fa fa-book-open', 'Jurnal Mengajar', $current_page == 'jurnal.php')."
        ".nav_link('admin/rekap_absensi.php', 'fa fa-chart-line', 'Rekap Absensi', $current_page == 'rekap_absensi.php')."
        ".nav_link('admin/perangkat.php', 'fa fa-folder-open', 'Data Perangkat', $current_page == 'perangkat.php')."
    </div>";

    // Group: Sistem
    echo "
    <div class='pt-6'>
        <p class='px-4 text-[10px] font-black text-slate-600 uppercase tracking-[0.2em] mb-2'>Sistem</p>
        ".nav_link('admin/pengaturan.php', 'fa fa-cog', 'Pengaturan', $current_page == 'pengaturan.php')."
    </div>";
} elseif ($role == 'waka') {
    echo nav_link('waka/index.php', 'fa fa-tachometer-alt', 'Beranda', $current_page == 'index.php');

    // Group: Data Master (View only for Waka)
    echo "
    <div class='pt-4'>
        <p class='px-4 text-[10px] font-black text-slate-600 uppercase tracking-[0.2em] mb-2'>Data Master</p>
        ".nav_link('waka/guru.php', 'fa fa-chalkboard-teacher', 'Data Guru', $current_page == 'guru.php')."
        ".nav_link('waka/mapel.php', 'fa fa-book', 'Mata Pelajaran', $current_page == 'mapel.php')."
    </div>";

    // Group: Akademik (View only for Waka)
    echo "
    <div class='pt-6'>
        <p class='px-4 text-[10px] font-black text-slate-600 uppercase tracking-[0.2em] mb-2'>Data Akademik</p>
        ".nav_link('waka/kelas.php', 'fa fa-school', 'Manajemen Kelas', $current_page == 'kelas.php')."
        ".nav_link('admin/siswa.php', 'fa fa-user-graduate', 'Data Siswa', $current_page == 'siswa.php')."
        ".nav_link('admin/alumni.php', 'fa fa-user-tag', 'Data Alumni', $current_page == 'alumni.php')."
    </div>";

    // Group: Laporan (Full access for Waka)
    echo "
    <div class='pt-6'>
        <p class='px-4 text-[10px] font-black text-slate-600 uppercase tracking-[0.2em] mb-2'>Laporan & Rekap</p>
        ".nav_link('waka/jurnal.php', 'fa fa-book-open', 'Jurnal Mengajar', $current_page == 'jurnal.php')."
        ".nav_link('admin/rekap_absensi.php', 'fa fa-chart-line', 'Rekap Absensi', $current_page == 'rekap_absensi.php')."
        ".nav_link('admin/perangkat.php', 'fa fa-folder-open', 'Data Perangkat', $current_page == 'perangkat.php')."
    </div>";
}

echo "<div class='pt-8 mt-8 border-t border-slate-800/50'>";
echo nav_link('logout.php', 'fa fa-sign-out-alt text-rose-500', 'Keluar Sistem', false);
echo "</div>";
?>
