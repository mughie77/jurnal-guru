<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);

$page_title = "Manajemen Kelas";
$message = '';
$message_type = '';

$guru_list_result = mysqli_query($conn, "SELECT guru.id, users.nama_lengkap FROM guru JOIN users ON guru.user_id = users.id ORDER BY users.nama_lengkap ASC");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");
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
    <div class="flex flex-wrap gap-3">
        <button onclick="openModal('tambahModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-200 transition-all flex items-center">
            <i class="fa fa-plus mr-2"></i> Tambah Kelas
        </button>
        <a href="import_kelas_excel.php" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-blue-200 transition-all flex items-center">
            <i class="fa fa-plus-circle mr-2"></i> Import Daftar Kelas
        </a>
        <a href="import_siswa_excel.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-emerald-200 transition-all flex items-center">
            <i class="fa fa-file-excel mr-2"></i> Import Siswa (Assign ke Kelas)
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

<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<div id="tambahModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="bg-indigo-600 px-8 py-6 text-white font-bold italic text-2xl">Tambah Kelas</div>
    <form action="" method="POST" class="p-8 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Nama Kelas</label><input type="text" name="nama_kelas" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50"></div>
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Wali Kelas (Opsional)</label>
            <select name="wali_kelas_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white">
                <option value="">-- Tanpa Wali Kelas --</option>
                <?php mysqli_data_seek($guru_list_result, 0); while($g = mysqli_fetch_assoc($guru_list_result)): ?>
                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_lengkap']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="pt-4 flex gap-3"><button type="button" onclick="closeModal('tambahModal')" class="flex-1 px-6 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600">Batal</button><button type="submit" name="tambah" class="flex-1 px-6 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100">Simpan</button></div>
    </form>
</div>

<?php mysqli_data_seek($result, 0); while ($row = mysqli_fetch_assoc($result)): ?>
<div id="editModal-<?= $row['id'] ?>" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="bg-amber-500 px-8 py-6 text-white font-bold italic text-2xl">Edit Kelas</div>
    <form action="" method="POST" class="p-8 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <input type="hidden" name="id" value="<?= $row['id'] ?>">
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Nama Kelas</label><input type="text" name="nama_kelas" value="<?= htmlspecialchars($row['nama_kelas']) ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50"></div>
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Wali Kelas</label>
            <select name="wali_kelas_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 bg-white">
                <option value="">-- Tanpa Wali Kelas --</option>
                <?php mysqli_data_seek($guru_list_result, 0); while($g = mysqli_fetch_assoc($guru_list_result)): ?>
                    <option value="<?= $g['id'] ?>" <?= $g['id'] == $row['wali_kelas_id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_lengkap']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="pt-4 flex gap-3"><button type="button" onclick="closeModal('editModal-<?= $row['id'] ?>')" class="flex-1 px-6 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600">Batal</button><button type="submit" name="edit" class="flex-1 px-6 py-2.5 rounded-xl bg-amber-500 text-white font-bold hover:bg-amber-600 shadow-lg shadow-amber-100">Simpan</button></div>
    </form>
</div>

<div id="hapusModal-<?= $row['id'] ?>" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-sm bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="p-8 text-center">
        <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl"><i class="fa fa-trash"></i></div>
        <h3 class="text-xl font-bold text-slate-800 mb-2 italic">Hapus Kelas?</h3>
        <p class="text-sm text-slate-500 mb-8">Anda akan menghapus kelas <span class="font-bold text-slate-800"><?= htmlspecialchars($row['nama_kelas']) ?></span>.</p>
        <form action="" method="POST" class="flex gap-2">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            <input type="hidden" name="id" value="<?= $row['id'] ?>">
            <button type="button" onclick="closeModal('hapusModal-<?= $row['id'] ?>')" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600">Batal</button>
            <button type="submit" name="hapus" class="flex-1 px-4 py-2.5 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 transition-all">Hapus</button>
        </form>
    </div>
</div>
<?php endwhile; ?>

<script>
const overlay = document.getElementById('modalOverlay');
function openModal(id) { const m = document.getElementById(id); if(!m) return; overlay.classList.remove('hidden'); m.classList.remove('hidden'); setTimeout(() => { overlay.classList.add('opacity-100'); m.classList.add('opacity-100', 'scale-100'); }, 10); }
function closeModal(id) { const m = document.getElementById(id); overlay.classList.remove('opacity-100'); m.classList.remove('opacity-100', 'scale-100'); setTimeout(() => { overlay.classList.add('hidden'); m.classList.add('hidden'); }, 300); }
function closeAllModals() { document.querySelectorAll('.modal-content').forEach(m => { if(!m.classList.contains('hidden')) closeModal(m.id); }); }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
