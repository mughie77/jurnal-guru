<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

authorize_role(['admin']);

$page_title = "Import Guru dari Excel";
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];

    if ($xlsx = SimpleXLSX::parse($file)) {
        $rows = $xlsx->rows();
        $header = array_shift($rows); // Ambil header

        // Mapping header (index)
        // Nama Lengkap, NIP, Alamat, No. Telp
        $success_count = 0;
        $error_count = 0;
        $errors = [];

        mysqli_begin_transaction($conn);
        try {
            // Prepare statements outside the loop
            $stmt_user = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, username, password, role) VALUES (?, ?, ?, ?)");
            $stmt_guru = mysqli_prepare($conn, "INSERT INTO guru (user_id, nip, alamat, no_telp) VALUES (?, ?, ?, ?)");

            foreach ($rows as $index => $row) {
                if (empty($row[0]) || empty($row[1])) continue; // Skip jika nama atau NIP kosong

                $nama_lengkap = $row[0];
                $nip = $row[1];
                $alamat = $row[2] ?? '';
                $no_telp = $row[3] ?? '';

                $username = $nip;
                $password = password_hash($nip, PASSWORD_DEFAULT);
                $role = 'guru';

                // Cek duplikat
                $stmt_check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
                mysqli_stmt_bind_param($stmt_check, "s", $username);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);

                if (mysqli_stmt_num_rows($stmt_check) > 0) {
                    $errors[] = "Baris " . ($index + 2) . ": NIP/Username '$nip' sudah ada.";
                    $error_count++;
                    mysqli_stmt_close($stmt_check);
                    continue;
                }
                mysqli_stmt_close($stmt_check);

                // 1. Insert ke tabel users
                mysqli_stmt_bind_param($stmt_user, "ssss", $nama_lengkap, $username, $password, $role);
                mysqli_stmt_execute($stmt_user);
                $user_id = mysqli_insert_id($conn);

                // 2. Insert ke tabel guru
                mysqli_stmt_bind_param($stmt_guru, "isss", $user_id, $nip, $alamat, $no_telp);
                mysqli_stmt_execute($stmt_guru);

                $success_count++;
            }
            mysqli_stmt_close($stmt_user);
            mysqli_stmt_close($stmt_guru);
            mysqli_commit($conn);
            $message = "Berhasil mengimpor $success_count guru.";
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

<h1 class="h3 mb-4 text-gray-800">Import Guru dari Excel</h1>

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
        <p>Gunakan format kolom sebagai berikut: <strong>Nama Lengkap, NIP, Alamat, No. Telp</strong></p>
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> Import Sekarang</button>
            <a href="guru.php" class="btn btn-secondary">Kembali</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
