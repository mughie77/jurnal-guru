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
        <a href='{$base_url}{$url}' class='flex items-center py-2.5 pl-12 pr-4 transition-colors group {$class}'>
            <div class='w-1.5 h-1.5 rounded-full mr-3 {$bullet} group-hover:bg-indigo-400 transition-colors shrink-0'></div>
            <span class='text-sm truncate'>{$label}</span>
        </a>";
}

function sidebar_section($title, $icon, $open, $links_html) {
    $open_attr = $open ? 'open' : '';
    return "
    <div class='pt-4 border-t border-slate-800/40 first:border-0'>
        <details class='group' {$open_attr}>
            <summary class='flex items-center justify-between px-4 py-2.5 rounded-xl text-slate-500 hover:bg-slate-800 hover:text-white cursor-pointer transition-all list-none [&::-webkit-details-marker]:hidden outline-none select-none'>
                <div class='flex items-center'>
                    <i class='{$icon} w-6 text-center text-base mr-3 text-slate-500 group-hover:text-indigo-400 group-open:text-indigo-400 transition-colors'></i>
                    <span class='font-black text-xs uppercase tracking-[0.15em]'>{$title}</span>
                </div>
                <i class='fa fa-chevron-right text-[9px] text-slate-600 transition-transform duration-300 group-open:rotate-90'></i>
            </summary>
            <div class='mt-1.5 space-y-0.5 transition-all duration-300'>
                {$links_html}
            </div>
        </details>
    </div>";
}

if ($role == 'admin') {
    echo nav_link('admin/index.php', 'fa fa-tachometer-alt', 'Beranda', $current_page == 'index.php' && strpos($_SERVER['PHP_SELF'], '/admin/') !== false);

    // Group 1: Data Master
    $master_active = in_array($current_page, ['users.php', 'guru.php', 'mapel.php', 'tahun_pelajaran.php']);
    $master_links = sub_nav_link('admin/users.php', 'Manajemen User', $current_page == 'users.php') .
                    sub_nav_link('admin/guru.php', 'Data Guru', $current_page == 'guru.php') .
                    sub_nav_link('admin/mapel.php', 'Mata Pelajaran', $current_page == 'mapel.php') .
                    sub_nav_link('admin/tahun_pelajaran.php', 'Tahun Pelajaran', $current_page == 'tahun_pelajaran.php');
    echo sidebar_section('Data Master', 'fa fa-database', $master_active, $master_links);

    // Group 2: Data Akademik
    $akademik_active = in_array($current_page, ['kelas.php', 'siswa.php', 'import_foto_zip.php', 'alumni.php', 'mapping_siswa.php', 'naik_kelas.php']);
    $akademik_links = sub_nav_link('admin/kelas.php', 'Manajemen Kelas', $current_page == 'kelas.php') .
                      sub_nav_link('admin/siswa.php', 'Data Siswa', $current_page == 'siswa.php') .
                      sub_nav_link('admin/import_foto_zip.php', 'Import Foto ZIP', $current_page == 'import_foto_zip.php') .
                      sub_nav_link('admin/alumni.php', 'Data Alumni', $current_page == 'alumni.php') .
                      sub_nav_link('admin/mapping_siswa.php', 'Mapping Kelas', $current_page == 'mapping_siswa.php') .
                      sub_nav_link('admin/naik_kelas.php', 'Kenaikan Kelas', $current_page == 'naik_kelas.php');
    echo sidebar_section('Data Akademik', 'fa fa-graduation-cap', $akademik_active, $akademik_links);

    // Group 3: Laporan & Rekap
    $laporan_active = in_array($current_page, ['jurnal.php', 'rekap_absensi.php', 'rekap_persiswa.php', 'rekap_gps.php', 'rekap_izin.php', 'rekap_kritik.php', 'rekap_panic.php', 'rekap_mood.php', 'rekap_berkas.php', 'perangkat.php']);
    $laporan_links = sub_nav_link('admin/jurnal.php', 'Jurnal Mengajar', $current_page == 'jurnal.php') .
                     sub_nav_link('admin/rekap_absensi.php', 'Rekap Absensi Jurnal', $current_page == 'rekap_absensi.php') .
                     sub_nav_link('admin/rekap_persiswa.php', 'Rekap Kehadiran Siswa', $current_page == 'rekap_persiswa.php') .
                     sub_nav_link('admin/rekap_gps.php', 'Rekap Absensi GPS', $current_page == 'rekap_gps.php') .
                     sub_nav_link('waka/rekap_izin.php', 'Rekap Pengajuan Izin', $current_page == 'rekap_izin.php') .
                     sub_nav_link('admin/rekap_kritik.php', 'Rekap Kritik & Saran', $current_page == 'rekap_kritik.php') .
                     sub_nav_link('admin/rekap_panic.php', 'Laporan Bullying', $current_page == 'rekap_panic.php') .
                     sub_nav_link('admin/rekap_mood.php', 'Rekap Mood Harian', $current_page == 'rekap_mood.php') .
                     sub_nav_link('admin/rekap_berkas.php', 'Rekap Berkas Siswa', $current_page == 'rekap_berkas.php') .
                     sub_nav_link('admin/perangkat.php', 'Data Perangkat', $current_page == 'perangkat.php');
    echo sidebar_section('Laporan & Rekap', 'fa fa-chart-bar', $laporan_active, $laporan_links);

    // Group 4: Sistem
    $sistem_active = in_array($current_page, ['pengaturan.php', 'backup_restore.php']);
    $sistem_links = sub_nav_link('admin/pengaturan.php', 'Pengaturan', $current_page == 'pengaturan.php') .
                    sub_nav_link('admin/backup_restore.php', 'Backup & Restore', $current_page == 'backup_restore.php');
    echo sidebar_section('Sistem', 'fa fa-cogs', $sistem_active, $sistem_links);
} elseif ($role == 'waka') {
    echo nav_link('waka/index.php', 'fa fa-tachometer-alt', 'Beranda', $current_page == 'index.php' && strpos($_SERVER['PHP_SELF'], '/waka/') !== false);

    // Group 1: Data Master (Waka)
    $master_active = in_array($current_page, ['guru.php', 'mapel.php']);
    $master_links = sub_nav_link('waka/guru.php', 'Data Guru', $current_page == 'guru.php') .
                    sub_nav_link('waka/mapel.php', 'Mata Pelajaran', $current_page == 'mapel.php');
    echo sidebar_section('Data Master', 'fa fa-database', $master_active, $master_links);

    // Group 2: Data Akademik (Waka)
    $akademik_active = in_array($current_page, ['kelas.php', 'siswa.php', 'alumni.php']);
    $akademik_links = sub_nav_link('waka/kelas.php', 'Manajemen Kelas', $current_page == 'kelas.php') .
                      sub_nav_link('admin/siswa.php', 'Data Siswa', $current_page == 'siswa.php') .
                      sub_nav_link('admin/alumni.php', 'Data Alumni', $current_page == 'alumni.php');
    echo sidebar_section('Data Akademik', 'fa fa-graduation-cap', $akademik_active, $akademik_links);

    // Group 3: Laporan & Rekap (Waka)
    $laporan_active = in_array($current_page, ['jurnal.php', 'rekap_absensi.php', 'rekap_persiswa.php', 'rekap_gps.php', 'rekap_izin.php', 'rekap_kritik.php', 'rekap_panic.php', 'rekap_mood.php', 'rekap_berkas.php', 'perangkat.php']);
    $laporan_links = sub_nav_link('waka/jurnal.php', 'Jurnal Mengajar', $current_page == 'jurnal.php') .
                     sub_nav_link('admin/rekap_absensi.php', 'Rekap Absensi Jurnal', $current_page == 'rekap_absensi.php') .
                     sub_nav_link('admin/rekap_persiswa.php', 'Rekap Kehadiran Siswa', $current_page == 'rekap_persiswa.php') .
                     sub_nav_link('admin/rekap_gps.php', 'Rekap Absensi GPS', $current_page == 'rekap_gps.php') .
                     sub_nav_link('waka/rekap_izin.php', 'Rekap Pengajuan Izin', $current_page == 'rekap_izin.php') .
                     sub_nav_link('admin/rekap_kritik.php', 'Rekap Kritik & Saran', $current_page == 'rekap_kritik.php') .
                     sub_nav_link('admin/rekap_panic.php', 'Laporan Bullying', $current_page == 'rekap_panic.php') .
                     sub_nav_link('admin/rekap_mood.php', 'Rekap Mood Harian', $current_page == 'rekap_mood.php') .
                     sub_nav_link('admin/rekap_berkas.php', 'Rekap Berkas Siswa', $current_page == 'rekap_berkas.php') .
                     sub_nav_link('admin/perangkat.php', 'Data Perangkat', $current_page == 'perangkat.php');
    echo sidebar_section('Laporan & Rekap', 'fa fa-chart-bar', $laporan_active, $laporan_links);
}

echo "<div class='pt-8 mt-8 border-t border-slate-800/50'>";
echo nav_link('logout.php', 'fa fa-sign-out-alt text-rose-500', 'Keluar Sistem', false);
echo "</div>";
?>
