<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin', 'waka']);
$page_title = "Mapping Siswa PKL";
$message = ''; $message_type = '';

if (!$active_tahun_id) die("Error: Tidak ada tahun pelajaran aktif.");

// Get list of Tempat PKL
$res_tempat = mysqli_query($conn, "SELECT tp.*, u.nama_lengkap as nama_guru_pembimbing
                                   FROM tempat_pkl tp
                                   LEFT JOIN guru g ON tp.guru_pembimbing_id = g.id
                                   LEFT JOIN users u ON g.user_id = u.id
                                   ORDER BY tp.nama_tempat ASC");
$tempats = [];
while ($tp = mysqli_fetch_assoc($res_tempat)) $tempats[] = $tp;

// Get list of classes for Bulk Mapping
$res_kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
$kelases = [];
while ($k = mysqli_fetch_assoc($res_kelases)) $kelases[] = $k;

// Handle POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    if (isset($_POST['simpan_mapping_pkl'])) {
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
    } elseif (isset($_POST['hapus_siswa_pkl'])) {
        $sp_id = (int)$_POST['siswa_pkl_id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM siswa_pkl WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $sp_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Status PKL siswa berhasil dilepas!"; $message_type = 'success';
        } else {
            $message = "Gagal melepas status PKL siswa."; $message_type = 'error';
        }
    }
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

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Mapping Siswa PKL</h1>
        <p class="text-slate-500 font-medium">Tetapkan status dan alokasikan tempat PKL untuk siswa secara individu maupun masal.</p>
    </div>
    <div>
        <button onclick="openModal('mappingModal')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-2xl font-bold shadow-lg shadow-emerald-200 transition-all flex items-center">
            <i class="fa fa-user-plus mr-2"></i> Mapping Siswa PKL Baru
        </button>
    </div>
</div>

<?php if ($message): ?>
<script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= addslashes(htmlspecialchars($message)) ?>', confirmButtonColor: '#4f46e5' });</script>
<?php endif; ?>

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
            <a href="pkl_mapping.php" class="px-4 py-2 bg-slate-200 text-slate-600 font-bold rounded-xl text-xs hover:bg-slate-300 transition-all">Reset</a>
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

<!-- Modal Mapping Siswa PKL -->
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

function closeAllModals() {
    document.querySelectorAll('.modal-content').forEach(m => {
        if (!m.classList.contains('hidden')) closeModal(m.id);
    });
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
