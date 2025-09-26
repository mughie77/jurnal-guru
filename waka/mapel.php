<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi untuk waka dan admin
authorize_role(['waka', 'admin']);

$page_title = "Data Mata Pelajaran";

// Ambil semua data mata pelajaran
$result = mysqli_query($conn, "SELECT * FROM mata_pelajaran ORDER BY nama_mapel ASC");

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_waka.php';
?>

<h1 class="h3 mb-4 text-gray-800">Data Mata Pelajaran</h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Mata Pelajaran</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nama Mata Pelajaran</th>
                        <th>Kode Mapel</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['nama_mapel']) ?></td>
                            <td><?= htmlspecialchars($row['kode_mapel']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" class="text-center">Belum ada data mata pelajaran.</td>
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