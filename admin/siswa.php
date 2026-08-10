<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin', 'waka']);

$page_title = "Manajemen Siswa";
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_SESSION['role'] !== 'admin') die("Akses Ditolak: Hanya Admin yang dapat mengubah data.");
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        if (isset($_POST['tambah'])) {
            $nis = trim($_POST['nis']);
            $nisn = trim($_POST['nisn'] ?: '');
            $nisn = $nisn !== '' ? $nisn : null;
            $nama_siswa = $_POST['nama_siswa'];
            $jenis_kelamin = $_POST['jenis_kelamin'];
            $alamat = $_POST['alamat'] ?: null;
            $no_telp = $_POST['no_telp'] ?: null;
            $tempat_lahir = $_POST['tempat_lahir'] ?: null;
            $tanggal_lahir = $_POST['tanggal_lahir'] ?: null;
            $no_wa_ortu = $_POST['no_wa_ortu'] ?: null;

            // Pre-validation to avoid duplicate NIS
            $check_nis = mysqli_query($conn, "SELECT id, nama_siswa FROM siswa WHERE nis = '" . mysqli_real_escape_string($conn, $nis) . "'");
            if (mysqli_num_rows($check_nis) > 0) {
                $dup = mysqli_fetch_assoc($check_nis);
                throw new Exception("NIS '$nis' sudah terdaftar atas nama '" . $dup['nama_siswa'] . "'.");
            }

            // Pre-validation to avoid duplicate NISN (only check if NISN is supplied)
            if ($nisn !== null) {
                $check_nisn = mysqli_query($conn, "SELECT id, nama_siswa FROM siswa WHERE nisn = '" . mysqli_real_escape_string($conn, $nisn) . "'");
                if (mysqli_num_rows($check_nisn) > 0) {
                    $dup = mysqli_fetch_assoc($check_nisn);
                    throw new Exception("NISN '$nisn' sudah terdaftar atas nama '" . $dup['nama_siswa'] . "'.");
                }
            }

            $foto = null;
            if (!empty($_FILES['foto']['name'])) {
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    $foto = $nis . "_" . time() . "." . $ext;
                    move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . "/../uploads/siswa/" . $foto);
                }
            }

            $stmt = mysqli_prepare($conn, "INSERT INTO siswa (nis, nisn, nama_siswa, jenis_kelamin, alamat, no_telp, foto, tempat_lahir, tanggal_lahir, no_wa_ortu) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssssssssss", $nis, $nisn, $nama_siswa, $jenis_kelamin, $alamat, $no_telp, $foto, $tempat_lahir, $tanggal_lahir, $no_wa_ortu);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Siswa berhasil ditambahkan!";
                $message_type = 'success';
            }
        } elseif (isset($_POST['edit'])) {
            $id = (int)$_POST['id'];
            $nis = trim($_POST['nis']);
            $nisn = trim($_POST['nisn'] ?: '');
            $nisn = $nisn !== '' ? $nisn : null;
            $nama_siswa = $_POST['nama_siswa'];
            $jenis_kelamin = $_POST['jenis_kelamin'];
            $alamat = $_POST['alamat'] ?: null;
            $no_telp = $_POST['no_telp'] ?: null;
            $tempat_lahir = $_POST['tempat_lahir'] ?: null;
            $tanggal_lahir = $_POST['tanggal_lahir'] ?: null;
            $no_wa_ortu = $_POST['no_wa_ortu'] ?: null;

            // Pre-validation to avoid duplicate NIS
            $check_nis = mysqli_query($conn, "SELECT id, nama_siswa FROM siswa WHERE nis = '" . mysqli_real_escape_string($conn, $nis) . "' AND id != $id");
            if (mysqli_num_rows($check_nis) > 0) {
                $dup = mysqli_fetch_assoc($check_nis);
                throw new Exception("NIS '$nis' sudah digunakan oleh siswa lain ('" . $dup['nama_siswa'] . "').");
            }

            // Pre-validation to avoid duplicate NISN
            if ($nisn !== null) {
                $check_nisn = mysqli_query($conn, "SELECT id, nama_siswa FROM siswa WHERE nisn = '" . mysqli_real_escape_string($conn, $nisn) . "' AND id != $id");
                if (mysqli_num_rows($check_nisn) > 0) {
                    $dup = mysqli_fetch_assoc($check_nisn);
                    throw new Exception("NISN '$nisn' sudah digunakan oleh siswa lain ('" . $dup['nama_siswa'] . "').");
                }
            }

            $q_foto = "";
            $params = [$nis, $nisn, $nama_siswa, $jenis_kelamin, $alamat, $no_telp, $tempat_lahir, $tanggal_lahir, $no_wa_ortu];
            $types = "sssssssss";

            if (!empty($_FILES['foto']['name'])) {
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    $foto = $nis . "_" . time() . "." . $ext;
                    move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . "/../uploads/siswa/" . $foto);
                    $q_foto = ", foto = ?";
                    $params[] = $foto;
                    $types .= "s";
                }
            }

            $params[] = $id;
            $types .= "i";

            $stmt = mysqli_prepare($conn, "UPDATE siswa SET nis = ?, nisn = ?, nama_siswa = ?, jenis_kelamin = ?, alamat = ?, no_telp = ?, tempat_lahir = ?, tanggal_lahir = ?, no_wa_ortu = ? $q_foto WHERE id = ?");
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Data siswa diperbarui!";
                $message_type = 'success';
            }
        } elseif (isset($_POST['hapus'])) {
            $id = (int)$_POST['id'];
            $stmt = mysqli_prepare($conn, "DELETE FROM siswa WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Siswa dihapus!";
                $message_type = 'success';
            }
        } elseif (isset($_POST['hapus_masal'])) {
            $ids = $_POST['bulk_ids'] ?? [];
            if (!empty($ids) && is_array($ids)) {
                $sanitized_ids = array_map('intval', $ids);
                $ids_list = implode(',', $sanitized_ids);

                // Delete linked records if needed or rely on database schema
                mysqli_query($conn, "DELETE FROM siswa_kelas WHERE siswa_id IN ($ids_list)");
                mysqli_query($conn, "DELETE FROM absensi_harian WHERE siswa_id IN ($ids_list)");
                mysqli_query($conn, "DELETE FROM absensi_jurnal WHERE siswa_id IN ($ids_list)");
                mysqli_query($conn, "DELETE FROM mood_survey WHERE siswa_id IN ($ids_list)");
                mysqli_query($conn, "DELETE FROM pengaduan WHERE siswa_id IN ($ids_list)");
                mysqli_query($conn, "DELETE FROM konsultasi WHERE siswa_id IN ($ids_list)");

                $res = mysqli_query($conn, "DELETE FROM siswa WHERE id IN ($ids_list)");
                if ($res) {
                    $message = "Berhasil menghapus " . count($sanitized_ids) . " data siswa secara massal!";
                    $message_type = 'success';
                } else {
                    throw new Exception("Gagal menghapus siswa dari database.");
                }
            } else {
                throw new Exception("Tidak ada siswa yang dipilih untuk dihapus.");
            }
        }
    } catch (Exception $e) {
        if ($e->getCode() == 1062) {
            $message = "Gagal: NIS/NISN sudah terdaftar di sistem.";
            $message_type = 'error';
        } else {
            $message = "Database Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Search and Filter Logic
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$kelas_filter = (int)($_GET['kelas_id'] ?? 0);

$where_clauses = [];
// Main Siswa view only shows students with a class in the active year
$where_clauses[] = "s.id IN (SELECT siswa_id FROM siswa_kelas WHERE tahun_pelajaran_id = '$active_tahun_id')";

if (!empty($search)) {
    $where_clauses[] = "(s.nama_siswa LIKE '%$search%' OR s.nis LIKE '%$search%' OR s.nisn LIKE '%$search%')";
}
if ($kelas_filter > 0) {
    $where_clauses[] = "sk.kelas_id = $kelas_filter";
}

$where_sql = " WHERE 1=1";
if (!empty($where_clauses)) {
    $where_sql .= " AND " . implode(" AND ", $where_clauses);
}

$pagin = get_pagination_data($conn, "siswa s LEFT JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = '$active_tahun_id'", 15, $where_sql);

// Calculate totals based on filters
$total_query = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN s.jenis_kelamin = 'L' THEN 1 ELSE 0 END) as total_L,
                    SUM(CASE WHEN s.jenis_kelamin = 'P' THEN 1 ELSE 0 END) as total_P
                FROM siswa s
                LEFT JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = '$active_tahun_id'
                $where_sql";
$total_res = mysqli_query($conn, $total_query);
$totals = mysqli_fetch_assoc($total_res);

$query = "SELECT s.*, k.nama_kelas
          FROM siswa s
          LEFT JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = '$active_tahun_id'
          LEFT JOIN kelas k ON sk.kelas_id = k.id
          $where_sql
          ORDER BY s.nama_siswa ASC LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";
$result = mysqli_query($conn, $query);

$kelas_list = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Manajemen Siswa</h1>
        <p class="text-slate-500">Kelola database siswa dan penempatan kelas.</p>
    </div>
    <?php if($_SESSION['role'] == 'admin'): ?>
    <div class="flex flex-wrap gap-3">
        <button onclick="syncDapodik('<?= get_csrf_token() ?>')" class="bg-amber-500 hover:bg-amber-600 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-amber-200 transition-all flex items-center">
            <i class="fa fa-sync-alt mr-2"></i> Sinkron Dapodik
        </button>
        <button onclick="openModal('tambahModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-200 transition-all flex items-center">
            <i class="fa fa-plus mr-2"></i> Tambah Siswa
        </button>
        <a href="import_siswa_excel.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-emerald-200 transition-all flex items-center">
            <i class="fa fa-file-excel mr-2"></i> Import Excel
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cari Siswa</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama, NIS, atau NISN..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm">
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Filter Kelas</label>
            <select name="kelas_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700">
                <option value="">-- Semua Kelas --</option>
                <?php mysqli_data_seek($kelas_list, 0); while($k = mysqli_fetch_assoc($kelas_list)): ?>
                    <option value="<?= $k['id'] ?>" <?= $k['id'] == $kelas_filter ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="lg:col-span-2 flex items-end gap-3">
            <button type="submit" class="flex-1 px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100">Terapkan Filter</button>
            <a href="siswa.php" class="px-6 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-300 transition-all">Reset</a>
        </div>
    </form>
</div>

<?php if ($message): ?>
<script>
    Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= addslashes(htmlspecialchars($message)) ?>' });
</script>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
    <div class="lux-card p-6 bg-gradient-to-br from-indigo-500 to-indigo-700 text-white border-none shadow-lg shadow-indigo-100 flex items-center justify-between">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.2em] opacity-80 mb-1">Total Siswa Terfilter</p>
            <h3 class="text-3xl font-black italic tracking-tighter"><?= $totals['total'] ?></h3>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center text-2xl shadow-inner"><i class="fa fa-users"></i></div>
    </div>
    <div class="lux-card p-6 bg-white border-none shadow-lg shadow-slate-100 flex items-center justify-between">
        <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Laki-laki (L)</p>
            <h3 class="text-3xl font-black italic text-indigo-600 tracking-tighter"><?= $totals['total_L'] ?? 0 ?></h3>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-500 flex items-center justify-center text-2xl"><i class="fa fa-mars"></i></div>
    </div>
    <div class="lux-card p-6 bg-white border-none shadow-lg shadow-slate-100 flex items-center justify-between">
        <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Perempuan (P)</p>
            <h3 class="text-3xl font-black italic text-pink-500 tracking-tighter"><?= $totals['total_P'] ?? 0 ?></h3>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-pink-50 text-pink-500 flex items-center justify-center text-2xl"><i class="fa fa-venus"></i></div>
    </div>
</div>

<form action="" method="POST" id="bulk-delete-form" onsubmit="confirmBulkDelete(event)">
    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
    <input type="hidden" name="hapus_masal" value="1">

    <?php if($_SESSION['role'] == 'admin'): ?>
    <div class="mb-4 flex items-center justify-between bg-slate-50 border border-slate-100 p-4 rounded-2xl">
        <div class="flex items-center gap-3">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider"><span id="selected-count">0</span> Siswa Terpilih</span>
        </div>
        <button type="submit" id="bulk-delete-btn" disabled class="px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition-all bg-slate-200 text-slate-400 flex items-center gap-2 cursor-not-allowed">
            <i class="fa fa-trash-alt"></i> Hapus Terpilih
        </button>
    </div>
    <?php endif; ?>

    <div class="lux-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <?php if($_SESSION['role'] == 'admin'): ?>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center w-12">
                            <input type="checkbox" id="select-all-siswa" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500 cursor-pointer transition-all">
                        </th>
                        <?php endif; ?>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Foto</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">NIS / NISN</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Nama Siswa</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">JK</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Kelas Aktif</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <?php if($_SESSION['role'] == 'admin'): ?>
                        <td class="px-6 py-4 text-center">
                            <input type="checkbox" name="bulk_ids[]" value="<?= $row['id'] ?>" class="siswa-checkbox w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500 cursor-pointer transition-all">
                        </td>
                        <?php endif; ?>
                        <td class="px-6 py-4">
                            <div class="w-10 h-10 rounded-full bg-slate-100 border border-slate-200 overflow-hidden shadow-sm flex items-center justify-center mx-auto">
                                <?php if(!empty($row['foto'])): ?>
                                    <img src="<?= BASE_URL ?>uploads/siswa/<?= $row['foto'] ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fa fa-user-graduate text-slate-300"></i>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 font-mono text-sm">
                            <div class="text-indigo-600 font-bold"><?= htmlspecialchars($row['nis']) ?></div>
                            <div class="text-slate-400 text-[10px]"><?= htmlspecialchars($row['nisn'] ?? '-') ?></div>
                        </td>
                        <td class="px-6 py-4 font-semibold text-slate-700">
                            <?= htmlspecialchars($row['nama_siswa']) ?>
                            <div class="text-[10px] text-slate-400 font-normal italic"><?= htmlspecialchars($row['no_telp'] ?? '') ?></div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-2 py-0.5 rounded text-xs font-bold <?= $row['jenis_kelamin'] == 'L' ? 'bg-blue-100 text-blue-600' : 'bg-pink-100 text-pink-600' ?>">
                                <?= $row['jenis_kelamin'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600"><?= htmlspecialchars($row['nama_kelas'] ?? 'N/A') ?></td>
                        <td class="px-6 py-4">
                            <div class="flex justify-center gap-2">
                                <?php if($_SESSION['role'] == 'admin'): ?>
                                <button type="button" onclick="openModal('editModal-<?= $row['id'] ?>')" class="w-9 h-9 flex items-center justify-center rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white transition-all">
                                    <i class="fa fa-edit"></i>
                                </button>
                                <button type="button" onclick="openModal('hapusModal-<?= $row['id'] ?>')" class="w-9 h-9 flex items-center justify-center rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all">
                                    <i class="fa fa-trash"></i>
                                </button>
                                <?php else: ?>
                                    <span class="text-[10px] text-slate-400 italic">View only</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>

<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<div id="tambahModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-2xl max-h-[90vh] overflow-y-auto bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0">
    <div class="bg-indigo-600 px-6 sm:px-8 py-4 sm:py-6 text-white font-bold italic text-xl sm:text-2xl sticky top-0 z-10 flex justify-between items-center">
        <span>Tambah Siswa</span>
        <button type="button" onclick="closeModal('tambahModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>
    <form action="" method="POST" enctype="multipart/form-data" class="p-6 sm:p-8 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-bold text-slate-700 mb-1">NIS</label><input type="text" name="nis" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50"></div>
            <div><label class="block text-sm font-bold text-slate-700 mb-1">NISN</label><input type="text" name="nisn" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50"></div>
        </div>
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Nama Siswa</label><input type="text" name="nama_siswa" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50"></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-bold text-slate-700 mb-1">Jenis Kelamin</label>
                <select name="jenis_kelamin" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white">
                    <option value="L">Laki-laki</option>
                    <option value="P">Perempuan</option>
                </select>
            </div>
            <div><label class="block text-sm font-bold text-slate-700 mb-1">No. Telp/HP</label><input type="text" name="no_telp" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50"></div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-bold text-slate-700 mb-1">Tempat Lahir</label><input type="text" name="tempat_lahir" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50"></div>
            <div><label class="block text-sm font-bold text-slate-700 mb-1">Tanggal Lahir</label><input type="date" name="tanggal_lahir" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white"></div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-bold text-slate-700 mb-1">Alamat</label><textarea name="alamat" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50"></textarea></div>
            <div><label class="block text-sm font-bold text-slate-700 mb-1">No. WA Orang Tua / Wali</label><input type="text" name="no_wa_ortu" placeholder="Contoh: 081234567890" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50"></div>
        </div>
        <div>
            <label class="block text-sm font-bold text-slate-700 mb-1">Foto Siswa</label>
            <input type="file" name="foto" accept="image/*" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white">
        </div>
        <div class="pt-4 flex flex-col sm:flex-row gap-3"><button type="button" onclick="closeModal('tambahModal')" class="flex-1 px-6 py-3 rounded-xl border border-slate-200 font-bold text-slate-600">Batal</button><button type="submit" name="tambah" class="flex-1 px-6 py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100">Simpan</button></div>
    </form>
</div>

<?php mysqli_data_seek($result, 0); while ($row = mysqli_fetch_assoc($result)): ?>
<div id="editModal-<?= $row['id'] ?>" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-2xl max-h-[90vh] overflow-y-auto bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0">
    <div class="bg-amber-500 px-6 sm:px-8 py-4 sm:py-6 text-white font-bold italic text-xl sm:text-2xl sticky top-0 z-10 flex justify-between items-center">
        <span>Edit Siswa</span>
        <button type="button" onclick="closeModal('editModal-<?= $row['id'] ?>')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>
    <form action="" method="POST" enctype="multipart/form-data" class="p-6 sm:p-8 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <input type="hidden" name="id" value="<?= $row['id'] ?>">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-bold text-slate-700 mb-1">NIS</label><input type="text" name="nis" value="<?= htmlspecialchars($row['nis']) ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50"></div>
            <div><label class="block text-sm font-bold text-slate-700 mb-1">NISN</label><input type="text" name="nisn" value="<?= htmlspecialchars($row['nisn'] ?? '') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50"></div>
        </div>
        <div><label class="block text-sm font-bold text-slate-700 mb-1">Nama Siswa</label><input type="text" name="nama_siswa" value="<?= htmlspecialchars($row['nama_siswa']) ?>" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50"></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-bold text-slate-700 mb-1">Jenis Kelamin</label>
                <select name="jenis_kelamin" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 bg-white">
                    <option value="L" <?= $row['jenis_kelamin'] == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                    <option value="P" <?= $row['jenis_kelamin'] == 'P' ? 'selected' : '' ?>>Perempuan</option>
                </select>
            </div>
            <div><label class="block text-sm font-bold text-slate-700 mb-1">No. Telp/HP</label><input type="text" name="no_telp" value="<?= htmlspecialchars($row['no_telp'] ?? '') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50"></div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-bold text-slate-700 mb-1">Tempat Lahir</label><input type="text" name="tempat_lahir" value="<?= htmlspecialchars($row['tempat_lahir'] ?? '') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50"></div>
            <div><label class="block text-sm font-bold text-slate-700 mb-1">Tanggal Lahir</label><input type="date" name="tanggal_lahir" value="<?= htmlspecialchars($row['tanggal_lahir'] ?? '') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 bg-white"></div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-bold text-slate-700 mb-1">Alamat</label><textarea name="alamat" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50"><?= htmlspecialchars($row['alamat'] ?? '') ?></textarea></div>
            <div><label class="block text-sm font-bold text-slate-700 mb-1">No. WA Orang Tua / Wali</label><input type="text" name="no_wa_ortu" value="<?= htmlspecialchars($row['no_wa_ortu'] ?? '') ?>" placeholder="Contoh: 081234567890" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50"></div>
        </div>
        <div>
            <label class="block text-sm font-bold text-slate-700 mb-1">Update Foto</label>
            <input type="file" name="foto" accept="image/*" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 bg-white">
            <?php if(!empty($row['foto'])): ?>
                <p class="text-[10px] text-slate-400 mt-1 italic">Sudah ada foto. Unggah lagi untuk mengganti.</p>
            <?php endif; ?>
        </div>
        <div class="pt-4 flex flex-col sm:flex-row gap-3"><button type="button" onclick="closeModal('editModal-<?= $row['id'] ?>')" class="flex-1 px-6 py-3 rounded-xl border border-slate-200 font-bold text-slate-600">Batal</button><button type="submit" name="edit" class="flex-1 px-6 py-3 rounded-xl bg-amber-500 text-white font-bold hover:bg-amber-600 shadow-lg shadow-amber-100">Simpan</button></div>
    </form>
</div>

<div id="hapusModal-<?= $row['id'] ?>" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[90%] sm:w-full max-w-sm bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="p-8 text-center">
        <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl"><i class="fa fa-trash"></i></div>
        <h3 class="text-xl font-bold text-slate-800 mb-2 italic">Hapus Siswa?</h3>
        <p class="text-sm text-slate-500 mb-8">Anda akan menghapus data siswa <span class="font-bold text-slate-800"><?= htmlspecialchars($row['nama_siswa']) ?></span>.</p>
        <form action="" method="POST" class="flex gap-2">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            <input type="hidden" name="id" value="<?= $row['id'] ?>">
            <button type="button" onclick="closeModal('hapusModal-<?= $row['id'] ?>')" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600">Batal</button>
            <button type="submit" name="hapus" class="flex-1 px-4 py-2.5 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 transition-all">Hapus</button>
        </form>
    </div>
</div>
<?php endwhile; ?>

<!-- Page Specific Scripts -->
<script src="<?= BASE_URL ?>assets/js/siswa.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
