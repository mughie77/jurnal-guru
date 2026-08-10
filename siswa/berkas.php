<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];
$msg = '';
$msg_type = '';

// Handle WA Ortu Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_wa_ortu'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Token Invalid");
    }
    $no_wa_ortu = trim($_POST['no_wa_ortu']);
    if (empty($no_wa_ortu)) {
        $msg = "Nomor WA Orang Tua / Wali wajib diisi.";
        $msg_type = "error";
    } else {
        $stmt_wa = mysqli_prepare($conn, "UPDATE siswa SET no_wa_ortu = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_wa, "si", $no_wa_ortu, $siswa_id);
        if (mysqli_stmt_execute($stmt_wa)) {
            $msg = "Nomor WA Orang Tua / Wali berhasil diperbarui.";
            $msg_type = "success";
        } else {
            $msg = "Gagal memperbarui nomor WA.";
            $msg_type = "error";
        }
    }
}

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['berkas'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Token Invalid");
    }
    // Server-side enforcement of WA Ortu before file upload
    $check_wa = mysqli_query($conn, "SELECT no_wa_ortu FROM siswa WHERE id = $siswa_id");
    $wa_data = mysqli_fetch_assoc($check_wa);

    if (empty($wa_data['no_wa_ortu'])) {
        $msg = "Unggah berkas ditolak. Anda wajib mengisi nomor WA Orang Tua / Wali terlebih dahulu.";
        $msg_type = "error";
    } else {
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
}

// Get Current Data
$stmt = mysqli_prepare($conn, "SELECT berkas_kk, berkas_ijazah, no_wa_ortu FROM siswa WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $siswa_id);
mysqli_stmt_execute($stmt);
$berkas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$has_wa_ortu = !empty($berkas['no_wa_ortu']);

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

        <!-- Parent WA Card (Wajib) -->
        <div class="lux-card p-6 border-l-4 border-emerald-500 mb-6">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <div>
                    <h3 class="font-black text-slate-800 uppercase tracking-widest text-sm">No. WhatsApp Orang Tua / Wali</h3>
                    <p class="text-[10px] font-bold text-rose-500 uppercase tracking-widest italic">* Wajib Diisi Sebelum Upload Berkas</p>
                </div>
            </div>

            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="update_wa_ortu" value="1">
                <div>
                    <input type="text" name="no_wa_ortu" value="<?= htmlspecialchars($berkas['no_wa_ortu'] ?? '') ?>" required placeholder="Contoh: 081234567890"
                        class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-4 focus:ring-emerald-100 font-mono text-sm font-bold text-slate-700 outline-none">
                </div>
                <button type="submit" class="w-full py-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] shadow-xl shadow-emerald-100 active:scale-95 transition-all">
                    Simpan Nomor WA
                </button>
            </form>
        </div>

        <?php if (!$has_wa_ortu): ?>
        <div class="p-6 bg-rose-50 border-2 border-dashed border-rose-200 rounded-3xl text-center mb-6">
            <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center text-lg mx-auto mb-3">
                <i class="fa fa-exclamation-triangle"></i>
            </div>
            <h4 class="font-black text-slate-800 uppercase tracking-wider text-xs mb-1">Akses Diunggah Ditangguhkan</h4>
            <p class="text-[11px] font-bold text-slate-500 leading-relaxed uppercase">Silakan isi dan simpan Nomor WhatsApp Orang Tua / Wali di atas terlebih dahulu untuk mengaktifkan form upload berkas.</p>
        </div>
        <?php endif; ?>

        <div class="grid gap-6 <?= !$has_wa_ortu ? 'opacity-50 pointer-events-none' : '' ?>">
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
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="jenis_berkas" value="kk">
                    <div class="relative group">
                        <input type="file" name="berkas" accept=".jpg,.jpeg" required <?= !$has_wa_ortu ? 'disabled' : '' ?>
                            class="w-full px-4 py-3 rounded-xl border-2 border-dashed border-slate-200 group-hover:border-indigo-400 transition-all text-xs font-bold text-slate-500 bg-slate-50 cursor-pointer">
                    </div>
                    <button type="submit" <?= !$has_wa_ortu ? 'disabled' : '' ?> class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] shadow-xl shadow-indigo-100 hover:bg-indigo-700 active:scale-95 transition-all">Upload KK</button>
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
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="jenis_berkas" value="ijazah">
                    <div class="relative group">
                        <input type="file" name="berkas" accept=".jpg,.jpeg" required <?= !$has_wa_ortu ? 'disabled' : '' ?>
                            class="w-full px-4 py-3 rounded-xl border-2 border-dashed border-slate-200 group-hover:border-amber-400 transition-all text-xs font-bold text-slate-500 bg-slate-50 cursor-pointer">
                    </div>
                    <button type="submit" <?= !$has_wa_ortu ? 'disabled' : '' ?> class="w-full py-4 bg-amber-600 text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] shadow-xl shadow-amber-100 hover:bg-amber-700 active:scale-95 transition-all">Upload Ijazah</button>
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
