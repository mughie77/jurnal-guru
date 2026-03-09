<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);
$page_title = "Tahun Pelajaran";
$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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

<script>
function openModal(id) { document.getElementById('modalOverlay').classList.remove('hidden'); document.getElementById(id).classList.remove('hidden'); setTimeout(() => { document.getElementById('modalOverlay').classList.add('opacity-100'); document.getElementById(id).classList.add('opacity-100', 'scale-100'); }, 10); }
function closeModal(id) { document.getElementById('modalOverlay').classList.remove('opacity-100'); document.getElementById(id).classList.remove('opacity-100', 'scale-100'); setTimeout(() => { document.getElementById('modalOverlay').classList.add('hidden'); document.getElementById(id).classList.add('hidden'); }, 300); }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
