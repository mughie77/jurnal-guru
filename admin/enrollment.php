<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi untuk admin dan waka
authorize_role(['admin', 'waka']);

$page_title = "Manajemen Pendaftaran Kelas";
$message = '';
$message_type = '';

// Ambil tahun pelajaran aktif
$active_year_query = mysqli_query($conn, "SELECT id, tahun FROM tahun_pelajaran WHERE status = 'aktif'");
$active_year = mysqli_fetch_assoc($active_year_query);
$active_year_id = $active_year ? $active_year['id'] : null;
$active_year_name = $active_year ? $active_year['tahun'] : 'Tidak Ada';


// Proses Aksi (Tambah/Hapus Siswa dari Kelas)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $active_year_id) {
    // Aksi: Tambah Siswa ke Kelas
    if (isset($_POST['tambah_siswa'])) {
        $kelas_id = (int)$_POST['kelas_id'];
        $siswa_id = (int)$_POST['siswa_id'];

        // Cek dulu apakah siswa sudah terdaftar di kelas lain pada tahun ajaran ini
        $check_query = "SELECT id FROM siswa_kelas WHERE siswa_id = $siswa_id AND tahun_pelajaran_id = $active_year_id";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            $message = "Gagal: Siswa ini sudah terdaftar di kelas lain pada tahun ajaran ini.";
            $message_type = 'error';
        } else {
            $query = "INSERT INTO siswa_kelas (siswa_id, kelas_id, tahun_pelajaran_id) VALUES ($siswa_id, $kelas_id, $active_year_id)";
            if (mysqli_query($conn, $query)) {
                $message = "Siswa berhasil ditambahkan ke kelas!";
                $message_type = 'success';
            } else {
                $message = "Gagal menambahkan siswa: " . mysqli_error($conn);
                $message_type = 'error';
            }
        }
    }
    // Aksi: Hapus Siswa dari Kelas
    elseif (isset($_POST['hapus_siswa'])) {
        $enrollment_id = (int)$_POST['enrollment_id'];
        $query = "DELETE FROM siswa_kelas WHERE id = $enrollment_id";
        if (mysqli_query($conn, $query)) {
            $message = "Siswa berhasil dihapus dari kelas!";
            $message_type = 'success';
        } else {
            $message = "Gagal menghapus siswa: " . mysqli_error($conn);
            $message_type = 'error';
        }
    }
}

// Ambil semua data kelas
$kelas_query = "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC";
$kelas_result = mysqli_query($conn, $kelas_query);

// Ambil siswa yang belum terdaftar di kelas manapun di tahun ajaran aktif
$unassigned_students = [];
if ($active_year_id) {
    $unassigned_query = "
        SELECT s.id, s.nama_siswa
        FROM siswa s
        LEFT JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = $active_year_id
        WHERE sk.id IS NULL
        ORDER BY s.nama_siswa ASC
    ";
    $unassigned_result = mysqli_query($conn, $unassigned_query);
    while($row = mysqli_fetch_assoc($unassigned_result)) {
        $unassigned_students[] = $row;
    }
}


require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';
?>

<h1 class="h3 mb-4 text-gray-800">Manajemen Pendaftaran Kelas</h1>
<h5 class="mb-4">Tahun Pelajaran Aktif: <span class="badge bg-success"><?= htmlspecialchars($active_year_name) ?></span></h5>


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

<?php if (!$active_year_id): ?>
    <div class="alert alert-warning">
        Tidak ada tahun pelajaran yang aktif. Silakan aktifkan satu di <a href="tahun_pelajaran.php">Manajemen Tahun Pelajaran</a> untuk mengelola pendaftaran kelas.
    </div>
<?php else: ?>
<div class="row">
    <?php while ($kelas = mysqli_fetch_assoc($kelas_result)): ?>
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><?= htmlspecialchars($kelas['nama_kelas']) ?></h6>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tambahSiswaModal-<?= $kelas['id'] ?>">
                        <i class="fa fa-plus"></i> Tambah Siswa
                    </button>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <?php
                        $enrolled_query = "
                            SELECT sk.id as enrollment_id, s.nama_siswa
                            FROM siswa_kelas sk
                            JOIN siswa s ON sk.siswa_id = s.id
                            WHERE sk.kelas_id = {$kelas['id']} AND sk.tahun_pelajaran_id = $active_year_id
                            ORDER BY s.nama_siswa ASC
                        ";
                        $enrolled_result = mysqli_query($conn, $enrolled_query);
                        if (mysqli_num_rows($enrolled_result) > 0):
                            while($student = mysqli_fetch_assoc($enrolled_result)):
                        ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($student['nama_siswa']) ?>
                                <form action="" method="POST" class="d-inline">
                                    <input type="hidden" name="enrollment_id" value="<?= $student['enrollment_id'] ?>">
                                    <button type="submit" name="hapus_siswa" class="btn btn-danger btn-sm" title="Hapus dari kelas">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </form>
                            </li>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <li class="list-group-item text-center text-muted">Belum ada siswa terdaftar.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Siswa ke Kelas -->
        <div class="modal fade" id="tambahSiswaModal-<?= $kelas['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Siswa ke Kelas <?= htmlspecialchars($kelas['nama_kelas']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="" method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="kelas_id" value="<?= $kelas['id'] ?>">
                            <div class="mb-3">
                                <label class="form-label">Pilih Siswa</label>
                                <select class="form-select" name="siswa_id" required>
                                    <option value="">-- Siswa yang Belum Terdaftar --</option>
                                    <?php foreach($unassigned_students as $student): ?>
                                        <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['nama_siswa']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if(empty($unassigned_students)): ?>
                                    <div class="form-text text-warning">Semua siswa sudah terdaftar di sebuah kelas untuk tahun ajaran ini.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" name="tambah_siswa" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
</div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>