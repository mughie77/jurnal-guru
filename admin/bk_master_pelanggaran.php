<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka']);

$page_title = "Master Data Pelanggaran & Apresiasi";
$message = ''; $message_type = '';

// Handle Add/Edit Master
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['tambah_master']) || isset($_POST['edit_master']))) {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) throw new Exception("Token Keamanan Tidak Valid.");

        $m_id = (int)($_POST['master_id'] ?? 0);
        $kode = trim($_POST['kode'] ?? '');
        $nama = trim($_POST['nama_pelanggaran'] ?? '');
        $tipe = $_POST['tipe'] ?? 'Pelanggaran';
        $poin = (int)($_POST['poin'] ?? 10);
        $kategori = trim($_POST['kategori'] ?? 'Sedang');

        if (empty($nama) || $poin <= 0) {
            throw new Exception("Nama dan Poin wajib diisi.");
        }

        if (isset($_POST['tambah_master'])) {
            $stmt = mysqli_prepare($conn, "INSERT INTO bk_master_pelanggaran (kode, nama_pelanggaran, tipe, poin, kategori) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssis", $kode, $nama, $tipe, $poin, $kategori);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Master aturan berhasil ditambahkan!";
                $message_type = 'success';
            } else {
                throw new Exception("Gagal menyimpan data.");
            }
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE bk_master_pelanggaran SET kode=?, nama_pelanggaran=?, tipe=?, poin=?, kategori=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "sssisi", $kode, $nama, $tipe, $poin, $kategori, $m_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Master aturan berhasil diperbarui!";
                $message_type = 'success';
            } else {
                throw new Exception("Gagal memperbarui data.");
            }
        }
    } catch (Exception $e) {
        $message = "Gagal: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle Delete Master
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $del_id = (int)($_GET['id'] ?? 0);
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) die("CSRF Token Invalid");

    $stmt = mysqli_prepare($conn, "DELETE FROM bk_master_pelanggaran WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $del_id);
    if (mysqli_stmt_execute($stmt)) {
        header("Location: bk_master_pelanggaran.php?success_delete=1");
        exit;
    }
}

if (isset($_GET['success_delete'])) {
    $message = "Master aturan berhasil dihapus!";
    $message_type = 'success';
}

// Fetch all rules
$res_mp = mysqli_query($conn, "SELECT * FROM bk_master_pelanggaran ORDER BY tipe ASC, poin ASC");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Master Aturan Pelanggaran & Apresiasi</h1>
        <p class="text-slate-500 font-medium">Kelola daftar bobot poin kedisiplinan dan penghargaan siswa.</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <a href="bk_pelanggaran.php" class="px-4 py-2.5 bg-slate-200 text-slate-700 font-bold rounded-xl text-xs hover:bg-slate-300 transition-all">
            <i class="fa fa-arrow-left mr-1"></i> Kembali ke Catatan Siswa
        </a>
        <button onclick="openModal('addMasterModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-100 transition-all flex items-center text-xs sm:text-sm">
            <i class="fa fa-plus mr-2"></i> Tambah Master Aturan
        </button>
    </div>
</div>

<?php if ($message): ?>
<script>
    Swal.fire({
        icon: '<?= $message_type ?>',
        title: '<?= $message_type === 'success' ? 'Berhasil' : 'Gagal' ?>',
        text: '<?= addslashes($message) ?>',
        confirmButtonColor: '#4F46E5'
    });
</script>
<?php endif; ?>

<!-- Table Listing -->
<div class="lux-card overflow-hidden bg-white shadow-2xl rounded-3xl mb-8">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Kode</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Nama Aturan / Pelanggaran</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Tipe</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Kategori</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Bobot Poin</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php while ($r = mysqli_fetch_assoc($res_mp)):
                    $is_app = $r['tipe'] === 'Apresiasi';
                ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-mono font-bold text-indigo-600 text-xs">
                        <?= htmlspecialchars($r['kode'] ?: '-') ?>
                    </td>
                    <td class="px-6 py-4 font-bold text-slate-800 text-sm">
                        <?= htmlspecialchars($r['nama_pelanggaran']) ?>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?= $is_app ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                            <?= htmlspecialchars($r['tipe']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 font-bold text-slate-600 text-xs">
                        <?= htmlspecialchars($r['kategori']) ?>
                    </td>
                    <td class="px-6 py-4 text-center font-black text-sm <?= $is_app ? 'text-emerald-600' : 'text-rose-600' ?>">
                        <?= $is_app ? '-' : '+' ?><?= $r['poin'] ?>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            <button onclick='editMaster(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)' class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all flex items-center justify-center shadow-sm">
                                <i class="fa fa-edit text-xs"></i>
                            </button>
                            <button onclick="confirmDelete(<?= $r['id'] ?>, '<?= get_csrf_token() ?>')" class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all flex items-center justify-center shadow-sm">
                                <i class="fa fa-trash text-xs"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add / Edit Master -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<div id="addMasterModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-md bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="bg-indigo-600 px-6 py-5 text-white font-bold italic text-lg sticky top-0 z-10 flex justify-between items-center">
        <span id="modal_title">Tambah Master Aturan</span>
        <button type="button" onclick="closeModal('addMasterModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>

    <form action="" method="POST" class="p-6 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <input type="hidden" name="master_id" id="master_id" value="0">

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Kode Aturan (Opsional)</label>
            <input type="text" name="kode" id="kode" placeholder="Contoh: P01 / A01" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-mono text-slate-700 text-xs font-bold">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Nama Aturan / Pelanggaran</label>
            <input type="text" name="nama_pelanggaran" id="nama_pelanggaran" required placeholder="Contoh: Terlambat Masuk Sekolah" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-xs">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tipe</label>
                <select name="tipe" id="tipe" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-xs">
                    <option value="Pelanggaran">Pelanggaran (+Poin)</option>
                    <option value="Apresiasi">Apresiasi (-Poin Reduksi)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Bobot Poin</label>
                <input type="number" name="poin" id="poin" required value="10" min="1" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-xs">
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Kategori TIngkat</label>
            <input type="text" name="kategori" id="kategori" placeholder="Contoh: Ringan / Sedang / Berat / Prestasi" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-xs">
        </div>

        <div class="pt-4 flex gap-3">
            <button type="button" onclick="closeModal('addMasterModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600 text-xs">Batal</button>
            <button type="submit" id="submit_btn" name="tambah_master" class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100 text-xs">Simpan Master</button>
        </div>
    </form>
</div>

<script>
function openModal(id) {
    const modal = document.getElementById(id);
    const overlay = document.getElementById('modalOverlay');
    overlay.classList.remove('hidden');
    modal.classList.remove('hidden');
    setTimeout(() => {
        overlay.classList.add('opacity-100');
        modal.classList.remove('scale-95', 'opacity-0');
    }, 10);
}

function closeModal(id) {
    const modal = document.getElementById(id);
    const overlay = document.getElementById('modalOverlay');
    overlay.classList.remove('opacity-100');
    modal.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        overlay.classList.add('hidden');
        modal.classList.add('hidden');
    }, 300);
}

function closeAllModals() {
    document.querySelectorAll('.modal-content').forEach(m => {
        if (!m.classList.contains('hidden')) closeModal(m.id);
    });
}

function editMaster(item) {
    document.getElementById('modal_title').textContent = 'Edit Master Aturan';
    document.getElementById('master_id').value = item.id;
    document.getElementById('kode').value = item.kode || '';
    document.getElementById('nama_pelanggaran').value = item.nama_pelanggaran;
    document.getElementById('tipe').value = item.tipe;
    document.getElementById('poin').value = item.poin;
    document.getElementById('kategori').value = item.kategori || '';

    const btn = document.getElementById('submit_btn');
    btn.name = 'edit_master';
    btn.textContent = 'Update Master';

    openModal('addMasterModal');
}

function confirmDelete(id, csrfToken) {
    Swal.fire({
        title: 'Hapus Aturan Master?',
        text: 'Apakah Anda yakin ingin menghapus aturan ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'bk_master_pelanggaran.php?action=delete&id=' + id + '&csrf_token=' + csrfToken;
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
