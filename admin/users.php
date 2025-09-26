<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi hanya untuk admin
authorize_role(['admin']);

$page_title = "Manajemen User";
$message = '';
$message_type = '';

// Proses Aksi (Tambah, Edit, Hapus)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Aksi: Tambah User
    if (isset($_POST['tambah'])) {
        $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];

        // Cek username unik
        $check = mysqli_query($conn, "SELECT username FROM users WHERE username = '$username'");
        if (mysqli_num_rows($check) > 0) {
            $message = "Gagal: Username '$username' sudah digunakan.";
            $message_type = 'error';
        } else {
            $query = "INSERT INTO users (nama_lengkap, username, password, role) VALUES ('$nama_lengkap', '$username', '$password', '$role')";
            if (mysqli_query($conn, $query)) {
                $message = "User berhasil ditambahkan!";
                $message_type = 'success';
            } else {
                $message = "Gagal menambahkan user: " . mysqli_error($conn);
                $message_type = 'error';
            }
        }
    }
    // Aksi: Edit User
    elseif (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $role = $_POST['role'];

        // Cek username unik (kecuali untuk user saat ini)
        $check = mysqli_query($conn, "SELECT username FROM users WHERE username = '$username' AND id != $id");
        if (mysqli_num_rows($check) > 0) {
            $message = "Gagal: Username '$username' sudah digunakan oleh user lain.";
            $message_type = 'error';
        } else {
            $query = "UPDATE users SET nama_lengkap = '$nama_lengkap', username = '$username', role = '$role' WHERE id = $id";
            // Jika password diisi, update juga passwordnya
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $query = "UPDATE users SET nama_lengkap = '$nama_lengkap', username = '$username', role = '$role', password = '$password' WHERE id = $id";
            }

            if (mysqli_query($conn, $query)) {
                $message = "User berhasil diperbarui!";
                $message_type = 'success';
            } else {
                $message = "Gagal memperbarui user: " . mysqli_error($conn);
                $message_type = 'error';
            }
        }
    }
    // Aksi: Hapus User
    elseif (isset($_POST['hapus'])) {
        $id = $_POST['id'];
        // Mencegah admin menghapus diri sendiri
        if ($id == $_SESSION['user_id']) {
            $message = "Anda tidak dapat menghapus akun Anda sendiri.";
            $message_type = 'error';
        } else {
            $query = "DELETE FROM users WHERE id = $id";
            if (mysqli_query($conn, $query)) {
                $message = "User berhasil dihapus!";
                $message_type = 'success';
            } else {
                $message = "Gagal menghapus user: " . mysqli_error($conn);
                $message_type = 'error';
            }
        }
    }
}

// Ambil semua data user untuk ditampilkan
$result = mysqli_query($conn, "SELECT * FROM users ORDER BY nama_lengkap ASC");

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';
?>

<h1 class="h3 mb-4 text-gray-800">Manajemen User</h1>

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
    <i class="fa fa-plus"></i> Tambah User
</button>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar User</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nama Lengkap</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td><span class="badge bg-info"><?= htmlspecialchars($row['role']) ?></span></td>
                        <td>
                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal-<?= $row['id'] ?>">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#hapusModal-<?= $row['id'] ?>">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                    </tr>

                    <!-- Modal Edit -->
                    <div class="modal fade" id="editModal-<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="" method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit User</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Nama Lengkap</label>
                                            <input type="text" class="form-control" name="nama_lengkap" value="<?= htmlspecialchars($row['nama_lengkap']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Username</label>
                                            <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($row['username']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Password</label>
                                            <input type="password" class="form-control" name="password">
                                            <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah password.</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Role</label>
                                            <select class="form-select" name="role" required>
                                                <option value="admin" <?= ($row['role'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                                                <option value="waka" <?= ($row['role'] == 'waka') ? 'selected' : '' ?>>Waka</option>
                                                <option value="guru" <?= ($row['role'] == 'guru') ? 'selected' : '' ?>>Guru</option>
                                            </select>
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
                                <div class="modal-header">
                                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Anda yakin ingin menghapus user "<?= htmlspecialchars($row['nama_lengkap']) ?>"?</p>
                                    <?php if ($row['role'] == 'guru'): ?>
                                    <p class="text-danger"><strong>Perhatian:</strong> Menghapus user ini juga akan menghapus data guru yang terkait.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer">
                                    <form action="" method="POST">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" name="hapus" class="btn btn-danger" <?= ($row['id'] == $_SESSION['user_id']) ? 'disabled' : '' ?>>Hapus</button>
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
                <div class="modal-header">
                    <h5 class="modal-title">Tambah User Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" name="nama_lengkap" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" required>
                            <option value="admin">Admin</option>
                            <option value="waka">Waka</option>
                            <option value="guru">Guru</option>
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