<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi hanya untuk admin
authorize_role(['admin']);

$page_title = "Manajemen Kelas";
$message = '';
$message_type = '';

// Ambil tahun pelajaran aktif
$active_year_query = mysqli_query($conn, "SELECT id FROM tahun_pelajaran WHERE status = 'aktif'");
$active_year = mysqli_fetch_assoc($active_year_query);
$active_year_id = $active_year ? $active_year['id'] : null;

// Ambil data guru untuk dropdown wali kelas
$guru_list_query = "SELECT guru.id, users.nama_lengkap FROM guru JOIN users ON guru.user_id = users.id ORDER BY users.nama_lengkap ASC";
$guru_list_result = mysqli_query($conn, $guru_list_query);

// Proses Aksi (Tambah, Edit, Hapus)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Aksi: Tambah Kelas
    if (isset($_POST['tambah'])) {
        $nama_kelas = mysqli_real_escape_string($conn, $_POST['nama_kelas']);
        $wali_kelas_id = !empty($_POST['wali_kelas_id']) ? $_POST['wali_kelas_id'] : 'NULL';

        $query = "INSERT INTO kelas (nama_kelas, wali_kelas_id) VALUES ('$nama_kelas', $wali_kelas_id)";
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
        $id = (int)$_POST['id'];
        $nama_kelas = mysqli_real_escape_string($conn, $_POST['nama_kelas']);
        $wali_kelas_id = !empty($_POST['wali_kelas_id']) ? $_POST['wali_kelas_id'] : 'NULL';

        $query = "UPDATE kelas SET nama_kelas = '$nama_kelas', wali_kelas_id = $wali_kelas_id WHERE id = $id";
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
        $id = (int)$_POST['id'];
        // Cek dulu apakah ada jurnal atau siswa terkait
        $check_jurnal_query = "SELECT COUNT(*) as total FROM jurnal WHERE kelas_id = $id";
        $check_jurnal_result = mysqli_query($conn, $check_jurnal_query);
        $total_jurnal = mysqli_fetch_assoc($check_jurnal_result)['total'];

        $check_siswa_query = "SELECT COUNT(*) as total FROM siswa_kelas WHERE kelas_id = $id";
        $check_siswa_result = mysqli_query($conn, $check_siswa_query);
        $total_siswa = mysqli_fetch_assoc($check_siswa_result)['total'];

        if ($total_jurnal > 0 || $total_siswa > 0) {
            $message = "Gagal menghapus: Kelas ini sudah digunakan di data jurnal atau memiliki siswa terdaftar.";
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

// Ambil semua data kelas untuk ditampilkan
$query = "
    SELECT
        k.id,
        k.nama_kelas,
        u.nama_lengkap as nama_wali_kelas,
        COUNT(sk.id) as total_siswa,
        SUM(CASE WHEN s.jenis_kelamin = 'L' THEN 1 ELSE 0 END) as jumlah_siswa_L,
        SUM(CASE WHEN s.jenis_kelamin = 'P' THEN 1 ELSE 0 END) as jumlah_siswa_P
    FROM kelas k
    LEFT JOIN guru g ON k.wali_kelas_id = g.id
    LEFT JOIN users u ON g.user_id = u.id
    LEFT JOIN siswa_kelas sk ON k.id = sk.kelas_id " . ($active_year_id ? "AND sk.tahun_pelajaran_id = $active_year_id" : "AND 1=0") . "
    LEFT JOIN siswa s ON sk.siswa_id = s.id
    GROUP BY k.id, k.nama_kelas, u.nama_lengkap
    ORDER BY k.nama_kelas ASC
";
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

<!-- Tabel Data -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Kelas (Tahun Pelajaran Aktif)</h6>
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
                        <td><?= (int)$row['jumlah_siswa_L'] ?> / <?= (int)$row['jumlah_siswa_P'] ?> / <strong><?= (int)$row['total_siswa'] ?></strong></td>
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
                                        <div class="mb-3">
                                            <label class="form-label">Wali Kelas (Opsional)</label>
                                            <select class="form-select" name="wali_kelas_id">
                                                <option value="">-- Pilih Wali Kelas --</option>
                                                <?php
                                                mysqli_data_seek($guru_list_result, 0);
                                                while($guru = mysqli_fetch_assoc($guru_list_result)) {
                                                    $guru_id_in_db_query = mysqli_query($conn, "SELECT wali_kelas_id FROM kelas WHERE id = ".$row['id']);
                                                    $current_wali_id = mysqli_fetch_assoc($guru_id_in_db_query)['wali_kelas_id'];
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
                    <div class="mb-3">
                        <label class="form-label">Wali Kelas (Opsional)</label>
                        <select class="form-select" name="wali_kelas_id">
                            <option value="">-- Pilih Wali Kelas --</option>
                            <?php
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