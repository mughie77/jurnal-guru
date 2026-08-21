<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['dudi', 'admin']);

$user_id = $_SESSION['user_id'];

// Get Tempat PKL assigned to this DU/DI user
$res_tp = mysqli_query($conn, "SELECT id, nama_tempat FROM tempat_pkl WHERE user_dudi_id = $user_id LIMIT 1");
$tempat_pkl = mysqli_fetch_assoc($res_tp);
$tempat_pkl_id = $tempat_pkl['id'] ?? 0;

$page_title = "Verifikasi Jurnal PKL Murid";
$message = ''; $message_type = '';

// Handle Verification Action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verifikasi_jurnal'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    $jurnal_id = (int)$_POST['jurnal_id'];
    $status_verifikasi = $_POST['status_verifikasi'] ?? 'pending';
    $catatan_dudi = trim($_POST['catatan_dudi'] ?? '');

    if (!in_array($status_verifikasi, ['disetujui', 'ditolak'])) {
        $status_verifikasi = 'disetujui';
    }

    $stmt = mysqli_prepare($conn, "UPDATE jurnal_pkl SET status_verifikasi = ?, catatan_dudi = ? WHERE id = ? AND tempat_pkl_id = ?");
    mysqli_stmt_bind_param($stmt, "ssii", $status_verifikasi, $catatan_dudi, $jurnal_id, $tempat_pkl_id);

    if (mysqli_stmt_execute($stmt)) {
        $message = "Status jurnal PKL murid berhasil diperbarui!";
        $message_type = 'success';
    } else {
        $message = "Gagal memperbarui status jurnal PKL.";
        $message_type = 'error';
    }
}

// Search & Filters
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$filter_status = mysqli_real_escape_string($conn, $_GET['status'] ?? '');

$where_clauses = ["jp.tempat_pkl_id = $tempat_pkl_id"];
if (!empty($search)) {
    $where_clauses[] = "(s.nama_siswa LIKE '%$search%' OR s.nis LIKE '%$search%' OR jp.kegiatan LIKE '%$search%')";
}
if (!empty($filter_status) && in_array($filter_status, ['pending', 'disetujui', 'ditolak'])) {
    $where_clauses[] = "jp.status_verifikasi = '$filter_status'";
}

$where_sql = " WHERE " . implode(" AND ", $where_clauses);

$pagin = get_pagination_data($conn, "jurnal_pkl jp JOIN siswa s ON jp.siswa_id = s.id", 10, $where_sql);

$q_jurnal = "SELECT jp.*, s.nama_siswa, s.nis, k.nama_kelas
             FROM jurnal_pkl jp
             JOIN siswa s ON jp.siswa_id = s.id
             JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = $active_tahun_id
             JOIN kelas k ON sk.kelas_id = k.id
             $where_sql
             ORDER BY jp.tanggal DESC, jp.created_at DESC
             LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";
$res_jurnal = mysqli_query($conn, $q_jurnal);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto pb-24">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Jurnal Kegiatan PKL Murid</h1>
            <p class="text-slate-500 font-medium">Tinjau dan berikan catatan/persetujuan pada laporan harian siswa PKL.</p>
        </div>
    </div>

    <?php if ($message): ?>
    <script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= addslashes(htmlspecialchars($message)) ?>', confirmButtonColor: '#4f46e5' });</script>
    <?php endif; ?>

    <div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cari Jurnal</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama siswa, NIS, kegiatan..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white text-xs font-semibold">
            </div>
            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Status Verifikasi</label>
                <select name="status" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white text-xs font-semibold">
                    <option value="">Semua Status</option>
                    <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Pending (Menunggu)</option>
                    <option value="disetujui" <?= $filter_status == 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
                    <option value="ditolak" <?= $filter_status == 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                </select>
            </div>
            <div class="flex items-end gap-3">
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl text-xs hover:bg-indigo-700 transition-all shadow-md shadow-indigo-100">Filter</button>
                <a href="jurnal.php" class="px-5 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl text-xs hover:bg-slate-300 transition-all">Reset</a>
            </div>
        </form>
    </div>

    <div class="lux-card overflow-hidden bg-white shadow-2xl rounded-3xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Siswa & Kelas</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Tanggal</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Aktivitas Kegiatan</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Foto</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Status</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (mysqli_num_rows($res_jurnal) == 0): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400 font-bold italic">Belum ada laporan jurnal PKL siswa.</td>
                    </tr>
                    <?php else: ?>
                        <?php while ($j = mysqli_fetch_assoc($res_jurnal)): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($j['nama_siswa']) ?></div>
                                <div class="text-[10px] text-slate-400 font-bold">NIS: <?= htmlspecialchars($j['nis']) ?> | <span class="text-indigo-600"><?= htmlspecialchars($j['nama_kelas']) ?></span></div>
                            </td>
                            <td class="px-6 py-4 text-xs font-bold text-slate-600 whitespace-nowrap">
                                <?= date('d M Y', strtotime($j['tanggal'])) ?>
                            </td>
                            <td class="px-6 py-4 max-w-md">
                                <div class="text-xs text-slate-700 leading-relaxed font-medium line-clamp-3"><?= htmlspecialchars($j['kegiatan']) ?></div>
                                <?php if (!empty($j['catatan_dudi'])): ?>
                                <div class="mt-2 p-2 bg-amber-50 rounded-xl border border-amber-100 text-[10px] font-semibold text-amber-800 italic">
                                    Catatan DU/DI: <?= htmlspecialchars($j['catatan_dudi']) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if (!empty($j['foto_kegiatan'])): ?>
                                    <a href="<?= BASE_URL ?>uploads/pkl/<?= $j['foto_kegiatan'] ?>" target="_blank" class="inline-block w-12 h-12 rounded-xl overflow-hidden border border-slate-200 hover:scale-105 transition-transform shadow-sm">
                                        <img src="<?= BASE_URL ?>uploads/pkl/<?= $j['foto_kegiatan'] ?>" class="w-full h-full object-cover">
                                    </a>
                                <?php else: ?>
                                    <span class="text-slate-300 text-xs italic">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($j['status_verifikasi'] == 'disetujui'): ?>
                                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-100">Disetujui</span>
                                <?php elseif ($j['status_verifikasi'] == 'ditolak'): ?>
                                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider bg-rose-50 text-rose-600 border border-rose-100">Ditolak</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-100 animate-pulse">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button onclick="openVerifikasiModal(<?= htmlspecialchars(json_encode($j)) ?>)" class="px-3 py-1.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 shadow-md shadow-indigo-100 transition-all">
                                    <i class="fa fa-check-square mr-1"></i> Verifikasi
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>

<!-- Overlay -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeModal('verifikasiModal')"></div>

<!-- Modal Verifikasi Jurnal -->
<div id="verifikasiModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-lg max-h-[90vh] overflow-y-auto bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0">
    <div class="bg-indigo-600 px-6 sm:px-8 py-5 text-white flex justify-between items-center sticky top-0 z-10">
        <h3 class="text-xl font-black italic">Verifikasi Jurnal PKL</h3>
        <button type="button" onclick="closeModal('verifikasiModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>
    <form action="" method="POST" class="p-6 sm:p-8 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <input type="hidden" name="jurnal_id" id="v_jurnal_id">

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Nama Siswa</label>
            <input type="text" id="v_nama_siswa" readonly class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 text-xs">
        </div>

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Uraian Kegiatan Siswa</label>
            <textarea id="v_kegiatan" readonly rows="3" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-medium text-slate-600 text-xs italic"></textarea>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Pilih Status Verifikasi</label>
            <select name="status_verifikasi" id="v_status_verifikasi" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-sm bg-white">
                <option value="disetujui">Disetujui</option>
                <option value="ditolak">Ditolak</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Catatan DU/DI (Opsional)</label>
            <textarea name="catatan_dudi" id="v_catatan_dudi" rows="3" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-medium text-xs" placeholder="Tambahkan saran atau evaluasi untuk siswa..."></textarea>
        </div>

        <div class="pt-4 flex gap-3">
            <button type="button" onclick="closeModal('verifikasiModal')" class="flex-1 px-4 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
            <button type="submit" name="verifikasi_jurnal" class="flex-1 px-4 py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100 text-sm">Simpan Verifikasi</button>
        </div>
    </form>
</div>

<script>
const overlay = document.getElementById('modalOverlay');

function openModal(id) {
    const m = document.getElementById(id);
    overlay.classList.remove('hidden');
    m.classList.remove('hidden');
    setTimeout(() => {
        overlay.classList.add('opacity-100');
        m.classList.add('opacity-100', 'scale-100');
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

function openVerifikasiModal(j) {
    document.getElementById('v_jurnal_id').value = j.id;
    document.getElementById('v_nama_siswa').value = j.nama_siswa + " (" + j.nama_kelas + ")";
    document.getElementById('v_kegiatan').value = j.kegiatan;
    document.getElementById('v_status_verifikasi').value = j.status_verifikasi !== 'pending' ? j.status_verifikasi : 'disetujui';
    document.getElementById('v_catatan_dudi').value = j.catatan_dudi || '';
    openModal('verifikasiModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
