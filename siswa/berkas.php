<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];
$msg = '';
$msg_type = '';

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['berkas'])) {
    $jenis = $_POST['jenis_berkas']; // kk or ijazah
    $file = $_FILES['berkas'];

    // Validate
    $allowed = ['image/jpeg', 'image/jpg'];
    $max_size = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowed)) {
        $msg = "Format file harus JPEG/JPG.";
        $msg_type = "error";
    } elseif ($file['size'] > $max_size) {
        $msg = "Ukuran file maksimal 2MB.";
        $msg_type = "error";
    } else {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = "berkas_{$jenis}_{$siswa_id}_" . time() . "." . $ext;
        $upload_dir = __DIR__ . '/../uploads/siswa/berkas/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Get old filename to delete
        $old_file_res = mysqli_query($conn, "SELECT berkas_{$jenis} FROM siswa WHERE id = $siswa_id");
        $old_file = mysqli_fetch_assoc($old_file_res)["berkas_{$jenis}"];

        if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            // Delete old file
            if ($old_file && file_exists($upload_dir . $old_file)) {
                unlink($upload_dir . $old_file);
            }

            // Update database
            $col = "berkas_" . $jenis;
            $stmt = mysqli_prepare($conn, "UPDATE siswa SET $col = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $filename, $siswa_id);

            if (mysqli_stmt_execute($stmt)) {
                $msg = "Berkas " . strtoupper($jenis) . " berhasil diunggah.";
                $msg_type = "success";
            } else {
                $msg = "Gagal memperbarui data database.";
                $msg_type = "error";
            }
        } else {
            $msg = "Gagal mengunggah file.";
            $msg_type = "error";
        }
    }
}

// Get Current Data
$stmt = mysqli_prepare($conn, "SELECT berkas_kk, berkas_ijazah FROM siswa WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $siswa_id);
mysqli_stmt_execute($stmt);
$berkas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$page_title = "Upload Berkas Siswa";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    body { background-color: #F8FAFC; }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 lg:p-8 max-w-2xl mx-auto">

        <div class="mb-8 flex items-center justify-between">
            <a href="index.php" class="w-12 h-12 rounded-2xl bg-white shadow-sm flex items-center justify-center text-slate-400 hover:text-indigo-600 transition-all">
                <i class="fa fa-arrow-left"></i>
            </a>
            <h1 class="text-xl font-black italic text-slate-800 uppercase tracking-widest">Upload Berkas</h1>
            <div class="w-12"></div>
        </div>

        <?php if ($msg): ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            Swal.fire({
                icon: '<?= $msg_type ?>',
                title: '<?= ($msg_type == "success") ? "Berhasil!" : "Gagal!" ?>',
                text: '<?= $msg ?>',
                confirmButtonColor: '#4f46e5'
            });
        </script>
        <?php endif; ?>

        <div class="grid gap-6">
            <!-- KK Card -->
            <div class="lux-card p-6 border-l-4 border-indigo-500">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                            <i class="fa fa-users"></i>
                        </div>
                        <div>
                            <h3 class="font-black text-slate-800 uppercase tracking-widest text-sm">Kartu Keluarga (KK)</h3>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Format: JPEG/JPG, Maks: 2MB</p>
                        </div>
                    </div>
                    <?php if ($berkas['berkas_kk']): ?>
                        <span class="px-3 py-1 bg-emerald-100 text-emerald-600 rounded-full text-[10px] font-black uppercase tracking-widest italic">Tersedia</span>
                    <?php else: ?>
                        <span class="px-3 py-1 bg-rose-100 text-rose-500 rounded-full text-[10px] font-black uppercase tracking-widest italic">Belum Ada</span>
                    <?php endif; ?>
                </div>

                <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="jenis_berkas" value="kk">
                    <div class="relative group">
                        <input type="file" name="berkas" accept=".jpg,.jpeg" required
                            class="w-full px-4 py-3 rounded-xl border-2 border-dashed border-slate-200 group-hover:border-indigo-400 transition-all text-xs font-bold text-slate-500 bg-slate-50 cursor-pointer">
                    </div>
                    <button type="submit" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] shadow-xl shadow-indigo-100 hover:bg-indigo-700 active:scale-95 transition-all">Upload KK</button>
                </form>

                <?php if ($berkas['berkas_kk']): ?>
                <div class="mt-6 pt-6 border-t border-slate-100">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 italic">Preview KK Saat Ini:</p>
                    <div class="w-full aspect-video rounded-2xl overflow-hidden border-4 border-white shadow-xl">
                        <img src="<?= BASE_URL ?>uploads/siswa/berkas/<?= $berkas['berkas_kk'] ?>" class="w-full h-full object-cover">
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Ijazah Card -->
            <div class="lux-card p-6 border-l-4 border-amber-500">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                            <i class="fa fa-graduation-cap"></i>
                        </div>
                        <div>
                            <h3 class="font-black text-slate-800 uppercase tracking-widest text-sm">Ijazah Terakhir</h3>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Format: JPEG/JPG, Maks: 2MB</p>
                        </div>
                    </div>
                    <?php if ($berkas['berkas_ijazah']): ?>
                        <span class="px-3 py-1 bg-emerald-100 text-emerald-600 rounded-full text-[10px] font-black uppercase tracking-widest italic">Tersedia</span>
                    <?php else: ?>
                        <span class="px-3 py-1 bg-rose-100 text-rose-500 rounded-full text-[10px] font-black uppercase tracking-widest italic">Belum Ada</span>
                    <?php endif; ?>
                </div>

                <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="jenis_berkas" value="ijazah">
                    <div class="relative group">
                        <input type="file" name="berkas" accept=".jpg,.jpeg" required
                            class="w-full px-4 py-3 rounded-xl border-2 border-dashed border-slate-200 group-hover:border-amber-400 transition-all text-xs font-bold text-slate-500 bg-slate-50 cursor-pointer">
                    </div>
                    <button type="submit" class="w-full py-4 bg-amber-600 text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] shadow-xl shadow-amber-100 hover:bg-amber-700 active:scale-95 transition-all">Upload Ijazah</button>
                </form>

                <?php if ($berkas['berkas_ijazah']): ?>
                <div class="mt-6 pt-6 border-t border-slate-100">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 italic">Preview Ijazah Saat Ini:</p>
                    <div class="w-full aspect-video rounded-2xl overflow-hidden border-4 border-white shadow-xl">
                        <img src="<?= BASE_URL ?>uploads/siswa/berkas/<?= $berkas['berkas_ijazah'] ?>" class="w-full h-full object-cover">
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
