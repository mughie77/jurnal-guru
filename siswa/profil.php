<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profil'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    $jk = $_POST['jenis_kelamin'];
    $tempat_lahir = $_POST['tempat_lahir'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $no_telp = $_POST['no_telp'];
    $alamat = $_POST['alamat'];

    $sql = "UPDATE siswa SET jenis_kelamin = ?, tempat_lahir = ?, tanggal_lahir = ?, no_telp = ?, alamat = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssssi", $jk, $tempat_lahir, $tanggal_lahir, $no_telp, $alamat, $siswa_id);

    if (mysqli_stmt_execute($stmt)) {
        $message = "Profil berhasil diperbarui!";
        $message_type = "success";
    } else {
        $message = "Gagal memperbarui profil.";
        $message_type = "error";
    }
}

// Get Student Profile and Active Class
$query_profile = "SELECT s.*, k.nama_kelas, tp.tahun as tahun_pelajaran
                  FROM siswa s
                  LEFT JOIN siswa_kelas sk ON s.id = sk.siswa_id
                  LEFT JOIN kelas k ON sk.kelas_id = k.id
                  LEFT JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id
                  WHERE s.id = ? AND (tp.status = 'aktif' OR tp.status IS NULL)
                  LIMIT 1";
$stmt = mysqli_prepare($conn, $query_profile);
mysqli_stmt_bind_param($stmt, "i", $siswa_id);
mysqli_stmt_execute($stmt);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$page_title = "Profil Saya";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    body { background-color: #F8FAFC; }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 lg:p-8 max-w-2xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-black italic text-slate-800 tracking-tight">Profil Saya</h1>
                <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mt-1">Informasi Data Diri Siswa</p>
            </div>
            <a href="index.php" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-slate-600 transition-all">
                <i class="fa fa-arrow-left"></i>
            </a>
        </div>

        <?php if ($message): ?>
            <script>
                Swal.fire({
                    icon: '<?= $message_type ?>',
                    title: '<?= $message_type == 'success' ? 'Berhasil' : 'Gagal' ?>',
                    text: '<?= $message ?>',
                    customClass: { popup: 'rounded-3xl', title: 'font-black italic' }
                });
            </script>
        <?php endif; ?>

        <div class="lux-card p-8 mb-8 bg-gradient-to-br from-indigo-600 to-indigo-800 text-white relative overflow-hidden">
            <div class="relative z-10 flex flex-col items-center text-center">
                <div class="w-32 h-32 rounded-3xl bg-white/20 backdrop-blur-md border-4 border-white/30 flex items-center justify-center text-4xl font-black italic shadow-2xl overflow-hidden mb-6">
                    <?php if(!empty($siswa['foto'])): ?>
                        <img src="<?= BASE_URL ?>uploads/siswa/<?= $siswa['foto'] ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?= strtoupper(substr($siswa['nama_siswa'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <h2 class="text-2xl font-black italic tracking-tight leading-tight mb-1"><?= htmlspecialchars($siswa['nama_siswa']) ?></h2>
                <p class="text-indigo-100 font-bold text-sm tracking-widest"><?= htmlspecialchars($siswa['nis']) ?> / <?= htmlspecialchars($siswa['nisn'] ?? '-') ?></p>
                <div class="flex items-center gap-3 mt-4">
                    <span class="px-4 py-1.5 bg-white/10 rounded-full text-[10px] font-black uppercase tracking-widest border border-white/20"><?= htmlspecialchars($siswa['nama_kelas'] ?? 'Belum Ada Kelas') ?></span>
                    <span class="px-4 py-1.5 bg-emerald-400 text-emerald-950 rounded-full text-[10px] font-black uppercase tracking-widest italic">Aktif</span>
                </div>
            </div>
            <i class="fa fa-user-graduate absolute -bottom-10 -right-10 text-[200px] opacity-10"></i>
        </div>

        <div class="space-y-4">
            <div class="lux-card p-6 bg-white border-none shadow-xl flex items-center gap-6">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-500 flex items-center justify-center text-xl shrink-0"><i class="fa fa-venus-mars"></i></div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Jenis Kelamin</p>
                    <p class="font-bold text-slate-800"><?= $siswa['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan' ?></p>
                </div>
            </div>

            <div class="lux-card p-6 bg-white border-none shadow-xl flex items-center gap-6">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center text-xl shrink-0"><i class="fa fa-birthday-cake"></i></div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Tempat, Tanggal Lahir</p>
                    <p class="font-bold text-slate-800">
                        <?= htmlspecialchars($siswa['tempat_lahir'] ?? '-') ?>,
                        <?= $siswa['tanggal_lahir'] ? date('d F Y', strtotime($siswa['tanggal_lahir'])) : '-' ?>
                    </p>
                </div>
            </div>

            <div class="lux-card p-6 bg-white border-none shadow-xl flex items-center gap-6">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl shrink-0"><i class="fa fa-phone"></i></div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Nomor Telepon / HP</p>
                    <p class="font-bold text-slate-800"><?= htmlspecialchars($siswa['no_telp'] ?? '-') ?></p>
                </div>
            </div>

            <div class="lux-card p-6 bg-white border-none shadow-xl flex items-center gap-6">
                <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center text-xl shrink-0"><i class="fa fa-map-marker-alt"></i></div>
                <div class="flex-1">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Alamat Lengkap</p>
                    <p class="font-bold text-slate-800 leading-relaxed italic"><?= nl2br(htmlspecialchars($siswa['alamat'] ?? 'Belum diisi')) ?></p>
                </div>
            </div>
        </div>

        <div class="mt-12 text-center">
            <button onclick="openModal('editProfilModal')" class="px-8 py-3 bg-slate-900 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest italic shadow-xl hover:bg-indigo-600 transition-all flex items-center justify-center mx-auto gap-2">
                <i class="fa fa-edit"></i> Edit Profil
            </button>
        </div>
    </div>
</div>

<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<div id="editProfilModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] max-w-lg bg-white rounded-[32px] sm:rounded-[40px] shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="bg-slate-900 px-6 py-5 sm:px-8 sm:py-6 text-white flex items-center justify-between">
        <h3 class="text-lg sm:text-xl font-black italic tracking-widest uppercase">Edit Profil</h3>
        <button onclick="closeModal('editProfilModal')" class="text-white/50 hover:text-white transition-colors"><i class="fa fa-times text-xl"></i></button>
    </div>
    <form action="" method="POST" class="p-6 sm:p-8 space-y-4 sm:space-y-5 max-h-[70vh] overflow-y-auto">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Jenis Kelamin</label>
            <select name="jenis_kelamin" required class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 border-2 border-transparent focus:border-indigo-100 outline-none font-bold text-slate-700 transition-all">
                <option value="L" <?= $siswa['jenis_kelamin'] == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                <option value="P" <?= $siswa['jenis_kelamin'] == 'P' ? 'selected' : '' ?>>Perempuan</option>
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Tempat Lahir</label>
                <input type="text" name="tempat_lahir" value="<?= htmlspecialchars($siswa['tempat_lahir'] ?? '') ?>" required class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 border-2 border-transparent focus:border-indigo-100 outline-none font-bold text-slate-700 transition-all">
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Tanggal Lahir</label>
                <input type="date" name="tanggal_lahir" value="<?= htmlspecialchars($siswa['tanggal_lahir'] ?? '') ?>" required class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 border-2 border-transparent focus:border-indigo-100 outline-none font-bold text-slate-700 transition-all">
            </div>
        </div>

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Nomor Telp / HP</label>
            <input type="text" name="no_telp" value="<?= htmlspecialchars($siswa['no_telp'] ?? '') ?>" required class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 border-2 border-transparent focus:border-indigo-100 outline-none font-bold text-slate-700 transition-all">
        </div>

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Alamat Lengkap</label>
            <textarea name="alamat" rows="3" required class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 border-2 border-transparent focus:border-indigo-100 outline-none font-bold text-slate-700 transition-all"><?= htmlspecialchars($siswa['alamat'] ?? '') ?></textarea>
        </div>

        <div class="pt-4">
            <button type="submit" name="update_profil" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black italic tracking-widest uppercase shadow-xl shadow-indigo-100 hover:bg-indigo-700 transition-all active:scale-95">Simpan Perubahan</button>
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
