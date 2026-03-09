<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
if(mysqli_num_rows($guru_res) == 0) die("Error: Data guru tidak ditemukan.");
$guru_id = mysqli_fetch_assoc($guru_res)['id'];

$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");
    $nama = mysqli_real_escape_string($conn, $_POST['nama_perangkat']);
    $jenis = mysqli_real_escape_string($conn, $_POST['jenis_perangkat']);
    $file = $_FILES['file_perangkat'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = time() . '_' . uniqid() . '.' . $ext;
    $target = __DIR__ . '/../uploads/perangkat/' . $filename;

    if (in_array($ext, ['pdf', 'doc', 'docx'])) {
        if (!is_dir(__DIR__ . '/../uploads/perangkat/')) mkdir(__DIR__ . '/../uploads/perangkat/', 0777, true);
        if (move_uploaded_file($file['tmp_name'], $target)) {
            $path = 'uploads/perangkat/' . $filename;
            mysqli_query($conn, "INSERT INTO perangkat (guru_id, nama_perangkat, jenis_perangkat, file_path) VALUES ($guru_id, '$nama', '$jenis', '$path')");
            $message = "Perangkat berhasil diunggah!"; $message_type = 'success';
        } else { $message = "Gagal mengunggah file."; $message_type = 'error'; }
    } else { $message = "Format file tidak didukung (PDF/Word)."; $message_type = 'error'; }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");
    $id = (int)$_POST['id'];
    $res = mysqli_query($conn, "SELECT file_path FROM perangkat WHERE id = $id AND guru_id = $guru_id");
    if ($row = mysqli_fetch_assoc($res)) {
        if(file_exists(__DIR__ . '/../' . $row['file_path'])) unlink(__DIR__ . '/../' . $row['file_path']);
        mysqli_query($conn, "DELETE FROM perangkat WHERE id = $id");
        $message = "Perangkat dihapus!"; $message_type = 'success';
    }
}

$perangkats = mysqli_query($conn, "SELECT * FROM perangkat WHERE guru_id = $guru_id ORDER BY created_at DESC");
require_once __DIR__ . '/../includes/header.php';
?>

<style>#sidebar, header, nav.navbar { display: none !important; } .lg\:ml-64 { margin-left: 0 !important; } .main-content { margin-left: 0 !important; padding-top: 2rem !important; }</style>

<div class="max-w-5xl mx-auto pb-32 px-4">
    <div class="flex items-center justify-between mb-12">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Perangkat Saya</h1>
            <p class="text-slate-500 font-medium">Kelola berkas administrasi dan modul ajar.</p>
        </div>
        <a href="index.php" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm transition-all"><i class="fa fa-arrow-left"></i></a>
    </div>

    <?php if ($message): ?>
    <script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= $message ?>' });</script>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
        <div class="lg:col-span-1">
            <div class="lux-card p-8 bg-white shadow-2xl sticky top-8 border-none">
                <h3 class="text-sm font-black text-slate-400 uppercase tracking-widest mb-6 italic">Unggah Berkas Baru</h3>
                <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Nama Perangkat</label>
                        <input type="text" name="nama_perangkat" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Jenis</label>
                        <select name="jenis_perangkat" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white font-bold">
                            <option value="RPP">RPP</option><option value="Silabus">Silabus</option><option value="Modul Ajar">Modul Ajar</option><option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">File (PDF/Word)</label>
                        <input type="file" name="file_perangkat" required class="w-full text-xs text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:bg-indigo-50 file:text-indigo-600 cursor-pointer hover:file:bg-indigo-100 transition-all">
                    </div>
                    <button type="submit" name="upload" class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-2xl shadow-xl shadow-indigo-100 transition-all transform hover:-translate-y-1 active:scale-95">SIMPAN BERKAS</button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php while($p = mysqli_fetch_assoc($perangkats)): ?>
                <div class="lux-card p-6 bg-white border-none shadow-xl flex flex-col justify-between hover:scale-[1.02] transition-all group">
                    <div class="flex items-start justify-between mb-6">
                        <div class="w-12 h-12 rounded-2xl bg-slate-50 text-slate-300 flex items-center justify-center text-xl group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-all duration-500">
                            <i class="fa <?= strpos($p['file_path'], '.pdf') !== false ? 'fa-file-pdf' : 'fa-file-word' ?>"></i>
                        </div>
                        <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-500 text-[9px] font-black uppercase border border-slate-200"><?= $p['jenis_perangkat'] ?></span>
                    </div>
                    <div class="mb-6">
                        <h4 class="font-bold text-slate-800 line-clamp-2 italic" title="<?= htmlspecialchars($p['nama_perangkat']) ?>"><?= htmlspecialchars($p['nama_perangkat']) ?></h4>
                        <p class="text-[9px] text-slate-400 font-bold uppercase mt-2 tracking-widest"><?= date('d M Y', strtotime($p['created_at'])) ?></p>
                    </div>
                    <div class="flex gap-2 pt-4 border-t border-slate-50">
                        <a href="<?= BASE_URL . $p['file_path'] ?>" target="_blank" class="flex-1 flex items-center justify-center py-2 bg-indigo-50 text-indigo-600 text-[10px] font-black rounded-xl hover:bg-indigo-600 hover:text-white transition-all italic">BUKA FILE</a>
                        <form action="" method="POST" onsubmit="return confirm('Hapus berkas ini?')">
                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" name="hapus" class="w-9 h-9 flex items-center justify-center text-rose-300 hover:text-rose-600 transition-all"><i class="fa fa-trash-alt text-xs"></i></button>
                        </form>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php if(mysqli_num_rows($perangkats) == 0): ?>
                    <div class="col-span-full py-20 text-center lux-card bg-slate-50/30 border-dashed border-2 border-slate-100 shadow-none">
                        <i class="fa fa-folder-open text-4xl text-slate-100 mb-4"></i>
                        <p class="text-slate-300 font-bold italic tracking-widest text-xs uppercase">Belum ada berkas</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
