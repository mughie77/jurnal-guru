<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi hanya untuk admin
authorize_role(['admin']);

$page_title = "Manajemen Kelas";
$message = '';
$message_type = '';

// Ambil data guru untuk dropdown wali kelas
$guru_list_query = "SELECT guru.id, users.nama_lengkap FROM guru JOIN users ON guru.user_id = users.id ORDER BY users.nama_lengkap ASC";
$guru_list_result = mysqli_query($conn, $guru_list_query);

// Proses Aksi (Tambah, Edit, Hapus)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Aksi: Tambah Kelas
    if (isset($_POST['tambah'])) {
        $nama_kelas = mysqli_real_escape_string($conn, $_POST['nama_kelas']);
        $wali_kelas_id = !empty($_POST['wali_kelas_id']) ? $_POST['wali_kelas_id'] : 'NULL';
        $jumlah_siswa_L = (int)$_POST['jumlah_siswa_L'];
        $jumlah_siswa_P = (int)$_POST['jumlah_siswa_P'];

        $query = "INSERT INTO kelas (nama_kelas, wali_kelas_id, jumlah_siswa_L, jumlah_siswa_P) VALUES ('$nama_kelas', $wali_kelas_id, $jumlah_siswa_L, $jumlah_siswa_P)";
        if (mysqli_query($conn, $query)) {
            $message = "Kelas berhasil ditambahkan!";
            $message_type = 'success';
        } else {
            $message = "Gagal menambahkan kelas: " . mysqli_error($conn);
            $message_type = 'error';
        }
    }
    // Aksi: Edit Kelas
    elseif (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $nama_kelas = mysqli_real_escape_string($conn, $_POST['nama_kelas']);
        $wali_kelas_id = !empty($_POST['wali_kelas_id']) ? $_POST['wali_kelas_id'] : 'NULL';
        $jumlah_siswa_L = (int)$_POST['jumlah_siswa_L'];
        $jumlah_siswa_P = (int)$_POST['jumlah_siswa_P'];

        $query = "UPDATE kelas SET nama_kelas = '$nama_kelas', wali_kelas_id = $wali_kelas_id, jumlah_siswa_L = $jumlah_siswa_L, jumlah_siswa_P = $jumlah_siswa_P WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            $message = "Kelas berhasil diperbarui!";
            $message_type = 'success';
        } else {
            $message = "Gagal memperbarui kelas: " . mysqli_error($conn);
            $message_type = 'error';
        }
    }
    // Aksi: Hapus Kelas
    elseif (isset($_POST['hapus'])) {
        $id = $_POST['id'];
        // Cek dulu apakah ada jurnal terkait
        $check_query = "SELECT COUNT(*) as total FROM jurnal WHERE kelas_id = $id";
        $check_result = mysqli_query($conn, $check_query);
        $total_jurnal = mysqli_fetch_assoc($check_result)['total'];

        if ($total_jurnal > 0) {
            $message = "Gagal menghapus: Kelas ini sudah digunakan di data jurnal.";
            $message_type = 'error';
        } else {
            $query = "DELETE FROM kelas WHERE id = $id";
            if (mysqli_query($conn, $query)) {
                $message = "Kelas berhasil dihapus!";
                $message_type = 'success';
            } else {
                $message = "Gagal menghapus kelas: " . mysqli_error($conn);
                $message_type = 'error';
            }
        }
    }
}

// Ambil semua data kelas untuk ditampilkan, join dengan guru dan users untuk nama wali kelas
$query = "SELECT kelas.id, kelas.nama_kelas, kelas.jumlah_siswa_L, kelas.jumlah_siswa_P, users.nama_lengkap as nama_wali_kelas
          FROM kelas
          LEFT JOIN guru ON kelas.wali_kelas_id = guru.id
          LEFT JOIN users ON guru.user_id = users.id
          ORDER BY kelas.nama_kelas ASC";
$result = mysqli_query($conn, $query);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';
?>

<h1 class="h3 mb-4 text-gray-800">Manajemen Kelas</h1>

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

<!-- Tombol untuk memunculkan modal tambah -->
<button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#tambahModal">
    <i class="fa fa-plus"></i> Tambah Kelas
</button>
<a href="import_siswa_excel.php" class="btn btn-success mb-3">
    <i class="fa fa-file-excel"></i> Import Excel Data Siswa
</a>

<!-- Tabel Data -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Kelas</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nama Kelas</th>
                        <th>Wali Kelas</th>
                        <th>Jumlah Siswa (L/P/Total)</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nama_kelas']) ?></td>
                        <td><?= htmlspecialchars($row['nama_wali_kelas'] ?? 'Belum Diatur') ?></td>
                        <td><?= $row['jumlah_siswa_L'] ?> / <?= $row['jumlah_siswa_P'] ?> / <strong><?= $row['jumlah_siswa_L'] + $row['jumlah_siswa_P'] ?></strong></td>
                        <td>
                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal-<?= $row['id'] ?>" title="Edit">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#hapusModal-<?= $row['id'] ?>" title="Hapus">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                    </tr>

                    <!-- Modal Edit -->
                    <div class="modal fade" id="editModal-<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Kelas</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form action="" method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Nama Kelas</label>
                                            <input type="text" class="form-control" name="nama_kelas" value="<?= htmlspecialchars($row['nama_kelas']) ?>" required>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Jumlah Siswa Laki-laki</label>
                                                <input type="number" class="form-control" name="jumlah_siswa_L" value="<?= $row['jumlah_siswa_L'] ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Jumlah Siswa Perempuan</label>
                                                <input type="number" class="form-control" name="jumlah_siswa_P" value="<?= $row['jumlah_siswa_P'] ?>" required>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Wali Kelas (Opsional)</label>
                                            <select class="form-select" name="wali_kelas_id">
                                                <option value="">-- Pilih Wali Kelas --</option>
                                                <?php
                                                // Reset pointer result set guru
                                                mysqli_data_seek($guru_list_result, 0);
                                                while($guru = mysqli_fetch_assoc($guru_list_result)) {
                                                    $guru_id_in_db = mysqli_query($conn, "SELECT wali_kelas_id FROM kelas WHERE id = ".$row['id']);
                                                    $current_wali_id = mysqli_fetch_assoc($guru_id_in_db)['wali_kelas_id'];
                                                    $selected = ($guru['id'] == $current_wali_id) ? 'selected' : '';
                                                    echo "<option value='{$guru['id']}' $selected>" . htmlspecialchars($guru['nama_lengkap']) . "</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" name="edit" class="btn btn-primary">Simpan Perubahan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Hapus -->
                    <div class="modal fade" id="hapusModal-<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    Anda yakin ingin menghapus kelas "<?= htmlspecialchars($row['nama_kelas']) ?>"?
                                </div>
                                <div class="modal-footer">
                                    <form action="" method="POST">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" name="hapus" class="btn btn-danger">Hapus</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="tambahModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Kelas Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Kelas</label>
                        <input type="text" class="form-control" name="nama_kelas" placeholder="Contoh: X IPA 1" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jumlah Siswa Laki-laki</label>
                            <input type="number" class="form-control" name="jumlah_siswa_L" value="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jumlah Siswa Perempuan</label>
                            <input type="number" class="form-control" name="jumlah_siswa_P" value="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Wali Kelas (Opsional)</label>
                        <select class="form-select" name="wali_kelas_id">
                            <option value="">-- Pilih Wali Kelas --</option>
                            <?php
                            // Reset pointer result set guru
                            mysqli_data_seek($guru_list_result, 0);
                            while($guru = mysqli_fetch_assoc($guru_list_result)) {
                                echo "<option value='{$guru['id']}'>" . htmlspecialchars($guru['nama_lengkap']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>