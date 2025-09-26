<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi untuk waka dan admin
authorize_role(['waka', 'admin']);

$page_title = "Data Guru";

// Ambil semua data guru untuk ditampilkan
$result = mysqli_query($conn, "SELECT guru.*, users.nama_lengkap, users.username FROM guru JOIN users ON guru.user_id = users.id ORDER BY users.nama_lengkap ASC");

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_waka.php';
?>

<h1 class="h3 mb-4 text-gray-800">Data Guru</h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Guru</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>NIP / Username</th>
                        <th>Nama Lengkap</th>
                        <th>Alamat</th>
                        <th>No. Telp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['nip']) ?></td>
                            <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                            <td><?= htmlspecialchars($row['alamat']) ?></td>
                            <td><?= htmlspecialchars($row['no_telp']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">Belum ada data guru.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>