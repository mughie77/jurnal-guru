<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi hanya untuk guru
authorize_role(['guru', 'admin']);

$page_title = "Riwayat Jurnal Saya";

// Dapatkan guru_id dari user_id yang login
$user_id = $_SESSION['user_id'];
$guru_result = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
if(mysqli_num_rows($guru_result) == 0) {
    die("Error: Data guru tidak ditemukan untuk user ini. Silakan hubungi admin.");
}
$guru_id = mysqli_fetch_assoc($guru_result)['id'];

// Logika Filter
$where_clause = "jurnal.guru_id = $guru_id";
if (!empty($_GET['tanggal'])) {
    $tanggal_filter = mysqli_real_escape_string($conn, $_GET['tanggal']);
    $where_clause .= " AND jurnal.tanggal = '$tanggal_filter'";
}

$sql = "SELECT jurnal.*, mata_pelajaran.nama_mapel, kelas.nama_kelas
        FROM jurnal
        JOIN mata_pelajaran ON jurnal.mapel_id = mata_pelajaran.id
        JOIN kelas ON jurnal.kelas_id = kelas.id
        WHERE $where_clause
        ORDER BY jurnal.tanggal DESC, jurnal.created_at DESC";

$result = mysqli_query($conn, $sql);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Override sidebar style untuk dashboard guru */
.main-content { margin-left: 0; }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Riwayat Jurnal Mengajar Saya</h1>
        <a href="<?= BASE_URL ?>guru/index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Kembali ke Dashboard</a>
    </div>

    <!-- Filter Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-search"></i> Cari Jurnal Berdasarkan Tanggal</h6>
        </div>
        <div class="card-body">
            <form action="" method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="tanggal" class="form-label">Pilih Tanggal</label>
                    <input type="date" id="tanggal" name="tanggal" class="form-control" value="<?= htmlspecialchars($_GET['tanggal'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">Cari</button>
                    <a href="riwayat.php" class="btn btn-secondary ms-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Jurnal yang Pernah Diisi</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Mata Pelajaran</th>
                            <th>Kelas</th>
                            <th>Jam Ke-</th>
                            <th>Materi Pembahasan</th>
                            <th>Kehadiran (H/S/I/A)</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('d-m-Y', strtotime($row['tanggal']))) ?></td>
                                <td><?= htmlspecialchars($row['nama_mapel']) ?></td>
                                <td><?= htmlspecialchars($row['nama_kelas']) ?></td>
                                <td><?= htmlspecialchars($row['jam_ke']) ?></td>
                                <td style="min-width: 250px;"><?= nl2br(htmlspecialchars($row['materi'])) ?></td>
                                <td><?= "{$row['jml_hadir']}/{$row['jml_sakit']}/{$row['jml_izin']}/{$row['jml_alfa']}" ?></td>
                                <td style="min-width: 200px;"><?= nl2br(htmlspecialchars($row['keterangan'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">Tidak ada riwayat jurnal yang ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>