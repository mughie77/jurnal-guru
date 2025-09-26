<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);

$page_title = "Manajemen Guru";
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Aksi: Tambah Guru
    if (isset($_POST['tambah'])) {
        $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
        $nip = mysqli_real_escape_string($conn, $_POST['nip']);
        $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
        $no_telp = mysqli_real_escape_string($conn, $_POST['no_telp']);

        // Username = NIP, Password default = NIP (dihash)
        $username = $nip;
        $password = password_hash($nip, PASSWORD_DEFAULT);
        $role = 'guru';

        // Cek NIP/username duplikat
        $check_nip = mysqli_query($conn, "SELECT nip FROM guru WHERE nip = '$nip'");
        $check_user = mysqli_query($conn, "SELECT username FROM users WHERE username = '$username'");

        if (mysqli_num_rows($check_nip) > 0 || mysqli_num_rows($check_user) > 0) {
            $message = "Gagal: NIP atau Username sudah ada.";
            $message_type = 'error';
        } else {
            // Mulai transaksi
            mysqli_begin_transaction($conn);
            try {
                // 1. Insert ke tabel users
                $stmt_user = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, username, password, role) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt_user, "ssss", $nama_lengkap, $username, $password, $role);
                mysqli_stmt_execute($stmt_user);
                $user_id = mysqli_insert_id($conn);

                // 2. Insert ke tabel guru
                $stmt_guru = mysqli_prepare($conn, "INSERT INTO guru (user_id, nip, alamat, no_telp) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt_guru, "isss", $user_id, $nip, $alamat, $no_telp);
                mysqli_stmt_execute($stmt_guru);

                // Commit transaksi
                mysqli_commit($conn);
                $message = "Guru berhasil ditambahkan! Akun user juga telah dibuat.";
                $message_type = 'success';
            } catch (mysqli_sql_exception $exception) {
                mysqli_rollback($conn);
                $message = "Gagal menambahkan guru: " . $exception->getMessage();
                $message_type = 'error';
            }
        }
    }
    // Aksi: Edit Guru
    elseif (isset($_POST['edit'])) {
        $id = $_POST['id']; // guru.id
        $user_id = $_POST['user_id'];
        $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
        $nip = mysqli_real_escape_string($conn, $_POST['nip']);
        $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
        $no_telp = mysqli_real_escape_string($conn, $_POST['no_telp']);

        // Cek NIP duplikat (kecuali untuk guru saat ini)
        $check_nip = mysqli_query($conn, "SELECT nip FROM guru WHERE nip = '$nip' AND id != $id");
        if (mysqli_num_rows($check_nip) > 0) {
            $message = "Gagal: NIP sudah digunakan oleh guru lain.";
            $message_type = 'error';
        } else {
            mysqli_begin_transaction($conn);
            try {
                // 1. Update tabel users
                $stmt_user = mysqli_prepare($conn, "UPDATE users SET nama_lengkap = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_user, "si", $nama_lengkap, $user_id);
                mysqli_stmt_execute($stmt_user);

                // 2. Update tabel guru
                $stmt_guru = mysqli_prepare($conn, "UPDATE guru SET nip = ?, alamat = ?, no_telp = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_guru, "sssi", $nip, $alamat, $no_telp, $id);
                mysqli_stmt_execute($stmt_guru);

                mysqli_commit($conn);
                $message = "Data guru berhasil diperbarui!";
                $message_type = 'success';
            } catch (mysqli_sql_exception $exception) {
                mysqli_rollback($conn);
                $message = "Gagal memperbarui data guru: " . $exception->getMessage();
                $message_type = 'error';
            }
        }
    }
    // Aksi: Hapus Guru
    elseif (isset($_POST['hapus'])) {
        $user_id = $_POST['user_id']; // Hapus berdasarkan user_id akan cascade
        $guru_id = $_POST['id'];

        // Cek apakah guru punya jurnal
        $check_jurnal = mysqli_query($conn, "SELECT id FROM jurnal WHERE guru_id = $guru_id");
        if(mysqli_num_rows($check_jurnal) > 0) {
            $message = "Gagal menghapus: Guru ini memiliki data jurnal. Hapus data jurnal terkait terlebih dahulu.";
            $message_type = 'error';
        } else {
            $query = "DELETE FROM users WHERE id = $user_id";
            if (mysqli_query($conn, $query)) {
                $message = "Data guru dan akun user terkait berhasil dihapus!";
                $message_type = 'success';
            } else {
                $message = "Gagal menghapus guru: " . mysqli_error($conn);
                $message_type = 'error';
            }
        }
    }
}

// Ambil semua data guru
$result = mysqli_query($conn, "SELECT guru.*, users.nama_lengkap FROM guru JOIN users ON guru.user_id = users.id ORDER BY users.nama_lengkap ASC");

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';
?>

<h1 class="h3 mb-4 text-gray-800">Manajemen Guru</h1>

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

<button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#tambahModal">
    <i class="fa fa-plus"></i> Tambah Guru
</button>

<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Daftar Guru</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>NIP</th>
                        <th>Nama Lengkap</th>
                        <th>Alamat</th>
                        <th>No. Telp</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nip']) ?></td>
                        <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                        <td><?= htmlspecialchars($row['alamat']) ?></td>
                        <td><?= htmlspecialchars($row['no_telp']) ?></td>
                        <td>
                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal-<?= $row['id'] ?>"><i class="fa fa-edit"></i></button>
                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#hapusModal-<?= $row['id'] ?>"><i class="fa fa-trash"></i></button>
                        </td>
                    </tr>

                    <!-- Modal Edit -->
                    <div class="modal fade" id="editModal-<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="" method="POST">
                                    <div class="modal-header"><h5 class="modal-title">Edit Guru</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Nama Lengkap</label>
                                            <input type="text" class="form-control" name="nama_lengkap" value="<?= htmlspecialchars($row['nama_lengkap']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">NIP</label>
                                            <input type="text" class="form-control" name="nip" value="<?= htmlspecialchars($row['nip']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Alamat</label>
                                            <textarea class="form-control" name="alamat" rows="3"><?= htmlspecialchars($row['alamat']) ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">No. Telp</label>
                                            <input type="text" class="form-control" name="no_telp" value="<?= htmlspecialchars($row['no_telp']) ?>">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" name="edit" class="btn btn-primary">Simpan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Hapus -->
                    <div class="modal fade" id="hapusModal-<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header"><h5 class="modal-title">Konfirmasi Hapus</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body">
                                    <p>Anda yakin ingin menghapus data guru "<?= htmlspecialchars($row['nama_lengkap']) ?>"?</p>
                                    <p class="text-danger"><strong>Perhatian:</strong> Tindakan ini juga akan menghapus akun user yang terkait secara permanen.</p>
                                </div>
                                <div class="modal-footer">
                                    <form action="" method="POST">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
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
            <form action="" method="POST">
                <div class="modal-header"><h5 class="modal-title">Tambah Guru Baru</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" name="nama_lengkap" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">NIP</label>
                        <input type="text" class="form-control" name="nip" required>
                        <small class="form-text text-muted">NIP akan digunakan sebagai username. Password default akan sama dengan NIP.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-control" name="alamat" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No. Telp</label>
                        <input type="text" class="form-control" name="no_telp">
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