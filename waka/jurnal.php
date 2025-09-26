<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi untuk waka dan admin
authorize_role(['waka', 'admin']);

$page_title = "Laporan Jurnal Mengajar";

// Ambil data untuk filter dropdowns
$gurus = mysqli_query($conn, "SELECT guru.id, users.nama_lengkap FROM guru JOIN users ON guru.user_id = users.id ORDER BY nama_lengkap");
$mapels = mysqli_query($conn, "SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel");
$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");
$tahuns = mysqli_query($conn, "SELECT id, tahun FROM tahun_pelajaran ORDER BY tahun DESC");

// Logika Filter
$where_clauses = [];
$filter_params = []; // Untuk link ekspor PDF

if (!empty($_GET['start_date'])) {
    $where_clauses[] = "jurnal.tanggal >= '" . mysqli_real_escape_string($conn, $_GET['start_date']) . "'";
    $filter_params['start_date'] = $_GET['start_date'];
}
if (!empty($_GET['end_date'])) {
    $where_clauses[] = "jurnal.tanggal <= '" . mysqli_real_escape_string($conn, $_GET['end_date']) . "'";
    $filter_params['end_date'] = $_GET['end_date'];
}
if (!empty($_GET['guru_id'])) {
    $where_clauses[] = "jurnal.guru_id = " . (int)$_GET['guru_id'];
    $filter_params['guru_id'] = $_GET['guru_id'];
}
if (!empty($_GET['mapel_id'])) {
    $where_clauses[] = "jurnal.mapel_id = " . (int)$_GET['mapel_id'];
    $filter_params['mapel_id'] = $_GET['mapel_id'];
}
if (!empty($_GET['kelas_id'])) {
    $where_clauses[] = "jurnal.kelas_id = " . (int)$_GET['kelas_id'];
    $filter_params['kelas_id'] = $_GET['kelas_id'];
}
// Filter tahun pelajaran: default ke tahun aktif jika tidak ada yang dipilih
$selected_tahun_id = $_GET['tahun_id'] ?? null;

if ($selected_tahun_id === null && $active_tahun_id) {
    // Default ke tahun aktif pada tampilan awal
    $where_clauses[] = "jurnal.tahun_pelajaran_id = " . $active_tahun_id;
    $filter_params['tahun_id'] = $active_tahun_id;
    $selected_for_dropdown = $active_tahun_id;
} elseif (!empty($selected_tahun_id)) {
    // Jika tahun spesifik dipilih
    $where_clauses[] = "jurnal.tahun_pelajaran_id = " . (int)$selected_tahun_id;
    $filter_params['tahun_id'] = $selected_tahun_id;
    $selected_for_dropdown = (int)$selected_tahun_id;
} else {
    // Jika "Semua Tahun" dipilih (tahun_id adalah string kosong)
    $selected_for_dropdown = '';
}

$sql = "SELECT jurnal.*, users.nama_lengkap, mata_pelajaran.nama_mapel, kelas.nama_kelas, tahun_pelajaran.tahun
        FROM jurnal
        JOIN guru ON jurnal.guru_id = guru.id
        JOIN users ON guru.user_id = users.id
        JOIN mata_pelajaran ON jurnal.mapel_id = mata_pelajaran.id
        JOIN kelas ON jurnal.kelas_id = kelas.id
        JOIN tahun_pelajaran ON jurnal.tahun_pelajaran_id = tahun_pelajaran.id";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY jurnal.tanggal DESC, jurnal.created_at DESC";

$result = mysqli_query($conn, $sql);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_waka.php';
?>

<h1 class="h3 mb-4 text-gray-800">Laporan Jurnal Mengajar</h1>

<!-- Filter Form -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-filter"></i> Filter Laporan Jurnal</h6>
    </div>
    <div class="card-body">
        <form action="" method="GET">
            <div class="row">
                <div class="col-md-3">
                    <label for="start_date">Dari Tanggal</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date">Sampai Tanggal</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label for="guru_id">Guru</label>
                    <select name="guru_id" id="guru_id" class="form-select">
                        <option value="">Semua Guru</option>
                        <?php mysqli_data_seek($gurus, 0); while($g = mysqli_fetch_assoc($gurus)): ?>
                            <option value="<?= $g['id'] ?>" <?= (($_GET['guru_id'] ?? '') == $g['id']) ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_lengkap']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="mapel_id">Mata Pelajaran</label>
                    <select name="mapel_id" id="mapel_id" class="form-select">
                        <option value="">Semua Mapel</option>
                        <?php mysqli_data_seek($mapels, 0); while($m = mysqli_fetch_assoc($mapels)): ?>
                            <option value="<?= $m['id'] ?>" <?= (($_GET['mapel_id'] ?? '') == $m['id']) ? 'selected' : '' ?>><?= htmlspecialchars($m['nama_mapel']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-3">
                    <label for="kelas_id">Kelas</label>
                    <select name="kelas_id" id="kelas_id" class="form-select">
                        <option value="">Semua Kelas</option>
                        <?php mysqli_data_seek($kelases, 0); while($k = mysqli_fetch_assoc($kelases)): ?>
                            <option value="<?= $k['id'] ?>" <?= (($_GET['kelas_id'] ?? '') == $k['id']) ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="tahun_id">Tahun Pelajaran</label>
                    <select name="tahun_id" id="tahun_id" class="form-select">
                        <option value="">Semua Tahun</option>
                        <?php mysqli_data_seek($tahuns, 0); while($t = mysqli_fetch_assoc($tahuns)): ?>
                            <option value="<?= $t['id'] ?>" <?= ($selected_for_dropdown == $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['tahun']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="jurnal.php" class="btn btn-secondary ms-2">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Data Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Hasil Data Jurnal</h6>
        <a href="../admin/export_csv.php?<?= http_build_query($filter_params) ?>" class="btn btn-success" target="_blank"><i class="fa fa-file-excel"></i> Ekspor ke CSV</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Guru</th>
                        <th>Mapel</th>
                        <th>Kelas</th>
                        <th>Jam Ke-</th>
                        <th>Materi</th>
                        <th>Absensi (H/S/I/A)</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d-m-Y', strtotime($row['tanggal']))) ?></td>
                            <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                            <td><?= htmlspecialchars($row['nama_mapel']) ?></td>
                            <td><?= htmlspecialchars($row['nama_kelas']) ?></td>
                            <td><?= htmlspecialchars($row['jam_ke']) ?></td>
                            <td><?= nl2br(htmlspecialchars($row['materi'])) ?></td>
                            <td><?= "{$row['jml_hadir']}/{$row['jml_sakit']}/{$row['jml_izin']}/{$row['jml_alfa']}" ?></td>
                            <td><?= nl2br(htmlspecialchars($row['keterangan'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Tidak ada data jurnal yang ditemukan.</td>
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