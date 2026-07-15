<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];
$nama_siswa = $_SESSION['nama_lengkap'];
$message = '';
$message_type = '';

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_kritik'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Token CSRF tidak valid.";
        $message_type = "error";
    } else {
        $subjek = mysqli_real_escape_string($conn, $_POST['subjek']);
        $isi = mysqli_real_escape_string($conn, $_POST['isi']);

        if (empty($subjek) || empty($isi)) {
            $message = "Subjek dan Isi kritik/saran tidak boleh kosong.";
            $message_type = "error";
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO kritik_saran (user_id, nama_pengirim, role, subjek, isi) VALUES (?, ?, 'siswa', ?, ?)");
            mysqli_stmt_bind_param($stmt, "isss", $siswa_id, $nama_siswa, $subjek, $isi);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Kritik & saran Anda berhasil dikirim!";
                $message_type = "success";
            } else {
                $message = "Gagal mengirim data. Silakan coba kembali.";
                $message_type = "error";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Retrieve past submissions for current student
$query = "SELECT * FROM kritik_saran WHERE user_id = ? AND role = 'siswa' ORDER BY tanggal DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $siswa_id);
mysqli_stmt_execute($stmt);
$feedbacks = mysqli_stmt_get_result($stmt);

$page_title = "Kritik & Saran";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    body { background-color: #F8FAFC; }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 lg:p-8 max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-black italic text-slate-800 tracking-tight">Kritik & Saran</h1>
                <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mt-1">Kirim Umpan Balik untuk Kemajuan Sekolah</p>
            </div>
            <a href="index.php" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-slate-600 transition-all">
                <i class="fa fa-arrow-left"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Form Card -->
            <div class="lg:col-span-1">
                <div class="lux-card p-6 bg-white border-none shadow-xl">
                    <h3 class="font-black text-slate-800 italic uppercase tracking-widest text-xs mb-6">Form Pengaduan</h3>
                    <form action="" method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Subjek / Judul</label>
                            <input type="text" name="subjek" required placeholder="Contoh: Fasilitas Kelas, dll..."
                                   class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 transition-all">
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Kritik / Saran / Isi</label>
                            <textarea name="isi" rows="5" required placeholder="Tulis kritik atau saran Anda di sini..."
                                      class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-medium text-slate-700 transition-all italic text-sm"></textarea>
                        </div>

                        <button type="submit" name="submit_kritik" class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-xl shadow-lg shadow-indigo-100 transition-all uppercase tracking-wider text-xs">
                            <i class="fa fa-paper-plane mr-2"></i> Kirim Sekarang
                        </button>
                    </form>
                </div>
            </div>

            <!-- History list -->
            <div class="lg:col-span-2">
                <div class="lux-card p-6 bg-white border-none shadow-xl min-h-[400px]">
                    <h3 class="font-black text-slate-800 italic uppercase tracking-widest text-xs mb-6 font-bold">Riwayat Kiriman Anda</h3>

                    <div class="space-y-4">
                        <?php if (mysqli_num_rows($feedbacks) == 0): ?>
                            <div class="text-center py-20 text-slate-400 italic font-bold text-sm">
                                <i class="fa fa-comment-slash text-4xl block mb-4 text-slate-200"></i>
                                Belum ada kritik & saran yang Anda kirimkan.
                            </div>
                        <?php else: ?>
                            <?php while ($f = mysqli_fetch_assoc($feedbacks)): ?>
                                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-black text-slate-800 italic text-sm"><?= htmlspecialchars($f['subjek']) ?></h4>
                                        <span class="text-[9px] text-slate-400 font-black"><?= date('d M Y, H:i', strtotime($f['tanggal'])) ?></span>
                                    </div>
                                    <p class="text-xs text-slate-600 leading-relaxed italic">"<?= nl2br(htmlspecialchars($f['isi'])) ?>"</p>

                                    <?php if (!empty($f['umpan_balik'])): ?>
                                        <div class="mt-3 p-4 bg-emerald-50 rounded-xl border border-emerald-100 text-emerald-800 space-y-1">
                                            <div class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-wider text-emerald-600">
                                                <i class="fa fa-reply"></i> Balasan / Umpan Balik Waka/Admin:
                                            </div>
                                            <p class="text-xs leading-relaxed font-bold italic">"<?= nl2br(htmlspecialchars($f['umpan_balik'])) ?>"</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-2 flex items-center gap-1">
                                            <i class="fa fa-clock"></i> Menunggu Tanggapan
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php if (!empty($message)): ?>
    Swal.fire({
        icon: '<?= $message_type ?>',
        title: '<?= $message_type == "success" ? "Berhasil!" : "Gagal!" ?>',
        text: '<?= $message ?>',
        confirmButtonColor: '#4f46e5',
        customClass: { popup: 'rounded-3xl', title: 'font-black italic' }
    });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
