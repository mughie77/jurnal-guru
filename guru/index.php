<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi hanya untuk guru
authorize_role(['guru', 'admin']); // Admin ditambahkan untuk kemudahan testing

$page_title = "Dashboard Guru";

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Override default layout for this specific page */
.main-content {
    margin-left: 0; /* Remove sidebar margin */
    padding: 0;
    background-color: #f8f9fa; /* Default background */
}
.navbar {
    display: none; /* Hide top navbar on this page */
}
.guru-header {
    background-color: #6a63e8; /* Purple background */
    color: #fff;
    padding: 2rem;
    border-bottom-left-radius: 1.5rem;
    border-bottom-right-radius: 1.5rem;
}
.guru-dashboard-container {
    padding: 2rem;
}
.guru-welcome {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.guru-welcome h2 {
    font-weight: 600;
    margin: 0;
}
.logout-btn {
    color: #6a63e8;
    background-color: #fff;
    border-radius: 20px;
    padding: 0.5rem 1rem;
    text-decoration: none;
    font-weight: 500;
    transition: background-color 0.2s;
}
.logout-btn:hover {
    background-color: #f0f0f0;
}
.menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}
.menu-card {
    background-color: #fff;
    border-radius: 1rem;
    padding: 2rem;
    text-align: center;
    text-decoration: none;
    color: #333;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.menu-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 35px rgba(0, 0, 0, 0.15);
}
.menu-card .icon {
    font-size: 4rem;
    color: #6a63e8;
    margin-bottom: 1rem;
}
.menu-card h5 {
    font-weight: 600;
    margin: 0;
}
</style>

<div class="main-content">
    <div class="guru-header">
        <div class="guru-welcome">
            <div>
                <h2>Selamat Datang,</h2>
                <p class="lead mb-0"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?>!</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <div class="guru-dashboard-container">
        <div class="menu-grid">
        <a href="<?= BASE_URL ?>guru/isi_jurnal.php" class="menu-card">
            <div class="icon">
                <i class="fas fa-edit"></i>
            </div>
            <h5>Isi Jurnal</h5>
        </a>
        <a href="<?= BASE_URL ?>guru/riwayat.php" class="menu-card">
            <div class="icon">
                <i class="fas fa-history"></i>
            </div>
            <h5>Riwayat Jurnal</h5>
        </a>
    </div>
</div>

<?php
// Custom footer for this page to avoid duplicating elements
?>
</div> <!-- End of .main-content -->
</div> <!-- End of #content -->
</div><!-- End of #content-wrapper -->
</div><!-- End of #wrapper -->

<!-- Bootstrap 5 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom scripts for all pages-->
<script src="<?= BASE_URL ?>assets/js/script.js"></script>

</body>
</html>