<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin', 'waka']);

$page_title = "Kategori Perangkat Guru";
$message = ''; $message_type = '';

// Handle POST actions (Add/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Token Invalid");
    }

    if (isset($_POST['tambah'])) {
        $nama = trim($_POST['nama_kategori']);
        if (!empty($nama)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO kategori_perangkat (nama_kategori) VALUES (?) ON DUPLICATE KEY UPDATE nama_kategori = VALUES(nama_kategori)");
            mysqli_stmt_bind_param($stmt, "s", $nama);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Kategori \"$nama\" berhasil ditambahkan!";
                $message_type = 'success';
            } else {
                $message = "Gagal menyimpan kategori.";
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['hapus'])) {
        $id = (int)$_POST['id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM kategori_perangkat WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Kategori berhasil dihapus!";
            $message_type = 'success';
        } else {
            $message = "Gagal menghapus kategori.";
            $message_type = 'error';
        }
    }
}

// Search Logic
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$where_sql = "";
if (!empty($search)) {
    $where_sql = " WHERE nama_kategori LIKE '%$search%'";
}

$pagin = get_pagination_data($conn, "kategori_perangkat", 15, $where_sql);
$result = mysqli_query($conn, "SELECT * FROM kategori_perangkat $where_sql ORDER BY nama_kategori ASC LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Kategori Perangkat</h1>
        <p class="text-slate-500">Kelola kategori berkas administrasi dan media pembelajaran guru.</p>
    </div>
    <button onclick="openModal('tambahModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-200 transition-all flex items-center">
        <i class="fa fa-plus mr-2"></i> Tambah Kategori
    </button>
</div>

<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cari Kategori</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama kategori..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm">
        </div>
        <div class="lg:col-span-2 flex items-end gap-3">
            <button type="submit" class="px-8 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100">Cari</button>
            <a href="kategori_perangkat.php" class="px-6 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-300 transition-all">Reset</a>
        </div>
    </form>
</div>

<?php if ($message): ?>
<script>
    Swal.fire({
        icon: '<?= $message_type ?>',
        title: '<?= $message_type === 'success' ? 'Berhasil!' : 'Gagal' ?>',
        text: '<?= addslashes(htmlspecialchars($message)) ?>',
        confirmButtonColor: '#4F46E5'
    });
</script>
<?php endif; ?>

<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">No</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Nama Kategori Perangkat</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php
                $no = $pagin['offset'] + 1;
                while ($row = mysqli_fetch_assoc($result)):
                ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-mono text-sm text-slate-400"><?= $no++ ?></td>
                    <td class="px-6 py-4 font-semibold text-slate-700"><?= htmlspecialchars($row['nama_kategori']) ?></td>
                    <td class="px-6 py-4">
                        <div class="flex justify-center gap-2">
                            <button onclick="openDeleteModal(<?= $row['id'] ?>, '<?= addslashes($row['nama_kategori']) ?>')" class="w-9 h-9 flex items-center justify-center rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all shadow-sm">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($result) === 0): ?>
                    <tr>
                        <td colspan="3" class="px-6 py-20 text-center text-slate-400 italic">Belum ada kategori yang ditambahkan.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>

<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<!-- Tambah Modal -->
<div id="tambahModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-lg max-h-[90vh] overflow-y-auto bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0">
    <div class="bg-indigo-600 px-6 sm:px-8 py-4 sm:py-6 text-white font-bold italic text-xl sm:text-2xl sticky top-0 z-10 flex justify-between items-center">
        <span>Tambah Kategori</span>
        <button type="button" onclick="closeModal('tambahModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>
    <form action="" method="POST" class="p-6 sm:p-8 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <div>
            <label class="block text-sm font-bold text-slate-700 mb-1">Nama Kategori</label>
            <input type="text" name="nama_kategori" required placeholder="Contoh: Rencana Pembelajaran Semester" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50">
        </div>
        <div class="pt-4 flex flex-col sm:flex-row gap-3">
            <button type="button" onclick="closeModal('tambahModal')" class="flex-1 px-6 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600">Batal</button>
            <button type="submit" name="tambah" class="flex-1 px-6 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100">Simpan</button>
        </div>
    </form>
</div>

<!-- Hapus Modal -->
<div id="hapusModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[90%] sm:w-full max-w-sm bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="p-8 text-center">
        <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl"><i class="fa fa-folder-minus"></i></div>
        <h3 class="text-xl font-bold text-slate-800 mb-2 italic">Hapus Kategori?</h3>
        <p class="text-sm text-slate-500 mb-8">Anda akan menghapus kategori <span id="hapus_nama" class="font-bold text-slate-800"></span>.</p>
        <form action="" method="POST" class="flex gap-2">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            <input type="hidden" name="hapus" value="1">
            <input type="hidden" name="id" id="hapus_id">
            <button type="button" onclick="closeModal('hapusModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600">Batal</button>
            <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 transition-all">Hapus</button>
        </form>
    </div>
</div>

<script>
const overlay = document.getElementById('modalOverlay');
function openModal(id) {
    const m = document.getElementById(id);
    if(!m) return;
    overlay.classList.remove('hidden');
    m.classList.remove('hidden');
    setTimeout(() => {
        overlay.classList.add('opacity-100');
        m.classList.add('opacity-100', 'scale-100');
    }, 10);
}
function closeModal(id) {
    const m = document.getElementById(id);
    if(!m) return;
    overlay.classList.remove('opacity-100');
    m.classList.remove('opacity-100', 'scale-100');
    setTimeout(() => {
        overlay.classList.add('hidden');
        m.classList.add('hidden');
    }, 300);
}
function closeAllModals() {
    document.querySelectorAll('.modal-content').forEach(m => {
        if(!m.classList.contains('hidden')) closeModal(m.id);
    });
}
function openDeleteModal(id, nama) {
    document.getElementById('hapus_id').value = id;
    document.getElementById('hapus_nama').textContent = nama;
    openModal('hapusModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
