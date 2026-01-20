<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi hanya untuk guru
authorize_role(['guru']);

$page_title = "Manajemen Absensi Kelas";
$message = '';
$message_type = '';

// Dapatkan guru_id dari user_id session
$user_id = $_SESSION['user_id'];
$guru_info_query = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
$guru_info = mysqli_fetch_assoc($guru_info_query);
$guru_id = $guru_info ? $guru_info['id'] : null;

// Dapatkan kelas yang diampu oleh guru sebagai wali kelas
$kelas_ids = [];
if ($guru_id) {
    $kelas_query = mysqli_query($conn, "SELECT id FROM kelas WHERE wali_kelas_id = $guru_id");
    while ($kelas = mysqli_fetch_assoc($kelas_query)) {
        $kelas_ids[] = $kelas['id'];
    }
}

// Ambil tahun pelajaran aktif
$active_year_query = mysqli_query($conn, "SELECT id FROM tahun_pelajaran WHERE status = 'aktif'");
$active_year = mysqli_fetch_assoc($active_year_query);
$active_year_id = $active_year ? $active_year['id'] : null;

// Ambil data siswa dari kelas yang diampu guru untuk dropdown
$siswa_list_query_string = "
    SELECT s.id, s.nama_siswa, k.nama_kelas
    FROM siswa s
    JOIN siswa_kelas sk ON s.id = sk.siswa_id
    JOIN kelas k ON sk.kelas_id = k.id
    WHERE sk.tahun_pelajaran_id = " . ($active_year_id ?? 0) . "
";
if (!empty($kelas_ids)) {
    $siswa_list_query_string .= " AND sk.kelas_id IN (" . implode(',', $kelas_ids) . ")";
} else {
    $siswa_list_query_string .= " AND 1=0"; // Jika tidak ada kelas, jangan tampilkan siswa
}
$siswa_list_query_string .= " ORDER BY s.nama_siswa ASC";
$siswa_list_result = mysqli_query($conn, $siswa_list_query_string);

// Proses Aksi (Tambah Manual)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_manual'])) {
    $siswa_id = (int)$_POST['siswa_id'];
    $tanggal = $_POST['tanggal'];
    $status = $_POST['status'];

    // Validasi: pastikan guru hanya bisa input untuk siswanya
    $is_his_student_query = "SELECT s.id FROM siswa s JOIN siswa_kelas sk ON s.id = sk.siswa_id WHERE s.id = $siswa_id AND sk.kelas_id IN (" . (!empty($kelas_ids) ? implode(',', $kelas_ids) : '0') . ") AND sk.tahun_pelajaran_id = " . ($active_year_id ?? 0);
    $validation_result = mysqli_query($conn, $is_his_student_query);

    if (mysqli_num_rows($validation_result) > 0) {
        $check_query = "SELECT id FROM absensi WHERE siswa_id = $siswa_id AND tanggal = '$tanggal'";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            $message = "Siswa ini sudah memiliki catatan absensi pada tanggal tersebut.";
            $message_type = 'error';
        } else {
            $query = "INSERT INTO absensi (siswa_id, tanggal, status) VALUES ($siswa_id, '$tanggal', '$status')";
            if (mysqli_query($conn, $query)) {
                $message = "Absensi manual berhasil ditambahkan!";
                $message_type = 'success';
            } else {
                $message = "Gagal menambahkan absensi: " . mysqli_error($conn);
                $message_type = 'error';
            }
        }
    } else {
        $message = "Error: Anda tidak memiliki hak untuk mengelola siswa ini.";
        $message_type = 'error';
    }
}

// Logika Filter
$filter_start_date = $_GET['start_date'] ?? date('Y-m-d');
$filter_end_date = $_GET['end_date'] ?? date('Y-m-d');
$filter_search = $_GET['search'] ?? '';

$query = "
    SELECT
        a.id, s.nama_siswa, k.nama_kelas, a.tanggal, a.status, a.jam_masuk
    FROM absensi a
    JOIN siswa s ON a.siswa_id = s.id
    JOIN siswa_kelas sk ON s.id = sk.siswa_id
    JOIN kelas k ON sk.kelas_id = k.id
";

$where_clauses = [
    "a.tanggal BETWEEN '{$filter_start_date}' AND '{$filter_end_date}'",
    "sk.tahun_pelajaran_id = " . ($active_year_id ?? 0)
];

if (!empty($kelas_ids)) {
    $where_clauses[] = "k.id IN (" . implode(',', $kelas_ids) . ")";
} else {
    $where_clauses[] = "1=0"; // Jika guru tidak punya kelas, jangan tampilkan apa-apa
}

if (!empty($filter_search)) {
    $sanitized_search = mysqli_real_escape_string($conn, $filter_search);
    $where_clauses[] = "s.nama_siswa LIKE '%{$sanitized_search}%'";
}

$query .= " WHERE " . implode(' AND ', $where_clauses);
$query .= " ORDER BY a.tanggal DESC, s.nama_siswa ASC";
$result = mysqli_query($conn, $query);

// Kueri untuk rekapitulasi
$rekap_data = [
    'Hadir' => 0, 'Terlambat' => 0, 'Sakit' => 0, 'Izin' => 0, 'Tanpa Keterangan' => 0
];
if (!empty($kelas_ids)) { // Hanya jalankan jika guru punya kelas
    $rekap_query_string = "
        SELECT status, COUNT(id) as total
        FROM ({$query}) as filtered_absensi
        GROUP BY status
    ";
    $rekap_result = mysqli_query($conn, $rekap_query_string);
    while ($row = mysqli_fetch_assoc($rekap_result)) {
        if (array_key_exists($row['status'], $rekap_data)) {
            $rekap_data[$row['status']] = $row['total'];
        }
    }
}

// Sertakan header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Manajemen Absensi Kelas Anda</h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="fa fa-arrow-left"></i> Kembali ke Dasbor
    </a>
</div>

<?php if ($message): ?>
<script>
    Swal.fire({
        icon: '<?= $message_type ?>',
        title: '<?= ucfirst($message_type) ?>',
        text: '<?= addslashes($message) ?>',
        timer: 3000,
        showConfirmButton: false
    });
</script>
<?php endif; ?>

<!-- Form Filter -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Data Absensi</h6>
    </div>
    <div class="card-body">
        <form action="" method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="start_date" class="form-label">Dari Tanggal</label>
                <input type="date" name="start_date" id="start_date" class="form-control" value="<?= htmlspecialchars($filter_start_date) ?>">
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">Sampai Tanggal</label>
                <input type="date" name="end_date" id="end_date" class="form-control" value="<?= htmlspecialchars($filter_end_date) ?>">
            </div>
            <div class="col-md-4">
                <label for="search" class="form-label">Cari Nama Siswa</label>
                <input type="text" name="search" id="search" class="form-control" value="<?= htmlspecialchars($filter_search) ?>" placeholder="Masukkan nama...">
            </div>
            <div class="col-md-12 text-end mt-3">
                <button type="submit" class="btn btn-info">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Tombol Aksi dan Ekspor -->
<div class="d-flex justify-content-between mb-3">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal" <?= (!$active_year_id || empty($kelas_ids)) ? 'disabled' : '' ?>>
        <i class="fa fa-plus"></i> Input Absensi Manual
    </button>
    <a href="export_absensi.php?start_date=<?= htmlspecialchars($filter_start_date) ?>&end_date=<?= htmlspecialchars($filter_end_date) ?>&search=<?= htmlspecialchars($filter_search) ?>" class="btn btn-success" <?= (!$active_year_id || empty($kelas_ids)) ? 'disabled' : '' ?>>
        <i class="fa fa-file-csv"></i> Ekspor ke CSV
    </a>
</div>

<!-- Rekapitulasi -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-2">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Hadir</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $rekap_data['Hadir'] ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-user-check fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-2">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Terlambat</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $rekap_data['Terlambat'] ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-6 mb-2">
        <div class="card border-left-secondary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Sakit</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $rekap_data['Sakit'] ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-medkit fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-6 mb-2">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Izin</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $rekap_data['Izin'] ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-info-circle fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-6 mb-2">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Alfa</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $rekap_data['Tanpa Keterangan'] ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-user-times fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Tabel Data -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Absensi</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Nama Siswa</th>
                        <th>Status</th>
                        <th>Jam Masuk</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($active_year_id && !empty($kelas_ids) && mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($row['tanggal'])) ?></td>
                            <td><?= htmlspecialchars($row['nama_siswa']) ?></td>
                            <td>
                                <?php
                                $status_class = '';
                                switch ($row['status']) {
                                    case 'Hadir': $status_class = 'bg-success text-white'; break;
                                    case 'Terlambat': $status_class = 'bg-warning text-dark'; break;
                                    case 'Sakit': $status_class = 'bg-secondary text-white'; break;
                                    case 'Izin': $status_class = 'bg-info text-dark'; break;
                                    case 'Tanpa Keterangan': $status_class = 'bg-danger text-white'; break;
                                }
                                ?>
                                <span class="badge <?= $status_class ?>"><?= htmlspecialchars($row['status']) ?></span>
                            </td>
                            <td><?= ($row['status'] == 'Hadir' || $row['status'] == 'Terlambat') ? htmlspecialchars($row['jam_masuk']) : '-' ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">Tidak ada data absensi untuk filter yang dipilih atau Anda bukan wali kelas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Absensi Manual -->
<div class="modal fade" id="tambahModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Input Absensi Manual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Siswa</label>
                        <select class="form-select" name="siswa_id" required>
                            <option value="">-- Pilih Siswa --</option>
                            <?php mysqli_data_seek($siswa_list_result, 0); ?>
                            <?php while($siswa = mysqli_fetch_assoc($siswa_list_result)): ?>
                                <option value="<?= $siswa['id'] ?>"><?= htmlspecialchars($siswa['nama_siswa']) . ' (' . htmlspecialchars($siswa['nama_kelas']) . ')' ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-control" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" required>
                            <option value="Sakit">Sakit</option>
                            <option value="Izin">Izin</option>
                            <option value="Tanpa Keterangan">Tanpa Keterangan</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_manual" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>