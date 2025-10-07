<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka']);

$page_title = "Kenaikan Kelas";
$message = '';
$message_type = '';

// 1. Get active school year (destination)
$active_year_query = mysqli_query($conn, "SELECT id, tahun FROM tahun_pelajaran WHERE status = 'aktif'");
$active_year = mysqli_fetch_assoc($active_year_query);
$active_year_id = $active_year ? $active_year['id'] : null;

// 2. Get inactive school years (source)
$source_years_query = mysqli_query($conn, "SELECT id, tahun FROM tahun_pelajaran WHERE status = 'tidak aktif' ORDER BY tahun DESC");

// 3. Get all classes for destination mapping
$all_classes_query = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
$all_classes = [];
while ($row = mysqli_fetch_assoc($all_classes_query)) {
    $all_classes[] = $row;
}

// Handle class promotion processing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_promotion'])) {
    $source_year_id_post = (int)$_POST['source_year_id'];
    $class_mappings = $_POST['class_mappings'] ?? [];

    if (!$active_year_id) {
        $message = "Error: Tidak ada tahun ajaran tujuan (aktif) yang ditemukan.";
        $message_type = 'error';
    } elseif (empty($class_mappings)) {
        $message = "Peringatan: Tidak ada pemetaan kelas yang dipilih untuk diproses.";
        $message_type = 'warning';
    } else {
        $promoted_count = 0;
        $skipped_count = 0;

        mysqli_begin_transaction($conn);
        try {
            foreach ($class_mappings as $source_class_id => $destination_class_id) {
                if (empty($destination_class_id)) {
                    continue; // Skip if no destination class is selected
                }
                $source_class_id = (int)$source_class_id;
                $destination_class_id = (int)$destination_class_id;

                // Get all students from the source class in the source year
                $students_to_promote_query = "SELECT siswa_id FROM siswa_kelas WHERE kelas_id = $source_class_id AND tahun_pelajaran_id = $source_year_id_post";
                $students_result = mysqli_query($conn, $students_to_promote_query);

                while ($student = mysqli_fetch_assoc($students_result)) {
                    $siswa_id = $student['siswa_id'];

                    // Check if student is already enrolled in the active year
                    $check_enrollment_query = "SELECT id FROM siswa_kelas WHERE siswa_id = $siswa_id AND tahun_pelajaran_id = $active_year_id";
                    $check_result = mysqli_query($conn, $check_enrollment_query);

                    if (mysqli_num_rows($check_result) == 0) {
                        // Not enrolled, so promote them
                        $insert_query = "INSERT INTO siswa_kelas (siswa_id, kelas_id, tahun_pelajaran_id) VALUES ($siswa_id, $destination_class_id, $active_year_id)";
                        if (mysqli_query($conn, $insert_query)) {
                            $promoted_count++;
                        } else {
                            throw new Exception(mysqli_error($conn));
                        }
                    } else {
                        $skipped_count++;
                    }
                }
            }
            mysqli_commit($conn);
            $message = "Proses kenaikan kelas selesai. Berhasil menaikkan: $promoted_count siswa. Dilewati (sudah terdaftar): $skipped_count siswa.";
            $message_type = 'success';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Terjadi kesalahan saat proses kenaikan kelas: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get selected source year from URL to display the mapping table
$selected_source_year_id = isset($_GET['source_year_id']) ? (int)$_GET['source_year_id'] : null;
$source_classes = [];
if ($selected_source_year_id) {
    $source_classes_query = "
        SELECT DISTINCT k.id, k.nama_kelas, COUNT(sk.siswa_id) as total_siswa
        FROM kelas k
        JOIN siswa_kelas sk ON k.id = sk.kelas_id
        WHERE sk.tahun_pelajaran_id = $selected_source_year_id
        GROUP BY k.id, k.nama_kelas
        ORDER BY k.nama_kelas ASC
    ";
    $source_classes_result = mysqli_query($conn, $source_classes_query);
    while ($row = mysqli_fetch_assoc($source_classes_result)) {
        $source_classes[] = $row;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';
?>

<h1 class="h3 mb-4 text-gray-800">Proses Kenaikan Kelas</h1>

<?php if ($message): ?>
<script>
    Swal.fire({
        icon: '<?= $message_type ?>',
        title: '<?= ucfirst($message_type) ?>',
        text: '<?= addslashes($message) ?>',
        timer: 5000,
        showConfirmButton: true
    });
</script>
<?php endif; ?>

<?php if (!$active_year): ?>
    <div class="alert alert-danger">
        <strong>Error:</strong> Tidak ada tahun pelajaran yang aktif. Silakan aktifkan satu di <a href="tahun_pelajaran.php">Manajemen Tahun Pelajaran</a> untuk melanjutkan.
    </div>
<?php else: ?>
    <!-- Step 1: Select Source Year -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Langkah 1: Pilih Tahun Ajaran Sumber</h6>
        </div>
        <div class="card-body">
            <form action="" method="GET">
                <div class="row align-items-end">
                    <div class="col-md-5">
                        <label for="source_year_id" class="form-label">Salin pendaftaran dari:</label>
                        <select name="source_year_id" id="source_year_id" class="form-select" required>
                            <option value="">-- Pilih Tahun Ajaran --</option>
                            <?php while ($year = mysqli_fetch_assoc($source_years_query)): ?>
                                <option value="<?= $year['id'] ?>" <?= ($selected_source_year_id == $year['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($year['tahun']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Ke tahun ajaran aktif:</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($active_year['tahun']) ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-info w-100">Tampilkan Kelas</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Step 2: Map Classes and Process -->
    <?php if ($selected_source_year_id && !empty($source_classes)): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Langkah 2: Petakan Kelas Tujuan dan Proses</h6>
        </div>
        <div class="card-body">
            <form action="" method="POST">
                <input type="hidden" name="source_year_id" value="<?= $selected_source_year_id ?>">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Kelas Sumber</th>
                                <th>Jumlah Siswa</th>
                                <th>Naik ke Kelas Tujuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($source_classes as $source_class): ?>
                            <tr>
                                <td><?= htmlspecialchars($source_class['nama_kelas']) ?></td>
                                <td><?= $source_class['total_siswa'] ?></td>
                                <td>
                                    <select name="class_mappings[<?= $source_class['id'] ?>]" class="form-select">
                                        <option value="">-- Pilih Kelas Tujuan --</option>
                                        <?php foreach ($all_classes as $dest_class): ?>
                                            <option value="<?= $dest_class['id'] ?>"><?= htmlspecialchars($dest_class['nama_kelas']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" name="process_promotion" class="btn btn-success btn-lg">
                        <i class="fa fa-check-double"></i> Proses Kenaikan Kelas
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php elseif ($selected_source_year_id): ?>
        <div class="alert alert-info">Tidak ada data pendaftaran siswa yang ditemukan untuk tahun ajaran sumber yang dipilih.</div>
    <?php endif; ?>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>