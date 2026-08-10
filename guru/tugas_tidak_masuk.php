<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id, NIP FROM guru WHERE user_id = $user_id");
if(mysqli_num_rows($guru_res) == 0) die("Error: Data guru tidak ditemukan.");
$g_data = mysqli_fetch_assoc($guru_res);
$guru_id = $g_data['id'];

$page_title = "Tugas Guru Tidak Masuk";
$message = ''; $message_type = '';

// Day Mapping for Piket detection
$day_eng = date('l');
$day_map = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];
$today_hari = $day_map[$day_eng] ?? '';

// Check if currently logged-in teacher is on piket today
$is_piket_today = false;
$q_piket = mysqli_query($conn, "SELECT id FROM piket_guru WHERE hari = '" . mysqli_real_escape_string($conn, $today_hari) . "' AND guru_id = $guru_id");
if (mysqli_num_rows($q_piket) > 0) {
    $is_piket_today = true;
}

// Handle Task Submission (Pemberian Tugas)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_tugas'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    $kelas_id = (int)$_POST['kelas_id'];
    $tanggal = $_POST['tanggal'];
    $keterangan_tugas = mysqli_real_escape_string($conn, $_POST['keterangan_tugas']);
    $latitude = $_POST['latitude'] ?: null;
    $longitude = $_POST['longitude'] ?: null;

    // Handle File Lampiran upload
    $file_lampiran = null;
    if (!empty($_FILES['file_lampiran']['name'])) {
        $target_dir = __DIR__ . "/../uploads/tugas/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        // Secure .htaccess inside uploads/tugas/
        $htaccess_content = "# Prevent PHP execution\n<Files \"*.php\">\n    Order Deny,Allow\n    Deny from all\n</Files>\n";
        file_put_contents($target_dir . '.htaccess', $htaccess_content);

        $file_ext = strtolower(pathinfo($_FILES["file_lampiran"]["name"], PATHINFO_EXTENSION));
        $allowed_ext = ['png', 'jpg', 'jpeg', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];

        if (in_array($file_ext, $allowed_ext)) {
            $filename = "tugas_" . time() . "_" . uniqid() . "." . $file_ext;
            $target_file = $target_dir . $filename;

            if (move_uploaded_file($_FILES["file_lampiran"]["tmp_name"], $target_file)) {
                $file_lampiran = $filename;
            }
        } else {
            $message = "Format file lampiran tidak didukung!";
            $message_type = "error";
        }
    }

    if ($message_type !== 'error' && $kelas_id > 0 && !empty($tanggal)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO tugas_kelas (guru_id, kelas_id, tanggal, keterangan_tugas, file_lampiran, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iisssss", $guru_id, $kelas_id, $tanggal, $keterangan_tugas, $file_lampiran, $latitude, $longitude);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Tugas kelas berhasil dikirim!";
            $message_type = "success";
        } else {
            $message = "Gagal mengirim tugas.";
            $message_type = "error";
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle Check/Uncheck (Dicentang / Completed status)
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status') {
    $task_id = (int)$_GET['id'];
    $new_status = (int)$_GET['status'];
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        die("CSRF Token Invalid");
    }

    // Toggle completed status (Guru Piket or the Owner can check/uncheck)
    $stmt = mysqli_prepare($conn, "UPDATE tugas_kelas SET status_selesai = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $new_status, $task_id);
    if (mysqli_stmt_execute($stmt)) {
        header("Location: tugas_tidak_masuk.php?success_toggle=1");
        exit;
    }
}

if (isset($_GET['success_toggle'])) {
    $message = "Status tugas kelas berhasil diperbarui!";
    $message_type = "success";
}

// Fetch classes taught by this teacher for dropdown selection
$classes_list = mysqli_query($conn, "SELECT DISTINCT k.id, k.nama_kelas
                                     FROM kelas k
                                     JOIN guru_mapel gm ON k.id = gm.mapel_id OR 1=1
                                     ORDER BY k.nama_kelas ASC");

// Fetch tasks submitted BY this teacher (Absent Task History)
$my_tasks = [];
$res_my = mysqli_query($conn, "SELECT tk.*, k.nama_kelas
                               FROM tugas_kelas tk
                               JOIN kelas k ON tk.kelas_id = k.id
                               WHERE tk.guru_id = $guru_id
                               ORDER BY tk.tanggal DESC, tk.created_at DESC");
while ($row = mysqli_fetch_assoc($res_my)) {
    $my_tasks[] = $row;
}

// Fetch tasks for Guru Piket today
$piket_tasks = [];
if ($is_piket_today) {
    $today_date = date('Y-m-d');
    $res_piket = mysqli_query($conn, "SELECT tk.*, k.nama_kelas, u.nama_lengkap as nama_guru
                                      FROM tugas_kelas tk
                                      JOIN kelas k ON tk.kelas_id = k.id
                                      JOIN guru g ON tk.guru_id = g.id
                                      JOIN users u ON g.user_id = u.id
                                      WHERE tk.tanggal = '$today_date'
                                      ORDER BY k.nama_kelas ASC");
    while ($row = mysqli_fetch_assoc($res_piket)) {
        $piket_tasks[] = $row;
    }
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Tugas Guru Tidak Masuk</h1>
        <p class="text-slate-500">Kelola pemberian tugas saat berhalangan hadir, serta pantau tugas kelas jika bertugas Piket.</p>
    </div>
    <div class="flex gap-3">
        <!-- Display Piket Indicator Badge -->
        <?php if ($is_piket_today): ?>
            <span class="px-4 py-2.5 bg-emerald-100 text-emerald-800 text-xs font-black uppercase tracking-widest rounded-xl border border-emerald-200 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Piket Hari Ini (<?= $today_hari ?>)
            </span>
        <?php else: ?>
            <span class="px-4 py-2.5 bg-slate-100 text-slate-500 text-xs font-black uppercase tracking-widest rounded-xl border border-slate-200 flex items-center gap-2">
                Bukan Jadwal Piket
            </span>
        <?php endif; ?>
        <button onclick="openModal('addTugasModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-100 transition-all flex items-center">
            <i class="fa fa-paper-plane mr-2"></i> Kirim Tugas Kelas
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

<!-- Guru Piket Monitoring Grid (Only visible if teacher is on Piket today) -->
<?php if ($is_piket_today): ?>
<div class="lux-card p-6 sm:p-8 bg-gradient-to-br from-emerald-50 to-white border-2 border-emerald-100 rounded-3xl mb-10">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-xl font-black text-emerald-900 italic tracking-tight uppercase flex items-center gap-2">
                <i class="fa fa-clipboard-list text-emerald-600"></i> Tugas Piket Hari Ini
            </h3>
            <p class="text-slate-500 text-xs mt-1">Daftar semua tugas dari guru tidak masuk hari ini untuk kelas binaan Anda.</p>
        </div>
        <span class="bg-emerald-600 text-white font-black text-xs px-3 py-1 rounded-full uppercase tracking-wider"><?= count($piket_tasks) ?> TUGAS</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-emerald-500/10 border-b border-emerald-100">
                    <th class="px-4 py-3 text-[10px] font-black text-emerald-800 uppercase tracking-widest">Kelas</th>
                    <th class="px-4 py-3 text-[10px] font-black text-emerald-800 uppercase tracking-widest">Guru Pemberi</th>
                    <th class="px-4 py-3 text-[10px] font-black text-emerald-800 uppercase tracking-widest">Deskripsi / Lampiran</th>
                    <th class="px-4 py-3 text-[10px] font-black text-emerald-800 uppercase tracking-widest text-center">Status Selesai</th>
                    <th class="px-4 py-3 text-[10px] font-black text-emerald-800 uppercase tracking-widest text-center">Tandai</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-emerald-100/50">
                <?php if (empty($piket_tasks)): ?>
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-xs text-slate-400 italic">Tidak ada sebaran tugas guru piket hari ini. Kelas aman sejahtera!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($piket_tasks as $pt): ?>
                        <tr class="hover:bg-emerald-50/20 transition-colors">
                            <td class="px-4 py-3 font-black text-slate-800 text-sm"><?= htmlspecialchars($pt['nama_kelas']) ?></td>
                            <td class="px-4 py-3 font-semibold text-slate-700 text-xs"><?= htmlspecialchars($pt['nama_guru']) ?></td>
                            <td class="px-4 py-3 text-xs text-slate-600 max-w-sm">
                                <div class="italic mb-1 leading-relaxed font-semibold">"<?= htmlspecialchars($pt['keterangan_tugas']) ?>"</div>
                                <div class="flex items-center gap-2.5 mt-2 flex-wrap">
                                    <?php if ($pt['file_lampiran']): ?>
                                        <a href="<?= BASE_URL ?>uploads/tugas/<?= $pt['file_lampiran'] ?>" target="_blank" class="text-indigo-600 font-black hover:underline inline-flex items-center gap-1 text-[10px] bg-indigo-50 px-2 py-0.5 rounded">
                                            <i class="fa fa-download"></i> Lampiran File
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($pt['latitude']) && !empty($pt['longitude'])): ?>
                                        <a href="https://www.google.com/maps?q=<?= $pt['latitude'] ?>,<?= $pt['longitude'] ?>" target="_blank" class="text-rose-600 font-black hover:underline inline-flex items-center gap-1 text-[10px] bg-rose-50 px-2 py-0.5 rounded">
                                            <i class="fa fa-map-marker-alt"></i> Pin Lokasi
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php if ($pt['status_selesai']): ?>
                                    <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase rounded-full">Selesai</span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 bg-slate-100 text-slate-400 text-[10px] font-black uppercase rounded-full">Belum</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php if ($pt['status_selesai']): ?>
                                    <a href="tugas_tidak_masuk.php?action=toggle_status&id=<?= $pt['id'] ?>&status=0&csrf_token=<?= get_csrf_token() ?>" class="inline-flex items-center justify-center w-8 h-8 bg-slate-100 text-slate-500 hover:bg-slate-200 rounded-lg transition-all" title="Tandai Belum Selesai">
                                        <i class="fa fa-undo text-xs"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="tugas_tidak_masuk.php?action=toggle_status&id=<?= $pt['id'] ?>&status=1&csrf_token=<?= get_csrf_token() ?>" class="inline-flex items-center justify-center w-8 h-8 bg-emerald-500 text-white hover:bg-emerald-600 rounded-lg transition-all shadow-md shadow-emerald-100" title="Tandai Selesai">
                                        <i class="fa fa-check text-xs"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Absent Teacher Task History -->
<div class="lux-card p-6 sm:p-8 bg-white">
    <h3 class="text-xl font-bold text-slate-800 italic uppercase tracking-widest mb-6">Arsip Pemberian Tugas Saya</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-widest">Tanggal</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-widest">Kelas</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-widest">Tugas & Lampiran</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (empty($my_tasks)): ?>
                    <tr>
                        <td colspan="4" class="px-4 py-12 text-center text-xs text-slate-400 italic">Anda belum pernah mengirim tugas kelas berhalangan hadir.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($my_tasks as $mt): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-4 py-3 font-semibold text-slate-700 text-xs"><?= date('d M Y', strtotime($mt['tanggal'])) ?></td>
                            <td class="px-4 py-3 font-black text-slate-800 text-sm"><?= htmlspecialchars($mt['nama_kelas']) ?></td>
                            <td class="px-4 py-3 text-xs text-slate-600 max-w-md">
                                <div class="italic leading-relaxed font-semibold">"<?= htmlspecialchars($mt['keterangan_tugas']) ?>"</div>
                                <div class="flex items-center gap-2.5 mt-2 flex-wrap">
                                    <?php if ($mt['file_lampiran']): ?>
                                        <a href="<?= BASE_URL ?>uploads/tugas/<?= $mt['file_lampiran'] ?>" target="_blank" class="text-indigo-600 font-black hover:underline inline-flex items-center gap-1 text-[10px] bg-indigo-50 px-2 py-0.5 rounded">
                                            <i class="fa fa-download"></i> Lampiran File
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($mt['latitude']) && !empty($mt['longitude'])): ?>
                                        <a href="https://www.google.com/maps?q=<?= $mt['latitude'] ?>,<?= $mt['longitude'] ?>" target="_blank" class="text-rose-600 font-black hover:underline inline-flex items-center gap-1 text-[10px] bg-rose-50 px-2 py-0.5 rounded">
                                            <i class="fa fa-map-marker-alt"></i> Pin Lokasi
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php if ($mt['status_selesai']): ?>
                                    <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase rounded-full">Selesai</span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 bg-slate-100 text-slate-400 text-[10px] font-black uppercase rounded-full">Belum</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Tugas Modal -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<div id="addTugasModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-lg max-h-[90vh] overflow-y-auto bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0">
    <div class="bg-indigo-600 px-6 py-5 text-white font-bold italic text-lg sm:text-xl sticky top-0 z-10 flex justify-between items-center rounded-t-3xl">
        <span>Kirim Tugas Kelas (Guru Absen)</span>
        <button type="button" onclick="closeModal('addTugasModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>
    <form action="" method="POST" enctype="multipart/form-data" id="add-tugas-form" class="p-6 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <input type="hidden" name="latitude" id="task-lat">
        <input type="hidden" name="longitude" id="task-lng">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Kelas</label>
                <select name="kelas_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white font-bold text-slate-700 text-sm">
                    <option value="">-- Kelas --</option>
                    <?php mysqli_data_seek($classes_list, 0); while ($row = mysqli_fetch_assoc($classes_list)): ?>
                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['nama_kelas']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal</label>
                <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required class="w-full px-4 py-2 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-semibold text-slate-700 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Keterangan / Deskripsi Tugas</label>
            <textarea name="keterangan_tugas" rows="4" required placeholder="Tulis instruksi pengerjaan tugas secara rinci untuk siswa..." class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-medium text-slate-600 text-sm"></textarea>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Upload File Lampiran (Opsional)</label>
            <input type="file" name="file_lampiran" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 transition-all">
            <p class="text-[9px] text-slate-400 italic mt-1">Mendukung: PDF, DOCX, XLSX, PNG, JPG (Maks 5MB)</p>
        </div>

        <div class="pt-4 flex gap-3">
            <button type="button" onclick="closeModal('addTugasModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600">Batal</button>
            <button type="submit" name="tambah_tugas" class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100">Kirim Tugas</button>
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

document.addEventListener('DOMContentLoaded', function() {
    const taskForm = document.getElementById('add-tugas-form');
    const taskLat = document.getElementById('task-lat');
    const taskLng = document.getElementById('task-lng');

    if (taskForm) {
        taskForm.addEventListener('submit', function(e) {
            if (taskLat.value && taskLng.value) {
                return true;
            }

            e.preventDefault();

            Swal.fire({
                title: 'Mendeteksi Lokasi GPS...',
                text: 'Mengambil koordinat Anda untuk melampirkan pin lokasi pengiriman tugas kelas.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    Swal.close();
                    taskLat.value = position.coords.latitude;
                    taskLng.value = position.coords.longitude;
                    taskForm.submit();
                }, function(error) {
                    Swal.close();
                    // Fallback proceed even if GPS fails but log warning
                    Swal.fire({
                        icon: 'warning',
                        title: 'Akses Lokasi Gagal',
                        text: 'Koordinat GPS tidak terdeteksi. Tugas akan tetap dikirim tanpa pin lokasi.',
                        confirmButtonColor: '#4F46E5'
                    }).then(() => {
                        taskForm.submit();
                    });
                }, { enableHighAccuracy: true, timeout: 8000 });
            } else {
                Swal.close();
                taskForm.submit();
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
