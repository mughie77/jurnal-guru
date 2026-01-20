<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi untuk admin dan waka
authorize_role(['admin', 'waka']);

$page_title = "Pengaturan Umum";
$message = '';
$message_type = '';

// Proses update pengaturan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_pengaturan'])) {
    $jam_masuk = $_POST['jam_masuk_sekolah'];

    // Validasi format waktu
    if (preg_match("/^(?:2[0-3]|[01]?[0-9]):[0-5][0-9](?::[0-5][0-9])?$/", $jam_masuk)) {
        $query = "UPDATE pengaturan SET setting_value = ? WHERE setting_key = 'jam_masuk_sekolah'";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $jam_masuk);

        if (mysqli_stmt_execute($stmt)) {
            $message = "Pengaturan berhasil disimpan!";
            $message_type = 'success';
        } else {
            $message = "Gagal menyimpan pengaturan: " . mysqli_error($conn);
            $message_type = 'error';
        }
        mysqli_stmt_close($stmt);
    } else {
        $message = "Format jam tidak valid. Gunakan format HH:MM atau HH:MM:SS.";
        $message_type = 'error';
    }
}

// Ambil nilai pengaturan saat ini
$pengaturan_query = mysqli_query($conn, "SELECT setting_value FROM pengaturan WHERE setting_key = 'jam_masuk_sekolah'");
$jam_masuk_sekolah = '07:00:00'; // Default value
if (mysqli_num_rows($pengaturan_query) > 0) {
    $jam_masuk_sekolah = mysqli_fetch_assoc($pengaturan_query)['setting_value'];
}

require_once __DIR__ . '/../includes/header.php';
// Tentukan sidebar berdasarkan peran
if ($_SESSION['role'] == 'admin') {
    require_once __DIR__ . '/../includes/sidebar_admin.php';
} elseif ($_SESSION['role'] == 'waka') {
    require_once __DIR__ . '/../includes/sidebar_waka.php';
}
?>

<h1 class="h3 mb-4 text-gray-800">Pengaturan Umum</h1>

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

<div class="row">
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Pengaturan Jam Masuk Sekolah</h6>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <div class="mb-3">
                        <label for="jam_masuk_sekolah" class="form-label">Jam Masuk Sekolah</label>
                        <input type="time" class="form-control" id="jam_masuk_sekolah" name="jam_masuk_sekolah" value="<?= htmlspecialchars($jam_masuk_sekolah) ?>" step="1" required>
                        <div class="form-text">
                            Siswa yang melakukan absensi setelah jam ini akan otomatis ditandai sebagai "Terlambat".
                        </div>
                    </div>
                    <button type="submit" name="simpan_pengaturan" class="btn btn-primary">
                        <i class="fa fa-save"></i> Simpan Pengaturan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>