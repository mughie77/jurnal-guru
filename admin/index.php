<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi hanya untuk admin
authorize_role(['admin']);

// Ambil data statistik
// 1. Jumlah Guru
$result_guru = mysqli_query($conn, "SELECT COUNT(id) as total FROM guru");
$total_guru = mysqli_fetch_assoc($result_guru)['total'];

// 2. Jumlah Mata Pelajaran
$result_mapel = mysqli_query($conn, "SELECT COUNT(id) as total FROM mata_pelajaran");
$total_mapel = mysqli_fetch_assoc($result_mapel)['total'];

// 3. Jumlah Kelas
$result_kelas = mysqli_query($conn, "SELECT COUNT(id) as total FROM kelas");
$total_kelas = mysqli_fetch_assoc($result_kelas)['total'];

// 4. Jumlah Jurnal Hari Ini
$today = date('Y-m-d');
$result_jurnal = mysqli_query($conn, "SELECT COUNT(id) as total FROM jurnal WHERE tanggal = '$today'");
$total_jurnal_hari_ini = mysqli_fetch_assoc($result_jurnal)['total'];

// Set judul halaman
$page_title = "Dashboard Admin";

// Sertakan header
require_once __DIR__ . '/../includes/header.php';
?>

<?php
// Sertakan sidebar admin
require_once __DIR__ . '/../includes/sidebar_admin.php';
?>

<!-- Page Heading -->
<h1 class="h3 mb-4 text-gray-800">Dashboard</h1>

<!-- Content Row -->
<div class="row">

    <!-- Card Jumlah Guru -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Jumlah Guru</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_guru ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card Jumlah Mata Pelajaran -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Jumlah Mata Pelajaran</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_mapel ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-book fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card Jumlah Kelas -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Jumlah Kelas
                        </div>
                        <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800"><?= $total_kelas ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-school fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card Jurnal Hari Ini -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Jurnal Terisi (Hari Ini)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_jurnal_hari_ini ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Selamat Datang!</h6>
    </div>
    <div class="card-body">
        <p>Selamat datang di Dashboard Admin Aplikasi Jurnal Mengajar.</p>
        <p>Anda dapat mengelola semua data master melalui menu navigasi di sebelah kiri, termasuk data pengguna, guru, kelas, mata pelajaran, dan tahun pelajaran. Anda juga dapat memantau dan mengekspor semua jurnal yang telah diisi oleh para guru.</p>
    </div>
</div>

<?php
// Sertakan footer
require_once __DIR__ . '/../includes/footer.php';
?>