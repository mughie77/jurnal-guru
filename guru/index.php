<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi hanya untuk guru
authorize_role(['guru', 'admin']); // Admin ditambahkan untuk kemudahan testing

$page_title = "Dashboard Guru";

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Override sidebar style untuk dashboard guru */
.main-content {
    margin-left: 0;
    padding: 2rem;
}
.guru-dashboard .card-menu {
    text-decoration: none;
    color: #212529;
    transition: transform 0.2s, box-shadow 0.2s;
    border-radius: 1rem;
    overflow: hidden;
}
.guru-dashboard .card-menu:hover {
    transform: translateY(-10px);
    box-shadow: 0 1rem 2rem rgba(0,0,0,0.15);
}
.guru-dashboard .card-icon {
    font-size: 5rem;
    color: #0d6efd;
    transition: color 0.3s;
}
.guru-dashboard .card-menu:hover .card-icon {
    color: #0a58ca;
}
.guru-dashboard .card-title {
    font-weight: 600;
}
.guru-dashboard .card-text {
    color: #6c757d;
}
</style>

<div class="container-fluid guru-dashboard">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dashboard Guru</h1>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <h5 class="card-title">Selamat Datang, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>!</h5>
            <p class="card-text">Silakan pilih menu di bawah ini untuk mulai mengelola jurnal mengajar Anda.</p>
        </div>
    </div>

    <div class="row justify-content-center mt-5">
        <!-- Menu Isi Jurnal Baru -->
        <div class="col-xl-4 col-md-6 mb-4">
            <a href="<?= BASE_URL ?>guru/isi_jurnal.php" class="card-menu">
                <div class="card border-left-primary shadow h-100 py-4">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-edit card-icon"></i>
                        </div>
                        <h5 class="card-title">Isi Jurnal Baru</h5>
                        <p class="card-text">Lengkapi jurnal mengajar harian Anda di sini.</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Menu Riwayat Jurnal -->
        <div class="col-xl-4 col-md-6 mb-4">
            <a href="<?= BASE_URL ?>guru/riwayat.php" class="card-menu">
                <div class="card border-left-success shadow h-100 py-4">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-history card-icon"></i>
                        </div>
                        <h5 class="card-title">Riwayat Jurnal Saya</h5>
                        <p class="card-text">Lihat dan kelola semua jurnal yang pernah Anda isi.</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<?php
// Footer disesuaikan agar tidak ada duplikasi
// Karena header sudah termasuk wrapper, kita tutup di sini
?>
</div> <!-- Menutup .main-content atau container-fluid dari header -->
<?php
require_once __DIR__ . '/../includes/footer.php';
?>