<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka']);

$page_title = "Manajemen Jadwal Pelajaran";
$message = ''; $message_type = '';

// Selected class filter
$filter_kelas_id = (int)($_GET['kelas_id'] ?? 0);

// Fetch all classes for filter & modals
$kelases = [];
$res_k = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
while ($row = mysqli_fetch_assoc($res_k)) {
    $kelases[] = $row;
}

if ($filter_kelas_id === 0 && !empty($kelases)) {
    $filter_kelas_id = (int)$kelases[0]['id'];
}

// Fetch all teachers
$teachers = [];
$res_t = mysqli_query($conn, "SELECT g.id, u.nama_lengkap FROM guru g JOIN users u ON g.user_id = u.id ORDER BY u.nama_lengkap ASC");
while ($row = mysqli_fetch_assoc($res_t)) {
    $teachers[] = $row;
}

// Fetch all subjects
$mapels = [];
$res_m = mysqli_query($conn, "SELECT id, nama_mapel, kode_mapel FROM mata_pelajaran ORDER BY nama_mapel ASC");
while ($row = mysqli_fetch_assoc($res_m)) {
    $mapels[] = $row;
}

// Handle Add Schedule
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_jadwal'])) {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) throw new Exception("Token Keamanan Tidak Valid.");

        $kelas_id = (int)$_POST['kelas_id'];
        $hari = $_POST['hari'];
        $guru_id = (int)$_POST['guru_id'];
        $mapel_id = (int)$_POST['mapel_id'];
        $jam_ke = trim($_POST['jam_ke']);

        if ($kelas_id <= 0 || empty($hari) || $guru_id <= 0 || $mapel_id <= 0 || empty($jam_ke)) {
            throw new Exception("Semua field wajib diisi.");
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO jadwal_pelajaran (kelas_id, hari, guru_id, mapel_id, jam_ke) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isiis", $kelas_id, $hari, $guru_id, $mapel_id, $jam_ke);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Jadwal pelajaran berhasil ditambahkan!";
            $message_type = "success";
        } else {
            throw new Exception("Gagal menyimpan jadwal: " . mysqli_error($conn));
        }
        mysqli_stmt_close($stmt);
    } catch (Exception $e) {
        $message = "Gagal: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle Edit Schedule
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_jadwal'])) {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) throw new Exception("Token Keamanan Tidak Valid.");

        $jadwal_id = (int)$_POST['jadwal_id'];
        $kelas_id = (int)$_POST['kelas_id'];
        $hari = $_POST['hari'];
        $guru_id = (int)$_POST['guru_id'];
        $mapel_id = (int)$_POST['mapel_id'];
        $jam_ke = trim($_POST['jam_ke']);

        if ($jadwal_id <= 0 || $kelas_id <= 0 || empty($hari) || $guru_id <= 0 || $mapel_id <= 0 || empty($jam_ke)) {
            throw new Exception("Semua field wajib diisi.");
        }

        $stmt = mysqli_prepare($conn, "UPDATE jadwal_pelajaran SET kelas_id = ?, hari = ?, guru_id = ?, mapel_id = ?, jam_ke = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "isiisi", $kelas_id, $hari, $guru_id, $mapel_id, $jam_ke, $jadwal_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Jadwal pelajaran berhasil diperbarui!";
            $message_type = "success";
        } else {
            throw new Exception("Gagal memperbarui jadwal.");
        }
        mysqli_stmt_close($stmt);
    } catch (Exception $e) {
        $message = "Gagal: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle Delete Schedule
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $del_id = (int)($_GET['id'] ?? 0);
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        die("CSRF Token Invalid");
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM jadwal_pelajaran WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $del_id);
    if (mysqli_stmt_execute($stmt)) {
        header("Location: jadwal.php?kelas_id={$filter_kelas_id}&success_delete=1");
        exit;
    }
}

if (isset($_GET['success_delete'])) {
    $message = "Jadwal pelajaran berhasil dihapus!";
    $message_type = "success";
}

// Fetch schedules for selected class
$schedules_by_day = [
    'Senin' => [],
    'Selasa' => [],
    'Rabu' => [],
    'Kamis' => [],
    'Jumat' => [],
    'Sabtu' => []
];

if ($filter_kelas_id > 0) {
    $q_jp = "SELECT jp.*, u.nama_lengkap as nama_guru, mp.nama_mapel, mp.kode_mapel
             FROM jadwal_pelajaran jp
             JOIN guru g ON jp.guru_id = g.id
             JOIN users u ON g.user_id = u.id
             JOIN mata_pelajaran mp ON jp.mapel_id = mp.id
             WHERE jp.kelas_id = $filter_kelas_id
             ORDER BY FIELD(jp.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'), jp.jam_ke ASC";
    $res_jp = mysqli_query($conn, $q_jp);
    while ($row = mysqli_fetch_assoc($res_jp)) {
        if (isset($schedules_by_day[$row['hari']])) {
            $schedules_by_day[$row['hari']][] = $row;
        } else {
            $schedules_by_day[$row['hari']] = [$row];
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Manajemen Jadwal Pelajaran</h1>
        <p class="text-slate-500">Kelola jadwal pelajaran mingguan per kelas (Hari, Guru, Mapel, dan Jam Ke-).</p>
    </div>
    <button onclick="openModal('addJadwalModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-100 transition-all flex items-center shrink-0">
        <i class="fa fa-plus mr-2"></i> Tambah Jadwal Pelajaran
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

<!-- Class Selector Filter -->
<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/40 to-white">
    <form action="" method="GET" class="flex flex-col sm:flex-row items-end gap-4">
        <div class="flex-1 space-y-1 w-full">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Pilih Kelas</label>
            <select name="kelas_id" onchange="this.form.submit()" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold text-slate-700 shadow-sm">
                <?php foreach ($kelases as $k): ?>
                    <option value="<?= $k['id'] ?>" <?= $k['id'] == $filter_kelas_id ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<!-- Schedule Grid by Days -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php
    $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $day_badges = [
        'Senin' => 'bg-blue-600 text-white',
        'Selasa' => 'bg-indigo-600 text-white',
        'Rabu' => 'bg-purple-600 text-white',
        'Kamis' => 'bg-pink-600 text-white',
        'Jumat' => 'bg-emerald-600 text-white',
        'Sabtu' => 'bg-amber-600 text-white'
    ];
    foreach ($days as $day):
        $day_list = $schedules_by_day[$day] ?? [];
    ?>
        <div class="lux-card bg-white overflow-hidden flex flex-col justify-between border border-slate-100">
            <div>
                <!-- Card Header -->
                <div class="p-4 bg-slate-900 text-white font-black italic tracking-wider text-sm flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-lg text-xs font-black <?= $day_badges[$day] ?> uppercase"><?= $day ?></span>
                    </div>
                    <span class="text-xs font-bold text-slate-400"><?= count($day_list) ?> Mata Pelajaran</span>
                </div>

                <!-- Schedule List -->
                <div class="p-4 space-y-3">
                    <?php if (empty($day_list)): ?>
                        <div class="p-8 text-center text-slate-400 italic text-xs">Belum ada jadwal untuk hari ini.</div>
                    <?php else: ?>
                        <?php foreach ($day_list as $item): ?>
                            <div class="p-3.5 bg-slate-50 border border-slate-200/60 rounded-2xl space-y-1.5 hover:border-indigo-200 transition-all group relative">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-indigo-50 text-indigo-700">Jam Ke: <?= htmlspecialchars($item['jam_ke']) ?></span>
                                    <div class="flex items-center gap-1">
                                        <button onclick="editJadwal(<?= htmlspecialchars(json_encode($item)) ?>)" class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all flex items-center justify-center">
                                            <i class="fa fa-edit text-xs"></i>
                                        </button>
                                        <button onclick="confirmDelete(<?= $item['id'] ?>, '<?= get_csrf_token() ?>')" class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all flex items-center justify-center">
                                            <i class="fa fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="font-bold text-slate-800 text-sm leading-tight">
                                    <?= htmlspecialchars($item['nama_mapel']) ?> <span class="text-xs text-slate-400 font-mono font-normal">(<?= htmlspecialchars($item['kode_mapel']) ?>)</span>
                                </div>
                                <div class="text-xs text-slate-500 font-medium flex items-center gap-1.5">
                                    <i class="fa fa-user-tie text-indigo-400 text-[10px]"></i>
                                    <span><?= htmlspecialchars($item['nama_guru']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Add Modal -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<div id="addJadwalModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-lg bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="bg-indigo-600 px-6 py-5 text-white font-bold italic text-lg sticky top-0 z-10 flex justify-between items-center">
        <span>Tambah Jadwal Pelajaran</span>
        <button type="button" onclick="closeModal('addJadwalModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>
    <form action="" method="POST" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Kelas</label>
            <select name="kelas_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white font-bold text-slate-700">
                <?php foreach ($kelases as $k): ?>
                    <option value="<?= $k['id'] ?>" <?= $k['id'] == $filter_kelas_id ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Hari</label>
            <select name="hari" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white font-bold text-slate-700">
                <option value="Senin">Senin</option>
                <option value="Selasa">Selasa</option>
                <option value="Rabu">Rabu</option>
                <option value="Kamis">Kamis</option>
                <option value="Jumat">Jumat</option>
                <option value="Sabtu">Sabtu</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Mata Pelajaran</label>
            <select name="mapel_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white font-bold text-slate-700">
                <option value="">-- Pilih Mapel --</option>
                <?php foreach ($mapels as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?> (<?= htmlspecialchars($m['kode_mapel']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Guru Pengajar</label>
            <select name="guru_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white font-bold text-slate-700">
                <option value="">-- Pilih Guru --</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nama_lengkap']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Jam Ke-</label>
            <input type="text" name="jam_ke" placeholder="Contoh: 1-2 atau 3" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700">
        </div>

        <div class="pt-4 flex gap-3">
            <button type="button" onclick="closeModal('addJadwalModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600">Batal</button>
            <button type="submit" name="tambah_jadwal" class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100">Simpan Jadwal</button>
        </div>
    </form>
</div>

<!-- Edit Modal -->
<div id="editJadwalModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-lg bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="bg-indigo-600 px-6 py-5 text-white font-bold italic text-lg sticky top-0 z-10 flex justify-between items-center">
        <span>Edit Jadwal Pelajaran</span>
        <button type="button" onclick="closeModal('editJadwalModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>
    <form action="" method="POST" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <input type="hidden" name="jadwal_id" id="edit_jadwal_id">

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Kelas</label>
            <select name="kelas_id" id="edit_kelas_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white font-bold text-slate-700">
                <?php foreach ($kelases as $k): ?>
                    <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Hari</label>
            <select name="hari" id="edit_hari" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white font-bold text-slate-700">
                <option value="Senin">Senin</option>
                <option value="Selasa">Selasa</option>
                <option value="Rabu">Rabu</option>
                <option value="Kamis">Kamis</option>
                <option value="Jumat">Jumat</option>
                <option value="Sabtu">Sabtu</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Mata Pelajaran</label>
            <select name="mapel_id" id="edit_mapel_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white font-bold text-slate-700">
                <option value="">-- Pilih Mapel --</option>
                <?php foreach ($mapels as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?> (<?= htmlspecialchars($m['kode_mapel']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Guru Pengajar</label>
            <select name="guru_id" id="edit_guru_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white font-bold text-slate-700">
                <option value="">-- Pilih Guru --</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nama_lengkap']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Jam Ke-</label>
            <input type="text" name="jam_ke" id="edit_jam_ke" placeholder="Contoh: 1-2 atau 3" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700">
        </div>

        <div class="pt-4 flex gap-3">
            <button type="button" onclick="closeModal('editJadwalModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600">Batal</button>
            <button type="submit" name="edit_jadwal" class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100">Update Jadwal</button>
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

function editJadwal(item) {
    document.getElementById('edit_jadwal_id').value = item.id;
    document.getElementById('edit_kelas_id').value = item.kelas_id;
    document.getElementById('edit_hari').value = item.hari;
    document.getElementById('edit_mapel_id').value = item.mapel_id;
    document.getElementById('edit_guru_id').value = item.guru_id;
    document.getElementById('edit_jam_ke').value = item.jam_ke;
    openModal('editJadwalModal');
}

function confirmDelete(id, csrfToken) {
    Swal.fire({
        title: 'Hapus Jadwal?',
        text: 'Apakah Anda yakin ingin menghapus item jadwal ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'jadwal.php?kelas_id=<?= $filter_kelas_id ?>&action=delete&id=' + id + '&csrf_token=' + csrfToken;
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
