<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['dudi', 'admin']);

$user_id = $_SESSION['user_id'];

// Get Tempat PKL assigned to this DU/DI user
$res_tp = mysqli_query($conn, "SELECT id, nama_tempat FROM tempat_pkl WHERE user_dudi_id = $user_id LIMIT 1");
$tempat_pkl = mysqli_fetch_assoc($res_tp);
$tempat_pkl_id = $tempat_pkl['id'] ?? 0;

$page_title = "Penilaian PKL Murid";
$message = ''; $message_type = '';

// Handle POST Save Grades
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_nilai'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    $siswa_id = (int)$_POST['siswa_id'];
    $nilai_disiplin = (float)($_POST['nilai_disiplin'] ?? 0);
    $nilai_keterampilan = (float)($_POST['nilai_keterampilan'] ?? 0);
    $nilai_sikap = (float)($_POST['nilai_sikap'] ?? 0);
    $catatan = trim($_POST['catatan'] ?? '');

    // Calculate Average
    $nilai_rata = round(($nilai_disiplin + $nilai_keterampilan + $nilai_sikap) / 3, 2);

    $stmt = mysqli_prepare($conn, "INSERT INTO nilai_pkl (siswa_id, tempat_pkl_id, user_dudi_id, nilai_disiplin, nilai_keterampilan, nilai_sikap, nilai_rata, catatan)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE nilai_disiplin = VALUES(nilai_disiplin), nilai_keterampilan = VALUES(nilai_keterampilan), nilai_sikap = VALUES(nilai_sikap), nilai_rata = VALUES(nilai_rata), catatan = VALUES(catatan)");
    mysqli_stmt_bind_param($stmt, "iiidddds", $siswa_id, $tempat_pkl_id, $user_id, $nilai_disiplin, $nilai_keterampilan, $nilai_sikap, $nilai_rata, $catatan);

    if (mysqli_stmt_execute($stmt)) {
        $message = "Nilai PKL berhasil disimpan!";
        $message_type = 'success';
    } else {
        $message = "Gagal menyimpan nilai PKL.";
        $message_type = 'error';
    }
}

// Fetch Active Siswa PKL List with existing grades
$q_siswa_nilai = "SELECT sp.siswa_id, s.nama_siswa, s.nis, k.nama_kelas, np.nilai_disiplin, np.nilai_keterampilan, np.nilai_sikap, np.nilai_rata, np.catatan
                  FROM siswa_pkl sp
                  JOIN siswa s ON sp.siswa_id = s.id
                  JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = sp.tahun_pelajaran_id
                  JOIN kelas k ON sk.kelas_id = k.id
                  LEFT JOIN nilai_pkl np ON sp.siswa_id = np.siswa_id AND sp.tempat_pkl_id = np.tempat_pkl_id
                  WHERE sp.tempat_pkl_id = $tempat_pkl_id AND sp.tahun_pelajaran_id = $active_tahun_id AND sp.status = 'aktif'
                  ORDER BY k.nama_kelas ASC, s.nama_siswa ASC";
$res_siswa_nilai = mysqli_query($conn, $q_siswa_nilai);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto pb-24">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Penilaian Praktik Kerja Lapangan</h1>
            <p class="text-slate-500 font-medium">Input dan kelola nilai evaluasi kedisiplinan, keterampilan, dan sikap siswa PKL.</p>
        </div>
    </div>

    <?php if ($message): ?>
    <script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= addslashes(htmlspecialchars($message)) ?>', confirmButtonColor: '#4f46e5' });</script>
    <?php endif; ?>

    <div class="lux-card overflow-hidden bg-white shadow-2xl rounded-3xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Siswa & Kelas</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Kedisiplinan</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Keterampilan</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Sikap</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Rata-Rata</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (mysqli_num_rows($res_siswa_nilai) == 0): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400 font-bold italic">Belum ada siswa PKL terdaftar.</td>
                    </tr>
                    <?php else: ?>
                        <?php while ($sn = mysqli_fetch_assoc($res_siswa_nilai)): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($sn['nama_siswa']) ?></div>
                                <div class="text-[10px] text-slate-400 font-bold">NIS: <?= htmlspecialchars($sn['nis']) ?> | <span class="text-indigo-600"><?= htmlspecialchars($sn['nama_kelas']) ?></span></div>
                            </td>
                            <td class="px-6 py-4 text-center font-black text-slate-700 text-sm">
                                <?= $sn['nilai_disiplin'] ? number_format($sn['nilai_disiplin'], 1) : '-' ?>
                            </td>
                            <td class="px-6 py-4 text-center font-black text-slate-700 text-sm">
                                <?= $sn['nilai_keterampilan'] ? number_format($sn['nilai_keterampilan'], 1) : '-' ?>
                            </td>
                            <td class="px-6 py-4 text-center font-black text-slate-700 text-sm">
                                <?= $sn['nilai_sikap'] ? number_format($sn['nilai_sikap'], 1) : '-' ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($sn['nilai_rata']): ?>
                                    <span class="px-3 py-1 bg-emerald-50 text-emerald-600 border border-emerald-100 font-black text-sm rounded-xl">
                                        <?= number_format($sn['nilai_rata'], 1) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-300 text-xs italic">Belum Dinilai</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button onclick="openNilaiModal(<?= htmlspecialchars(json_encode($sn)) ?>)" class="px-3 py-1.5 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 shadow-md shadow-emerald-100 transition-all">
                                    <i class="fa fa-star mr-1"></i> Input / Edit Nilai
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

<!-- Overlay -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeModal('nilaiModal')"></div>

<!-- Modal Input / Edit Nilai -->
<div id="nilaiModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-lg max-h-[90vh] overflow-y-auto bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0">
    <div class="bg-emerald-600 px-6 sm:px-8 py-5 text-white flex justify-between items-center sticky top-0 z-10">
        <h3 class="text-xl font-black italic">Form Penilaian PKL Siswa</h3>
        <button type="button" onclick="closeModal('nilaiModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>
    <form action="" method="POST" class="p-6 sm:p-8 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <input type="hidden" name="siswa_id" id="n_siswa_id">

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Nama Siswa</label>
            <input type="text" id="n_nama_siswa" readonly class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 text-xs">
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Kedisiplinan</label>
                <input type="number" step="0.1" min="0" max="100" name="nilai_disiplin" id="n_disiplin" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200 font-black text-sm text-center">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Keterampilan</label>
                <input type="number" step="0.1" min="0" max="100" name="nilai_keterampilan" id="n_keterampilan" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200 font-black text-sm text-center">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Sikap / Ethos</label>
                <input type="number" step="0.1" min="0" max="100" name="nilai_sikap" id="n_sikap" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200 font-black text-sm text-center">
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Catatan Evaluasi (Opsional)</label>
            <textarea name="catatan" id="n_catatan" rows="3" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-100 font-medium text-xs" placeholder="Tuliskan apresiasi atau saran pengembangan siswa..."></textarea>
        </div>

        <div class="pt-4 flex gap-3">
            <button type="button" onclick="closeModal('nilaiModal')" class="flex-1 px-4 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
            <button type="submit" name="simpan_nilai" class="flex-1 px-4 py-3 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 shadow-lg shadow-emerald-100 text-sm">Simpan Nilai</button>
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

function openNilaiModal(sn) {
    document.getElementById('n_siswa_id').value = sn.siswa_id;
    document.getElementById('n_nama_siswa').value = sn.nama_siswa + " (" + sn.nama_kelas + ")";
    document.getElementById('n_disiplin').value = sn.nilai_disiplin || 80;
    document.getElementById('n_keterampilan').value = sn.nilai_keterampilan || 80;
    document.getElementById('n_sikap').value = sn.nilai_sikap || 80;
    document.getElementById('n_catatan').value = sn.catatan || '';
    openModal('nilaiModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
