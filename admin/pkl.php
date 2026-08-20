<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin', 'waka']);
$page_title = "Manajemen PKL";
$message = ''; $message_type = '';

if (!$active_tahun_id) die("Error: Tidak ada tahun pelajaran aktif.");

// Get list of teachers for Pembimbing dropdown
$res_gurus = mysqli_query($conn, "SELECT g.id, u.nama_lengkap, g.nip FROM guru g JOIN users u ON g.user_id = u.id ORDER BY u.nama_lengkap ASC");
$gurus = [];
while ($g = mysqli_fetch_assoc($res_gurus)) $gurus[] = $g;

// Get list of classes for Bulk Mapping
$res_kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
$kelases = [];
while ($k = mysqli_fetch_assoc($res_kelases)) $kelases[] = $k;

// Get list of Tempat PKL
$res_tempat = mysqli_query($conn, "SELECT tp.*, u.nama_lengkap as nama_guru_pembimbing,
                                   (SELECT COUNT(*) FROM siswa_pkl sp WHERE sp.tempat_pkl_id = tp.id AND sp.tahun_pelajaran_id = $active_tahun_id AND sp.status = 'aktif') as jml_siswa
                                   FROM tempat_pkl tp
                                   LEFT JOIN guru g ON tp.guru_pembimbing_id = g.id
                                   LEFT JOIN users u ON g.user_id = u.id
                                   ORDER BY tp.nama_tempat ASC");
$tempats = [];
while ($tp = mysqli_fetch_assoc($res_tempat)) $tempats[] = $tp;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    // Action 1: Tambah Tempat PKL
    if (isset($_POST['tambah_tempat'])) {
        $nama_tempat = trim($_POST['nama_tempat'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');
        $radius_absen = (int)($_POST['radius_absen'] ?? 50);
        $guru_pembimbing_id = !empty($_POST['guru_pembimbing_id']) ? (int)$_POST['guru_pembimbing_id'] : null;
        $pembimbing_dudi = trim($_POST['pembimbing_dudi'] ?? '');
        $no_telp_dudi = trim($_POST['no_telp_dudi'] ?? '');

        if (empty($nama_tempat)) {
            $message = "Nama Tempat PKL wajib diisi."; $message_type = 'error';
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO tempat_pkl (nama_tempat, alamat, latitude, longitude, radius_absen, guru_pembimbing_id, pembimbing_dudi, no_telp_dudi) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssssiiss", $nama_tempat, $alamat, $latitude, $longitude, $radius_absen, $guru_pembimbing_id, $pembimbing_dudi, $no_telp_dudi);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Tempat PKL berhasil ditambahkan!"; $message_type = 'success';
            } else {
                $message = "Gagal menambahkan tempat PKL: " . mysqli_error($conn); $message_type = 'error';
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

        if (empty($nama_tempat)) {
            $message = "Nama Tempat PKL wajib diisi."; $message_type = 'error';
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE tempat_pkl SET nama_tempat = ?, alamat = ?, latitude = ?, longitude = ?, radius_absen = ?, guru_pembimbing_id = ?, pembimbing_dudi = ?, no_telp_dudi = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssssiissi", $nama_tempat, $alamat, $latitude, $longitude, $radius_absen, $guru_pembimbing_id, $pembimbing_dudi, $no_telp_dudi, $id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Tempat PKL berhasil diperbarui!"; $message_type = 'success';
            } else {
                $message = "Gagal memperbarui tempat PKL."; $message_type = 'error';
            }
        }
    }
    // Action 3: Hapus Tempat PKL
    elseif (isset($_POST['hapus_tempat'])) {
        $id = (int)$_POST['id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM tempat_pkl WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Tempat PKL dihapus!"; $message_type = 'success';
        } else {
            $message = "Gagal menghapus tempat PKL."; $message_type = 'error';
        }
    }
    // Action 4: Assign Siswa PKL (Single atau Bulk)
    elseif (isset($_POST['simpan_mapping_pkl'])) {
        $tempat_pkl_id = (int)($_POST['tempat_pkl_id'] ?? 0);
        $siswa_ids = $_POST['siswa_ids'] ?? [];

        if ($tempat_pkl_id <= 0) {
            $message = "Pilih Tempat PKL terlebih dahulu."; $message_type = 'error';
        } elseif (empty($siswa_ids) || !is_array($siswa_ids)) {
            $message = "Pilih setidaknya satu siswa untuk diberi status PKL."; $message_type = 'error';
        } else {
            mysqli_begin_transaction($conn);
            try {
                $inserted = 0;
                foreach ($siswa_ids as $sid) {
                    $sid = (int)$sid;
                    $stmt = mysqli_prepare($conn, "INSERT INTO siswa_pkl (siswa_id, tempat_pkl_id, tahun_pelajaran_id, status) VALUES (?, ?, ?, 'aktif') ON DUPLICATE KEY UPDATE tempat_pkl_id = VALUES(tempat_pkl_id), status = 'aktif'");
                    mysqli_stmt_bind_param($stmt, "iii", $sid, $tempat_pkl_id, $active_tahun_id);
                    mysqli_stmt_execute($stmt);
                    $inserted++;
                }
                mysqli_commit($conn);
                $message = "Berhasil menetapkan status PKL untuk $inserted siswa!"; $message_type = 'success';
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = "Gagal menyimpan mapping PKL: " . $e->getMessage(); $message_type = 'error';
            }
        }
    }
    // Action 5: Hapus / Nonaktifkan Status PKL Siswa
    elseif (isset($_POST['hapus_siswa_pkl'])) {
        $sp_id = (int)$_POST['siswa_pkl_id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM siswa_pkl WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $sp_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Status PKL siswa berhasil dihapus!"; $message_type = 'success';
        } else {
            $message = "Gagal menghapus status PKL siswa."; $message_type = 'error';
        }
    }

    // Refresh tempats array
    $res_tempat = mysqli_query($conn, "SELECT tp.*, u.nama_lengkap as nama_guru_pembimbing,
                                       (SELECT COUNT(*) FROM siswa_pkl sp WHERE sp.tempat_pkl_id = tp.id AND sp.tahun_pelajaran_id = $active_tahun_id AND sp.status = 'aktif') as jml_siswa
                                       FROM tempat_pkl tp
                                       LEFT JOIN guru g ON tp.guru_pembimbing_id = g.id
                                       LEFT JOIN users u ON g.user_id = u.id
                                       ORDER BY tp.nama_tempat ASC");
    $tempats = [];
    while ($tp = mysqli_fetch_assoc($res_tempat)) $tempats[] = $tp;
}

// Fetch Active Siswa PKL List for Monitoring
$search_siswa = mysqli_real_escape_string($conn, $_GET['search_siswa'] ?? '');
$filter_tempat = (int)($_GET['filter_tempat'] ?? 0);

$where_spkl = " WHERE sp.tahun_pelajaran_id = $active_tahun_id AND sp.status = 'aktif'";
if (!empty($search_siswa)) {
    $where_spkl .= " AND (s.nama_siswa LIKE '%$search_siswa%' OR s.nis LIKE '%$search_siswa%' OR k.nama_kelas LIKE '%$search_siswa%')";
}
if ($filter_tempat > 0) {
    $where_spkl .= " AND sp.tempat_pkl_id = $filter_tempat";
}

$pagin = get_pagination_data($conn, "siswa_pkl sp JOIN siswa s ON sp.siswa_id = s.id JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = sp.tahun_pelajaran_id JOIN kelas k ON sk.kelas_id = k.id", 10, $where_spkl);

$q_siswa_pkl = "SELECT sp.id as siswa_pkl_id, s.id as siswa_id, s.nis, s.nama_siswa, k.nama_kelas, tp.nama_tempat, tp.pembimbing_dudi, u.nama_lengkap as nama_guru_pembimbing
                FROM siswa_pkl sp
                JOIN siswa s ON sp.siswa_id = s.id
                JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = sp.tahun_pelajaran_id
                JOIN kelas k ON sk.kelas_id = k.id
                JOIN tempat_pkl tp ON sp.tempat_pkl_id = tp.id
                LEFT JOIN guru g ON tp.guru_pembimbing_id = g.id
                LEFT JOIN users u ON g.user_id = u.id
                $where_spkl
                ORDER BY tp.nama_tempat ASC, k.nama_kelas ASC, s.nama_siswa ASC
                LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";
$res_siswa_pkl = mysqli_query($conn, $q_siswa_pkl);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Leaflet JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Manajemen Praktik Kerja Lapangan (PKL)</h1>
        <p class="text-slate-500 font-medium">Kelola lokasi industri/DU-DI, geofencing lokasi PKL, dan pemetaan siswa PKL.</p>
    </div>
    <div class="flex gap-3">
        <button onclick="openModal('tambahTempatModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-2xl font-bold shadow-lg shadow-indigo-200 transition-all flex items-center">
            <i class="fa fa-map-marker-alt mr-2"></i> Tambah Lokasi PKL
        </button>
        <button onclick="openModal('mappingModal')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-2xl font-bold shadow-lg shadow-emerald-200 transition-all flex items-center">
            <i class="fa fa-user-plus mr-2"></i> Mapping Siswa PKL
        </button>
    </div>
</div>

<?php if ($message): ?>
<script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= addslashes(htmlspecialchars($message)) ?>', confirmButtonColor: '#4f46e5' });</script>
<?php endif; ?>

<!-- Section 1: Daftar Lokasi Industri / Tempat PKL -->
<div class="mb-10">
    <h2 class="text-xl font-black text-slate-800 italic uppercase tracking-wider mb-4 flex items-center">
        <i class="fa fa-building text-indigo-600 mr-2"></i> Daftar Tempat / Industri PKL
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($tempats)): ?>
        <div class="col-span-full lux-card p-8 text-center text-slate-400 italic">Belum ada lokasi PKL yang ditambahkan.</div>
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
                            <span class="font-bold"><?= htmlspecialchars($tp['pembimbing_dudi'] ?? '-') ?> <?= !empty($tp['no_telp_dudi']) ? "(".htmlspecialchars($tp['no_telp_dudi']).")" : "" ?></span>
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
</div>

<!-- Section 2: Daftar Siswa Berstatus PKL (Monitoring) -->
<div class="lux-card p-6 bg-white shadow-2xl rounded-3xl mb-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
        <div>
            <h2 class="text-xl font-black text-slate-800 italic uppercase tracking-wider">Daftar Siswa Berstatus PKL</h2>
            <p class="text-xs text-slate-500 font-medium">Monitoring alokasi tempat dan pembimbing siswa PKL aktif.</p>
        </div>
        <form action="" method="GET" class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="search_siswa" value="<?= htmlspecialchars($search_siswa) ?>" placeholder="Cari nama, NIS, kelas..." class="px-4 py-2 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 text-xs font-semibold">
            <select name="filter_tempat" class="px-4 py-2 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 text-xs font-semibold bg-white">
                <option value="0">Semua Tempat PKL</option>
                <?php foreach ($tempats as $tp): ?>
                <option value="<?= $tp['id'] ?>" <?= $filter_tempat == $tp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tp['nama_tempat']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-5 py-2 bg-indigo-600 text-white font-bold rounded-xl text-xs hover:bg-indigo-700 transition-all">Filter</button>
            <a href="pkl.php" class="px-4 py-2 bg-slate-200 text-slate-600 font-bold rounded-xl text-xs hover:bg-slate-300 transition-all">Reset</a>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Siswa & Kelas</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Tempat PKL</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Guru Pembimbing</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Pembimbing DU/DI</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (mysqli_num_rows($res_siswa_pkl) == 0): ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-bold italic">Belum ada siswa yang ditetapkan berstatus PKL.</td>
                </tr>
                <?php else: ?>
                    <?php while ($sp = mysqli_fetch_assoc($res_siswa_pkl)): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($sp['nama_siswa']) ?></div>
                            <div class="text-[10px] text-slate-400 font-bold">NIS: <?= htmlspecialchars($sp['nis']) ?> | <span class="text-indigo-600"><?= htmlspecialchars($sp['nama_kelas']) ?></span></div>
                        </td>
                        <td class="px-6 py-4 font-bold text-slate-700 text-sm"><?= htmlspecialchars($sp['nama_tempat']) ?></td>
                        <td class="px-6 py-4 font-semibold text-slate-600 text-xs"><?= htmlspecialchars($sp['nama_guru_pembimbing'] ?? '-') ?></td>
                        <td class="px-6 py-4 font-semibold text-slate-600 text-xs"><?= htmlspecialchars($sp['pembimbing_dudi'] ?? '-') ?></td>
                        <td class="px-6 py-4 text-center">
                            <form action="" method="POST" onsubmit="return confirm('Hapus status PKL siswa ini?');" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                <input type="hidden" name="siswa_pkl_id" value="<?= $sp['siswa_pkl_id'] ?>">
                                <button type="submit" name="hapus_siswa_pkl" class="px-3 py-1 bg-rose-50 hover:bg-rose-600 hover:text-white text-rose-600 rounded-lg text-xs font-bold transition-all">
                                    <i class="fa fa-times mr-1"></i> Lepas PKL
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>

<!-- Overlay -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<!-- Modal Tambah Tempat PKL -->
<div id="tambahTempatModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-2xl max-h-[90vh] overflow-y-auto bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0">
    <div class="bg-indigo-600 px-6 sm:px-8 py-5 text-white flex justify-between items-center sticky top-0 z-10">
        <h3 class="text-xl font-black italic">Tambah Lokasi PKL Baru</h3>
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
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Pembimbing DU/DI (Industri)</label>
                <input type="text" name="pembimbing_dudi" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-semibold text-sm" placeholder="Nama Pembimbing Industri">
            </div>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">No. Telp Pembimbing DU/DI</label>
            <input type="text" name="no_telp_dudi" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-semibold text-sm" placeholder="081234567890">
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
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Pembimbing DU/DI (Industri)</label>
                <input type="text" name="pembimbing_dudi" id="edit_pembimbing_dudi" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 font-semibold text-sm">
            </div>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">No. Telp Pembimbing DU/DI</label>
            <input type="text" name="no_telp_dudi" id="edit_no_telp_dudi" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 font-semibold text-sm">
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

<!-- Modal Mapping Siswa PKL (Single / Bulk) -->
<div id="mappingModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-2xl max-h-[90vh] overflow-y-auto bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0">
    <div class="bg-emerald-600 px-6 sm:px-8 py-5 text-white flex justify-between items-center sticky top-0 z-10">
        <h3 class="text-xl font-black italic">Mapping / Penetapan Siswa PKL</h3>
        <button type="button" onclick="closeModal('mappingModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>
    <form action="" method="POST" class="p-6 sm:p-8 space-y-5">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Pilih Tempat / Industri PKL</label>
            <select name="tempat_pkl_id" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-100 font-bold text-sm bg-white">
                <option value="">-- Pilih Tempat PKL --</option>
                <?php foreach ($tempats as $tp): ?>
                <option value="<?= $tp['id'] ?>"><?= htmlspecialchars($tp['nama_tempat']) ?> (Pembimbing: <?= htmlspecialchars($tp['nama_guru_pembimbing'] ?? 'Belum diatur') ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Filter Berdasarkan Kelas</label>
            <select id="mapping_kelas_id" onchange="fetchSiswaMapping(this.value)" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-100 font-bold text-sm bg-white">
                <option value="">-- Pilih Kelas --</option>
                <?php foreach ($kelases as $k): ?>
                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="siswa_checkbox_container" class="space-y-2 max-h-60 overflow-y-auto p-3 bg-slate-50 border border-slate-200 rounded-2xl">
            <div class="text-xs text-slate-400 italic text-center p-4">Pilih kelas di atas untuk menampilkan daftar siswa.</div>
        </div>

        <div class="pt-4 flex flex-col sm:flex-row gap-3">
            <button type="button" onclick="closeModal('mappingModal')" class="flex-1 px-4 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
            <button type="submit" name="simpan_mapping_pkl" class="flex-1 px-4 py-3 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 shadow-lg shadow-emerald-100 text-sm">Tetapkan Status PKL</button>
        </div>
    </form>
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

function fetchSiswaMapping(kelasId) {
    const container = document.getElementById('siswa_checkbox_container');
    if (!kelasId) {
        container.innerHTML = '<div class="text-xs text-slate-400 italic text-center p-4">Pilih kelas di atas untuk menampilkan daftar siswa.</div>';
        return;
    }

    container.innerHTML = '<div class="text-xs text-slate-400 italic text-center p-4"><i class="fa fa-spinner fa-spin mr-2"></i> Memuat daftar siswa...</div>';

    fetch(`../api/get_siswa_kelas.php?kelas_id=${kelasId}`)
        .then(r => r.json())
        .then(data => {
            if (!data || data.length === 0) {
                container.innerHTML = '<div class="text-xs text-slate-400 italic text-center p-4">Tidak ada siswa di kelas ini.</div>';
                return;
            }

            let html = `<div class="flex items-center justify-between p-2 border-b border-slate-200 mb-2 font-bold text-xs">
                <span>Daftar Siswa (${data.length})</span>
                <label class="cursor-pointer text-indigo-600 hover:underline">
                    <input type="checkbox" onchange="toggleSelectAllSiswa(this)" class="mr-1"> Pilih Semua
                </label>
            </div>`;

            data.forEach(s => {
                html += `
                <label class="flex items-center justify-between p-2 bg-white rounded-xl border border-slate-100 hover:bg-indigo-50/50 cursor-pointer transition-colors text-xs font-semibold text-slate-700">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="siswa_ids[]" value="${s.id}" class="siswa-chk rounded text-indigo-600 focus:ring-indigo-500">
                        <span>${s.nama_siswa}</span>
                    </div>
                    <span class="text-[10px] text-slate-400 font-mono">NIS: ${s.nis}</span>
                </label>`;
            });

            container.innerHTML = html;
        })
        .catch(err => {
            container.innerHTML = '<div class="text-xs text-rose-500 font-bold text-center p-4">Gagal memuat siswa: ' + err.message + '</div>';
        });
}

function toggleSelectAllSiswa(master) {
    document.querySelectorAll('.siswa-chk').forEach(chk => chk.checked = master.checked);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
