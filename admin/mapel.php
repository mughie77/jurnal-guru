<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);

$page_title = "Manajemen Mata Pelajaran";
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Aksi: Tambah Mapel
    if (isset($_POST['tambah'])) {
        $nama_mapel = mysqli_real_escape_string($conn, $_POST['nama_mapel']);
        $kode_mapel = mysqli_real_escape_string($conn, $_POST['kode_mapel']);

        $check = mysqli_query($conn, "SELECT kode_mapel FROM mata_pelajaran WHERE kode_mapel = '$kode_mapel'");
        if (mysqli_num_rows($check) > 0) {
            $message = "Gagal: Kode Mapel '$kode_mapel' sudah ada.";
            $message_type = 'error';
        } else {
            $query = "INSERT INTO mata_pelajaran (nama_mapel, kode_mapel) VALUES ('$nama_mapel', '$kode_mapel')";
            if (mysqli_query($conn, $query)) {
                $message = "Mata pelajaran berhasil ditambahkan!";
                $message_type = 'success';
            } else {
                $message = "Gagal menambahkan mata pelajaran: " . mysqli_error($conn);
                $message_type = 'error';
            }
        }
    }
    // Aksi: Edit Mapel
    elseif (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $nama_mapel = mysqli_real_escape_string($conn, $_POST['nama_mapel']);
        $kode_mapel = mysqli_real_escape_string($conn, $_POST['kode_mapel']);

        $check = mysqli_query($conn, "SELECT kode_mapel FROM mata_pelajaran WHERE kode_mapel = '$kode_mapel' AND id != $id");
        if (mysqli_num_rows($check) > 0) {
            $message = "Gagal: Kode Mapel '$kode_mapel' sudah digunakan.";
            $message_type = 'error';
        } else {
            $query = "UPDATE mata_pelajaran SET nama_mapel = '$nama_mapel', kode_mapel = '$kode_mapel' WHERE id = $id";
            if (mysqli_query($conn, $query)) {
                $message = "Mata pelajaran berhasil diperbarui!";
                $message_type = 'success';
            } else {
                $message = "Gagal memperbarui mata pelajaran: " . mysqli_error($conn);
                $message_type = 'error';
            }
        }
    }
    // Aksi: Hapus Mapel
    elseif (isset($_POST['hapus'])) {
        $id = $_POST['id'];
        $check_jurnal = mysqli_query($conn, "SELECT id FROM jurnal WHERE mapel_id = $id");
        if(mysqli_num_rows($check_jurnal) > 0) {
            $message = "Gagal menghapus: Mata pelajaran ini sedang digunakan di data jurnal.";
            $message_type = 'error';
        } else {
            $query = "DELETE FROM mata_pelajaran WHERE id = $id";
            if (mysqli_query($conn, $query)) {
                $message = "Mata pelajaran berhasil dihapus!";
                $message_type = 'success';
            } else {
                $message = "Gagal menghapus mata pelajaran: " . mysqli_error($conn);
                $message_type = 'error';
            }
        }
    }
}

// Ambil semua data mapel
$result = mysqli_query($conn, "SELECT * FROM mata_pelajaran ORDER BY nama_mapel ASC");

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';
?>

<h1 class="h3 mb-4 text-gray-800">Manajemen Mata Pelajaran</h1>

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
    <i class="fa fa-plus"></i> Tambah Mata Pelajaran
</button>

<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Daftar Mata Pelajaran</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nama Mata Pelajaran</th>
                        <th>Kode Mapel</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nama_mapel']) ?></td>
                        <td><?= htmlspecialchars($row['kode_mapel']) ?></td>
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
                                    <div class="modal-header"><h5 class="modal-title">Edit Mata Pelajaran</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Nama Mata Pelajaran</label>
                                            <input type="text" class="form-control" name="nama_mapel" value="<?= htmlspecialchars($row['nama_mapel']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Kode Mapel</label>
                                            <input type="text" class="form-control" name="kode_mapel" value="<?= htmlspecialchars($row['kode_mapel']) ?>" required>
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
                                    <p>Anda yakin ingin menghapus mata pelajaran "<?= htmlspecialchars($row['nama_mapel']) ?>"?</p>
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
            <form action="" method="POST">
                <div class="modal-header"><h5 class="modal-title">Tambah Mata Pelajaran</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Mata Pelajaran</label>
                        <input type="text" class="form-control" id="nama_mapel_tambah" name="nama_mapel" placeholder="Contoh: Matematika" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kode Mapel</label>
                        <input type="text" class="form-control" id="kode_mapel_tambah" name="kode_mapel" placeholder="Contoh: MTK-X" required>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const namaMapelInput = document.getElementById('nama_mapel_tambah');
    const kodeMapelInput = document.getElementById('kode_mapel_tambah');
    let kodeMapelManuallyEdited = false;

    // Tandai bahwa kode mapel diedit manual
    kodeMapelInput.addEventListener('input', function() {
        kodeMapelManuallyEdited = true;
    });

    // Generate kode mapel otomatis
    namaMapelInput.addEventListener('input', function() {
        if (!kodeMapelManuallyEdited) {
            let namaMapel = this.value;
            let kode = '';

            // Ambil 3 huruf pertama dari nama mapel
            kode = namaMapel.substring(0, 3).toUpperCase();

            // Tambahkan 3 angka acak untuk keunikan
            const randomNum = Math.floor(100 + Math.random() * 900);
            kode += `-${randomNum}`;

            kodeMapelInput.value = kode;
        }
    });

    // Reset flag saat modal ditutup
    const tambahModal = document.getElementById('tambahModal');
    tambahModal.addEventListener('hidden.bs.modal', function () {
        kodeMapelManuallyEdited = false;
        namaMapelInput.value = '';
        kodeMapelInput.value = '';
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>