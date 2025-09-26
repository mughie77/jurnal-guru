<?php
// Mendapatkan nama file saat ini untuk menentukan link aktif
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h4><i class="fa fa-user-tie"></i> Waka Panel</h4>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= ($current_page == 'index.php') ? 'active' : ''; ?>" href="<?= BASE_URL ?>waka/index.php">
                <i class="fa fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($current_page == 'jurnal.php') ? 'active' : ''; ?>" href="<?= BASE_URL ?>waka/jurnal.php">
                <i class="fa fa-book-open"></i> Laporan Jurnal
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($current_page == 'guru.php') ? 'active' : ''; ?>" href="<?= BASE_URL ?>waka/guru.php">
                <i class="fa fa-chalkboard-teacher"></i> Lihat Data Guru
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($current_page == 'kelas.php') ? 'active' : ''; ?>" href="<?= BASE_URL ?>waka/kelas.php">
                <i class="fa fa-school"></i> Lihat Data Kelas
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($current_page == 'mapel.php') ? 'active' : ''; ?>" href="<?= BASE_URL ?>waka/mapel.php">
                <i class="fa fa-book"></i> Lihat Data Mapel
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($current_page == 'tahun_pelajaran.php') ? 'active' : ''; ?>" href="<?= BASE_URL ?>waka/tahun_pelajaran.php">
                <i class="fa fa-calendar-alt"></i> Lihat Th. Pelajaran
            </a>
        </li>
    </ul>
</div>
<div class="main-content">