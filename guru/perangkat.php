<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
$guru_id = mysqli_fetch_assoc($guru_res)['id'];

$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama_perangkat']);
    $jenis = mysqli_real_escape_string($conn, $_POST['jenis_perangkat']);

    $file = $_FILES['file_perangkat'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = time() . '_' . uniqid() . '.' . $ext;
    $target = __DIR__ . '/../uploads/perangkat/' . $filename;

    if (in_array(strtolower($ext), ['pdf', 'doc', 'docx'])) {
        if (move_uploaded_file($file['tmp_name'], $target)) {
            $path = 'uploads/perangkat/' . $filename;
            mysqli_query($conn, "INSERT INTO perangkat (guru_id, nama_perangkat, jenis_perangkat, file_path) VALUES ($guru_id, '$nama', '$jenis', '$path')");
            $message = "Perangkat berhasil diunggah!"; $message_type = 'success';
        } else {
            $message = "Gagal mengunggah file."; $message_type = 'error';
        }
    } else {
        $message = "Hanya file PDF, DOC, atau DOCX yang diizinkan."; $message_type = 'error';
    }
}

if (isset($_POST['hapus'])) {
    $id = (int)$_POST['id'];
    $res = mysqli_query($conn, "SELECT file_path FROM perangkat WHERE id = $id AND guru_id = $guru_id");
    if ($row = mysqli_fetch_assoc($res)) {
        unlink(__DIR__ . '/../' . $row['file_path']);
        mysqli_query($conn, "DELETE FROM perangkat WHERE id = $id");
        $message = "Perangkat dihapus!"; $message_type = 'success';
    }
}

$perangkats = mysqli_query($conn, "SELECT * FROM perangkat WHERE guru_id = $guru_id ORDER BY created_at DESC");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-5xl mx-auto pb-20 px-4">
    <div class="flex items-center justify-between mb-12">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Perangkat Mengajar</h1>
            <p class="text-slate-500 font-medium tracking-wide">Kelola modul, RPP, dan silabus Anda.</p>
        </div>
        <a href="index.php" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
            <i class="fa fa-arrow-left"></i>
        </a>
    </div>

    <?php if ($message): ?>
    <script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= $message ?>' });</script>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Form Upload -->
        <div class="lg:col-span-1">
            <div class="lux-card p-8 sticky top-8">
                <h3 class="text-lg font-black text-slate-800 mb-6 italic uppercase tracking-widest border-b border-slate-50 pb-4">Unggah Baru</h3>
                <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Nama Perangkat</label>
                        <input type="text" name="nama_perangkat" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold" placeholder="Contoh: RPP Matematika Ganjil">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Jenis Perangkat</label>
                        <select name="jenis_perangkat" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white font-bold">
                            <option value="RPP">RPP</option>
                            <option value="Silabus">Silabus</option>
                            <option value="Modul Ajar">Modul Ajar</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">File (PDF/Word)</label>
                        <input type="file" name="file_perangkat" required class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-black file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 cursor-pointer">
                    </div>
                    <button type="submit" name="upload" class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-2xl shadow-xl shadow-indigo-100 transition-all transform hover:-translate-y-1 active:scale-95">SIMPAN PERANGKAT</button>
                </form>
            </div>
        </div>

        <!-- Daftar Perangkat -->
        <div class="lg:col-span-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php while($p = mysqli_fetch_assoc($perangkats)): ?>
                <div class="lux-card p-6 flex flex-col justify-between hover:border-indigo-200 transition-all group">
                    <div class="flex items-start justify-between mb-6">
                        <div class="w-12 h-12 rounded-xl bg-slate-50 text-slate-400 flex items-center justify-center text-xl group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-all">
                            <i class="fa <?= str_contains($p['file_path'], '.pdf') ? 'fa-file-pdf' : 'fa-file-word' ?>"></i>
                        </div>
                        <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-500 text-[9px] font-black uppercase border border-slate-200"><?= $p['jenis_perangkat'] ?></span>
                    </div>
                    <div class="mb-6">
                        <h4 class="font-bold text-slate-800 line-clamp-2" title="<?= htmlspecialchars($p['nama_perangkat']) ?>"><?= htmlspecialchars($p['nama_perangkat']) ?></h4>
                        <p class="text-[10px] text-slate-400 mt-1"><?= date('d M Y, H:i', strtotime($p['created_at'])) ?></p>
                    </div>
                    <div class="flex gap-2 pt-4 border-t border-slate-50">
                        <a href="<?= BASE_URL . $p['file_path'] ?>" target="_blank" class="flex-1 flex items-center justify-center py-2 bg-indigo-50 text-indigo-600 text-xs font-black rounded-lg hover:bg-indigo-600 hover:text-white transition-all italic tracking-tighter">Buka File</a>
                        <form action="" method="POST" onsubmit="return confirm('Hapus perangkat ini?')">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" name="hapus" class="w-8 h-8 flex items-center justify-center text-rose-300 hover:text-rose-600 transition-all"><i class="fa fa-trash-alt"></i></button>
                        </form>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php if(mysqli_num_rows($perangkats) == 0): ?>
                    <div class="col-span-full py-20 text-center lux-card bg-slate-50/50 border-dashed border-2 border-slate-200">
                        <i class="fa fa-folder-open text-4xl text-slate-200 mb-4"></i>
                        <p class="text-slate-400 font-medium italic">Belum ada perangkat yang diunggah.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
