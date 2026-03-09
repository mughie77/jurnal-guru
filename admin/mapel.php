<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);
$page_title = "Manajemen Mata Pelajaran";
$message = ''; $message_type = '';

function generateUniqueKode($conn) {
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    do {
        $kode = "";
        for ($i = 0; $i < 5; $i++) $kode .= $chars[rand(0, strlen($chars) - 1)];
        $check = mysqli_query($conn, "SELECT id FROM mata_pelajaran WHERE kode_mapel = '$kode'");
    } while (mysqli_num_rows($check) > 0);
    return $kode;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['tambah'])) {
        $nama_mapel = mysqli_real_escape_string($conn, $_POST['nama_mapel']);
        $kode_mapel = generateUniqueKode($conn);
        if (mysqli_query($conn, "INSERT INTO mata_pelajaran (nama_mapel, kode_mapel) VALUES ('$nama_mapel', '$kode_mapel')")) {
            $message = "Mata pelajaran ditambahkan dengan kode: $kode_mapel"; $message_type = 'success';
        }
    } elseif (isset($_POST['edit'])) {
        $id = (int)$_POST['id'];
        $nama_mapel = mysqli_real_escape_string($conn, $_POST['nama_mapel']);
        mysqli_query($conn, "UPDATE mata_pelajaran SET nama_mapel = '$nama_mapel' WHERE id = $id");
        $message = "Mata pelajaran diperbarui!"; $message_type = 'success';
    } elseif (isset($_POST['hapus'])) {
        $id = (int)$_POST['id'];
        if (mysqli_query($conn, "DELETE FROM mata_pelajaran WHERE id = $id")) {
            $message = "Mata pelajaran dihapus!"; $message_type = 'success';
        }
    }
}

$result = mysqli_query($conn, "SELECT * FROM mata_pelajaran ORDER BY nama_mapel ASC");
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Mata Pelajaran</h1>
        <p class="text-slate-500">Daftar mata pelajaran yang tersedia di kurikulum.</p>
    </div>
    <div class="flex gap-3">
        <button onclick="openModal('tambahModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-200 transition-all flex items-center">
            <i class="fa fa-plus mr-2"></i> Tambah Mapel
        </button>
        <a href="import_mapel_excel.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-emerald-200 transition-all flex items-center">
            <i class="fa fa-file-excel mr-2"></i> Import Excel
        </a>
    </div>
</div>

<?php if ($message): ?>
<script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= addslashes(htmlspecialchars($message)) ?>' });</script>
<?php endif; ?>

<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Kode Mapel</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Nama Mata Pelajaran</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-mono text-sm text-indigo-600 font-bold"><?= htmlspecialchars($row['kode_mapel']) ?></td>
                    <td class="px-6 py-4 font-semibold text-slate-700"><?= htmlspecialchars($row['nama_mapel']) ?></td>
                    <td class="px-6 py-4">
                        <div class="flex justify-center gap-2">
                            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)" class="w-9 h-9 flex items-center justify-center rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white transition-all">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button onclick="openDeleteModal(<?= $row['id'] ?>, '<?= addslashes($row['nama_mapel']) ?>')" class="w-9 h-9 flex items-center justify-center rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all">
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

<!-- Modals -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<!-- Tambah Modal -->
<div id="tambahModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="bg-indigo-600 px-8 py-6 text-white"><h3 class="text-2xl font-bold">Tambah Mapel</h3></div>
    <form action="" method="POST" class="p-8 space-y-4">
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Nama Mapel</label><input type="text" name="nama_mapel" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50" placeholder="Contoh: Pemrograman Web"></div>
        <div class="pt-4 flex gap-3">
            <button type="button" onclick="closeModal('tambahModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50">Batal</button>
            <button type="submit" name="tambah" class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100">Simpan</button>
        </div>
    </form>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="bg-amber-500 px-8 py-6 text-white"><h3 class="text-2xl font-bold">Edit Mapel</h3></div>
    <form action="" method="POST" class="p-8 space-y-4">
        <input type="hidden" name="id" id="edit_id">
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Kode Mapel (Permanen)</label><input type="text" id="edit_kode" disabled class="w-full px-4 py-2.5 rounded-xl border border-slate-100 bg-slate-50 text-slate-400 font-mono font-bold outline-none cursor-not-allowed"></div>
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Nama Mata Pelajaran</label><input type="text" name="nama_mapel" id="edit_nama" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50"></div>
        <div class="pt-4 flex gap-3">
            <button type="button" onclick="closeModal('editModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50">Batal</button>
            <button type="submit" name="edit" class="flex-1 px-4 py-2.5 rounded-xl bg-amber-500 text-white font-bold hover:bg-amber-600">Simpan</button>
        </div>
    </form>
</div>

<!-- Delete Modal -->
<div id="hapusModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-sm bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="p-8 text-center">
        <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl"><i class="fa fa-trash"></i></div>
        <h3 class="text-lg font-bold text-slate-800 mb-1">Hapus Mapel?</h3>
        <p class="text-sm text-slate-500 mb-6">Anda akan menghapus <span id="hapus_nama" class="font-bold"></span>.</p>
        <form action="" method="POST" class="flex gap-2">
            <input type="hidden" name="id" id="hapus_id">
            <button type="button" onclick="closeModal('hapusModal')" class="flex-1 px-4 py-2 rounded-xl border border-slate-200 font-bold text-slate-600">Batal</button>
            <button type="submit" name="hapus" class="flex-1 px-4 py-2 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 transition-all">Hapus</button>
        </form>
    </div>
</div>

<script>
const overlay = document.getElementById('modalOverlay');
function openModal(id) { const m = document.getElementById(id); overlay.classList.remove('hidden'); m.classList.remove('hidden'); setTimeout(() => { overlay.classList.add('opacity-100'); m.classList.add('opacity-100', 'scale-100'); }, 10); }
function closeModal(id) { const m = document.getElementById(id); overlay.classList.remove('opacity-100'); m.classList.remove('opacity-100', 'scale-100'); setTimeout(() => { overlay.classList.add('hidden'); m.classList.add('hidden'); }, 300); }
function closeAllModals() { document.querySelectorAll('.modal-content').forEach(m => { if(!m.classList.contains('hidden')) closeModal(m.id); }); }
function openEditModal(d) { document.getElementById('edit_id').value = d.id; document.getElementById('edit_nama').value = d.nama_mapel; document.getElementById('edit_kode').value = d.kode_mapel; openModal('editModal'); }
function openDeleteModal(id, n) { document.getElementById('hapus_id').value = id; document.getElementById('hapus_nama').textContent = n; openModal('hapusModal'); }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
