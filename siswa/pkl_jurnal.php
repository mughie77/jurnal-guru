<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];

// Check active PKL Mapping
$q_pkl = "SELECT sp.*, tp.nama_tempat, tp.alamat, tp.pembimbing_dudi, tp.no_telp_dudi, u.nama_lengkap as nama_guru_pembimbing, u.no_telp as no_telp_guru
          FROM siswa_pkl sp
          JOIN tempat_pkl tp ON sp.tempat_pkl_id = tp.id
          LEFT JOIN guru g ON tp.guru_pembimbing_id = g.id
          LEFT JOIN users u ON g.user_id = u.id
          WHERE sp.siswa_id = $siswa_id AND sp.tahun_pelajaran_id = $active_tahun_id AND sp.status = 'aktif'
          LIMIT 1";
$res_pkl = mysqli_query($conn, $q_pkl);
$pkl_info = ($res_pkl && mysqli_num_rows($res_pkl) > 0) ? mysqli_fetch_assoc($res_pkl) : null;
$is_pkl = ($pkl_info !== null);

$page_title = $is_pkl ? "Dashboard PKL Siswa" : "Jurnal Kegiatan PKL";
$message = ''; $message_type = '';

// Handle POST add PKL Journal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_jurnal_pkl']) && $is_pkl) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $kegiatan = trim($_POST['kegiatan'] ?? '');
    $tempat_pkl_id = $pkl_info['tempat_pkl_id'];

    if (empty($kegiatan)) {
        $message = "Uraian kegiatan PKL wajib diisi."; $message_type = 'error';
    } else {
        $foto_kegiatan = null;
        if (!empty($_FILES['foto_kegiatan']['name'])) {
            $target_dir = __DIR__ . "/../uploads/pkl/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

            $file_ext = strtolower(pathinfo($_FILES["foto_kegiatan"]["name"], PATHINFO_EXTENSION));
            $allowed_ext = ['png', 'jpg', 'jpeg'];

            if (in_array($file_ext, $allowed_ext)) {
                if ($_FILES["foto_kegiatan"]["size"] <= 3 * 1024 * 1024) {
                    $foto_kegiatan = "pkl_" . time() . "_" . $siswa_id . "." . $file_ext;
                    move_uploaded_file($_FILES["foto_kegiatan"]["tmp_name"], $target_dir . $foto_kegiatan);
                } else {
                    $message = "Ukuran foto maksimal 3MB."; $message_type = 'error';
                }
            } else {
                $message = "Format foto tidak didukung (Gunakan PNG, JPG, JPEG)."; $message_type = 'error';
            }
        }

        if (empty($message_type)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO jurnal_pkl (siswa_id, tempat_pkl_id, tanggal, kegiatan, foto_kegiatan, status_verifikasi) VALUES (?, ?, ?, ?, ?, 'pending')");
            mysqli_stmt_bind_param($stmt, "iisss", $siswa_id, $tempat_pkl_id, $tanggal, $kegiatan, $foto_kegiatan);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Laporan kegiatan PKL berhasil dikirim!"; $message_type = 'success';
            } else {
                $message = "Gagal mengirim laporan PKL: " . mysqli_error($conn); $message_type = 'error';
            }
        }
    }
}

// Fetch list of submitted PKL journals for this student
$jurnals = [];
if ($is_pkl) {
    $tempat_pkl_id = $pkl_info['tempat_pkl_id'];
    $res_j = mysqli_query($conn, "SELECT * FROM jurnal_pkl WHERE siswa_id = $siswa_id AND tempat_pkl_id = $tempat_pkl_id ORDER BY tanggal DESC, created_at DESC");
    while ($j = mysqli_fetch_assoc($res_j)) $jurnals[] = $j;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 sm:p-6 lg:p-8 max-w-full w-full mx-auto">
        <?php if (!$is_pkl): ?>
        <div class="lux-card p-12 text-center bg-white rounded-3xl shadow-xl border border-slate-100 max-w-2xl mx-auto my-12">
            <div class="w-20 h-20 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center mx-auto text-3xl mb-4 shadow-inner">
                <i class="fa fa-info-circle"></i>
            </div>
            <h2 class="text-2xl font-black text-slate-800 italic">Anda Belum Termapping PKL</h2>
            <p class="text-slate-500 text-sm mt-2 leading-relaxed">Menu PKL hanya aktif apabila Anda telah terdaftar dan memiliki status PKL di sistem yang ditentukan oleh sekolah.</p>
            <a href="index.php" class="inline-block mt-6 px-6 py-3 bg-indigo-600 text-white font-bold text-xs rounded-xl shadow-lg hover:bg-indigo-700 transition-all">Kembali ke Dashboard Siswa</a>
        </div>
        <?php else: ?>

        <!-- Dedicated PKL Header -->
        <div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-700 to-indigo-900 text-white relative overflow-hidden">
            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <span class="px-3 py-1 bg-white/20 rounded-full text-[10px] font-black uppercase tracking-widest border border-white/20">Status: Siswa PKL Aktif</span>
                    <h1 class="text-3xl font-black italic tracking-tight mt-2"><?= htmlspecialchars($pkl_info['nama_tempat']) ?></h1>
                    <p class="text-xs text-indigo-100/80 mt-1"><?= htmlspecialchars($pkl_info['alamat'] ?? '-') ?></p>
                </div>
                <div class="flex gap-2">
                    <button onclick="openModal('tambahJurnalModal')" class="px-5 py-3 bg-white text-indigo-900 hover:bg-indigo-50 font-black text-xs uppercase tracking-wider rounded-2xl shadow-xl transition-all flex items-center gap-2">
                        <i class="fa fa-pen-alt"></i> Buat Laporan Jurnal PKL
                    </button>
                </div>
            </div>
            <i class="fa fa-briefcase absolute -bottom-8 -right-8 text-9xl opacity-10"></i>
        </div>

        <?php if ($message): ?>
        <script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= addslashes(htmlspecialchars($message)) ?>', confirmButtonColor: '#4f46e5' });</script>
        <?php endif; ?>

        <!-- Action Grid Menu Khusus PKL Siswa -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-10">
            <button onclick="openModal('tambahJurnalModal')" class="p-4 rounded-3xl bg-indigo-600 text-white shadow-xl shadow-indigo-100 flex flex-col items-center justify-center gap-2 group transition-all hover:scale-105">
                <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center text-lg"><i class="fa fa-file-signature"></i></div>
                <span class="text-xs font-black italic uppercase text-center leading-tight">Laporan Jurnal PKL</span>
            </button>

            <a href="izin.php" class="p-4 rounded-3xl bg-violet-600 text-white shadow-xl shadow-violet-100 flex flex-col items-center justify-center gap-2 group transition-all hover:scale-105">
                <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center text-lg"><i class="fa fa-envelope-open-text"></i></div>
                <span class="text-xs font-black italic uppercase text-center leading-tight">Pengajuan Izin PKL</span>
            </a>

            <a href="absensi.php" class="p-4 rounded-3xl bg-emerald-600 text-white shadow-xl shadow-emerald-100 flex flex-col items-center justify-center gap-2 group transition-all hover:scale-105">
                <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center text-lg"><i class="fa fa-map-marked-alt"></i></div>
                <span class="text-xs font-black italic uppercase text-center leading-tight">Presensi GPS PKL</span>
            </a>

            <a href="pkl_kontak.php" class="p-4 rounded-3xl bg-sky-600 text-white shadow-xl shadow-sky-100 flex flex-col items-center justify-center gap-2 group transition-all hover:scale-105">
                <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center text-lg"><i class="fa fa-address-book"></i></div>
                <span class="text-xs font-black italic uppercase text-center leading-tight">Kontak Pembimbing</span>
            </a>
        </div>

        <!-- Riwayat Jurnal PKL Murid -->
        <div class="lux-card p-6 bg-white shadow-2xl rounded-3xl mb-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-black text-slate-800 italic uppercase tracking-wider">Riwayat Jurnal PKL Saya</h2>
                    <p class="text-xs text-slate-500 font-medium">Daftar laporan harian yang dikirim ke Pembimbing Industri DU/DI.</p>
                </div>
            </div>

            <div class="space-y-4">
                <?php if (empty($jurnals)): ?>
                <div class="p-8 text-center text-slate-400 italic">Belum ada laporan kegiatan PKL yang dikirim.</div>
                <?php else: ?>
                    <?php foreach ($jurnals as $j): ?>
                    <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:border-indigo-100 transition-all">
                        <div class="flex items-start gap-4">
                            <?php if (!empty($j['foto_kegiatan'])): ?>
                                <a href="<?= BASE_URL ?>uploads/pkl/<?= $j['foto_kegiatan'] ?>" target="_blank" class="w-16 h-16 rounded-xl overflow-hidden shrink-0 border border-slate-200">
                                    <img src="<?= BASE_URL ?>uploads/pkl/<?= $j['foto_kegiatan'] ?>" class="w-full h-full object-cover">
                                </a>
                            <?php else: ?>
                                <div class="w-16 h-16 rounded-xl bg-slate-200 text-slate-400 flex items-center justify-center text-2xl shrink-0"><i class="fa fa-image"></i></div>
                            <?php endif; ?>
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-black text-indigo-600"><?= date('d M Y', strtotime($j['tanggal'])) ?></span>
                                    <?php if ($j['status_verifikasi'] == 'disetujui'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-700">Disetujui DU/DI</span>
                                    <?php elseif ($j['status_verifikasi'] == 'ditolak'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-rose-100 text-rose-700">Ditolak DU/DI</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-100 text-amber-700">Menunggu Verifikasi</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-slate-700 leading-relaxed font-medium line-clamp-2"><?= htmlspecialchars($j['kegiatan']) ?></p>
                                <?php if (!empty($j['catatan_dudi'])): ?>
                                <p class="mt-2 text-[10px] text-amber-800 bg-amber-50 p-2 rounded-lg font-semibold italic">Catatan DU/DI: <?= htmlspecialchars($j['catatan_dudi']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah Jurnal PKL -->
<?php if ($is_pkl): ?>
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeModal('tambahJurnalModal')"></div>
<div id="tambahJurnalModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-lg max-h-[90vh] overflow-y-auto bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0">
    <div class="bg-indigo-600 px-6 sm:px-8 py-5 text-white flex justify-between items-center sticky top-0 z-10">
        <h3 class="text-xl font-black italic">Buat Laporan Kegiatan PKL</h3>
        <button type="button" onclick="closeModal('tambahJurnalModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>
    <form action="" method="POST" enctype="multipart/form-data" class="p-6 sm:p-8 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Tanggal Kegiatan</label>
            <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-sm">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Uraian / Deskripsi Kegiatan PKL</label>
            <textarea name="kegiatan" rows="4" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-medium text-xs" placeholder="Jelaskan aktivitas atau pekerjaan yang Anda lakukan hari ini di tempat PKL..."></textarea>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Foto Dokumentasi Kegiatan (Opsional)</label>
            <input type="file" name="foto_kegiatan" accept="image/*" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 font-medium text-xs text-slate-600 bg-slate-50">
            <p class="text-[10px] text-slate-400 mt-1">Format: PNG, JPG, JPEG (Maks. 3MB)</p>
        </div>

        <div class="pt-4 flex gap-3">
            <button type="button" onclick="closeModal('tambahJurnalModal')" class="flex-1 px-4 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
            <button type="submit" name="tambah_jurnal_pkl" class="flex-1 px-4 py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100 text-sm">Kirim Laporan</button>
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
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
