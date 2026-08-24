<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin', 'waka']);
$page_title = "Lokasi PKL";
$message = ''; $message_type = '';

if (!$active_tahun_id) die("Error: Tidak ada tahun pelajaran aktif.");

// Get list of teachers for Pembimbing dropdown
$res_gurus = mysqli_query($conn, "SELECT g.id, u.nama_lengkap, g.nip FROM guru g JOIN users u ON g.user_id = u.id ORDER BY u.nama_lengkap ASC");
$gurus = [];
while ($g = mysqli_fetch_assoc($res_gurus)) $gurus[] = $g;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    // Action 1: Tambah Tempat PKL & Akun DU/DI
    if (isset($_POST['tambah_tempat'])) {
        $nama_tempat = trim($_POST['nama_tempat'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');
        $radius_absen = (int)($_POST['radius_absen'] ?? 50);
        $guru_pembimbing_id = !empty($_POST['guru_pembimbing_id']) ? (int)$_POST['guru_pembimbing_id'] : null;
        $pembimbing_dudi = trim($_POST['pembimbing_dudi'] ?? '');
        $no_telp_dudi = trim($_POST['no_telp_dudi'] ?? '');
        $username_dudi = trim($_POST['username_dudi'] ?? '');
        $password_dudi = $_POST['password_dudi'] ?? '';

        if (empty($nama_tempat)) {
            $message = "Nama Tempat PKL wajib diisi."; $message_type = 'error';
        } else {
            mysqli_begin_transaction($conn);
            try {
                $user_dudi_id = null;
                // Create user DU/DI if username & password provided
                if (!empty($username_dudi) && !empty($password_dudi)) {
                    // Check duplicate username
                    $chk_u = mysqli_query($conn, "SELECT id FROM users WHERE username = '" . mysqli_real_escape_string($conn, $username_dudi) . "'");
                    if (mysqli_num_rows($chk_u) > 0) {
                        throw new Exception("Username DU/DI '$username_dudi' sudah digunakan.");
                    }
                    $hash_pass = password_hash($password_dudi, PASSWORD_BCRYPT);
                    $stmt_u = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, username, password, role) VALUES (?, ?, ?, 'dudi')");
                    $nama_dudi_full = !empty($pembimbing_dudi) ? "$pembimbing_dudi ($nama_tempat)" : "Pembimbing $nama_tempat";
                    mysqli_stmt_bind_param($stmt_u, "sss", $nama_dudi_full, $username_dudi, $hash_pass);
                    mysqli_stmt_execute($stmt_u);
                    $user_dudi_id = mysqli_insert_id($conn);
                }

                $stmt = mysqli_prepare($conn, "INSERT INTO tempat_pkl (nama_tempat, alamat, latitude, longitude, radius_absen, guru_pembimbing_id, pembimbing_dudi, no_telp_dudi, user_dudi_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "ssssiissi", $nama_tempat, $alamat, $latitude, $longitude, $radius_absen, $guru_pembimbing_id, $pembimbing_dudi, $no_telp_dudi, $user_dudi_id);
                mysqli_stmt_execute($stmt);

                mysqli_commit($conn);
                $message = "Tempat PKL & Akun DU/DI berhasil ditambahkan!"; $message_type = 'success';
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = "Gagal menambahkan tempat PKL: " . $e->getMessage(); $message_type = 'error';
            }
        }
    }
    // Action 2: Edit Tempat PKL
    elseif (isset($_POST['edit_tempat'])) {
        $id = (int)$_POST['id'];
        $nama_tempat = trim($_POST['nama_tempat'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');
        $radius_absen = (int)($_POST['radius_absen'] ?? 50);
        $guru_pembimbing_id = !empty($_POST['guru_pembimbing_id']) ? (int)$_POST['guru_pembimbing_id'] : null;
        $pembimbing_dudi = trim($_POST['pembimbing_dudi'] ?? '');
        $no_telp_dudi = trim($_POST['no_telp_dudi'] ?? '');
        $username_dudi = trim($_POST['username_dudi'] ?? '');
        $password_dudi = $_POST['password_dudi'] ?? '';

        if (empty($nama_tempat)) {
            $message = "Nama Tempat PKL wajib diisi."; $message_type = 'error';
        } else {
            mysqli_begin_transaction($conn);
            try {
                // Get existing user_dudi_id
                $res_curr = mysqli_query($conn, "SELECT user_dudi_id FROM tempat_pkl WHERE id = $id");
                $row_curr = mysqli_fetch_assoc($res_curr);
                $user_dudi_id = $row_curr['user_dudi_id'] ?? null;

                if (!empty($username_dudi)) {
                    if ($user_dudi_id) {
                        // Update existing DU/DI user
                        $nama_dudi_full = !empty($pembimbing_dudi) ? "$pembimbing_dudi ($nama_tempat)" : "Pembimbing $nama_tempat";
                        if (!empty($password_dudi)) {
                            $hash_pass = password_hash($password_dudi, PASSWORD_BCRYPT);
                            $stmt_u = mysqli_prepare($conn, "UPDATE users SET nama_lengkap = ?, username = ?, password = ? WHERE id = ?");
                            mysqli_stmt_bind_param($stmt_u, "sssi", $nama_dudi_full, $username_dudi, $hash_pass, $user_dudi_id);
                        } else {
                            $stmt_u = mysqli_prepare($conn, "UPDATE users SET nama_lengkap = ?, username = ? WHERE id = ?");
                            mysqli_stmt_bind_param($stmt_u, "ssi", $nama_dudi_full, $username_dudi, $user_dudi_id);
                        }
                        mysqli_stmt_execute($stmt_u);
                    } else if (!empty($password_dudi)) {
                        // Create new DU/DI user
                        $hash_pass = password_hash($password_dudi, PASSWORD_BCRYPT);
                        $stmt_u = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, username, password, role) VALUES (?, ?, ?, 'dudi')");
                        $nama_dudi_full = !empty($pembimbing_dudi) ? "$pembimbing_dudi ($nama_tempat)" : "Pembimbing $nama_tempat";
                        mysqli_stmt_bind_param($stmt_u, "sss", $nama_dudi_full, $username_dudi, $hash_pass);
                        mysqli_stmt_execute($stmt_u);
                        $user_dudi_id = mysqli_insert_id($conn);
                    }
                }

                $stmt = mysqli_prepare($conn, "UPDATE tempat_pkl SET nama_tempat = ?, alamat = ?, latitude = ?, longitude = ?, radius_absen = ?, guru_pembimbing_id = ?, pembimbing_dudi = ?, no_telp_dudi = ?, user_dudi_id = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "ssssiissii", $nama_tempat, $alamat, $latitude, $longitude, $radius_absen, $guru_pembimbing_id, $pembimbing_dudi, $no_telp_dudi, $user_dudi_id, $id);
                mysqli_stmt_execute($stmt);

                mysqli_commit($conn);
                $message = "Tempat PKL berhasil diperbarui!"; $message_type = 'success';
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = "Gagal memperbarui tempat PKL: " . $e->getMessage(); $message_type = 'error';
            }
        }
    }
    // Action 3: Hapus Tempat PKL
    elseif (isset($_POST['hapus_tempat'])) {
        $id = (int)$_POST['id'];
        $res_curr = mysqli_query($conn, "SELECT user_dudi_id FROM tempat_pkl WHERE id = $id");
        $row_curr = mysqli_fetch_assoc($res_curr);
        $user_dudi_id = $row_curr['user_dudi_id'] ?? null;

        $stmt = mysqli_prepare($conn, "DELETE FROM tempat_pkl WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            if ($user_dudi_id) {
                mysqli_query($conn, "DELETE FROM users WHERE id = $user_dudi_id");
            }
            $message = "Tempat PKL dihapus!"; $message_type = 'success';
        } else {
            $message = "Gagal menghapus tempat PKL."; $message_type = 'error';
        }
    }
}

// Get list of Tempat PKL
$res_tempat = mysqli_query($conn, "SELECT tp.*, u.nama_lengkap as nama_guru_pembimbing, ud.username as username_dudi,
                                   (SELECT COUNT(*) FROM siswa_pkl sp WHERE sp.tempat_pkl_id = tp.id AND sp.tahun_pelajaran_id = $active_tahun_id AND sp.status = 'aktif') as jml_siswa
                                   FROM tempat_pkl tp
                                   LEFT JOIN guru g ON tp.guru_pembimbing_id = g.id
                                   LEFT JOIN users u ON g.user_id = u.id
                                   LEFT JOIN users ud ON tp.user_dudi_id = ud.id
                                   ORDER BY tp.nama_tempat ASC");
$tempats = [];
while ($tp = mysqli_fetch_assoc($res_tempat)) $tempats[] = $tp;

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Leaflet JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Lokasi Industri / Tempat PKL</h1>
        <p class="text-slate-500 font-medium">Kelola lokasi industri, titik GPS geofencing PKL, Guru Pembimbing, dan Akun Pembimbing DU/DI.</p>
    </div>
    <div>
        <button onclick="openModal('tambahTempatModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-2xl font-bold shadow-lg shadow-indigo-200 transition-all flex items-center">
            <i class="fa fa-plus-circle mr-2"></i> Tambah Lokasi PKL
        </button>
    </div>
</div>

<?php if ($message): ?>
<script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= addslashes(htmlspecialchars($message)) ?>', confirmButtonColor: '#4f46e5' });</script>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if (empty($tempats)): ?>
    <div class="col-span-full lux-card p-12 text-center text-slate-400 italic">Belum ada lokasi PKL yang ditambahkan.</div>
    <?php else: ?>
        <?php foreach ($tempats as $tp): ?>
        <div class="lux-card p-6 bg-white shadow-xl rounded-3xl border border-slate-100 flex flex-col justify-between hover:border-indigo-200 transition-all">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-600 border border-indigo-100">
                        <?= $tp['jml_siswa'] ?> Siswa PKL
                    </span>
                    <div class="flex gap-1">
                        <button onclick="openEditTempatModal(<?= htmlspecialchars(json_encode($tp)) ?>)" class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white flex items-center justify-center transition-all">
                            <i class="fa fa-edit text-xs"></i>
                        </button>
                        <button onclick="openDeleteTempatModal(<?= $tp['id'] ?>, '<?= addslashes(htmlspecialchars($tp['nama_tempat'])) ?>')" class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white flex items-center justify-center transition-all">
                            <i class="fa fa-trash text-xs"></i>
                        </button>
                    </div>
                </div>
                <h3 class="font-black text-slate-800 text-lg mb-1"><?= htmlspecialchars($tp['nama_tempat']) ?></h3>
                <p class="text-xs text-slate-500 italic mb-4 line-clamp-2"><?= htmlspecialchars($tp['alamat'] ?? 'Alamat belum diatur') ?></p>

                <div class="space-y-2 text-xs border-t border-slate-50 pt-3">
                    <div class="flex items-center justify-between text-slate-600 font-semibold">
                        <span class="text-slate-400 text-[10px] uppercase font-black">Guru Pembimbing:</span>
                        <span class="text-indigo-600 font-bold"><?= htmlspecialchars($tp['nama_guru_pembimbing'] ?? '-') ?></span>
                    </div>
                    <div class="flex items-center justify-between text-slate-600 font-semibold">
                        <span class="text-slate-400 text-[10px] uppercase font-black">Pembimbing DU/DI:</span>
                        <span class="font-bold"><?= htmlspecialchars($tp['pembimbing_dudi'] ?? '-') ?></span>
                    </div>
                    <div class="flex items-center justify-between text-slate-600 font-semibold">
                        <span class="text-slate-400 text-[10px] uppercase font-black">Akun Login DU/DI:</span>
                        <span class="font-mono text-emerald-600 font-bold"><?= htmlspecialchars($tp['username_dudi'] ?? 'Belum dibuat') ?></span>
                    </div>
                    <div class="flex items-center justify-between text-slate-600 font-semibold">
                        <span class="text-slate-400 text-[10px] uppercase font-black">Geofence GPS:</span>
                        <?php if (!empty($tp['latitude']) && !empty($tp['longitude'])): ?>
                            <a href="https://www.google.com/maps/search/?api=1&query=<?= $tp['latitude'] ?>,<?= $tp['longitude'] ?>" target="_blank" class="text-emerald-600 hover:underline font-bold inline-flex items-center gap-1">
                                <i class="fa fa-map-marker-alt"></i> Set (<?= $tp['radius_absen'] ?>m)
                            </a>
                        <?php else: ?>
                            <span class="text-rose-500 font-bold italic">Belum di-point</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Overlay -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<!-- Modal Tambah Tempat PKL -->
<div id="tambahTempatModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-2xl max-h-[90vh] overflow-y-auto bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0">
    <div class="bg-indigo-600 px-6 sm:px-8 py-5 text-white flex justify-between items-center sticky top-0 z-10">
        <h3 class="text-xl font-black italic">Tambah Lokasi PKL & Akun DU/DI</h3>
        <button type="button" onclick="closeModal('tambahTempatModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>
    <form action="" method="POST" class="p-6 sm:p-8 space-y-5">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Nama Perusahaan / Tempat PKL</label>
            <input type="text" name="nama_tempat" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-semibold text-sm" placeholder="Contoh: PT. Teknologi Nusantara">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Alamat Lengkap</label>
            <textarea name="alamat" rows="2" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-medium text-sm" placeholder="Jl. Raya Industri No. 45..."></textarea>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Guru Pembimbing Sekolah</label>
                <select name="guru_pembimbing_id" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-semibold text-sm bg-white">
                    <option value="">-- Pilih Guru Pembimbing --</option>
                    <?php foreach ($gurus as $g): ?>
                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_lengkap']) ?> (<?= htmlspecialchars($g['nip']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Nama Pembimbing DU/DI (Industri)</label>
                <input type="text" name="pembimbing_dudi" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-semibold text-sm" placeholder="Bpk/Ibu Pembimbing Industri">
            </div>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">No. Telp Pembimbing DU/DI</label>
            <input type="text" name="no_telp_dudi" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-semibold text-sm" placeholder="081234567890">
        </div>

        <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-100 space-y-3">
            <h4 class="text-xs font-black uppercase text-emerald-800 flex items-center gap-1.5"><i class="fa fa-user-shield"></i> Buat Akun Login DU/DI (Role: DU/DI)</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold text-emerald-700 uppercase mb-1">Username DU/DI</label>
                    <input type="text" name="username_dudi" class="w-full px-3 py-2 bg-white rounded-xl border border-emerald-200 font-semibold text-xs" placeholder="Contoh: dudi_ptteknologi">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-emerald-700 uppercase mb-1">Password DU/DI</label>
                    <input type="password" name="password_dudi" class="w-full px-3 py-2 bg-white rounded-xl border border-emerald-200 font-semibold text-xs" placeholder="Password login">
                </div>
            </div>
        </div>

        <!-- Pointing Location Map -->
        <div class="space-y-3">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center">
                <i class="fa fa-map-marker-alt text-rose-500 mr-2"></i> Pointing Lokasi GPS Tempat PKL
            </label>
            <div id="map_tambah" class="w-full h-60 rounded-2xl border border-slate-200 shadow-inner z-10"></div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase">Latitude</label>
                    <input type="text" id="tambah_lat" name="latitude" readonly class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-200 text-xs font-mono font-bold text-slate-600">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase">Longitude</label>
                    <input type="text" id="tambah_lng" name="longitude" readonly class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-200 text-xs font-mono font-bold text-slate-600">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase">Radius Absen (m)</label>
                    <input type="number" name="radius_absen" value="50" required class="w-full px-3 py-2 rounded-xl border border-slate-200 font-bold text-xs">
                </div>
            </div>
            <p class="text-[10px] text-slate-400 italic">Geser penanda di peta untuk menetapkan koordinat presisi tempat PKL.</p>
        </div>

        <div class="pt-4 flex flex-col sm:flex-row gap-3">
            <button type="button" onclick="closeModal('tambahTempatModal')" class="flex-1 px-4 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
            <button type="submit" name="tambah_tempat" class="flex-1 px-4 py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100 text-sm">Simpan Tempat PKL</button>
        </div>
    </form>
</div>

<!-- Modal Edit Tempat PKL -->
<div id="editTempatModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-2xl max-h-[90vh] overflow-y-auto bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0">
    <div class="bg-amber-500 px-6 sm:px-8 py-5 text-white flex justify-between items-center sticky top-0 z-10">
        <h3 class="text-xl font-black italic">Edit Lokasi PKL</h3>
        <button type="button" onclick="closeModal('editTempatModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>
    <form action="" method="POST" class="p-6 sm:p-8 space-y-5">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <input type="hidden" name="id" id="edit_tempat_id">
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Nama Perusahaan / Tempat PKL</label>
            <input type="text" name="nama_tempat" id="edit_nama_tempat" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 font-semibold text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Alamat Lengkap</label>
            <textarea name="alamat" id="edit_alamat" rows="2" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 font-medium text-sm"></textarea>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Guru Pembimbing Sekolah</label>
                <select name="guru_pembimbing_id" id="edit_guru_pembimbing_id" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 font-semibold text-sm bg-white">
                    <option value="">-- Pilih Guru Pembimbing --</option>
                    <?php foreach ($gurus as $g): ?>
                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_lengkap']) ?> (<?= htmlspecialchars($g['nip']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Nama Pembimbing DU/DI (Industri)</label>
                <input type="text" name="pembimbing_dudi" id="edit_pembimbing_dudi" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 font-semibold text-sm">
            </div>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">No. Telp Pembimbing DU/DI</label>
            <input type="text" name="no_telp_dudi" id="edit_no_telp_dudi" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 font-semibold text-sm">
        </div>

        <div class="p-4 bg-amber-50 rounded-2xl border border-amber-100 space-y-3">
            <h4 class="text-xs font-black uppercase text-amber-800 flex items-center gap-1.5"><i class="fa fa-user-shield"></i> Kelola Akun Login DU/DI</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold text-amber-700 uppercase mb-1">Username DU/DI</label>
                    <input type="text" name="username_dudi" id="edit_username_dudi" class="w-full px-3 py-2 bg-white rounded-xl border border-amber-200 font-semibold text-xs">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-amber-700 uppercase mb-1">Password Baru (Opsional)</label>
                    <input type="password" name="password_dudi" class="w-full px-3 py-2 bg-white rounded-xl border border-amber-200 font-semibold text-xs" placeholder="Kosongkan jika tidak diubah">
                </div>
            </div>
        </div>

        <div class="space-y-3">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center">
                <i class="fa fa-map-marker-alt text-rose-500 mr-2"></i> Pointing Lokasi GPS Tempat PKL
            </label>
            <div id="map_edit" class="w-full h-60 rounded-2xl border border-slate-200 shadow-inner z-10"></div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase">Latitude</label>
                    <input type="text" id="edit_lat" name="latitude" readonly class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-200 text-xs font-mono font-bold text-slate-600">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase">Longitude</label>
                    <input type="text" id="edit_lng" name="longitude" readonly class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-200 text-xs font-mono font-bold text-slate-600">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase">Radius Absen (m)</label>
                    <input type="number" id="edit_radius_absen" name="radius_absen" required class="w-full px-3 py-2 rounded-xl border border-slate-200 font-bold text-xs">
                </div>
            </div>
        </div>

        <div class="pt-4 flex flex-col sm:flex-row gap-3">
            <button type="button" onclick="closeModal('editTempatModal')" class="flex-1 px-4 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
            <button type="submit" name="edit_tempat" class="flex-1 px-4 py-3 rounded-xl bg-amber-500 text-white font-bold hover:bg-amber-600 shadow-lg shadow-amber-100 text-sm">Simpan Perubahan</button>
        </div>
    </form>
</div>

<!-- Modal Delete Tempat PKL -->
<div id="hapusTempatModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[90%] sm:w-full max-w-sm bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="p-8 text-center">
        <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl"><i class="fa fa-trash"></i></div>
        <h3 class="text-lg font-bold text-slate-800 mb-1">Hapus Tempat PKL?</h3>
        <p class="text-sm text-slate-500 mb-6">Anda akan menghapus <span id="hapus_nama_tempat" class="font-bold"></span>.</p>
        <form action="" method="POST" class="flex gap-2">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            <input type="hidden" name="id" id="hapus_tempat_id">
            <button type="button" onclick="closeModal('hapusTempatModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600 text-sm">Batal</button>
            <button type="submit" name="hapus_tempat" class="flex-1 px-4 py-2.5 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 transition-all text-sm">Hapus</button>
        </form>
    </div>
</div>

<script>
const overlay = document.getElementById('modalOverlay');
let mapTambah, markerTambah, circleTambah;
let mapEdit, markerEdit, circleEdit;

function openModal(id) {
    const m = document.getElementById(id);
    overlay.classList.remove('hidden');
    m.classList.remove('hidden');
    setTimeout(() => {
        overlay.classList.add('opacity-100');
        m.classList.add('opacity-100', 'scale-100');

        if (id === 'tambahTempatModal') initMapTambah();
    }, 10);
}

function closeModal(id) {
    const m = document.getElementById(id);
    overlay.classList.remove('opacity-100');
    m.classList.remove('opacity-100', 'scale-100');
    setTimeout(() => {
        overlay.classList.add('hidden');
        m.classList.add('hidden');
    }, 300);
}

function closeAllModals() {
    document.querySelectorAll('.modal-content').forEach(m => {
        if (!m.classList.contains('hidden')) closeModal(m.id);
    });
}

function initMapTambah() {
    if (mapTambah) { mapTambah.invalidateSize(); return; }
    const defaultLat = -7.9135;
    const defaultLng = 113.8217;

    document.getElementById('tambah_lat').value = defaultLat;
    document.getElementById('tambah_lng').value = defaultLng;

    mapTambah = L.map('map_tambah').setView([defaultLat, defaultLng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapTambah);

    markerTambah = L.marker([defaultLat, defaultLng], {draggable: true}).addTo(mapTambah);
    circleTambah = L.circle([defaultLat, defaultLng], {radius: 50, color: '#4F46E5', fillColor: '#4F46E5', fillOpacity: 0.15}).addTo(mapTambah);

    markerTambah.on('dragend', function(e) {
        const pos = markerTambah.getLatLng();
        document.getElementById('tambah_lat').value = pos.lat.toFixed(6);
        document.getElementById('tambah_lng').value = pos.lng.toFixed(6);
        circleTambah.setLatLng(pos);
    });

    mapTambah.on('click', function(e) {
        markerTambah.setLatLng(e.latlng);
        document.getElementById('tambah_lat').value = e.latlng.lat.toFixed(6);
        document.getElementById('tambah_lng').value = e.latlng.lng.toFixed(6);
        circleTambah.setLatLng(e.latlng);
    });
}

function openEditTempatModal(tp) {
    document.getElementById('edit_tempat_id').value = tp.id;
    document.getElementById('edit_nama_tempat').value = tp.nama_tempat;
    document.getElementById('edit_alamat').value = tp.alamat || '';
    document.getElementById('edit_guru_pembimbing_id').value = tp.guru_pembimbing_id || '';
    document.getElementById('edit_pembimbing_dudi').value = tp.pembimbing_dudi || '';
    document.getElementById('edit_no_telp_dudi').value = tp.no_telp_dudi || '';
    document.getElementById('edit_username_dudi').value = tp.username_dudi || '';

    const lat = parseFloat(tp.latitude) || -7.9135;
    const lng = parseFloat(tp.longitude) || 113.8217;
    const radius = parseInt(tp.radius_absen) || 50;

    document.getElementById('edit_lat').value = lat;
    document.getElementById('edit_lng').value = lng;
    document.getElementById('edit_radius_absen').value = radius;

    openModal('editTempatModal');

    setTimeout(() => {
        if (!mapEdit) {
            mapEdit = L.map('map_edit').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapEdit);
            markerEdit = L.marker([lat, lng], {draggable: true}).addTo(mapEdit);
            circleEdit = L.circle([lat, lng], {radius: radius, color: '#f59e0b', fillColor: '#f59e0b', fillOpacity: 0.15}).addTo(mapEdit);

            markerEdit.on('dragend', function(e) {
                const pos = markerEdit.getLatLng();
                document.getElementById('edit_lat').value = pos.lat.toFixed(6);
                document.getElementById('edit_lng').value = pos.lng.toFixed(6);
                circleEdit.setLatLng(pos);
            });

            mapEdit.on('click', function(e) {
                markerEdit.setLatLng(e.latlng);
                document.getElementById('edit_lat').value = e.latlng.lat.toFixed(6);
                document.getElementById('edit_lng').value = e.latlng.lng.toFixed(6);
                circleEdit.setLatLng(e.latlng);
            });
        } else {
            mapEdit.setView([lat, lng], 15);
            markerEdit.setLatLng([lat, lng]);
            circleEdit.setLatLng([lat, lng]).setRadius(radius);
            mapEdit.invalidateSize();
        }
    }, 150);
}

function openDeleteTempatModal(id, nama) {
    document.getElementById('hapus_tempat_id').value = id;
    document.getElementById('hapus_nama_tempat').textContent = nama;
    openModal('hapusTempatModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
