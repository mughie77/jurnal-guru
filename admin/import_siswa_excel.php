<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

authorize_role(['admin']);

$page_title = "Import Siswa dari Excel";
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];
    $kelas_id = (int)$_POST['kelas_id'];
    $tahun_id = $active_tahun_id;

    if (!$tahun_id) {
        $message = "Gagal: Tidak ada tahun pelajaran aktif.";
        $message_type = 'error';
    } elseif ($xlsx = SimpleXLSX::parse($file)) {
        $rows = $xlsx->rows();
        array_shift($rows); // Skip header

        $success_count = 0;
        $error_count = 0;
        $errors = [];

        mysqli_begin_transaction($conn);
        try {
            // Prepare statements
            $stmt_siswa = mysqli_prepare($conn, "INSERT INTO siswa (nis, nama_siswa, jenis_kelamin) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nama_siswa = VALUES(nama_siswa), jenis_kelamin = VALUES(jenis_kelamin)");
            $stmt_link = mysqli_prepare($conn, "INSERT INTO siswa_kelas (siswa_id, kelas_id, tahun_pelajaran_id) VALUES (?, ?, ?)");

            foreach ($rows as $index => $row) {
                if (empty($row[0]) || empty($row[1])) continue; // Skip if NIS or Name is empty

                $nis = $row[0];
                $nama_siswa = $row[1];
                $jk = strtoupper($row[2]) == 'P' ? 'P' : 'L';

                // 1. Insert/Update Siswa
                mysqli_stmt_bind_param($stmt_siswa, "sss", $nis, $nama_siswa, $jk);
                mysqli_stmt_execute($stmt_siswa);

                // Get ID (could be from insert or existing)
                $res_id = mysqli_query($conn, "SELECT id FROM siswa WHERE nis = '".mysqli_real_escape_string($conn, $nis)."'");
                $siswa_id = mysqli_fetch_assoc($res_id)['id'];

                // 2. Link to Class for this school year
                // Cek dulu apakah sudah ada linknya
                $check_link = mysqli_query($conn, "SELECT id FROM siswa_kelas WHERE siswa_id = $siswa_id AND tahun_pelajaran_id = $tahun_id");
                if (mysqli_num_rows($check_link) == 0) {
                    mysqli_stmt_bind_param($stmt_link, "iii", $siswa_id, $kelas_id, $tahun_id);
                    mysqli_stmt_execute($stmt_link);
                } else {
                    // Update if already exists (move to this class)
                    mysqli_query($conn, "UPDATE siswa_kelas SET kelas_id = $kelas_id WHERE siswa_id = $siswa_id AND tahun_pelajaran_id = $tahun_id");
                }

                $success_count++;
            }

            // 3. Update Class Counts
            $q_count_L = mysqli_query($conn, "SELECT COUNT(*) as jml FROM siswa_kelas JOIN siswa ON siswa_kelas.siswa_id = siswa.id WHERE kelas_id = $kelas_id AND tahun_pelajaran_id = $tahun_id AND jenis_kelamin = 'L'");
            $jml_L = mysqli_fetch_assoc($q_count_L)['jml'];
            $q_count_P = mysqli_query($conn, "SELECT COUNT(*) as jml FROM siswa_kelas JOIN siswa ON siswa_kelas.siswa_id = siswa.id WHERE kelas_id = $kelas_id AND tahun_pelajaran_id = $tahun_id AND jenis_kelamin = 'P'");
            $jml_P = mysqli_fetch_assoc($q_count_P)['jml'];

            mysqli_query($conn, "UPDATE kelas SET jumlah_siswa_L = $jml_L, jumlah_siswa_P = $jml_P WHERE id = $kelas_id");

            mysqli_stmt_close($stmt_siswa);
            mysqli_stmt_close($stmt_link);
            mysqli_commit($conn);

            $message = "Berhasil mengimpor $success_count siswa ke kelas ini.";
            $message_type = 'success';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Gagal mengimpor: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "Gagal membaca file Excel: " . SimpleXLSX::parseError();
        $message_type = 'error';
    }
}

// Ambil data kelas untuk dropdown
$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';
?>

<h1 class="h3 mb-4 text-gray-800">Import Siswa dari Excel</h1>

<?php if ($message): ?>
<div class="alert alert-<?= ($message_type == 'success') ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
    <?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Upload File Excel (.xlsx)</h6>
    </div>
    <div class="card-body">
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Pilih Kelas Tujuan</label>
                <select name="kelas_id" class="form-select" required>
                    <option value="">-- Pilih Kelas --</option>
                    <?php while($k = mysqli_fetch_assoc($kelases)): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                    <?php endwhile; ?>
                </select>
                <small class="text-muted">Impor akan dilakukan untuk tahun pelajaran aktif.</small>
            </div>
            <div class="mb-3">
                <label class="form-label">File Excel</label>
                <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
                <small class="text-muted">Format: <strong>NIS, Nama Siswa, L/P</strong></small>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> Import Sekarang</button>
            <a href="kelas.php" class="btn btn-secondary">Kembali</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
