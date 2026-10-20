<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin', 'waka', 'guru_bk']);

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$page_title = "Poin Pelanggaran & Apresiasi Siswa";
$message = ''; $message_type = '';

// Get guru ID if role is guru
$guru_id = 0;
if ($role === 'guru') {
    $res_g = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id LIMIT 1");
    if ($res_g && $r_g = mysqli_fetch_assoc($res_g)) {
        $guru_id = (int)$r_g['id'];
    }
}

// Handle Submit Pelanggaran / Apresiasi Siswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['tambah_poin']) || isset($_POST['edit_poin']))) {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) throw new Exception("Token Keamanan Tidak Valid.");

        $p_id = (int)($_POST['poin_id'] ?? 0);
        $siswa_id = (int)($_POST['siswa_id'] ?? 0);
        $master_id = (int)($_POST['master_id'] ?? 0);
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $catatan = trim($_POST['catatan'] ?? '');

        if ($siswa_id <= 0) throw new Exception("Pilih siswa terlebih dahulu.");

        $nama_kejadian = ''; $tipe = 'Pelanggaran'; $poin = 10;
        if ($master_id > 0) {
            $q_m = mysqli_query($conn, "SELECT * FROM bk_master_pelanggaran WHERE id = $master_id LIMIT 1");
            if ($r_m = mysqli_fetch_assoc($q_m)) {
                $nama_kejadian = $r_m['nama_pelanggaran'];
                $tipe = $r_m['tipe'];
                $poin = (int)$r_m['poin'];
            }
        } else {
            $nama_kejadian = trim($_POST['custom_nama_kejadian'] ?? '');
            $tipe = $_POST['custom_tipe'] ?? 'Pelanggaran';
            $poin = (int)($_POST['custom_poin'] ?? 10);
        }

        if (empty($nama_kejadian)) throw new Exception("Nama kejadian/pelanggaran wajib diisi.");

        if ($guru_id <= 0 && isset($_POST['guru_id'])) {
            $post_guru = (int)$_POST['guru_id'];
            if ($post_guru > 0) $guru_id = $post_guru;
        }

        if (isset($_POST['tambah_poin'])) {
            $stmt = mysqli_prepare($conn, "INSERT INTO bk_pelanggaran_siswa (siswa_id, master_id, guru_id, tanggal, nama_kejadian, tipe, poin, catatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iiisssis", $siswa_id, $master_id, $guru_id, $tanggal, $nama_kejadian, $tipe, $poin, $catatan);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Catatan kedisiplinan siswa berhasil ditambahkan!";
                $message_type = 'success';
            } else {
                throw new Exception("Gagal menyimpan data.");
            }
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE bk_pelanggaran_siswa SET siswa_id=?, master_id=?, tanggal=?, nama_kejadian=?, tipe=?, poin=?, catatan=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "iisssisi", $siswa_id, $master_id, $tanggal, $nama_kejadian, $tipe, $poin, $catatan, $p_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Catatan kedisiplinan berhasil diperbarui!";
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

// Handle Delete Record
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $del_id = (int)($_GET['id'] ?? 0);
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) die("CSRF Token Invalid");

    $stmt = mysqli_prepare($conn, "DELETE FROM bk_pelanggaran_siswa WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $del_id);
    if (mysqli_stmt_execute($stmt)) {
        header("Location: " . basename($_SERVER['PHP_SELF']) . "?success_delete=1");
        exit;
    }
}

if (isset($_GET['success_delete'])) {
    $message = "Record berhasil dihapus!";
    $message_type = 'success';
}

// Filters & Pagination
$kelas_id_filter = (int)($_GET['kelas_id'] ?? 0);
$tipe_filter = $_GET['tipe'] ?? '';
$search_query = trim($_GET['q'] ?? '');

$where_clauses = ["1=1"];
if ($kelas_id_filter > 0) $where_clauses[] = "sk.kelas_id = $kelas_id_filter";
if (!empty($tipe_filter)) $where_clauses[] = "ps.tipe = '" . mysqli_real_escape_string($conn, $tipe_filter) . "'";
if (!empty($search_query)) {
    $sq = mysqli_real_escape_string($conn, $search_query);
    $where_clauses[] = "(s.nama_siswa LIKE '%$sq%' OR s.nis LIKE '%$sq%' OR ps.nama_kejadian LIKE '%$sq%' OR u.nama_lengkap LIKE '%$sq%')";
}

$where_sql = implode(' AND ', $where_clauses);

$pagin = get_pagination_data($conn, "bk_pelanggaran_siswa ps JOIN siswa s ON ps.siswa_id = s.id LEFT JOIN siswa_kelas sk ON (s.id = sk.siswa_id AND sk.tahun_pelajaran_id = $active_tahun_id) LEFT JOIN kelas k ON sk.kelas_id = k.id LEFT JOIN guru g ON ps.guru_id = g.id LEFT JOIN users u ON g.user_id = u.id", 15, "WHERE " . $where_sql);

$query = "SELECT ps.*, s.nama_siswa, s.nis, k.nama_kelas, u.nama_lengkap as nama_guru,
            (SELECT SUM(CASE WHEN tipe='Pelanggaran' THEN poin ELSE -poin END) FROM bk_pelanggaran_siswa WHERE siswa_id = s.id) as total_poin_siswa
          FROM bk_pelanggaran_siswa ps
          JOIN siswa s ON ps.siswa_id = s.id
          LEFT JOIN siswa_kelas sk ON (s.id = sk.siswa_id AND sk.tahun_pelajaran_id = $active_tahun_id)
          LEFT JOIN kelas k ON sk.kelas_id = k.id
          LEFT JOIN guru g ON ps.guru_id = g.id
          LEFT JOIN users u ON g.user_id = u.id
          WHERE $where_sql
          ORDER BY ps.tanggal DESC, ps.created_at DESC
          LIMIT {$pagin['offset']}, {$pagin['limit']}";
$res_ps = mysqli_query($conn, $query);

// Fetch master rules & active students for modal
$res_mp = mysqli_query($conn, "SELECT * FROM bk_master_pelanggaran ORDER BY tipe ASC, nama_pelanggaran ASC");
$master_rules = [];
while ($mp = mysqli_fetch_assoc($res_mp)) {
    $master_rules[] = $mp;
}

$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
$students = mysqli_query($conn, "SELECT s.id, s.nama_siswa, s.nis, k.nama_kelas
                                 FROM siswa s
                                 JOIN siswa_kelas sk ON s.id = sk.siswa_id
                                 JOIN kelas k ON sk.kelas_id = k.id
                                 JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id
                                 WHERE tp.status = 'aktif'
                                 ORDER BY k.nama_kelas ASC, s.nama_siswa ASC");
$student_list = [];
while ($st = mysqli_fetch_assoc($students)) {
    $student_list[] = $st;
}

$gurus = mysqli_query($conn, "SELECT g.id, u.nama_lengkap FROM guru g JOIN users u ON g.user_id = u.id ORDER BY u.nama_lengkap ASC");

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Select2 CSS & jQuery -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
/* Select2 Custom Styling for Modals */
.select2-container--default .select2-selection--single {
    border-radius: 0.75rem !important;
    border-color: #e2e8f0 !important;
    height: 42px !important;
    display: flex !important;
    align-items: center !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    font-weight: 700 !important;
    color: #334155 !important;
    font-size: 0.75rem !important;
    padding-left: 0.75rem !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 40px !important;
    right: 8px !important;
}
.select2-dropdown {
    border-radius: 1rem !important;
    border-color: #e2e8f0 !important;
    box-shadow: 0 10px 25px -3px rgba(15, 23, 42, 0.1) !important;
    overflow: hidden !important;
    z-index: 9999 !important;
}
.select2-search__field {
    border-radius: 0.5rem !important;
    border-color: #e2e8f0 !important;
    padding: 0.4rem 0.6rem !important;
    font-size: 0.75rem !important;
    font-weight: 600 !important;
}
</style>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Poin Pelanggaran & Apresiasi Siswa</h1>
        <p class="text-slate-500 font-medium">Pencatatan akumulasi poin kedisiplinan dan apresiasi prestasi siswa.</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <a href="<?= BASE_URL ?>guru/bk_index.php" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2.5 rounded-xl font-bold text-xs flex items-center shadow-md">
            <i class="fa fa-arrow-left mr-2"></i> Kembali ke Layanan BK
        </a>
        <?php if ($role === 'admin' || $role === 'waka'): ?>
        <a href="bk_master_pelanggaran.php" class="bg-slate-700 hover:bg-slate-800 text-white px-4 py-2.5 rounded-xl font-bold text-xs flex items-center shadow-md">
            <i class="fa fa-cogs mr-2"></i> Master Aturan Poin
        </a>
        <?php endif; ?>
        <button onclick="openModal('addPoinModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-100 transition-all flex items-center text-xs sm:text-sm">
            <i class="fa fa-plus mr-2"></i> Catat Kejadian / Poin
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

<!-- Filter Form -->
<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/40 to-white border border-indigo-100/50">
    <form action="" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Cari Siswa / Pelanggaran / Guru</label>
            <input type="text" name="q" value="<?= htmlspecialchars($search_query) ?>" placeholder="Kata kunci..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold text-xs text-slate-700">
        </div>

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Kelas</label>
            <select name="kelas_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold text-xs text-slate-700">
                <option value="">-- Semua Kelas --</option>
                <?php mysqli_data_seek($kelases, 0); while ($k = mysqli_fetch_assoc($kelases)): ?>
                    <option value="<?= $k['id'] ?>" <?= $k['id'] == $kelas_id_filter ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Tipe</label>
            <select name="tipe" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold text-xs text-slate-700">
                <option value="">-- Semua Tipe --</option>
                <option value="Pelanggaran" <?= $tipe_filter === 'Pelanggaran' ? 'selected' : '' ?>>Pelanggaran</option>
                <option value="Apresiasi" <?= $tipe_filter === 'Apresiasi' ? 'selected' : '' ?>>Apresiasi</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 py-2.5 bg-indigo-600 text-white font-bold rounded-xl text-xs hover:bg-indigo-700 transition-all shadow-md shadow-indigo-100">Filter</button>
            <a href="<?= basename($_SERVER['PHP_SELF']) ?>" class="px-4 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl text-xs hover:bg-slate-300 transition-all">Reset</a>
        </div>
    </form>
</div>

<!-- Table Listing -->
<div class="lux-card overflow-hidden bg-white shadow-2xl rounded-3xl mb-8">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Tanggal</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Siswa & Kelas</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Nama Kejadian / Pelanggaran</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Poin</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Total Akumulasi</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (mysqli_num_rows($res_ps) == 0): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-slate-400 font-bold italic">
                        Belum ada catatan pelanggaran atau apresiasi siswa.
                    </td>
                </tr>
                <?php else: ?>
                    <?php while ($r = mysqli_fetch_assoc($res_ps)):
                        $is_app = $r['tipe'] === 'Apresiasi';
                        $tot_poin = max(0, (int)$r['total_poin_siswa']);

                        // Status SP Badge
                        $sp_badge = 'bg-emerald-100 text-emerald-800'; $sp_label = 'Aman';
                        if ($tot_poin >= 20 && $tot_poin < 40) { $sp_badge = 'bg-amber-100 text-amber-800'; $sp_label = 'Peringatan'; }
                        elseif ($tot_poin >= 40 && $tot_poin < 60) { $sp_badge = 'bg-orange-100 text-orange-800'; $sp_label = 'SP 1'; }
                        elseif ($tot_poin >= 60 && $tot_poin < 85) { $sp_badge = 'bg-rose-100 text-rose-800'; $sp_label = 'SP 2'; }
                        elseif ($tot_poin >= 85) { $sp_badge = 'bg-red-600 text-white animate-pulse'; $sp_label = 'SP 3 / Rujukan'; }
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 font-bold text-slate-800 text-xs">
                            <?= date('d M Y', strtotime($r['tanggal'])) ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-extrabold text-slate-800 text-sm"><?= htmlspecialchars($r['nama_siswa']) ?></div>
                            <div class="text-[10px] text-slate-400 font-mono">NIS: <?= htmlspecialchars($r['nis']) ?> | Kelas <?= htmlspecialchars($r['nama_kelas'] ?? '-') ?></div>
                        </td>
                        <td class="px-6 py-4 max-w-xs">
                            <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider <?= $is_app ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' ?> block w-fit mb-1">
                                <?= htmlspecialchars($r['tipe']) ?>
                            </span>
                            <div class="font-bold text-slate-800 text-xs line-clamp-1"><?= htmlspecialchars($r['nama_kejadian']) ?></div>
                            <div class="text-[10px] text-slate-400">Pencatat: <?= htmlspecialchars($r['nama_guru'] ?? 'Sistem Admin') ?></div>
                        </td>
                        <td class="px-6 py-4 text-center font-black text-sm <?= $is_app ? 'text-emerald-600' : 'text-rose-600' ?>">
                            <?= $is_app ? '-' : '+' ?><?= $r['poin'] ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="font-black text-sm text-slate-800"><?= $tot_poin ?> Poin</div>
                            <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider <?= $sp_badge ?> block w-fit mx-auto mt-0.5">
                                <?= $sp_label ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <a href="<?= BASE_URL ?>admin/print_bk_pelanggaran.php?siswa_id=<?= $r['siswa_id'] ?>" target="_blank" class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-800 hover:text-white transition-all flex items-center justify-center shadow-sm" title="Cetak Kartu Poin Siswa">
                                    <i class="fa fa-print text-xs"></i>
                                </a>
                                <button onclick='editPoin(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)' class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all flex items-center justify-center shadow-sm" title="Edit Poin">
                                    <i class="fa fa-edit text-xs"></i>
                                </button>
                                <button onclick="confirmDelete(<?= $r['id'] ?>, '<?= get_csrf_token() ?>')" class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all flex items-center justify-center shadow-sm" title="Hapus Poin">
                                    <i class="fa fa-trash text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>
</div>

<!-- Modal Add / Edit Poin -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<div id="addPoinModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[94vw] sm:w-full max-w-lg bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden max-h-[90vh] flex flex-col">
    <div class="bg-indigo-600 px-6 py-5 text-white font-bold italic text-lg sticky top-0 z-10 flex justify-between items-center flex-shrink-0">
        <span id="modal_title">Catat Pelanggaran / Apresiasi Siswa</span>
        <button type="button" onclick="closeModal('addPoinModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>

    <form action="" method="POST" class="p-6 space-y-4 overflow-y-auto flex-1">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <input type="hidden" name="poin_id" id="poin_id" value="0">

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Siswa</label>
            <select name="siswa_id" id="siswa_id" required class="select2-modal w-full">
                <option value="">-- Cari / Pilih Siswa --</option>
                <?php foreach ($student_list as $sl): ?>
                    <option value="<?= $sl['id'] ?>"><?= htmlspecialchars($sl['nama_siswa']) ?> (<?= htmlspecialchars($sl['nama_kelas']) ?> - NIS: <?= htmlspecialchars($sl['nis']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Aturan Dari Master</label>
            <select name="master_id" id="master_id" class="select2-modal w-full">
                <option value="0">-- Input Aturan Custom (Lainnya) --</option>
                <?php foreach ($master_rules as $mr): ?>
                    <option value="<?= $mr['id'] ?>">
                        [<?= $mr['tipe'] ?>] <?= htmlspecialchars($mr['nama_pelanggaran']) ?> (<?= $mr['tipe'] === 'Apresiasi' ? '-' : '+' ?><?= $mr['poin'] ?> Poin)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Custom Fields if Master not selected -->
        <div id="custom_fields_box" class="space-y-3 p-4 bg-slate-50 border border-slate-200 rounded-2xl">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Kejadian / Pelanggaran</label>
                <input type="text" name="custom_nama_kejadian" id="custom_nama_kejadian" placeholder="Contoh: Merusak fasilitas kelas..." class="w-full px-4 py-2 rounded-xl border border-slate-200 outline-none font-bold text-slate-700 text-xs">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tipe</label>
                    <select name="custom_tipe" id="custom_tipe" class="w-full px-4 py-2 rounded-xl border border-slate-200 outline-none font-bold text-slate-700 text-xs">
                        <option value="Pelanggaran">Pelanggaran (+Poin)</option>
                        <option value="Apresiasi">Apresiasi (-Poin)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Bobot Poin</label>
                    <input type="number" name="custom_poin" id="custom_poin" value="10" min="1" class="w-full px-4 py-2 rounded-xl border border-slate-200 outline-none font-bold text-slate-700 text-xs">
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Kejadian</label>
                <input type="date" name="tanggal" id="tanggal" required value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-xs">
            </div>

            <?php if ($role === 'admin' || $role === 'waka'): ?>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pencatat / Guru</label>
                <select name="guru_id" id="guru_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-xs">
                    <option value="">-- Pencatat Admin --</option>
                    <?php mysqli_data_seek($gurus, 0); while ($g = mysqli_fetch_assoc($gurus)): ?>
                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_lengkap']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Catatan Kejadian / Keterangan Tambahan</label>
            <textarea name="catatan" id="catatan" rows="3" placeholder="Sebutkan tempat, saksi, atau kronologi singkat..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-medium text-slate-700 text-xs leading-relaxed"></textarea>
        </div>

        <div class="pt-4 flex gap-3">
            <button type="button" onclick="closeModal('addPoinModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600 text-xs">Batal</button>
            <button type="submit" id="submit_btn" name="tambah_poin" class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100 text-xs">Simpan Poin</button>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    $('#siswa_id, #master_id').select2({
        dropdownParent: $('#addPoinModal'),
        width: '100%'
    });

    $('#master_id').on('change', function() {
        toggleCustomFields(this.value);
    });
});

function toggleCustomFields(masterVal) {
    const customBox = document.getElementById('custom_fields_box');
    if (parseInt(masterVal) > 0) {
        customBox.classList.add('hidden');
    } else {
        customBox.classList.remove('hidden');
    }
}

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

function editPoin(item) {
    document.getElementById('modal_title').textContent = 'Edit Record Poin Kedisiplinan';
    document.getElementById('poin_id').value = item.id;
    $('#siswa_id').val(item.siswa_id).trigger('change');
    $('#master_id').val(item.master_id || 0).trigger('change');
    document.getElementById('tanggal').value = item.tanggal;
    document.getElementById('catatan').value = item.catatan || '';

    if (!item.master_id || parseInt(item.master_id) === 0) {
        document.getElementById('custom_nama_kejadian').value = item.nama_kejadian;
        document.getElementById('custom_tipe').value = item.tipe;
        document.getElementById('custom_poin').value = item.poin;
    }
    toggleCustomFields(item.master_id || 0);

    const btn = document.getElementById('submit_btn');
    btn.name = 'edit_poin';
    btn.textContent = 'Update Record';

    openModal('addPoinModal');
}

function confirmDelete(id, csrfToken) {
    Swal.fire({
        title: 'Hapus Record Poin?',
        text: 'Apakah Anda yakin ingin menghapus catatan poin kedisiplinan ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '<?= basename($_SERVER['PHP_SELF']) ?>?action=delete&id=' + id + '&csrf_token=' + csrfToken;
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
