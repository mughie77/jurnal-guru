<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka']);

$page_title = "Manajemen Jadwal Piket Guru";
$message = ''; $message_type = '';

// Handle Add Piket
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_piket'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    $hari = $_POST['hari'];
    $guru_id = (int)$_POST['guru_id'];

    if ($guru_id > 0) {
        $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO piket_guru (hari, guru_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "si", $hari, $guru_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Piket guru berhasil ditambahkan!";
            $message_type = "success";
        } else {
            $message = "Gagal menambahkan piket guru.";
            $message_type = "error";
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle Delete Piket
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $del_id = (int)($_GET['id'] ?? 0);
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        die("CSRF Token Invalid");
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM piket_guru WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $del_id);
    if (mysqli_stmt_execute($stmt)) {
        header("Location: piket.php?success_delete=1");
        exit;
    }
}

// Success deletes alert
if (isset($_GET['success_delete'])) {
    $message = "Piket guru berhasil dihapus!";
    $message_type = "success";
}

// Fetch assigned piket
$piket = [
    'Senin' => [],
    'Selasa' => [],
    'Rabu' => [],
    'Kamis' => [],
    'Jumat' => []
];

$query = "SELECT pg.*, u.nama_lengkap, g.nip
          FROM piket_guru pg
          JOIN guru g ON pg.guru_id = g.id
          JOIN users u ON g.user_id = u.id
          ORDER BY pg.hari, u.nama_lengkap ASC";
$res = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($res)) {
    $piket[$row['hari']][] = $row;
}

// Get all teachers for selector
$teachers = [];
$res_t = mysqli_query($conn, "SELECT g.id, u.nama_lengkap FROM guru g JOIN users u ON g.user_id = u.id ORDER BY u.nama_lengkap ASC");
while ($row = mysqli_fetch_assoc($res_t)) {
    $teachers[] = $row;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Jadwal Piket Guru</h1>
        <p class="text-slate-500">Konfigurasi daftar guru piket harian mulai Senin sampai Jumat.</p>
    </div>
    <button onclick="openModal('addPiketModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-100 transition-all flex items-center">
        <i class="fa fa-plus mr-2"></i> Tambah Guru Piket
    </button>
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

<!-- Daily Cards Grid -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6">
    <?php
    $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
    $day_colors = [
        'Senin' => 'from-blue-500 to-blue-600 text-blue-100',
        'Selasa' => 'from-indigo-500 to-indigo-600 text-indigo-100',
        'Rabu' => 'from-purple-500 to-purple-600 text-purple-100',
        'Kamis' => 'from-pink-500 to-pink-600 text-pink-100',
        'Jumat' => 'from-emerald-500 to-emerald-600 text-emerald-100'
    ];
    foreach ($days as $day): ?>
        <div class="lux-card bg-white overflow-hidden flex flex-col justify-between min-h-[350px]">
            <div>
                <!-- Header -->
                <div class="p-4 bg-gradient-to-br <?= $day_colors[$day] ?> font-black italic tracking-wider text-sm flex items-center justify-between">
                    <span><?= strtoupper($day) ?></span>
                    <span class="bg-white/20 text-white px-2 py-0.5 rounded-full text-[10px] font-black"><?= count($piket[$day]) ?> GURU</span>
                </div>

                <!-- Teacher list -->
                <div class="p-4 space-y-3">
                    <?php if (empty($piket[$day])): ?>
                        <p class="text-xs text-slate-400 italic text-center py-10">Belum ada guru piket.</p>
                    <?php else: ?>
                        <?php foreach ($piket[$day] as $p_row): ?>
                            <div class="p-3 bg-slate-50 border border-slate-100 rounded-xl flex items-center justify-between group">
                                <div class="truncate max-w-[80%]">
                                    <span class="text-xs font-bold text-slate-800 leading-tight block truncate" title="<?= htmlspecialchars($p_row['nama_lengkap']) ?>"><?= htmlspecialchars($p_row['nama_lengkap']) ?></span>
                                    <span class="text-[9px] text-slate-400 font-mono block mt-0.5"><?= $p_row['nip'] ?: 'NIP: -' ?></span>
                                </div>
                                <button onclick="confirmDelete(<?= $p_row['id'] ?>, '<?= get_csrf_token() ?>')" class="w-6 h-6 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all flex items-center justify-center shrink-0 opacity-0 group-hover:opacity-100 shadow-sm">
                                    <i class="fa fa-times text-[10px]"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Add Piket Modal -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<div id="addPiketModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-md bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0">
    <div class="bg-indigo-600 px-6 py-5 text-white font-bold italic text-lg sm:text-xl sticky top-0 z-10 flex justify-between items-center rounded-t-3xl">
        <span>Tambah Guru Piket</span>
        <button type="button" onclick="closeModal('addPiketModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>
    <form action="" method="POST" class="p-6 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Hari</label>
            <select name="hari" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white font-bold text-slate-700">
                <option value="Senin">Senin</option>
                <option value="Selasa">Selasa</option>
                <option value="Rabu">Rabu</option>
                <option value="Kamis">Kamis</option>
                <option value="Jumat">Jumat</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Guru</label>
            <select name="guru_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white font-bold text-slate-700">
                <option value="">-- Pilih Guru --</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nama_lengkap']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="pt-4 flex gap-3">
            <button type="button" onclick="closeModal('addPiketModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600">Batal</button>
            <button type="submit" name="tambah_piket" class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100">Simpan</button>
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

function confirmDelete(id, csrfToken) {
    Swal.fire({
        title: 'Hapus Piket?',
        text: 'Apakah Anda yakin ingin menghapus guru ini dari daftar piket?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'piket.php?action=delete&id=' + id + '&csrf_token=' + csrfToken;
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
