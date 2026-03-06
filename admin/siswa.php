<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);

$page_title = "Manajemen Siswa";
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['tambah'])) {
        $nis = mysqli_real_escape_string($conn, $_POST['nis']);
        $nama_siswa = mysqli_real_escape_string($conn, $_POST['nama_siswa']);
        $jenis_kelamin = $_POST['jenis_kelamin'];
        $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
        $no_telp = mysqli_real_escape_string($conn, $_POST['no_telp']);

        $query = "INSERT INTO siswa (nis, nama_siswa, jenis_kelamin, alamat, no_telp) VALUES ('$nis', '$nama_siswa', '$jenis_kelamin', '$alamat', '$no_telp')";
        if (mysqli_query($conn, $query)) {
            $message = "Siswa berhasil ditambahkan!";
            $message_type = 'success';
        } else {
            $message = "Gagal: " . mysqli_error($conn);
            $message_type = 'error';
        }
    } elseif (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $nis = mysqli_real_escape_string($conn, $_POST['nis']);
        $nama_siswa = mysqli_real_escape_string($conn, $_POST['nama_siswa']);
        $jenis_kelamin = $_POST['jenis_kelamin'];
        $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
        $no_telp = mysqli_real_escape_string($conn, $_POST['no_telp']);

        $query = "UPDATE siswa SET nis = '$nis', nama_siswa = '$nama_siswa', jenis_kelamin = '$jenis_kelamin', alamat = '$alamat', no_telp = '$no_telp' WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            $message = "Siswa berhasil diperbarui!";
            $message_type = 'success';
        } else {
            $message = "Gagal: " . mysqli_error($conn);
            $message_type = 'error';
        }
    } elseif (isset($_POST['hapus'])) {
        $id = $_POST['id'];
        if (mysqli_query($conn, "DELETE FROM siswa WHERE id = $id")) {
            $message = "Siswa berhasil dihapus!";
            $message_type = 'success';
        } else {
            $message = "Gagal: " . mysqli_error($conn);
            $message_type = 'error';
        }
    }
}

$query = "SELECT * FROM siswa ORDER BY nama_siswa ASC";
$result = mysqli_query($conn, $query);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';
?>

<h1 class="h3 mb-4 text-gray-800">Manajemen Siswa</h1>

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

<button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#tambahModal">
    <i class="fa fa-plus"></i> Tambah Siswa
</button>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Siswa</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>NIS</th>
                        <th>Nama Siswa</th>
                        <th>L/P</th>
                        <th>No. Telp</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nis']) ?></td>
                        <td><?= htmlspecialchars($row['nama_siswa']) ?></td>
                        <td><?= $row['jenis_kelamin'] ?></td>
                        <td><?= htmlspecialchars($row['no_telp'] ?? '-') ?></td>
                        <td>
                            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal-<?= $row['id'] ?>"><i class="fa fa-edit"></i></button>
                            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#hapusModal-<?= $row['id'] ?>"><i class="fa fa-trash"></i></button>
                        </td>
                    </tr>

                    <!-- Modal Edit -->
                    <div class="modal fade" id="editModal-<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="" method="POST">
                                    <div class="modal-header"><h5 class="modal-title">Edit Siswa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <div class="mb-3"><label class="form-label">NIS</label><input type="text" name="nis" class="form-control" value="<?= htmlspecialchars($row['nis']) ?>" required></div>
                                        <div class="mb-3"><label class="form-label">Nama Siswa</label><input type="text" name="nama_siswa" class="form-control" value="<?= htmlspecialchars($row['nama_siswa']) ?>" required></div>
                                        <div class="mb-3">
                                            <label class="form-label">Jenis Kelamin</label>
                                            <select name="jenis_kelamin" class="form-select">
                                                <option value="L" <?= $row['jenis_kelamin'] == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                                <option value="P" <?= $row['jenis_kelamin'] == 'P' ? 'selected' : '' ?>>Perempuan</option>
                                            </select>
                                        </div>
                                        <div class="mb-3"><label class="form-label">Alamat</label><textarea name="alamat" class="form-control"><?= htmlspecialchars($row['alamat']) ?></textarea></div>
                                        <div class="mb-3"><label class="form-label">No. Telp</label><input type="text" name="no_telp" class="form-control" value="<?= htmlspecialchars($row['no_telp']) ?>"></div>
                                    </div>
                                    <div class="modal-footer"><button type="submit" name="edit" class="btn btn-primary">Simpan</button></div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Hapus -->
                    <div class="modal fade" id="hapusModal-<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="" method="POST">
                                    <div class="modal-header"><h5 class="modal-title">Hapus Siswa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">Apakah Anda yakin ingin menghapus "<?= htmlspecialchars($row['nama_siswa']) ?>"?</div>
                                    <div class="modal-footer">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" name="hapus" class="btn btn-danger">Hapus</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah (Simplified) -->
<div class="modal fade" id="tambahModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST">
                <div class="modal-header"><h5 class="modal-title">Tambah Siswa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">NIS</label><input type="text" name="nis" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Nama Siswa</label><input type="text" name="nama_siswa" class="form-control" required></div>
                    <div class="mb-3">
                        <label class="form-label">Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="form-select"><option value="L">Laki-laki</option><option value="P">Perempuan</option></select>
                    </div>
                    <div class="mb-3"><label class="form-label">Alamat</label><textarea name="alamat" class="form-control"></textarea></div>
                    <div class="mb-3"><label class="form-label">No. Telp</label><input type="text" name="no_telp" class="form-control"></div>
                </div>
                <div class="modal-footer"><button type="submit" name="tambah" class="btn btn-primary">Simpan</button></div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
