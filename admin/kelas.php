<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);

$page_title = "Manajemen Kelas";
$message = '';
$message_type = '';

$guru_list_result = mysqli_query($conn, "SELECT guru.id, users.nama_lengkap FROM guru JOIN users ON guru.user_id = users.id ORDER BY users.nama_lengkap ASC");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['tambah'])) {
        $nama_kelas = mysqli_real_escape_string($conn, $_POST['nama_kelas']);
        $wali_kelas_id = !empty($_POST['wali_kelas_id']) ? $_POST['wali_kelas_id'] : 'NULL';
        if (mysqli_query($conn, "INSERT INTO kelas (nama_kelas, wali_kelas_id) VALUES ('$nama_kelas', $wali_kelas_id)")) {
            $message = "Kelas ditambahkan!";
            $message_type = 'success';
        }
    } elseif (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $nama_kelas = mysqli_real_escape_string($conn, $_POST['nama_kelas']);
        $wali_kelas_id = !empty($_POST['wali_kelas_id']) ? $_POST['wali_kelas_id'] : 'NULL';
        mysqli_query($conn, "UPDATE kelas SET nama_kelas = '$nama_kelas', wali_kelas_id = $wali_kelas_id WHERE id = $id");
        $message = "Kelas diperbarui!";
        $message_type = 'success';
    } elseif (isset($_POST['hapus'])) {
        $id = $_POST['id'];
        if (mysqli_query($conn, "DELETE FROM kelas WHERE id = $id")) {
            $message = "Kelas dihapus!";
            $message_type = 'success';
        }
    }
}

$query = "SELECT kelas.*, users.nama_lengkap as nama_wali_kelas
          FROM kelas LEFT JOIN guru ON kelas.wali_kelas_id = guru.id
          LEFT JOIN users ON guru.user_id = users.id ORDER BY kelas.nama_kelas ASC";
$result = mysqli_query($conn, $query);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Manajemen Kelas</h1>
        <p class="text-slate-500">Kelola daftar kelas dan penugasan wali kelas.</p>
    </div>
    <div class="flex gap-3">
        <button onclick="openModal('tambahModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-200 transition-all flex items-center">
            <i class="fa fa-plus mr-2"></i> Tambah Kelas
        </button>
        <a href="import_siswa_excel.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-emerald-200 transition-all flex items-center">
            <i class="fa fa-file-excel mr-2"></i> Import Siswa
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
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Nama Kelas</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Wali Kelas</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Siswa (L/P)</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Total</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-bold text-slate-700"><?= htmlspecialchars($row['nama_kelas']) ?></td>
                    <td class="px-6 py-4 text-sm text-slate-600"><?= htmlspecialchars($row['nama_wali_kelas'] ?? 'Belum Diatur') ?></td>
                    <td class="px-6 py-4 text-center text-sm font-medium">
                        <span class="text-blue-600"><?= $row['jumlah_siswa_L'] ?></span> / <span class="text-pink-600"><?= $row['jumlah_siswa_P'] ?></span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-3 py-1 rounded-lg bg-slate-100 text-slate-800 font-bold text-sm border border-slate-200">
                            <?= $row['jumlah_siswa_L'] + $row['jumlah_siswa_P'] ?>
                        </span>
                    </td>
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
