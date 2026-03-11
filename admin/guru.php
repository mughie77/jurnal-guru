<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);

$page_title = "Manajemen Guru";
$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");
    if (isset($_POST['tambah'])) {
        $nama = $_POST['nama_lengkap'];
        $nip = $_POST['nip'];
        $alamat = $_POST['alamat'] ?: null;
        $no_telp = $_POST['no_telp'] ?: null;
        $pass = password_hash($nip, PASSWORD_DEFAULT);
        mysqli_begin_transaction($conn);
        try {
            $stmt1 = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, username, password, role) VALUES (?, ?, ?, 'guru')");
            mysqli_stmt_bind_param($stmt1, "sss", $nama, $nip, $pass);
            mysqli_stmt_execute($stmt1);
            $uid = mysqli_insert_id($conn);

            $stmt2 = mysqli_prepare($conn, "INSERT INTO guru (user_id, nip, alamat, no_telp) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt2, "isss", $uid, $nip, $alamat, $no_telp);
            mysqli_stmt_execute($stmt2);
            $gid = mysqli_insert_id($conn);

            if (!empty($_POST['mapel_ids'])) {
                $stmt3 = mysqli_prepare($conn, "INSERT INTO guru_mapel (guru_id, mapel_id) VALUES (?, ?)");
                foreach ($_POST['mapel_ids'] as $mid) {
                    $mid_int = (int)$mid;
                    mysqli_stmt_bind_param($stmt3, "ii", $gid, $mid_int);
                    mysqli_stmt_execute($stmt3);
                }
            }
            mysqli_commit($conn); $message = "Guru berhasil ditambahkan!"; $message_type = 'success';
        } catch (Exception $e) { mysqli_rollback($conn); $message = "Gagal: " . $e->getMessage(); $message_type = 'error'; }
    } elseif (isset($_POST['edit'])) {
        $gid = (int)$_POST['id']; $uid = (int)$_POST['user_id'];
        $nama = $_POST['nama_lengkap'];
        $nip = $_POST['nip'];
        $alamat = $_POST['alamat'] ?: null;
        $no_telp = $_POST['no_telp'] ?: null;
        mysqli_begin_transaction($conn);
        try {
            $stmt1 = mysqli_prepare($conn, "UPDATE users SET nama_lengkap = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt1, "si", $nama, $uid);
            mysqli_stmt_execute($stmt1);

            $stmt2 = mysqli_prepare($conn, "UPDATE guru SET nip = ?, alamat = ?, no_telp = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt2, "sssi", $nip, $alamat, $no_telp, $gid);
            mysqli_stmt_execute($stmt2);

            $stmt3 = mysqli_prepare($conn, "DELETE FROM guru_mapel WHERE guru_id = ?");
            mysqli_stmt_bind_param($stmt3, "i", $gid);
            mysqli_stmt_execute($stmt3);

            if (!empty($_POST['mapel_ids'])) {
                $stmt4 = mysqli_prepare($conn, "INSERT INTO guru_mapel (guru_id, mapel_id) VALUES (?, ?)");
                foreach ($_POST['mapel_ids'] as $mid) {
                    $mid_int = (int)$mid;
                    mysqli_stmt_bind_param($stmt4, "ii", $gid, $mid_int);
                    mysqli_stmt_execute($stmt4);
                }
            }
            mysqli_commit($conn); $message = "Data guru diperbarui!"; $message_type = 'success';
        } catch (Exception $e) { mysqli_rollback($conn); $message = "Gagal: " . $e->getMessage(); $message_type = 'error'; }
    } elseif (isset($_POST['hapus'])) {
        $uid = (int)$_POST['user_id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $uid);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Guru berhasil dihapus!"; $message_type = 'success';
        }
    }
}

$mapel_list = mysqli_query($conn, "SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel ASC");
// Search Logic
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$where_sql = "";
if (!empty($search)) {
    $where_sql = " WHERE (users.nama_lengkap LIKE '%$search%' OR guru.nip LIKE '%$search%')";
}

$query = "SELECT guru.*, users.nama_lengkap, GROUP_CONCAT(mata_pelajaran.nama_mapel SEPARATOR ', ') as mapel_diampu
          FROM guru JOIN users ON guru.user_id = users.id
          LEFT JOIN guru_mapel ON guru.id = guru_mapel.guru_id
          LEFT JOIN mata_pelajaran ON guru_mapel.mapel_id = mata_pelajaran.id
          $where_sql
          GROUP BY guru.id ORDER BY users.nama_lengkap ASC";
$result = mysqli_query($conn, $query);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Manajemen Guru</h1>
        <p class="text-slate-500">Kelola data tenaga pengajar dan mata pelajaran.</p>
    </div>
    <div class="flex gap-3">
        <button onclick="openModal('tambahModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-200 transition-all flex items-center">
            <i class="fa fa-plus mr-2"></i> Tambah Guru
        </button>
        <a href="import_guru_excel.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-emerald-200 transition-all flex items-center">
            <i class="fa fa-file-excel mr-2"></i> Import Excel
        </a>
    </div>
</div>

<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cari Guru</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama atau NIP..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm">
        </div>
        <div class="lg:col-span-2 flex items-end gap-3">
            <button type="submit" class="px-8 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100">Cari</button>
            <a href="guru.php" class="px-6 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-300 transition-all">Reset</a>
        </div>
    </form>
</div>

<?php if ($message): ?>
<script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= addslashes(htmlspecialchars($message)) ?>' });</script>
<?php endif; ?>

<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">NIP / Username</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Nama Lengkap</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Mata Pelajaran</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-mono text-sm text-indigo-600 font-bold"><?= htmlspecialchars($row['nip']) ?></td>
                    <td class="px-6 py-4 font-semibold text-slate-700"><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                    <td class="px-6 py-4 text-sm text-slate-500">
                        <?php if($row['mapel_diampu']): ?>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach(explode(', ', $row['mapel_diampu']) as $m): ?>
                                    <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded text-[10px] font-bold border border-indigo-100"><?= htmlspecialchars($m) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="italic text-slate-400">Belum ada mapel</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex justify-center gap-2">
                            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)" class="w-9 h-9 flex items-center justify-center rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white transition-all shadow-sm">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button onclick="openDeleteModal(<?= $row['id'] ?>, <?= $row['user_id'] ?>, '<?= addslashes($row['nama_lengkap']) ?>')" class="w-9 h-9 flex items-center justify-center rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all shadow-sm">
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

<!-- Modal Container -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<!-- Tambah Modal -->
<div id="tambahModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-2xl bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="bg-indigo-600 px-8 py-6 text-white"><h3 class="text-2xl font-bold italic">Tambah Guru</h3></div>
    <form action="" method="POST" class="p-8 space-y-5">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-bold text-slate-700 mb-2">Nama Lengkap</label><input type="text" name="nama_lengkap" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50"></div>
            <div><label class="block text-sm font-bold text-slate-700 mb-2">NIP (Username)</label><input type="text" name="nip" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50"></div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-bold text-slate-700 mb-2">No. Telp/HP</label><input type="text" name="no_telp" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50"></div>
            <div class="row-span-2">
                <label class="block text-sm font-bold text-slate-700 mb-2">Mata Pelajaran</label>
            <select name="mapel_ids[]" multiple class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 h-40">
                <?php mysqli_data_seek($mapel_list, 0); while($m = mysqli_fetch_assoc($mapel_list)): ?>
                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div><label class="block text-sm font-bold text-slate-700 mb-2">Alamat</label><textarea name="alamat" rows="2" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50"></textarea></div>
        <div class="pt-4 flex gap-4">
            <button type="button" onclick="closeModal('tambahModal')" class="flex-1 px-6 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50 transition-all">Batal</button>
            <button type="submit" name="tambah" class="flex-[2] px-6 py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100 transition-all">Simpan Data</button>
        </div>
    </form>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-2xl bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="bg-amber-500 px-8 py-6 text-white"><h3 class="text-2xl font-bold italic">Edit Data Guru</h3></div>
    <form action="" method="POST" class="p-8 space-y-5">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <input type="hidden" name="id" id="edit_id"><input type="hidden" name="user_id" id="edit_user_id">
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-bold text-slate-700 mb-2">Nama Lengkap</label><input type="text" name="nama_lengkap" id="edit_nama" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50"></div>
            <div><label class="block text-sm font-bold text-slate-700 mb-2">NIP</label><input type="text" name="nip" id="edit_nip" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50"></div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-bold text-slate-700 mb-2">No. Telp/HP</label><input type="text" name="no_telp" id="edit_telp" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50"></div>
            <div class="row-span-2">
                <label class="block text-sm font-bold text-slate-700 mb-2">Mata Pelajaran</label>
            <select name="mapel_ids[]" multiple class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 h-40">
                <?php mysqli_data_seek($mapel_list, 0); while($m = mysqli_fetch_assoc($mapel_list)): ?>
                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div><label class="block text-sm font-bold text-slate-700 mb-2">Alamat</label><textarea name="alamat" id="edit_alamat" rows="2" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50"></textarea></div>
        <div class="pt-4 flex gap-4">
            <button type="button" onclick="closeModal('editModal')" class="flex-1 px-6 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50 transition-all">Batal</button>
            <button type="submit" name="edit" class="flex-[2] px-6 py-3 rounded-xl bg-amber-500 text-white font-bold hover:bg-amber-600 shadow-lg shadow-amber-100 transition-all">Simpan Perubahan</button>
        </div>
    </form>
</div>

<!-- Hapus Modal -->
<div id="hapusModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-sm bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="p-8 text-center">
        <div class="w-20 h-20 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl"><i class="fa fa-trash-alt"></i></div>
        <h3 class="text-xl font-bold text-slate-800 mb-2">Hapus Guru?</h3>
        <p class="text-slate-500 mb-8 leading-relaxed text-sm">Anda akan menghapus data guru <span id="hapus_nama" class="font-bold text-slate-800"></span> beserta akun aksesnya. Tindakan ini tidak dapat dibatalkan.</p>
        <form action="" method="POST" class="flex gap-3">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            <input type="hidden" name="user_id" id="hapus_user_id">
            <button type="button" onclick="closeModal('hapusModal')" class="flex-1 px-4 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50">Batal</button>
            <button type="submit" name="hapus" class="flex-1 px-4 py-3 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 shadow-lg shadow-rose-100">Ya, Hapus</button>
        </form>
    </div>
</div>

<script>
const overlay = document.getElementById('modalOverlay');
function openModal(id) {
    const m = document.getElementById(id); overlay.classList.remove('hidden'); m.classList.remove('hidden');
    setTimeout(() => { overlay.classList.add('opacity-100'); m.classList.add('opacity-100', 'scale-100'); }, 10);
}
function closeModal(id) {
    const m = document.getElementById(id); overlay.classList.remove('opacity-100'); m.classList.remove('opacity-100', 'scale-100');
    setTimeout(() => { overlay.classList.add('hidden'); m.classList.add('hidden'); }, 300);
}
function closeAllModals() { document.querySelectorAll('.modal-content').forEach(m => { if(!m.classList.contains('hidden')) closeModal(m.id); }); }
function openEditModal(data) {
    document.getElementById('edit_id').value = data.id; document.getElementById('edit_user_id').value = data.user_id;
    document.getElementById('edit_nama').value = data.nama_lengkap; document.getElementById('edit_nip').value = data.nip;
    document.getElementById('edit_alamat').value = data.alamat || ''; document.getElementById('edit_telp').value = data.no_telp || '';

    // Set mapel selects if available
    const select = document.querySelector('#editModal select[name="mapel_ids[]"]');
    if (select && data.mapel_diampu) {
        const mapels = data.mapel_diampu.split(', ');
        Array.from(select.options).forEach(opt => {
            opt.selected = mapels.includes(opt.text);
        });
    }

    openModal('editModal');
}
function openDeleteModal(id, uid, nama) {
    document.getElementById('hapus_user_id').value = uid; document.getElementById('hapus_nama').textContent = nama;
    openModal('hapusModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
