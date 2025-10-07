<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi hanya untuk admin
authorize_role(['admin']);

$page_title = "Manajemen Absensi";
$message = '';
$message_type = '';

// Ambil tahun pelajaran aktif
$active_year_query = mysqli_query($conn, "SELECT id FROM tahun_pelajaran WHERE status = 'aktif'");
$active_year = mysqli_fetch_assoc($active_year_query);
$active_year_id = $active_year ? $active_year['id'] : null;

// Ambil data siswa yang terdaftar di tahun ajaran aktif untuk dropdown
$siswa_list_query = "
    SELECT s.id, s.nama_siswa, k.nama_kelas
    FROM siswa s
    JOIN siswa_kelas sk ON s.id = sk.siswa_id
    JOIN kelas k ON sk.kelas_id = k.id
    WHERE sk.tahun_pelajaran_id = " . ($active_year_id ?? 0) . "
    ORDER BY s.nama_siswa ASC
";
$siswa_list_result = mysqli_query($conn, $siswa_list_query);


// Ambil data kelas untuk dropdown filter
$kelas_list_query = "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC";
$kelas_list_result = mysqli_query($conn, $kelas_list_query);

// Proses Aksi (Tambah Manual)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_manual'])) {
    $siswa_id = (int)$_POST['siswa_id'];
    $tanggal = $_POST['tanggal'];
    $status = $_POST['status'];

    // Cek apakah sudah ada absensi untuk siswa ini di tanggal yang sama
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
}

// Logika Filter
$filter_tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$filter_kelas_id = $_GET['kelas_id'] ?? '';

$query = "
    SELECT
        a.id,
        s.nama_siswa,
        k.nama_kelas,
        a.tanggal,
        a.status,
        a.jam_masuk
    FROM absensi a
    JOIN siswa s ON a.siswa_id = s.id
    LEFT JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = " . ($active_year_id ?? 0) . "
    LEFT JOIN kelas k ON sk.kelas_id = k.id
    WHERE a.tanggal = '$filter_tanggal'
";

if (!empty($filter_kelas_id)) {
    $query .= " AND k.id = " . (int)$filter_kelas_id;
}

$query .= " ORDER BY k.nama_kelas, s.nama_siswa ASC";
$result = mysqli_query($conn, $query);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';
?>

<h1 class="h3 mb-4 text-gray-800">Manajemen Absensi Siswa</h1>

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

<!-- Tombol dan Filter -->
<div class="d-flex justify-content-between mb-3">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal" <?= !$active_year_id ? 'disabled' : '' ?>>
        <i class="fa fa-plus"></i> Input Absensi Manual
    </button>
    <form action="" method="GET" class="d-flex">
        <input type="date" name="tanggal" class="form-control me-2" value="<?= htmlspecialchars($filter_tanggal) ?>">
        <select name="kelas_id" class="form-select me-2">
            <option value="">Semua Kelas</option>
            <?php mysqli_data_seek($kelas_list_result, 0); ?>
            <?php while ($kelas = mysqli_fetch_assoc($kelas_list_result)): ?>
                <option value="<?= $kelas['id'] ?>" <?= ($filter_kelas_id == $kelas['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($kelas['nama_kelas']) ?>
                </option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="btn btn-info">Filter</button>
    </form>
</div>

<?php if (!$active_year_id): ?>
    <div class="alert alert-warning">
        Tidak ada tahun pelajaran yang aktif. Fitur absensi memerlukan tahun pelajaran aktif untuk menentukan kelas siswa.
    </div>
<?php endif; ?>

<!-- Tabel Data -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Absensi Tanggal: <?= date('d M Y', strtotime($filter_tanggal)) ?></h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nama Siswa</th>
                        <th>Kelas (Tahun Aktif)</th>
                        <th>Status</th>
                        <th>Jam Masuk</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($active_year_id && mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['nama_siswa']) ?></td>
                            <td><?= htmlspecialchars($row['nama_kelas'] ?? '<i>Tidak Terdaftar</i>') ?></td>
                            <td>
                                <?php
                                $status_class = '';
                                switch ($row['status']) {
                                    case 'Hadir': $status_class = 'bg-success text-white'; break;
                                    case 'Sakit': $status_class = 'bg-warning text-dark'; break;
                                    case 'Izin': $status_class = 'bg-info text-dark'; break;
                                    case 'Tanpa Keterangan': $status_class = 'bg-danger text-white'; break;
                                }
                                ?>
                                <span class="badge <?= $status_class ?>"><?= htmlspecialchars($row['status']) ?></span>
                            </td>
                            <td><?= $row['status'] == 'Hadir' ? htmlspecialchars($row['jam_masuk']) : '-' ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">Tidak ada data absensi untuk tanggal dan filter yang dipilih.</td>
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
                        <label class="form-label">Siswa (Tahun Ajaran Aktif)</label>
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