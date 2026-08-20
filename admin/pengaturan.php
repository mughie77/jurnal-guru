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
            'dapodik_token' => $_POST['dapodik_token'],
            'school_lat' => $_POST['school_lat'],
            'school_lng' => $_POST['school_lng'],
            'radius_absen' => $_POST['radius_absen'],
            'siswa_gps_absen' => $_POST['siswa_gps_absen']
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
                        <label class="block text-sm font-bold text-slate-700">Status Absensi GPS Siswa</label>
                        <select name="siswa_gps_absen" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white transition-all font-bold">
                            <option value="aktif" <?= (isset($sets['siswa_gps_absen']) && $sets['siswa_gps_absen'] === 'aktif') ? 'selected' : '' ?>>Aktif (Menggunakan GPS)</option>
                            <option value="nonaktif" <?= (!isset($sets['siswa_gps_absen']) || $sets['siswa_gps_absen'] !== 'aktif') ? 'selected' : '' ?>>Nonaktif (Absensi Manual di Jurnal)</option>
                        </select>
                        <p class="text-[10px] text-slate-400 font-bold italic">Jika dinonaktifkan, siswa tidak bisa melakukan absensi GPS mandiri dan absensi diisi manual oleh guru.</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <label class="block text-sm font-bold text-slate-700 flex items-center">
                        <i class="fa fa-map-marker-alt mr-2 text-rose-500"></i> Lokasi GPS Sekolah
                    </label>
                    <div id="map" class="w-full h-72 rounded-2xl border-2 border-slate-100 shadow-inner z-10"></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Latitude</label>
                            <input type="text" id="school_lat" name="school_lat" value="<?= htmlspecialchars($sets['school_lat'] ?? '-7.9135') ?>" readonly class="w-full px-4 py-2 bg-slate-50 rounded-xl border border-slate-200 text-sm font-mono font-bold text-slate-600">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Longitude</label>
                            <input type="text" id="school_lng" name="school_lng" value="<?= htmlspecialchars($sets['school_lng'] ?? '113.8217') ?>" readonly class="w-full px-4 py-2 bg-slate-50 rounded-xl border border-slate-200 text-sm font-mono font-bold text-slate-600">
                        </div>
                        <div class="col-span-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Radius Absensi (Meter)</label>
                            <input type="number" name="radius_absen" value="<?= htmlspecialchars($sets['radius_absen'] ?? '30') ?>" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 transition-all font-bold">
                            <p class="text-[10px] text-slate-400 font-bold italic mt-1 uppercase tracking-wider">Jarak maksimal siswa dari koordinat sekolah untuk dapat melakukan absensi.</p>
                        </div>
                    </div>
                    <p class="text-[10px] text-slate-400 font-bold italic uppercase tracking-wider">Geser penanda pada peta untuk menentukan lokasi presisi sekolah.</p>
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
                    <button type="button" onclick="checkDapodikConnection('<?= get_csrf_token() ?>')" class="px-6 py-3 bg-slate-800 text-white font-bold rounded-xl hover:bg-slate-900 transition-all flex items-center shadow-lg shadow-slate-200">
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
            <div class="mt-6 pt-4 border-t border-white/10">
                <button type="button" onclick="runDatabaseUpdate()" class="w-full py-3 bg-white text-indigo-700 hover:bg-indigo-50 font-black text-xs uppercase tracking-wider rounded-xl shadow-lg transition-all flex items-center justify-center gap-2">
                    <i class="fa fa-database"></i> Update Database
                </button>
            </div>
        </div>

        <div class="lux-card p-6 border-t-4 border-slate-700">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-slate-800 font-bold flex items-center italic">
                    <i class="fab fa-github mr-2 text-slate-800 text-lg"></i> Sinkronisasi GitHub
                </h3>
                <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded text-[9px] font-black uppercase tracking-wider">DevOps</span>
            </div>
            <p class="text-xs text-slate-500 leading-relaxed mb-4">Kelola pembaruan source code aplikasi langsung dari panel admin ini untuk ditarik atau didorong ke repositori GitHub.</p>

            <div class="p-3 bg-slate-50 rounded-xl mb-4 space-y-2 border border-slate-100">
                <div class="flex flex-col">
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">GitHub CLI (gh)</span>
                    <code class="text-[10px] font-mono font-bold text-slate-600 bg-white p-1.5 rounded border border-slate-100 break-all select-all">gh repo clone mughie77/jurnal-guru</code>
                </div>
                <div class="flex flex-col">
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Git URL</span>
                    <code class="text-[10px] font-mono font-bold text-slate-600 bg-white p-1.5 rounded border border-slate-100 break-all select-all">https://github.com/mughie77/jurnal-guru.git</code>
                </div>
            </div>

            <div class="space-y-3">
                <button type="button" onclick="runGitAction('status', '<?= get_csrf_token() ?>')" class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-all flex items-center justify-center gap-2">
                    <i class="fa fa-info-circle"></i> Cek Status Git
                </button>
                <button type="button" onclick="runGitAction('init_origin', '<?= get_csrf_token() ?>')" class="w-full py-2.5 bg-amber-50 hover:bg-amber-100 text-amber-700 font-bold text-xs rounded-xl transition-all flex items-center justify-center gap-2 border border-amber-100">
                    <i class="fa fa-link"></i> Set Remote origin URL
                </button>
                <button type="button" onclick="runGitAction('pull', '<?= get_csrf_token() ?>')" class="w-full py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-xs rounded-xl transition-all flex items-center justify-center gap-2 border border-emerald-100">
                    <i class="fa fa-cloud-download-alt"></i> Tarik Pembaruan (Pull)
                </button>
                <button type="button" onclick="confirmGitPush('<?= get_csrf_token() ?>')" class="w-full py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs rounded-xl transition-all flex items-center justify-center gap-2 border border-indigo-100">
                    <i class="fa fa-cloud-upload-alt"></i> Kirim Pembaruan (Push)
                </button>
            </div>

            <!-- Logger box for git terminal output -->
            <div id="git_log_container" class="mt-6 hidden">
                <label class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Terminal Output</label>
                <pre id="git_log_box" class="w-full p-4 bg-slate-900 text-slate-200 text-[11px] font-mono rounded-xl overflow-x-auto max-h-48 whitespace-pre-wrap leading-relaxed shadow-inner"></pre>
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

<!-- Leaflet JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var lat = <?= (isset($sets['school_lat']) && $sets['school_lat'] !== '') ? $sets['school_lat'] : '-7.9135' ?>;
    var lng = <?= (isset($sets['school_lng']) && $sets['school_lng'] !== '') ? $sets['school_lng'] : '113.8217' ?>;

    var map = L.map('map').setView([lat, lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var radiusAbsen = <?= (int)($sets['radius_absen'] ?? 30) ?>;

    var marker = L.marker([lat, lng], {draggable: true}).addTo(map);

    // Tambahkan lingkaran batas radius absensi
    var circle = L.circle([lat, lng], {
        color: '#4F46E5',
        fillColor: '#4F46E5',
        fillOpacity: 0.15,
        radius: radiusAbsen,
        weight: 2
    }).addTo(map);

    // Sesuaikan zoom peta agar lingkaran terlihat dengan baik
    map.fitBounds(circle.getBounds(), { padding: [20, 20] });

    marker.on('dragend', function(e) {
        var pos = marker.getLatLng();
        document.getElementById('school_lat').value = pos.lat.toFixed(6);
        document.getElementById('school_lng').value = pos.lng.toFixed(6);
        circle.setLatLng(pos);
    });

    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        document.getElementById('school_lat').value = e.latlng.lat.toFixed(6);
        document.getElementById('school_lng').value = e.latlng.lng.toFixed(6);
        circle.setLatLng(e.latlng);
    });

    // Update radius lingkaran secara dinamis saat input Radius Absensi diubah
    var radiusInput = document.querySelector('input[name="radius_absen"]');
    if (radiusInput) {
        radiusInput.addEventListener('input', function() {
            var rVal = parseInt(this.value, 10);
            if (!isNaN(rVal) && rVal > 0) {
                circle.setRadius(rVal);
                map.fitBounds(circle.getBounds(), { padding: [20, 20] });
            }
        });
    }
});
</script>

<!-- Page Specific Scripts -->
<script src="<?= BASE_URL ?>assets/js/pengaturan.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
