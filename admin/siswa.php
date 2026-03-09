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
        if (mysqli_query($conn, "INSERT INTO siswa (nis, nama_siswa, jenis_kelamin) VALUES ('$nis', '$nama_siswa', '$jenis_kelamin')")) {
            $message = "Siswa berhasil ditambahkan!";
            $message_type = 'success';
        }
    } elseif (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $nis = mysqli_real_escape_string($conn, $_POST['nis']);
        $nama_siswa = mysqli_real_escape_string($conn, $_POST['nama_siswa']);
        $jenis_kelamin = $_POST['jenis_kelamin'];
        mysqli_query($conn, "UPDATE siswa SET nis = '$nis', nama_siswa = '$nama_siswa', jenis_kelamin = '$jenis_kelamin' WHERE id = $id");
        $message = "Data siswa diperbarui!";
        $message_type = 'success';
    } elseif (isset($_POST['hapus'])) {
        mysqli_query($conn, "DELETE FROM siswa WHERE id = " . (int)$_POST['id']);
        $message = "Siswa dihapus!";
        $message_type = 'success';
    }
}

$query = "SELECT s.*, k.nama_kelas
          FROM siswa s
          LEFT JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = '$active_tahun_id'
          LEFT JOIN kelas k ON sk.kelas_id = k.id
          ORDER BY s.nama_siswa ASC";
$result = mysqli_query($conn, $query);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Manajemen Siswa</h1>
        <p class="text-slate-500">Kelola database siswa dan penempatan kelas.</p>
    </div>
    <div class="flex gap-3">
        <button onclick="openModal('tambahModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-200 transition-all flex items-center">
            <i class="fa fa-plus mr-2"></i> Tambah Siswa
        </button>
        <a href="import_siswa_excel.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-emerald-200 transition-all flex items-center">
            <i class="fa fa-file-excel mr-2"></i> Import Excel
        </a>
    </div>
</div>

<?php if ($message): ?>
<script>
    Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= addslashes(htmlspecialchars($message)) ?>' });
</script>
<?php endif; ?>

<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">NIS</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Nama Siswa</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">JK</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Kelas Aktif</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-mono text-sm text-indigo-600"><?= htmlspecialchars($row['nis']) ?></td>
                    <td class="px-6 py-4 font-semibold text-slate-700"><?= htmlspecialchars($row['nama_siswa']) ?></td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-2 py-0.5 rounded text-xs font-bold <?= $row['jenis_kelamin'] == 'L' ? 'bg-blue-100 text-blue-600' : 'bg-pink-100 text-pink-600' ?>">
                            <?= $row['jenis_kelamin'] ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600"><?= htmlspecialchars($row['nama_kelas'] ?? 'N/A') ?></td>
                    <td class="px-6 py-4">
                        <div class="flex justify-center gap-2">
                            <button onclick="openModal('editModal-<?= $row['id'] ?>')" class="w-9 h-9 flex items-center justify-center rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white transition-all">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button onclick="openModal('hapusModal-<?= $row['id'] ?>')" class="w-9 h-9 flex items-center justify-center rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function openModal(id) { document.getElementById('modalOverlay').classList.remove('hidden'); document.getElementById(id).classList.remove('hidden'); setTimeout(() => { document.getElementById('modalOverlay').classList.add('opacity-100'); document.getElementById(id).classList.add('opacity-100', 'scale-100'); }, 10); }
function closeModal(id) { document.getElementById('modalOverlay').classList.remove('opacity-100'); document.getElementById(id).classList.remove('opacity-100', 'scale-100'); setTimeout(() => { document.getElementById('modalOverlay').classList.add('hidden'); document.getElementById(id).classList.add('hidden'); }, 300); }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
