<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
if (mysqli_num_rows($guru_res) == 0) {
    die("Error: Data guru tidak ditemukan.");
}
$guru_id = (int)mysqli_fetch_assoc($guru_res)['id'];

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: riwayat.php");
    exit;
}

// Ensure the journal belongs to this teacher or user is admin
$res_j = mysqli_query($conn, "SELECT j.*, mp.nama_mapel, k.nama_kelas
                               FROM jurnal j
                               JOIN mata_pelajaran mp ON j.mapel_id = mp.id
                               JOIN kelas k ON j.kelas_id = k.id
                               WHERE j.id = $id AND (j.guru_id = $guru_id OR '" . $_SESSION['role'] . "' = 'admin')");

$j = mysqli_fetch_assoc($res_j);
if (!$j) {
    // Unauthorized or not found
    header("Location: riwayat.php");
    exit;
}

$message = ''; $message_type = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_jurnal'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    $materi = mysqli_real_escape_string($conn, $_POST['materi']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);

    if (trim($materi) === '') {
        $message = "Materi pembahasan wajib diisi!";
        $message_type = 'error';
    } else {
        if (mysqli_query($conn, "UPDATE jurnal SET materi = '$materi', keterangan = '$keterangan' WHERE id = $id")) {
            // Redirect with success
            header("Location: riwayat.php?success_edit=1");
            exit;
        } else {
            $message = "Error: " . mysqli_error($conn); $message_type = 'error';
        }
    }
}

$page_title = "Edit Jurnal";
require_once __DIR__ . '/../includes/header.php';
?>

<style>#sidebar, header, nav.navbar { display: none !important; } .lg\:ml-64 { margin-left: 0 !important; } .main-content { margin-left: 0 !important; padding-top: 2rem !important; }</style>

<div class="max-w-4xl mx-auto pb-32 px-2 sm:px-4">
    <div class="flex items-center justify-between mb-6 sm:mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight italic">Edit Jurnal Mengajar</h1>
            <p class="text-slate-500 font-medium tracking-wide uppercase text-[8px] sm:text-[10px] tracking-[0.2em]">Koreksi Catatan Mengajar</p>
        </div>
        <a href="riwayat.php" class="w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
            <i class="fa fa-arrow-left"></i>
        </a>
    </div>

    <div class="lux-card p-6 sm:p-10 bg-white shadow-2xl relative overflow-hidden">
        <div class="absolute top-0 left-0 w-1 h-full bg-indigo-600"></div>

        <div class="mb-8 sm:mb-10 grid grid-cols-1 xs:grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6 pb-6 sm:pb-8 border-b border-slate-50">
            <div><p class="text-[8px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Mata Pelajaran</p><p class="text-sm sm:text-base font-bold text-slate-800 italic"><?= htmlspecialchars($j['nama_mapel']) ?></p></div>
            <div><p class="text-[8px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Kelas</p><p class="text-sm sm:text-base font-bold text-slate-800"><?= htmlspecialchars($j['nama_kelas']) ?></p></div>
            <div><p class="text-[8px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Tanggal</p><p class="text-sm sm:text-base font-bold text-slate-800"><?= date('d/m/Y', strtotime($j['tanggal'])) ?></p></div>
            <div><p class="text-[8px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Jam Ke-</p><p class="text-sm sm:text-base font-bold text-slate-800"><?= htmlspecialchars($j['jam_ke']) ?></p></div>
        </div>

        <form action="" method="POST" id="edit-jurnal-form" class="space-y-6 sm:space-y-8">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            <div>
                <label class="block text-[10px] sm:text-xs font-black text-slate-400 uppercase tracking-widest mb-2 sm:mb-3 ml-1">Materi Pembahasan</label>
                <textarea name="materi" rows="5" required class="w-full px-4 sm:px-6 py-3 sm:py-4 rounded-2xl sm:rounded-3xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-medium text-slate-600 text-base sm:text-lg transition-all"><?= htmlspecialchars($j['materi']) ?></textarea>
            </div>

            <div>
                <label class="block text-[10px] sm:text-xs font-black text-slate-400 uppercase tracking-widest mb-2 sm:mb-3 ml-1">Catatan Tambahan (Opsional)</label>
                <input type="text" name="keterangan" value="<?= htmlspecialchars($j['keterangan'] ?? '') ?>" class="w-full px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-slate-100 font-medium text-slate-500 text-sm sm:text-base">
            </div>

            <button type="submit" name="update_jurnal" class="w-full py-4 sm:py-5 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-2xl sm:rounded-3xl shadow-2xl shadow-indigo-200 transition-all transform hover:-translate-y-1 active:scale-95 flex items-center justify-center gap-2 tracking-[0.1em] text-base sm:text-lg">
                <i class="fa fa-save text-lg sm:text-xl"></i> SIMPAN PERUBAHAN
            </button>
        </form>
    </div>
</div>

<?php if ($message !== ''): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?= $message_type ?>',
        title: '<?= $message_type === 'success' ? 'Berhasil' : 'Peringatan' ?>',
        text: '<?= addslashes($message) ?>',
        confirmButtonColor: '#4F46E5'
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
