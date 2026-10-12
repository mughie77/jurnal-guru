<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin', 'waka', 'guru_bk']);

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$page_title = "Laporan Bimbingan Konseling";
$message = ''; $message_type = '';

// Get guru ID if user is guru
$guru_id = 0;
if ($role === 'guru') {
    $res_g = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id LIMIT 1");
    if ($res_g && $r_g = mysqli_fetch_assoc($res_g)) {
        $guru_id = (int)$r_g['id'];
    }
}

// Handle Add / Edit Laporan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['tambah_laporan']) || isset($_POST['edit_laporan']))) {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) throw new Exception("Token Keamanan Tidak Valid.");

        $lap_id = (int)($_POST['laporan_id'] ?? 0);
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $jenis_bimbingan = $_POST['jenis_bimbingan'] ?? 'Individu';
        $siswa_id = (int)($_POST['siswa_id'] ?? 0);
        $topik = trim($_POST['topik_permasalahan'] ?? '');
        $ringkasan = trim($_POST['ringkasan_pembahasan'] ?? '');
        $tindak_lanjut = trim($_POST['tindak_lanjut'] ?? '');
        $status = $_POST['status'] ?? 'Proses';

        if ($siswa_id <= 0 || empty($topik) || empty($ringkasan)) {
            throw new Exception("Siswa, topik permasalahan, dan ringkasan pembahasan wajib diisi.");
        }

        // Get class for selected student
        $q_k = mysqli_query($conn, "SELECT sk.kelas_id FROM siswa_kelas sk JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id WHERE sk.siswa_id = $siswa_id AND tp.status = 'aktif' LIMIT 1");
        $kelas_id = ($r_k = mysqli_fetch_assoc($q_k)) ? (int)$r_k['kelas_id'] : 0;

        if ($guru_id <= 0 && isset($_POST['guru_id'])) {
            $post_guru = (int)$_POST['guru_id'];
            if ($post_guru > 0) $guru_id = $post_guru;
        }

        if (isset($_POST['tambah_laporan'])) {
            $stmt = mysqli_prepare($conn, "INSERT INTO bk_laporan (tanggal, jenis_bimbingan, siswa_id, guru_id, kelas_id, topik_permasalahan, ringkasan_pembahasan, tindak_lanjut, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssiiissss", $tanggal, $jenis_bimbingan, $siswa_id, $guru_id, $kelas_id, $topik, $ringkasan, $tindak_lanjut, $status);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Laporan Bimbingan Konseling berhasil disimpan!";
                $message_type = 'success';
            } else {
                throw new Exception("Gagal menyimpan laporan.");
            }
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE bk_laporan SET tanggal=?, jenis_bimbingan=?, siswa_id=?, kelas_id=?, topik_permasalahan=?, ringkasan_pembahasan=?, tindak_lanjut=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "ssiissssi", $tanggal, $jenis_bimbingan, $siswa_id, $kelas_id, $topik, $ringkasan, $tindak_lanjut, $status, $lap_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Laporan Bimbingan Konseling berhasil diperbarui!";
                $message_type = 'success';
            } else {
                throw new Exception("Gagal memperbarui laporan.");
            }
        }
    } catch (Exception $e) {
        $message = "Gagal: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle Delete Laporan
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $del_id = (int)($_GET['id'] ?? 0);
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) die("CSRF Token Invalid");

    $stmt = mysqli_prepare($conn, "DELETE FROM bk_laporan WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $del_id);
    if (mysqli_stmt_execute($stmt)) {
        header("Location: " . basename($_SERVER['PHP_SELF']) . "?success_delete=1");
        exit;
    }
}

if (isset($_GET['success_delete'])) {
    $message = "Laporan berhasil dihapus!";
    $message_type = 'success';
}

// Filters & Pagination
$kelas_id_filter = (int)($_GET['kelas_id'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$search_query = trim($_GET['q'] ?? '');

$where_clauses = ["1=1"];
if ($kelas_id_filter > 0) $where_clauses[] = "bl.kelas_id = $kelas_id_filter";
if (!empty($status_filter)) $where_clauses[] = "bl.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
if (!empty($search_query)) {
    $sq = mysqli_real_escape_string($conn, $search_query);
    $where_clauses[] = "(s.nama_siswa LIKE '%$sq%' OR s.nis LIKE '%$sq%' OR bl.topik_permasalahan LIKE '%$sq%' OR u.nama_lengkap LIKE '%$sq%')";
}
if ($role === 'guru') {
    $where_clauses[] = "(bl.guru_id = $guru_id OR 1=1)"; // Teachers can view counseling records
}

$where_sql = implode(' AND ', $where_clauses);

$pagin = get_pagination_data($conn, "bk_laporan bl JOIN siswa s ON bl.siswa_id = s.id LEFT JOIN kelas k ON bl.kelas_id = k.id LEFT JOIN guru g ON bl.guru_id = g.id LEFT JOIN users u ON g.user_id = u.id", 15, "WHERE " . $where_sql);

$query = "SELECT bl.*, s.nama_siswa, s.nis, k.nama_kelas, u.nama_lengkap as nama_guru
          FROM bk_laporan bl
          JOIN siswa s ON bl.siswa_id = s.id
          LEFT JOIN kelas k ON bl.kelas_id = k.id
          LEFT JOIN guru g ON bl.guru_id = g.id
          LEFT JOIN users u ON g.user_id = u.id
          WHERE $where_sql
          ORDER BY bl.tanggal DESC, bl.created_at DESC
          LIMIT {$pagin['offset']}, {$pagin['limit']}";
$res_lap = mysqli_query($conn, $query);

// Fetch active students & classes for modals & filters
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

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Laporan Bimbingan Konseling</h1>
        <p class="text-slate-500 font-medium">Pencatatan rekapitulasi sesi konseling individu dan kelompok siswa.</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <a href="<?= BASE_URL ?>guru/bk_index.php" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2.5 rounded-xl font-bold text-xs flex items-center shadow-md">
            <i class="fa fa-arrow-left mr-2"></i> Kembali ke Layanan BK
        </a>
        <button onclick="openModal('addModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-100 transition-all flex items-center text-xs sm:text-sm">
            <i class="fa fa-plus mr-2"></i> Tambah Laporan BK
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
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Cari Siswa / Topik / Guru</label>
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
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Status Konseling</label>
            <select name="status" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold text-xs text-slate-700">
                <option value="">-- Semua Status --</option>
                <option value="Proses" <?= $status_filter === 'Proses' ? 'selected' : '' ?>>Dalam Proses</option>
                <option value="Selesai" <?= $status_filter === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                <option value="Rujukan" <?= $status_filter === 'Rujukan' ? 'selected' : '' ?>>Rujukan (Alih Tangan)</option>
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
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Jenis & Konselor</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Topik Permasalahan</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Status</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (mysqli_num_rows($res_lap) == 0): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-slate-400 font-bold italic">
                        Belum ada laporan Bimbingan Konseling yang tercatat.
                    </td>
                </tr>
                <?php else: ?>
                    <?php while ($r = mysqli_fetch_assoc($res_lap)):
                        $status_badge = 'bg-amber-100 text-amber-800';
                        if ($r['status'] === 'Selesai') $status_badge = 'bg-emerald-100 text-emerald-800';
                        if ($r['status'] === 'Rujukan') $status_badge = 'bg-purple-100 text-purple-800';
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 font-bold text-slate-800 text-xs">
                            <?= date('d M Y', strtotime($r['tanggal'])) ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-extrabold text-slate-800 text-sm"><?= htmlspecialchars($r['nama_siswa']) ?></div>
                            <div class="text-[10px] text-slate-400 font-mono">NIS: <?= htmlspecialchars($r['nis']) ?> | Kelas <?= htmlspecialchars($r['nama_kelas'] ?? '-') ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-100 block w-fit mb-1">
                                Konseling <?= htmlspecialchars($r['jenis_bimbingan']) ?>
                            </span>
                            <span class="text-xs text-slate-600 font-medium"><i class="fa fa-user-tie text-indigo-400 text-[10px]"></i> <?= htmlspecialchars($r['nama_guru'] ?? 'Konselor BK') ?></span>
                        </td>
                        <td class="px-6 py-4 max-w-xs">
                            <p class="text-xs text-slate-700 font-bold line-clamp-2"><?= htmlspecialchars($r['topik_permasalahan']) ?></p>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?= $status_badge ?>">
                                <?= htmlspecialchars($r['status']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <a href="<?= BASE_URL ?>admin/print_bk_laporan.php?id=<?= $r['id'] ?>" target="_blank" class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-800 hover:text-white transition-all flex items-center justify-center shadow-sm" title="Cetak Laporan">
                                    <i class="fa fa-print text-xs"></i>
                                </a>
                                <button onclick='editLaporan(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)' class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all flex items-center justify-center shadow-sm" title="Edit Laporan">
                                    <i class="fa fa-edit text-xs"></i>
                                </button>
                                <button onclick="confirmDelete(<?= $r['id'] ?>, '<?= get_csrf_token() ?>')" class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all flex items-center justify-center shadow-sm" title="Hapus Laporan">
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

<!-- Modal Add / Edit -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<div id="addModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-2xl bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="bg-indigo-600 px-6 py-5 text-white font-bold italic text-lg sticky top-0 z-10 flex justify-between items-center">
        <span id="modal_title">Tambah Laporan Bimbingan Konseling</span>
        <button type="button" onclick="closeModal('addModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>

    <form action="" method="POST" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <input type="hidden" name="laporan_id" id="laporan_id" value="0">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Konseling</label>
                <input type="date" name="tanggal" id="tanggal" required value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-xs">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Jenis Bimbingan</label>
                <select name="jenis_bimbingan" id="jenis_bimbingan" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-xs">
                    <option value="Individu">Individu</option>
                    <option value="Kelompok">Kelompok</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Siswa</label>
            <select name="siswa_id" id="siswa_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-xs">
                <option value="">-- Pilih Siswa --</option>
                <?php foreach ($student_list as $sl): ?>
                    <option value="<?= $sl['id'] ?>"><?= htmlspecialchars($sl['nama_siswa']) ?> (<?= htmlspecialchars($sl['nama_kelas']) ?> - NIS: <?= htmlspecialchars($sl['nis']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($role === 'admin' || $role === 'waka'): ?>
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Konselor / Guru BK</label>
            <select name="guru_id" id="guru_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-xs">
                <option value="">-- Pilih Konselor --</option>
                <?php mysqli_data_seek($gurus, 0); while ($g = mysqli_fetch_assoc($gurus)): ?>
                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_lengkap']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <?php endif; ?>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Topik / Permasalahan Utama</label>
            <input type="text" name="topik_permasalahan" id="topik_permasalahan" required placeholder="Contoh: Kesulitan belajar, sering terlambat, motivasi diri..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-xs">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Ringkasan Pembahasan & Hasil Konseling</label>
            <textarea name="ringkasan_pembahasan" id="ringkasan_pembahasan" rows="4" required placeholder="Uraikan jalannya konseling dan poin kesepakatan bersama siswa..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-medium text-slate-700 text-xs leading-relaxed"></textarea>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Tindak Lanjut / Rekomendasi</label>
            <textarea name="tindak_lanjut" id="tindak_lanjut" rows="2" placeholder="Rencana tindak lanjut, pemantauan wali kelas, atau rujukan ke wali murid..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-medium text-slate-700 text-xs leading-relaxed"></textarea>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Status Konseling</label>
            <select name="status" id="status" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-xs">
                <option value="Proses">Dalam Proses</option>
                <option value="Selesai">Selesai</option>
                <option value="Rujukan">Rujukan (Alih Tangan Kasus)</option>
            </select>
        </div>

        <div class="pt-4 flex gap-3">
            <button type="button" onclick="closeModal('addModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600 text-xs">Batal</button>
            <button type="submit" id="submit_btn" name="tambah_laporan" class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100 text-xs">Simpan Laporan</button>
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

function editLaporan(item) {
    document.getElementById('modal_title').textContent = 'Edit Laporan Bimbingan Konseling';
    document.getElementById('laporan_id').value = item.id;
    document.getElementById('tanggal').value = item.tanggal;
    document.getElementById('jenis_bimbingan').value = item.jenis_bimbingan;
    document.getElementById('siswa_id').value = item.siswa_id;
    if (document.getElementById('guru_id')) document.getElementById('guru_id').value = item.guru_id;
    document.getElementById('topik_permasalahan').value = item.topik_permasalahan;
    document.getElementById('ringkasan_pembahasan').value = item.ringkasan_pembahasan;
    document.getElementById('tindak_lanjut').value = item.tindak_lanjut || '';
    document.getElementById('status').value = item.status;

    const btn = document.getElementById('submit_btn');
    btn.name = 'edit_laporan';
    btn.textContent = 'Update Laporan';

    openModal('addModal');
}

function confirmDelete(id, csrfToken) {
    Swal.fire({
        title: 'Hapus Laporan BK?',
        text: 'Apakah Anda yakin ingin menghapus data laporan konseling ini?',
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
