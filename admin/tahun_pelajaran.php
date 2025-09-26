<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi hanya untuk admin
authorize_role(['admin']);

$page_title = "Manajemen Tahun Pelajaran";
$message = '';
$message_type = '';

// Proses Aksi (Tambah, Edit, Hapus, Set Aktif)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Aksi: Tambah Tahun Pelajaran
    if (isset($_POST['tambah'])) {
        $tahun = mysqli_real_escape_string($conn, $_POST['tahun']);
        $query = "INSERT INTO tahun_pelajaran (tahun) VALUES ('$tahun')";
        if (mysqli_query($conn, $query)) {
            $message = "Tahun pelajaran berhasil ditambahkan!";
            $message_type = 'success';
        } else {
            $message = "Gagal menambahkan tahun pelajaran: " . mysqli_error($conn);
            $message_type = 'error';
        }
    }
    // Aksi: Edit Tahun Pelajaran
    elseif (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $tahun = mysqli_real_escape_string($conn, $_POST['tahun']);
        $query = "UPDATE tahun_pelajaran SET tahun = '$tahun' WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            $message = "Tahun pelajaran berhasil diperbarui!";
            $message_type = 'success';
        } else {
            $message = "Gagal memperbarui tahun pelajaran: " . mysqli_error($conn);
            $message_type = 'error';
        }
    }
    // Aksi: Hapus Tahun Pelajaran
    elseif (isset($_POST['hapus'])) {
        $id = $_POST['id'];
        // Cek dulu apakah ada jurnal terkait
        $check_query = "SELECT COUNT(*) as total FROM jurnal WHERE tahun_pelajaran_id = $id";
        $check_result = mysqli_query($conn, $check_query);
        $total_jurnal = mysqli_fetch_assoc($check_result)['total'];

        if ($total_jurnal > 0) {
            $message = "Gagal menghapus: Tahun pelajaran ini sudah digunakan di jurnal.";
            $message_type = 'error';
        } else {
            $query = "DELETE FROM tahun_pelajaran WHERE id = $id";
            if (mysqli_query($conn, $query)) {
                $message = "Tahun pelajaran berhasil dihapus!";
                $message_type = 'success';
            } else {
                $message = "Gagal menghapus tahun pelajaran: " . mysqli_error($conn);
                $message_type = 'error';
            }
        }
    }
    // Aksi: Set Aktif
    elseif (isset($_POST['set_aktif'])) {
        $id = $_POST['id'];
        // Mulai transaksi
        mysqli_begin_transaction($conn);
        try {
            // 1. Set semua menjadi 'tidak aktif'
            mysqli_query($conn, "UPDATE tahun_pelajaran SET status = 'tidak aktif'");
            // 2. Set yang dipilih menjadi 'aktif'
            mysqli_query($conn, "UPDATE tahun_pelajaran SET status = 'aktif' WHERE id = $id");
            // Commit transaksi
            mysqli_commit($conn);
            $message = "Status tahun pelajaran berhasil diubah!";
            $message_type = 'success';
        } catch (mysqli_sql_exception $exception) {
            mysqli_rollback($conn);
            $message = "Gagal mengubah status: " . $exception->getMessage();
            $message_type = 'error';
        }
    }
}

// Ambil semua data tahun pelajaran untuk ditampilkan
$result = mysqli_query($conn, "SELECT * FROM tahun_pelajaran ORDER BY tahun DESC");

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';
?>

<h1 class="h3 mb-4 text-gray-800">Manajemen Tahun Pelajaran</h1>

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
    <i class="fa fa-plus"></i> Tambah Tahun Pelajaran
</button>

<!-- Tabel Data -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Tahun Pelajaran</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Tahun Pelajaran</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['tahun']) ?></td>
                        <td>
                            <?php if ($row['status'] == 'aktif'): ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Tidak Aktif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form action="" method="POST" class="d-inline">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <?php if ($row['status'] != 'aktif'): ?>
                                <button type="submit" name="set_aktif" class="btn btn-success btn-sm" title="Jadikan Aktif">
                                    <i class="fa fa-check"></i>
                                </button>
                                <?php endif; ?>
                            </form>
                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal-<?= $row['id'] ?>" title="Edit">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#hapusModal-<?= $row['id'] ?>" title="Hapus">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                    </tr>

                    <!-- Modal Edit -->
                    <div class="modal fade" id="editModal-<?= $row['id'] ?>" tabindex="-1" aria-labelledby="editModalLabel-<?= $row['id'] ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editModalLabel-<?= $row['id'] ?>">Edit Tahun Pelajaran</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form action="" method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <div class="mb-3">
                                            <label for="tahun-<?= $row['id'] ?>" class="form-label">Tahun Pelajaran</label>
                                            <input type="text" class="form-control" id="tahun-<?= $row['id'] ?>" name="tahun" value="<?= htmlspecialchars($row['tahun']) ?>" required>
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
                    <div class="modal fade" id="hapusModal-<?= $row['id'] ?>" tabindex="-1" aria-labelledby="hapusModalLabel-<?= $row['id'] ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="hapusModalLabel-<?= $row['id'] ?>">Konfirmasi Hapus</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    Anda yakin ingin menghapus tahun pelajaran "<?= htmlspecialchars($row['tahun']) ?>"?
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
<div class="modal fade" id="tambahModal" tabindex="-1" aria-labelledby="tambahModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tambahModalLabel">Tambah Tahun Pelajaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="tahun" class="form-label">Tahun Pelajaran</label>
                        <input type="text" class="form-control" id="tahun" name="tahun" placeholder="Contoh: 2024/2025" required>
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