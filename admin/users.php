<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);
$page_title = "Manajemen User";
$message = ''; $message_type = '';

// Search Logic
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$role_filter = mysqli_real_escape_string($conn, $_GET['role'] ?? '');

$where_clauses = [];
if (!empty($search)) {
    $where_clauses[] = "(nama_lengkap LIKE '%$search%' OR username LIKE '%$search%')";
}
if (!empty($role_filter)) {
    $where_clauses[] = "role = '$role_filter'";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Invalid CSRF Token");
    }
    if (isset($_POST['tambah'])) {
        $nama = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
        $user = mysqli_real_escape_string($conn, $_POST['username']);
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        if (mysqli_query($conn, "INSERT INTO users (nama_lengkap, username, password, role) VALUES ('$nama', '$user', '$pass', '$role')")) {
            $message = "User ditambahkan!"; $message_type = 'success';
        }
    } elseif (isset($_POST['edit'])) {
        $id = (int)$_POST['id'];
        $nama = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
        $user = mysqli_real_escape_string($conn, $_POST['username']);
        $role = $_POST['role'];
        $q = "UPDATE users SET nama_lengkap = '$nama', username = '$user', role = '$role' WHERE id = $id";
        if (!empty($_POST['password'])) {
            $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $q = "UPDATE users SET nama_lengkap = '$nama', username = '$user', role = '$role', password = '$pass' WHERE id = $id";
        }
        mysqli_query($conn, $q);
        $message = "User diperbarui!"; $message_type = 'success';
    } elseif (isset($_POST['hapus'])) {
        $id = (int)$_POST['id'];
        if ($id != $_SESSION['user_id']) {
            mysqli_query($conn, "DELETE FROM users WHERE id = $id");
            $message = "User dihapus!"; $message_type = 'success';
        }
    }
}

$result = mysqli_query($conn, "SELECT * FROM users $where_sql ORDER BY nama_lengkap ASC");
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Manajemen User</h1>
        <p class="text-slate-500">Kelola hak akses pengguna sistem.</p>
    </div>
    <button onclick="openModal('tambahModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-200 transition-all flex items-center">
        <i class="fa fa-plus mr-2"></i> Tambah User
    </button>
</div>

<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cari Pengguna</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama atau Username..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm">
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Filter Role</label>
            <select name="role" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700">
                <option value="">-- Semua Role --</option>
                <option value="admin" <?= $role_filter == 'admin' ? 'selected' : '' ?>>Administrator</option>
                <option value="waka" <?= $role_filter == 'waka' ? 'selected' : '' ?>>Waka Kurikulum</option>
                <option value="guru" <?= $role_filter == 'guru' ? 'selected' : '' ?>>Guru</option>
            </select>
        </div>
        <div class="lg:col-span-2 flex items-end gap-3">
            <button type="submit" class="flex-1 px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100">Terapkan Filter</button>
            <a href="users.php" class="px-6 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-300 transition-all">Reset</a>
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
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Nama Lengkap</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Username</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Role</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-semibold text-slate-700"><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                    <td class="px-6 py-4 font-mono text-sm text-indigo-600"><?= htmlspecialchars($row['username']) ?></td>
                    <td class="px-6 py-4 text-center">
                        <?php
                        $role_colors = ['admin' => 'bg-rose-100 text-rose-700 border-rose-200', 'waka' => 'bg-amber-100 text-amber-700 border-amber-200', 'guru' => 'bg-emerald-100 text-emerald-700 border-emerald-200'];
                        $color = $role_colors[$row['role']] ?? 'bg-slate-100 text-slate-700';
                        ?>
                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border <?= $color ?>"><?= $row['role'] ?></span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex justify-center gap-2">
                            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)" class="w-9 h-9 flex items-center justify-center rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white transition-all shadow-sm">
                                <i class="fa fa-edit text-xs"></i>
                            </button>
                            <?php if($row['id'] != $_SESSION['user_id']): ?>
                            <button onclick="openDeleteModal(<?= $row['id'] ?>, '<?= addslashes($row['nama_lengkap']) ?>')" class="w-9 h-9 flex items-center justify-center rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all shadow-sm">
                                <i class="fa fa-trash text-xs"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<!-- Modals for Tambah, Edit, Delete (Similar to Guru page) -->
<div id="tambahModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="bg-indigo-600 px-8 py-6 text-white font-bold italic text-2xl">Tambah User</div>
    <form action="" method="POST" class="p-8 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Nama Lengkap</label><input type="text" name="nama_lengkap" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50"></div>
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Username</label><input type="text" name="username" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50"></div>
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Password</label><input type="password" name="password" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50"></div>
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Role</label><select name="role" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white"><option value="guru">Guru</option><option value="waka">Waka Kurikulum</option><option value="admin">Administrator</option></select></div>
        <div class="pt-4 flex gap-3"><button type="button" onclick="closeModal('tambahModal')" class="flex-1 px-6 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600">Batal</button><button type="submit" name="tambah" class="flex-1 px-6 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100">Simpan</button></div>
    </form>
</div>

<div id="editModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="bg-amber-500 px-8 py-6 text-white font-bold italic text-2xl">Edit User</div>
    <form action="" method="POST" class="p-8 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <input type="hidden" name="id" id="edit_id">
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Nama Lengkap</label><input type="text" name="nama_lengkap" id="edit_nama" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50"></div>
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Username</label><input type="text" name="username" id="edit_user" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50"></div>
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Ganti Password (Kosongkan jika tidak diubah)</label><input type="password" name="password" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50"></div>
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Role</label><select name="role" id="edit_role" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 bg-white"><option value="guru">Guru</option><option value="waka">Waka Kurikulum</option><option value="admin">Administrator</option></select></div>
        <div class="pt-4 flex gap-3"><button type="button" onclick="closeModal('editModal')" class="flex-1 px-6 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600">Batal</button><button type="submit" name="edit" class="flex-1 px-6 py-2.5 rounded-xl bg-amber-500 text-white font-bold hover:bg-amber-600 shadow-lg shadow-amber-100">Simpan</button></div>
    </form>
</div>

<div id="hapusModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-sm bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="p-8 text-center">
        <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl"><i class="fa fa-user-minus"></i></div>
        <h3 class="text-xl font-bold text-slate-800 mb-2 italic">Hapus Akses?</h3>
        <p class="text-sm text-slate-500 mb-8">Anda akan mencabut akses sistem untuk <span id="hapus_nama" class="font-bold text-slate-800"></span>.</p>
        <form action="" method="POST" class="flex gap-2">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            <input type="hidden" name="id" id="hapus_id">
            <button type="button" onclick="closeModal('hapusModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600">Batal</button>
            <button type="submit" name="hapus" class="flex-1 px-4 py-2.5 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 transition-all">Ya, Hapus</button>
        </form>
    </div>
</div>

<script>
const overlay = document.getElementById('modalOverlay');
function openModal(id) { const m = document.getElementById(id); overlay.classList.remove('hidden'); m.classList.remove('hidden'); setTimeout(() => { overlay.classList.add('opacity-100'); m.classList.add('opacity-100', 'scale-100'); }, 10); }
function closeModal(id) { const m = document.getElementById(id); overlay.classList.remove('opacity-100'); m.classList.remove('opacity-100', 'scale-100'); setTimeout(() => { overlay.classList.add('hidden'); m.classList.add('hidden'); }, 300); }
function closeAllModals() { document.querySelectorAll('.modal-content').forEach(m => { if(!m.classList.contains('hidden')) closeModal(m.id); }); }
function openEditModal(d) { document.getElementById('edit_id').value = d.id; document.getElementById('edit_nama').value = d.nama_lengkap; document.getElementById('edit_user').value = d.username; document.getElementById('edit_role').value = d.role; openModal('editModal'); }
function openDeleteModal(id, n) { document.getElementById('hapus_id').value = id; document.getElementById('hapus_nama').textContent = n; openModal('hapusModal'); }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
