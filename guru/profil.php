<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru']);

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle photo upload if submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['foto_profil'])) {
    $target_dir = __DIR__ . "/../uploads/guru/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_ext = strtolower(pathinfo($_FILES["foto_profil"]["name"], PATHINFO_EXTENSION));
    $allowed_ext = ['png', 'jpg', 'jpeg'];

    if (in_array($file_ext, $allowed_ext)) {
        // Create unique name
        $filename = "guru_" . $user_id . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $filename;

        if (move_uploaded_file($_FILES["foto_profil"]["tmp_name"], $target_file)) {
            // Get old photo to delete it
            $q_old = mysqli_query($conn, "SELECT foto FROM guru WHERE user_id = $user_id");
            if ($old_data = mysqli_fetch_assoc($q_old)) {
                if (!empty($old_data['foto']) && file_exists($target_dir . $old_data['foto'])) {
                    @unlink($target_dir . $old_data['foto']);
                }
            }

            // Update DB
            mysqli_query($conn, "UPDATE guru SET foto = '$filename' WHERE user_id = $user_id");
            $message = "Foto profil berhasil diperbarui!";
            $message_type = "success";
        } else {
            $message = "Gagal mengunggah file ke server.";
            $message_type = "error";
        }
    } else {
        $message = "Format file tidak didukung. Gunakan PNG, JPG, atau JPEG.";
        $message_type = "error";
    }
}

// Get Guru Profile
$query = "SELECT g.*, u.nama_lengkap, u.username FROM guru g JOIN users u ON g.user_id = u.id WHERE u.id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$guru = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

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
                <h1 class="text-2xl font-black italic text-slate-800 tracking-tight">Profil Guru</h1>
                <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mt-1">Informasi Personal Tenaga Pendidik</p>
            </div>
            <a href="index.php" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-slate-600 transition-all">
                <i class="fa fa-arrow-left"></i>
            </a>
        </div>

        <div class="lux-card p-8 mb-8 bg-gradient-to-br from-indigo-600 to-indigo-800 text-white relative overflow-hidden">
            <div class="relative z-10 flex flex-col items-center text-center">
                <!-- Foto Profil dengan Click to Upload -->
                <form id="fotoForm" action="" method="POST" enctype="multipart/form-data" class="relative group mb-6">
                    <div class="w-32 h-32 rounded-3xl bg-white/20 backdrop-blur-md border-4 border-white/30 flex items-center justify-center text-4xl font-black italic shadow-2xl overflow-hidden relative">
                        <?php if(!empty($guru['foto'])): ?>
                            <img src="<?= BASE_URL ?>uploads/guru/<?= $guru['foto'] ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fa fa-user-tie"></i>
                        <?php endif; ?>

                        <!-- Camera overlay on hover -->
                        <div class="absolute inset-0 bg-slate-900/60 flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                            <i class="fa fa-camera text-xl text-white mb-1"></i>
                            <span class="text-[9px] font-bold text-white uppercase tracking-widest">Ganti Foto</span>
                        </div>
                    </div>
                    <input type="file" name="foto_profil" accept="image/*" onchange="this.form.submit()" class="absolute inset-0 opacity-0 cursor-pointer w-32 h-32 mx-auto left-0 right-0">
                </form>

                <h2 class="text-2xl font-black italic tracking-tight leading-tight mb-1"><?= htmlspecialchars($guru['nama_lengkap']) ?></h2>
                <p class="text-indigo-100 font-bold text-sm tracking-widest">NIP. <?= htmlspecialchars($guru['nip'] ?? '-') ?></p>
                <div class="flex items-center gap-3 mt-4">
                    <span class="px-4 py-1.5 bg-emerald-400 text-emerald-950 rounded-full text-[10px] font-black uppercase tracking-widest italic">Tenaga Pendidik</span>
                </div>
            </div>
            <i class="fa fa-chalkboard-teacher absolute -bottom-10 -right-10 text-[200px] opacity-10"></i>
        </div>

        <div class="space-y-4">
            <div class="lux-card p-6 bg-white border-none shadow-xl flex items-center gap-6">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center text-xl shrink-0"><i class="fa fa-birthday-cake"></i></div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Tempat, Tanggal Lahir</p>
                    <p class="font-bold text-slate-800">
                        <?= htmlspecialchars($guru['tempat_lahir'] ?? '-') ?>,
                        <?= $guru['tanggal_lahir'] ? date('d F Y', strtotime($guru['tanggal_lahir'])) : '-' ?>
                    </p>
                </div>
            </div>

            <div class="lux-card p-6 bg-white border-none shadow-xl flex items-center gap-6">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl shrink-0"><i class="fa fa-phone"></i></div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Nomor Telepon / HP</p>
                    <p class="font-bold text-slate-800"><?= htmlspecialchars($guru['no_telp'] ?? '-') ?></p>
                </div>
            </div>

            <div class="lux-card p-6 bg-white border-none shadow-xl flex items-center gap-6">
                <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center text-xl shrink-0"><i class="fa fa-map-marker-alt"></i></div>
                <div class="flex-1">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Alamat Lengkap</p>
                    <p class="font-bold text-slate-800 leading-relaxed italic"><?= nl2br(htmlspecialchars($guru['alamat'] ?? 'Belum diisi')) ?></p>
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
        confirmButtonColor: '#4f46e5'
    });
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
