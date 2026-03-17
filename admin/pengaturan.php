<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);

$page_title = "Pengaturan Sekolah";
$message = ''; $message_type = '';

// Handle Post
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    mysqli_begin_transaction($conn);
    try {
        // Update simple settings
        $settings = [
            'nama_sekolah' => $_POST['nama_sekolah'],
            'alamat_sekolah' => $_POST['alamat_sekolah'],
            'jam_masuk_sekolah' => $_POST['jam_masuk_sekolah'],
            'dapodik_url' => $_POST['dapodik_url'],
            'dapodik_token' => $_POST['dapodik_token']
        ];

        foreach ($settings as $key => $val) {
            $stmt = mysqli_prepare($conn, "INSERT INTO pengaturan (nama_setting, nilai_setting) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai_setting = VALUES(nilai_setting)");
            mysqli_stmt_bind_param($stmt, "ss", $key, $val);
            mysqli_stmt_execute($stmt);
        }

        // Handle Favicon/Logo Upload
        if (!empty($_FILES['favicon']['name'])) {
            $target_dir = __DIR__ . "/../uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

            $file_ext = strtolower(pathinfo($_FILES["favicon"]["name"], PATHINFO_EXTENSION));
            $allowed_ext = ['png', 'jpg', 'jpeg', 'ico', 'svg'];

            if (in_array($file_ext, $allowed_ext)) {
                $filename = "favicon_" . time() . "." . $file_ext;
                $target_file = $target_dir . $filename;

                if (move_uploaded_file($_FILES["favicon"]["tmp_name"], $target_file)) {
                    $stmt = mysqli_prepare($conn, "INSERT INTO pengaturan (nama_setting, nilai_setting) VALUES ('favicon', ?) ON DUPLICATE KEY UPDATE nilai_setting = VALUES(nilai_setting)");
                    mysqli_stmt_bind_param($stmt, "s", $filename);
                    mysqli_stmt_execute($stmt);
                }
            } else {
                throw new Exception("Format file tidak didukung. Gunakan PNG, JPG, ICO, atau SVG.");
            }
        }

        mysqli_commit($conn);
        $message = "Pengaturan berhasil disimpan!";
        $message_type = 'success';
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "Gagal: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Get current settings
$res = mysqli_query($conn, "SELECT * FROM pengaturan");
$sets = [];
while ($r = mysqli_fetch_assoc($res)) {
    $sets[$r['nama_setting']] = $r['nilai_setting'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Pengaturan Sistem</h1>
    <p class="text-slate-500">Sesuaikan identitas sekolah dan parameter operasional sistem.</p>
</div>

<?php if ($message): ?>
<script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= addslashes(htmlspecialchars($message)) ?>' });</script>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2">
        <div class="lux-card p-8">
            <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-slate-700">Nama Sekolah</label>
                        <input type="text" name="nama_sekolah" value="<?= htmlspecialchars($sets['nama_sekolah'] ?? '') ?>" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 transition-all">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-slate-700">Jam Masuk Sekolah</label>
                        <input type="time" name="jam_masuk_sekolah" value="<?= htmlspecialchars($sets['jam_masuk_sekolah'] ?? '07:00') ?>" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 transition-all">
                        <p class="text-[10px] text-slate-400 font-bold italic">Digunakan untuk menentukan status 'Terlambat' pada absensi.</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-bold text-slate-700">Alamat Sekolah</label>
                    <textarea name="alamat_sekolah" rows="3" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 transition-all"><?= htmlspecialchars($sets['alamat_sekolah'] ?? '') ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-slate-700">URL Web Service Dapodik</label>
                        <input type="text" id="dapodik_url" name="dapodik_url" value="<?= htmlspecialchars($sets['dapodik_url'] ?? '') ?>" placeholder="http://localhost:5774" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 transition-all">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-slate-700">Token Dapodik</label>
                        <input type="password" id="dapodik_token" name="dapodik_token" value="<?= htmlspecialchars($sets['dapodik_token'] ?? '') ?>" placeholder="Masukkan Token..." class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 transition-all">
                    </div>
                </div>

                <div class="p-6 rounded-2xl bg-slate-50 border border-slate-100 space-y-4">
                    <label class="block text-sm font-bold text-slate-700 flex items-center">
                        <i class="fa fa-image mr-2 text-indigo-500"></i> Favicon / Logo Sekolah
                    </label>
                    <div class="flex items-center gap-6">
                        <div class="w-20 h-20 rounded-2xl bg-white border border-slate-200 flex items-center justify-center overflow-hidden shadow-sm p-2">
                            <?php if (!empty($sets['favicon'])): ?>
                                <img src="<?= BASE_URL ?>uploads/<?= $sets['favicon'] ?>" class="max-w-full max-h-full object-contain">
                            <?php else: ?>
                                <i class="fa fa-school text-3xl text-slate-300"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1">
                            <input type="file" name="favicon" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 transition-all">
                            <p class="mt-2 text-[10px] text-slate-400 font-bold italic uppercase tracking-wider">Mendukung: PNG, JPG, SVG, ICO (Maks 2MB)</p>
                        </div>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full md:w-auto px-10 py-4 bg-indigo-600 text-white font-bold rounded-2xl hover:bg-indigo-700 shadow-xl shadow-indigo-100 transition-all flex items-center justify-center active:scale-[0.98]">
                        <i class="fa fa-save mr-2"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>

        <div class="lux-card p-8 mt-8 border-t-4 border-amber-500">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-xl font-bold text-slate-800 italic">Integrasi Dapodik</h3>
                    <p class="text-slate-500 text-sm">Konfigurasi koneksi ke Web Service Dapodik Lokal.</p>
                </div>
                <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center text-amber-500 text-xl">
                    <i class="fa fa-sync-alt"></i>
                </div>
            </div>

            <div class="space-y-6">
                <p class="text-sm text-slate-600">Pastikan Web Service Dapodik sudah diaktifkan di komputer yang terinstall Dapodik dan Token sudah digenerate.</p>

                <div class="flex gap-3">
                    <button type="button" onclick="checkDapodikConnection()" class="px-6 py-3 bg-slate-800 text-white font-bold rounded-xl hover:bg-slate-900 transition-all flex items-center shadow-lg shadow-slate-200">
                        <i class="fa fa-plug mr-2"></i> Cek Koneksi
                    </button>
                    <div id="conn_status" class="flex-1 flex items-center px-4 rounded-xl border border-dashed border-slate-200 text-sm font-bold text-slate-400 italic">
                        Klik tombol untuk memeriksa status koneksi...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="lux-card p-6 bg-gradient-to-br from-indigo-600 to-indigo-800 text-white">
            <h3 class="text-xl font-bold mb-4 italic">Informasi Sistem</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center border-b border-white/10 pb-2">
                    <span class="text-white/60 text-xs font-bold uppercase tracking-wider">PHP Version</span>
                    <span class="font-mono font-bold"><?= phpversion() ?></span>
                </div>
                <div class="flex justify-between items-center border-b border-white/10 pb-2">
                    <span class="text-white/60 text-xs font-bold uppercase tracking-wider">Database</span>
                    <span class="font-mono font-bold">MySQL/MariaDB</span>
                </div>
                <div class="flex justify-between items-center border-b border-white/10 pb-2">
                    <span class="text-white/60 text-xs font-bold uppercase tracking-wider">Environment</span>
                    <span class="px-2 py-0.5 bg-emerald-400 text-emerald-900 rounded text-[10px] font-black uppercase tracking-tighter">Production</span>
                </div>
            </div>
        </div>

        <div class="lux-card p-6">
            <h3 class="text-slate-800 font-bold mb-3 flex items-center">
                <i class="fa fa-lightbulb mr-2 text-amber-500"></i> Tips
            </h3>
            <p class="text-sm text-slate-500 leading-relaxed italic">"Gunakan logo transparan berformat PNG atau SVG untuk hasil tampilan terbaik pada favicon dan dashboard."</p>
        </div>
    </div>
</div>

<script>
function checkDapodikConnection() {
    const url = document.getElementById('dapodik_url').value;
    const token = document.getElementById('dapodik_token').value;
    const statusDiv = document.getElementById('conn_status');

    if(!url || !token) {
        Swal.fire('Peringatan', 'URL dan Token harus diisi!', 'warning');
        return;
    }

    statusDiv.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i> Menghubungkan ke Dapodik...';
    statusDiv.className = "flex-1 flex items-center px-4 rounded-xl border border-dashed border-amber-200 text-sm font-bold text-amber-500 bg-amber-50";

    fetch('cek_koneksi_dapodik.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `url=${encodeURIComponent(url)}&token=${encodeURIComponent(token)}&csrf_token=<?= get_csrf_token() ?>`
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error("Malformed JSON:", text);
            throw new Error("Server mengembalikan format non-JSON. Periksa logs.");
        }
    })
    .then(data => {
        if(data.success) {
            statusDiv.innerHTML = '<i class="fa fa-check-circle mr-2"></i> Koneksi Berhasil: ' + data.message;
            statusDiv.className = "flex-1 flex items-center px-4 rounded-xl border border-dashed border-emerald-200 text-sm font-bold text-emerald-600 bg-emerald-50";
            Swal.fire('Berhasil', 'Koneksi ke Dapodik berhasil terjalin!', 'success');
        } else {
            statusDiv.innerHTML = '<i class="fa fa-times-circle mr-2"></i> Gagal: ' + data.message;
            statusDiv.className = "flex-1 flex items-center px-4 rounded-xl border border-dashed border-rose-200 text-sm font-bold text-rose-600 bg-rose-50";
            Swal.fire('Gagal', data.message, 'error');
        }
    })
    .catch(err => {
        statusDiv.innerHTML = '<i class="fa fa-exclamation-triangle mr-2"></i> Terjadi kesalahan jaringan.';
        statusDiv.className = "flex-1 flex items-center px-4 rounded-xl border border-dashed border-rose-200 text-sm font-bold text-rose-600 bg-rose-50";
        Swal.fire('Error', 'Gagal melakukan request ke server.', 'error');
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
