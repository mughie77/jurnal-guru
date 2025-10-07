<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi hanya untuk admin
authorize_role(['admin']);

$page_title = "Manajemen Siswa";
$message = '';
$message_type = '';

// Ambil data kelas untuk dropdown
$kelas_list_query = "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC";
$kelas_list_result = mysqli_query($conn, $kelas_list_query);

// Proses Aksi (Tambah, Edit, Hapus)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Aksi: Tambah Siswa
    if (isset($_POST['tambah'])) {
        $nama_siswa = mysqli_real_escape_string($conn, $_POST['nama_siswa']);
        $nis = mysqli_real_escape_string($conn, $_POST['nis']);
        $nisn = mysqli_real_escape_string($conn, $_POST['nisn']);
        $kelas_id = (int)$_POST['kelas_id'];

        $query = "INSERT INTO siswa (nama_siswa, nis, nisn, kelas_id) VALUES ('$nama_siswa', '$nis', '$nisn', $kelas_id)";
        if (mysqli_query($conn, $query)) {
            $message = "Siswa berhasil ditambahkan!";
            $message_type = 'success';
        } else {
            $message = "Gagal menambahkan siswa: " . mysqli_error($conn);
            $message_type = 'error';
        }
    }
    // Aksi: Edit Siswa
    elseif (isset($_POST['edit'])) {
        $id = (int)$_POST['id'];
        $nama_siswa = mysqli_real_escape_string($conn, $_POST['nama_siswa']);
        $nis = mysqli_real_escape_string($conn, $_POST['nis']);
        $nisn = mysqli_real_escape_string($conn, $_POST['nisn']);
        $kelas_id = (int)$_POST['kelas_id'];

        $query = "UPDATE siswa SET nama_siswa = '$nama_siswa', nis = '$nis', nisn = '$nisn', kelas_id = $kelas_id WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            $message = "Data siswa berhasil diperbarui!";
            $message_type = 'success';
        } else {
            $message = "Gagal memperbarui data siswa: " . mysqli_error($conn);
            $message_type = 'error';
        }
    }
    // Aksi: Hapus Siswa
    elseif (isset($_POST['hapus'])) {
        $id = (int)$_POST['id'];
        $query = "DELETE FROM siswa WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            $message = "Siswa berhasil dihapus!";
            $message_type = 'success';
        } else {
            $message = "Gagal menghapus siswa: " . mysqli_error($conn);
            $message_type = 'error';
        }
    }
}

// Ambil semua data siswa untuk ditampilkan
$query = "SELECT siswa.id, siswa.nama_siswa, siswa.nis, siswa.nisn, kelas.nama_kelas
          FROM siswa
          JOIN kelas ON siswa.kelas_id = kelas.id
          ORDER BY kelas.nama_kelas, siswa.nama_siswa ASC";
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

<!-- Tombol untuk memunculkan modal tambah -->
<button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#tambahModal">
    <i class="fa fa-plus"></i> Tambah Siswa
</button>

<!-- Tabel Data -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Siswa</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nama Siswa</th>
                        <th>NIS</th>
                        <th>NISN</th>
                        <th>Kelas</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nama_siswa']) ?></td>
                        <td><?= htmlspecialchars($row['nis']) ?></td>
                        <td><?= htmlspecialchars($row['nisn']) ?></td>
                        <td><?= htmlspecialchars($row['nama_kelas']) ?></td>
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
                                    <h5 class="modal-title">Edit Siswa</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form action="" method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Nama Siswa</label>
                                            <input type="text" class="form-control" name="nama_siswa" value="<?= htmlspecialchars($row['nama_siswa']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">NIS</label>
                                            <input type="text" class="form-control" name="nis" value="<?= htmlspecialchars($row['nis']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">NISN</label>
                                            <input type="text" class="form-control" name="nisn" value="<?= htmlspecialchars($row['nisn']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Kelas</label>
                                            <select class="form-select" name="kelas_id" required>
                                                <option value="">-- Pilih Kelas --</option>
                                                <?php
                                                $kelas_edit_query = "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC";
                                                $kelas_edit_result = mysqli_query($conn, $kelas_edit_query);
                                                while($kelas = mysqli_fetch_assoc($kelas_edit_result)) {
                                                    $selected = ($kelas['id'] == $row['kelas_id']) ? 'selected' : '';
                                                    echo "<option value='{$kelas['id']}' $selected>" . htmlspecialchars($kelas['nama_kelas']) . "</option>";
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
                                    Anda yakin ingin menghapus siswa "<?= htmlspecialchars($row['nama_siswa']) ?>"?
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
                <h5 class="modal-title">Tambah Siswa Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Siswa</label>
                        <input type="text" class="form-control" name="nama_siswa" placeholder="Masukkan Nama Lengkap Siswa" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">NIS</label>
                        <input type="text" class="form-control" name="nis" placeholder="Masukkan Nomor Induk Siswa" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">NISN</label>
                        <input type="text" class="form-control" name="nisn" placeholder="Masukkan Nomor Induk Siswa Nasional" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kelas</label>
                        <select class="form-select" name="kelas_id" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php
                            mysqli_data_seek($kelas_list_result, 0);
                            while($kelas = mysqli_fetch_assoc($kelas_list_result)) {
                                echo "<option value='{$kelas['id']}'>" . htmlspecialchars($kelas['nama_kelas']) . "</option>";
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