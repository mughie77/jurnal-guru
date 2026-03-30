<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);
$page_title = "Tahun Pelajaran";
$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");
    if (isset($_POST['tambah'])) {
        $tahun = mysqli_real_escape_string($conn, $_POST['tahun']);
        mysqli_query($conn, "INSERT INTO tahun_pelajaran (tahun) VALUES ('$tahun')");
        $message = "Tahun pelajaran ditambahkan!"; $message_type = 'success';
    } elseif (isset($_POST['set_aktif'])) {
        $id = $_POST['id'];
        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, "UPDATE tahun_pelajaran SET status = 'tidak aktif'");
            mysqli_query($conn, "UPDATE tahun_pelajaran SET status = 'aktif' WHERE id = $id");
            mysqli_commit($conn);
            $message = "Status tahun pelajaran diperbarui!"; $message_type = 'success';
        } catch (Exception $e) { mysqli_rollback($conn); $message = "Error: " . $e->getMessage(); $message_type = 'error'; }
    } elseif (isset($_POST['hapus'])) {
        mysqli_query($conn, "DELETE FROM tahun_pelajaran WHERE id = " . (int)$_POST['id']);
        $message = "Tahun pelajaran dihapus!"; $message_type = 'success';
    }
}

$result = mysqli_query($conn, "SELECT * FROM tahun_pelajaran ORDER BY tahun DESC");
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Tahun Pelajaran</h1>
        <p class="text-slate-500">Kelola periode akademik aktif dan riwayat tahun ajaran.</p>
    </div>
    <button onclick="openModal('tambahModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-200 transition-all flex items-center">
        <i class="fa fa-plus mr-2"></i> Tambah Tahun
    </button>
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
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Tahun Pelajaran</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Status</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-bold text-slate-700"><?= htmlspecialchars($row['tahun']) ?></td>
                    <td class="px-6 py-4 text-center">
                        <?php if ($row['status'] == 'aktif'): ?>
                            <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold uppercase tracking-widest border border-emerald-200 inline-flex items-center">
                                <span class="w-2 h-2 bg-emerald-500 rounded-full mr-2 animate-pulse"></span> Aktif
                            </span>
                        <?php else: ?>
                            <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-500 text-xs font-bold uppercase tracking-widest border border-slate-200">Tidak Aktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex justify-center gap-2">
                            <?php if ($row['status'] != 'aktif'): ?>
                            <form action="" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" name="set_aktif" class="w-9 h-9 flex items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white transition-all" title="Aktifkan">
                                    <i class="fa fa-check"></i>
                                </button>
                            </form>
                            <?php endif; ?>
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
    <div class="bg-indigo-600 px-8 py-6 text-white font-bold italic text-2xl">Tambah Tahun Pelajaran</div>
    <form action="" method="POST" class="p-8 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Tahun Pelajaran</label><input type="text" name="tahun" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50" placeholder="Contoh: 2024/2025"></div>
        <div class="pt-4 flex gap-3"><button type="button" onclick="closeModal('tambahModal')" class="flex-1 px-6 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600">Batal</button><button type="submit" name="tambah" class="flex-1 px-6 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100">Simpan</button></div>
    </form>
</div>

<?php mysqli_data_seek($result, 0); while ($row = mysqli_fetch_assoc($result)): ?>
<div id="hapusModal-<?= $row['id'] ?>" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-sm bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="p-8 text-center">
        <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl"><i class="fa fa-calendar-times"></i></div>
        <h3 class="text-xl font-bold text-slate-800 mb-2 italic">Hapus Tahun?</h3>
        <p class="text-sm text-slate-500 mb-8">Hapus periode <span class="font-bold text-slate-800"><?= htmlspecialchars($row['tahun']) ?></span>?</p>
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
