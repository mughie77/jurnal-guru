<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);

$page_title = "Pengaturan API Eksternal";
$message = ''; $message_type = '';

// Handle POST to save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Token Invalid");
    }

    if (isset($_POST['save_api_key'])) {
        $key = trim($_POST['external_api_key']);
        $stmt = mysqli_prepare($conn, "INSERT INTO pengaturan (nama_setting, nilai_setting) VALUES ('external_api_key', ?) ON DUPLICATE KEY UPDATE nilai_setting = VALUES(nilai_setting)");
        mysqli_stmt_bind_param($stmt, "s", $key);
        if (mysqli_stmt_execute($stmt)) {
            $message = "API Key berhasil disimpan!";
            $message_type = 'success';
        } else {
            $message = "Gagal menyimpan API Key.";
            $message_type = 'error';
        }
    }
}

// Fetch current setting
$res = mysqli_query($conn, "SELECT nilai_setting FROM pengaturan WHERE nama_setting = 'external_api_key'");
$api_key = '';
if ($row = mysqli_fetch_assoc($res)) {
    $api_key = $row['nilai_setting'];
}
if (empty($api_key)) {
    $api_key = 'CAKRA_SECURE_API_KEY_2026'; // Default fallback
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Pengaturan API</h1>
    <p class="text-slate-500">Konfigurasi dan kelola otentikasi API Eksternal untuk integrasi aplikasi sekolah lainnya.</p>
</div>

<?php if ($message): ?>
<script>
    Swal.fire({
        icon: '<?= $message_type ?>',
        title: '<?= $message_type === 'success' ? 'Berhasil!' : 'Gagal' ?>',
        text: '<?= addslashes(htmlspecialchars($message)) ?>',
        confirmButtonColor: '#4F46E5'
    });
</script>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2">
        <div class="lux-card p-8 bg-white space-y-6">
            <form action="" method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="save_api_key" value="1">

                <div class="space-y-2">
                    <label class="block text-sm font-bold text-slate-700">Kunci API Eksternal (API Key)</label>
                    <div class="flex gap-3">
                        <input type="text" id="api_key_input" name="external_api_key" required value="<?= htmlspecialchars($api_key) ?>" class="flex-1 px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-mono text-sm font-bold text-slate-700 shadow-sm">
                        <button type="button" onclick="generateApiKey()" class="px-5 bg-slate-800 text-white font-bold rounded-xl hover:bg-slate-900 transition-all flex items-center justify-center gap-2 shrink-0 shadow-lg shadow-slate-100 text-xs">
                            <i class="fa fa-key"></i> Generate Key
                        </button>
                    </div>
                    <p class="text-[10px] text-slate-400 font-bold italic mt-1 uppercase tracking-wider">Kunci rahasia untuk mengotentikasi request dari sistem atau aplikasi eksternal sekolah.</p>
                </div>

                <div class="space-y-4 pt-4 border-t border-slate-50">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest">Detail Integrasi & Endpoint</h3>

                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 space-y-3">
                        <div>
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Base Endpoint URL</span>
                            <div class="flex items-center justify-between p-2.5 bg-white border border-slate-150 rounded-lg text-xs font-mono font-bold text-indigo-600 select-all shadow-inner truncate">
                                <span><?= BASE_URL ?>api/external_data.php</span>
                                <button type="button" onclick="copyToClipboard('<?= BASE_URL ?>api/external_data.php')" class="text-slate-400 hover:text-indigo-600 ml-4"><i class="fa fa-copy"></i></button>
                            </div>
                        </div>

                        <div>
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Contoh Cek Koneksi / Request Siswa</span>
                            <div class="flex items-center justify-between p-2.5 bg-white border border-slate-150 rounded-lg text-xs font-mono font-bold text-slate-600 select-all shadow-inner truncate">
                                <span><?= BASE_URL ?>api/external_data.php?api_key=<span id="api_key_url_part"><?= htmlspecialchars($api_key) ?></span>&resource=students</span>
                                <button type="button" onclick="copyExampleUrl()" class="text-slate-400 hover:text-indigo-600 ml-4"><i class="fa fa-copy"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-50">
                    <button type="submit" class="px-8 py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-2xl shadow-xl shadow-indigo-100 transition-all flex items-center justify-center gap-2">
                        <i class="fa fa-save"></i> SIMPAN PERUBAHAN
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Help Card -->
    <div class="space-y-6">
        <div class="lux-card p-6 bg-gradient-to-br from-slate-800 to-slate-900 text-white border-none shadow-xl">
            <h3 class="text-lg font-bold mb-4 italic">Bantuan Integrasi</h3>
            <p class="text-xs text-slate-300 leading-relaxed">
                Gunakan header <code class="font-mono text-indigo-400 font-bold bg-white/10 px-1 py-0.5 rounded">X-API-KEY</code> atau kirimkan parameter URL <code class="font-mono text-indigo-400 font-bold bg-white/10 px-1 py-0.5 rounded">?api_key=...</code> saat mengirimkan request data dari aplikasi pihak ketiga.
            </p>
            <div class="mt-4 pt-4 border-t border-white/10">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-2">Resource yang Didukung:</span>
                <ul class="text-[10px] space-y-1 text-slate-300 font-bold">
                    <li><code class="font-mono text-indigo-400">&resource=students</code> - Mengambil daftar data siswa</li>
                    <li><code class="font-mono text-indigo-400">&resource=teachers</code> - Mengambil daftar data guru</li>
                    <li><code class="font-mono text-indigo-400">&resource=classes</code> - Mengambil daftar kelas aktif</li>
                    <li><code class="font-mono text-indigo-400">&resource=attendance</code> - Kehadiran harian siswa</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function generateApiKey() {
    const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    let key = "CAKRA_KEY_";
    for (let i = 0; i < 16; i++) {
        key += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('api_key_input').value = key;
    document.getElementById('api_key_url_part').textContent = key;
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text);
    Swal.fire({
        icon: 'success',
        title: 'Disalin!',
        text: 'URL Endpoint berhasil disalin ke clipboard.',
        timer: 1500,
        showConfirmButton: false
    });
}

function copyExampleUrl() {
    const text = "<?= BASE_URL ?>api/external_data.php?api_key=" + document.getElementById('api_key_input').value + "&resource=students";
    copyToClipboard(text);
}

document.getElementById('api_key_input').addEventListener('input', function() {
    document.getElementById('api_key_url_part').textContent = this.value;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
