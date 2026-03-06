<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

authorize_role(['admin']);

$page_title = "Import Data Siswa dari Excel";
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];

    if ($xlsx = SimpleXLSX::parse($file)) {
        $rows = $xlsx->rows();
        $header = array_shift($rows); // Ambil header

        $success_count = 0;
        $error_count = 0;
        $errors = [];

        mysqli_begin_transaction($conn);
        try {
            $stmt_check = mysqli_prepare($conn, "SELECT id FROM kelas WHERE nama_kelas = ?");
            $stmt_update = mysqli_prepare($conn, "UPDATE kelas SET jumlah_siswa_L = ?, jumlah_siswa_P = ? WHERE id = ?");

            foreach ($rows as $index => $row) {
                if (empty($row[0])) continue; // Skip jika nama kelas kosong

                $nama_kelas = $row[0];
                $jml_L = isset($row[1]) ? (int)$row[1] : 0;
                $jml_P = isset($row[2]) ? (int)$row[2] : 0;

                // Cek apakah kelas ada
                mysqli_stmt_bind_param($stmt_check, "s", $nama_kelas);
                mysqli_stmt_execute($stmt_check);
                $res = mysqli_stmt_get_result($stmt_check);

                if ($row_data = mysqli_fetch_assoc($res)) {
                    $kelas_id = $row_data['id'];
                    // Update jumlah siswa
                    mysqli_stmt_bind_param($stmt_update, "iii", $jml_L, $jml_P, $kelas_id);
                    mysqli_stmt_execute($stmt_update);
                    $success_count++;
                } else {
                    // Opsional: Buat kelas baru jika tidak ada?
                    // Lebih aman skip atau beri peringatan.
                    $errors[] = "Baris " . ($index + 2) . ": Kelas '$nama_kelas' tidak ditemukan.";
                    $error_count++;
                }
            }
            mysqli_stmt_close($stmt_check);
            mysqli_stmt_close($stmt_update);
            mysqli_commit($conn);
            $message = "Berhasil memperbarui data siswa untuk $success_count kelas.";
            if ($error_count > 0) {
                $message .= " Gagal: $error_count. " . implode(" ", $errors);
            }
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

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';
?>

<h1 class="h3 mb-4 text-gray-800">Import Data Siswa dari Excel</h1>

<?php if ($message): ?>
<div class="alert alert-<?= ($message_type == 'success') ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
    <?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Upload File Excel (.xlsx) untuk Jumlah Siswa</h6>
    </div>
    <div class="card-body">
        <p>Gunakan format kolom sebagai berikut: <strong>Nama Kelas, Jumlah Laki-laki, Jumlah Perempuan</strong></p>
        <p><small class="text-muted">Catatan: Nama kelas harus sama persis dengan yang ada di sistem.</small></p>
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> Import Sekarang</button>
            <a href="kelas.php" class="btn btn-secondary">Kembali</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
