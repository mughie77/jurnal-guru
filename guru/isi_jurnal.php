<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi hanya untuk guru
authorize_role(['guru', 'admin']);

$page_title = "Isi Jurnal Baru";
$message = '';
$message_type = '';

// Dapatkan guru_id dari user_id yang login
$user_id = $_SESSION['user_id'];
$guru_result = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
if(mysqli_num_rows($guru_result) == 0) {
    // Handle jika data guru tidak ditemukan untuk user ini
    die("Error: Data guru tidak ditemukan untuk user ini. Silakan hubungi admin.");
}
$guru_id = mysqli_fetch_assoc($guru_result)['id'];

// Dapatkan tahun pelajaran yang aktif
$tahun_aktif_result = mysqli_query($conn, "SELECT id FROM tahun_pelajaran WHERE status = 'aktif' LIMIT 1");
if(mysqli_num_rows($tahun_aktif_result) == 0) {
    die("Error: Tidak ada tahun pelajaran yang aktif. Silakan hubungi admin untuk mengaturnya.");
}
$tahun_pelajaran_id = mysqli_fetch_assoc($tahun_aktif_result)['id'];

// Ambil data untuk dropdown
$mapels = mysqli_query($conn, "SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel");
$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");

// Proses form jika disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mapel_id = $_POST['mapel_id'];
    $kelas_id = $_POST['kelas_id'];
    $tanggal = $_POST['tanggal'];
    $jam_ke = mysqli_real_escape_string($conn, $_POST['jam_ke']);
    $materi = mysqli_real_escape_string($conn, $_POST['materi']);
    $jml_hadir = (int)$_POST['jml_hadir'];
    $jml_sakit = (int)$_POST['jml_sakit'];
    $jml_izin = (int)$_POST['jml_izin'];
    $jml_alfa = (int)$_POST['jml_alfa'];
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);

    // Validasi dasar
    if (empty($mapel_id) || empty($kelas_id) || empty($tanggal) || empty($jam_ke) || empty($materi)) {
        $message = "Harap lengkapi semua field yang wajib diisi.";
        $message_type = 'error';
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO jurnal (guru_id, mapel_id, kelas_id, tahun_pelajaran_id, tanggal, jam_ke, materi, jml_hadir, jml_sakit, jml_izin, jml_alfa, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iiiisssiiiis", $guru_id, $mapel_id, $kelas_id, $tahun_pelajaran_id, $tanggal, $jam_ke, $materi, $jml_hadir, $jml_sakit, $jml_izin, $jml_alfa, $keterangan);

        if (mysqli_stmt_execute($stmt)) {
            $message = "Jurnal berhasil disimpan!";
            $message_type = 'success';
            // Kosongkan beberapa field setelah berhasil
            $_POST = [];
        } else {
            $message = "Gagal menyimpan jurnal: " . mysqli_stmt_error($stmt);
            $message_type = 'error';
        }
        mysqli_stmt_close($stmt);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Override sidebar style untuk dashboard guru */
.main-content { margin-left: 0; }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Formulir Jurnal Mengajar</h1>
        <a href="<?= BASE_URL ?>guru/index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Kembali ke Dashboard</a>
    </div>

    <?php if ($message): ?>
    <script>
        Swal.fire({
            icon: '<?= $message_type ?>',
            title: '<?= ucfirst($message_type) ?>',
            text: '<?= addslashes(htmlspecialchars($message)) ?>',
            timer: 3000,
            showConfirmButton: false
        });
    </script>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Isi Jurnal Baru</h6>
        </div>
        <div class="card-body">
            <form action="" method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="tanggal" class="form-label">Tanggal Mengajar <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="jam_ke" class="form-label">Jam Mengajar Ke- <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="jam_ke" name="jam_ke" placeholder="Contoh: 1-2" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="mapel_id" class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                        <select class="form-select" id="mapel_id" name="mapel_id" required>
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            <?php while($m = mysqli_fetch_assoc($mapels)): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="kelas_id" class="form-label">Kelas yang Diajar <span class="text-danger">*</span></label>
                        <select class="form-select" id="kelas_id" name="kelas_id" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php while($k = mysqli_fetch_assoc($kelases)): ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="materi" class="form-label">Materi Pembahasan <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="materi" name="materi" rows="4" required></textarea>
                </div>
                <hr>
                <p class="font-weight-bold">Kehadiran Siswa</p>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="jml_hadir" class="form-label">Jumlah Hadir <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="jml_hadir" name="jml_hadir" value="0" min="0" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="jml_sakit" class="form-label">Sakit</label>
                        <input type="number" class="form-control" id="jml_sakit" name="jml_sakit" value="0" min="0">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="jml_izin" class="form-label">Izin</label>
                        <input type="number" class="form-control" id="jml_izin" name="jml_izin" value="0" min="0">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="jml_alfa" class="form-label">Alfa</label>
                        <input type="number" class="form-control" id="jml_alfa" name="jml_alfa" value="0" min="0">
                    </div>
                </div>
                 <div class="mb-3">
                    <label for="keterangan" class="form-label">Catatan/Keterangan Tambahan</label>
                    <textarea class="form-control" id="keterangan" name="keterangan" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Jurnal</button>
            </form>
        </div>
    </div>
</div>

</div>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>