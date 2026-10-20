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
            'nama_sekolah' => $_POST['nama_sekolah'] ?? '',
            'alamat_sekolah' => $_POST['alamat_sekolah'] ?? '',
            'jam_masuk_sekolah' => $_POST['jam_masuk_sekolah'] ?? '07:00',
            'dapodik_url' => $_POST['dapodik_url'] ?? '',
            'dapodik_token' => $_POST['dapodik_token'] ?? '',
            'school_lat' => $_POST['school_lat'] ?? '',
            'school_lng' => $_POST['school_lng'] ?? '',
            'radius_absen' => $_POST['radius_absen'] ?? '30',
            'siswa_gps_absen' => $_POST['siswa_gps_absen'] ?? 'nonaktif',
            'pilih_tanggal_jurnal' => $_POST['pilih_tanggal_jurnal'] ?? 'nonaktif',
            'nama_kepsek' => $_POST['nama_kepsek'] ?? '',
            'nip_kepsek' => $_POST['nip_kepsek'] ?? '',
            'kop_sekolah_html' => $_POST['kop_sekolah_html'] ?? '',
            'kop_font_size' => $_POST['kop_font_size'] ?? '12',
            'kop_font_family' => $_POST['kop_font_family'] ?? 'Times New Roman'
        ];

        foreach ($settings as $key => $val) {
            $stmt = mysqli_prepare($conn, "INSERT INTO pengaturan (nama_setting, nilai_setting) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai_setting = VALUES(nilai_setting)");
            mysqli_stmt_bind_param($stmt, "ss", $key, $val);
            mysqli_stmt_execute($stmt);
        }

        // Target upload directory
        $target_dir = __DIR__ . "/../uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $allowed_ext = ['png', 'jpg', 'jpeg', 'ico', 'svg', 'webp'];

        // Upload Helper function
        $handle_logo_upload = function($file_key, $setting_key) use ($conn, $target_dir, $allowed_ext) {
            if (!empty($_FILES[$file_key]['name']) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                $file_ext = strtolower(pathinfo($_FILES[$file_key]["name"], PATHINFO_EXTENSION));
                if (in_array($file_ext, $allowed_ext)) {
                    $filename = $setting_key . "_" . time() . "." . $file_ext;
                    $target_file = $target_dir . $filename;
                    if (move_uploaded_file($_FILES[$file_key]["tmp_name"], $target_file)) {
                        $stmt = mysqli_prepare($conn, "INSERT INTO pengaturan (nama_setting, nilai_setting) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai_setting = VALUES(nilai_setting)");
                        mysqli_stmt_bind_param($stmt, "ss", $setting_key, $filename);
                        mysqli_stmt_execute($stmt);
                    }
                } else {
                    throw new Exception("Format logo $file_key tidak didukung. Gunakan PNG, JPG, ICO, SVG, atau WEBP.");
                }
            }
        };

        // Handle Favicon / Logo Utama
        $handle_logo_upload('favicon', 'favicon');

        // Handle Logo Kiri Kop
        $handle_logo_upload('logo_kiri', 'logo_kiri');

        // Handle Logo Kanan Kop
        $handle_logo_upload('logo_kanan', 'logo_kanan');

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
    <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Pengaturan Sistem & Dokumen</h1>
    <p class="text-slate-500 font-medium">Sesuaikan identitas sekolah, Kop Surat cetakan, data kepala sekolah, dan parameter operasional.</p>
</div>

<?php if ($message): ?>
<script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= addslashes(htmlspecialchars($message)) ?>' });</script>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2 space-y-8">
        <div class="lux-card p-8 bg-white shadow-xl rounded-3xl border border-slate-100">
            <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

                <!-- Section 1: Identitas Sekolah & Kepala Sekolah -->
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-black text-slate-800 italic flex items-center gap-2">
                        <i class="fa fa-school text-indigo-600"></i> Identitas Sekolah & Kepala Sekolah
                    </h2>
                    <p class="text-xs text-slate-400">Informasi dasar nama sekolah, jam masuk, dan data penandatangan resmi.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-700">Nama Sekolah</label>
                        <input type="text" name="nama_sekolah" value="<?= htmlspecialchars($sets['nama_sekolah'] ?? '') ?>" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-xs text-slate-800">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-700">Jam Masuk Sekolah</label>
                        <input type="time" name="jam_masuk_sekolah" value="<?= htmlspecialchars($sets['jam_masuk_sekolah'] ?? '07:00') ?>" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-xs text-slate-800">
                        <p class="text-[10px] text-slate-400 font-bold italic">Batas waktu penentuan keterlambatan absensi.</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700">Alamat Lengkap Sekolah</label>
                    <textarea name="alamat_sekolah" rows="2" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-medium text-xs text-slate-800 leading-relaxed"><?= htmlspecialchars($sets['alamat_sekolah'] ?? '') ?></textarea>
                </div>

                <!-- Data Kepala Sekolah -->
                <div class="p-5 rounded-2xl bg-indigo-50/50 border border-indigo-100/60 space-y-4">
                    <span class="text-xs font-black text-indigo-700 uppercase tracking-wider block">Data Kepala Sekolah (Untuk Dokumen Cetak)</span>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-slate-700">Nama Kepala Sekolah</label>
                            <input type="text" name="nama_kepsek" value="<?= htmlspecialchars($sets['nama_kepsek'] ?? '') ?>" placeholder="e.g. Drs. H. M. Yasin, M.Pd." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-xs text-slate-800 bg-white">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-slate-700">NIP / NIPPPK Kepala Sekolah</label>
                            <input type="text" name="nip_kepsek" value="<?= htmlspecialchars($sets['nip_kepsek'] ?? '') ?>" placeholder="e.g. 19680512 199403 1 008" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-mono font-bold text-xs text-slate-800 bg-white">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Editor KOP Surat & Upload Dual Logo (Kiri - Kanan) -->
                <div class="border-b border-slate-100 pb-4 pt-4">
                    <h2 class="text-lg font-black text-slate-800 italic flex items-center gap-2">
                        <i class="fa fa-file-invoice text-indigo-600"></i> Pengaturan KOP Surat Sekolah
                    </h2>
                    <p class="text-xs text-slate-400">Atur tampilan KOP dokumen cetak, ukuran & gaya font, serta logo kanan-kiri.</p>
                </div>

                <!-- Dual Logo Upload -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Logo Kiri -->
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                        <label class="block text-xs font-bold text-slate-700 flex items-center justify-between">
                            <span><i class="fa fa-image text-indigo-500 mr-1"></i> Logo Kiri KOP (e.g. Pemprov/Dinas)</span>
                            <?php if (!empty($sets['logo_kiri'])): ?>
                                <span class="text-[9px] font-black uppercase text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">Terpasang</span>
                            <?php endif; ?>
                        </label>
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-xl bg-white border border-slate-200 flex items-center justify-center p-1.5 shadow-sm">
                                <?php if (!empty($sets['logo_kiri'])): ?>
                                    <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($sets['logo_kiri']) ?>" class="max-w-full max-h-full object-contain">
                                <?php else: ?>
                                    <i class="fa fa-shield-alt text-2xl text-slate-300"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <input type="file" name="logo_kiri" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100">
                                <p class="mt-1 text-[9px] text-slate-400 italic">Format: PNG, JPG, WEBP (Transparan disarankan)</p>
                            </div>
                        </div>
                    </div>

                    <!-- Logo Kanan -->
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                        <label class="block text-xs font-bold text-slate-700 flex items-center justify-between">
                            <span><i class="fa fa-image text-indigo-500 mr-1"></i> Logo Kanan KOP (e.g. Logo Sekolah)</span>
                            <?php if (!empty($sets['logo_kanan'])): ?>
                                <span class="text-[9px] font-black uppercase text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">Terpasang</span>
                            <?php endif; ?>
                        </label>
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-xl bg-white border border-slate-200 flex items-center justify-center p-1.5 shadow-sm">
                                <?php if (!empty($sets['logo_kanan'])): ?>
                                    <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($sets['logo_kanan']) ?>" class="max-w-full max-h-full object-contain">
                                <?php else: ?>
                                    <i class="fa fa-school text-2xl text-slate-300"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <input type="file" name="logo_kanan" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100">
                                <p class="mt-1 text-[9px] text-slate-400 italic">Format: PNG, JPG, WEBP (Transparan disarankan)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Font Formatting Controls for Kop Surat -->
                <div class="p-4 rounded-2xl bg-amber-50/60 border border-amber-200/60 space-y-3">
                    <span class="text-xs font-black text-amber-800 uppercase tracking-wider block">Gaya Typography KOP Surat</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">Gaya Font (Font Family)</label>
                            <select name="kop_font_family" id="kop_font_family" onchange="updateKopPreview()" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-xs text-slate-800 bg-white">
                                <option value="Times New Roman" <?= ($sets['kop_font_family'] ?? '') === 'Times New Roman' ? 'selected' : '' ?>>Times New Roman (Standar Resmi)</option>
                                <option value="Arial" <?= ($sets['kop_font_family'] ?? '') === 'Arial' ? 'selected' : '' ?>>Arial (Sans-Serif Modern)</option>
                                <option value="Calibri" <?= ($sets['kop_font_family'] ?? '') === 'Calibri' ? 'selected' : '' ?>>Calibri</option>
                                <option value="Georgia" <?= ($sets['kop_font_family'] ?? '') === 'Georgia' ? 'selected' : '' ?>>Georgia</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">Base Font Size KOP (pt)</label>
                            <select name="kop_font_size" id="kop_font_size" onchange="updateKopPreview()" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-xs text-slate-800 bg-white">
                                <option value="10" <?= ($sets['kop_font_size'] ?? '') === '10' ? 'selected' : '' ?>>10 pt (Kecil Padat)</option>
                                <option value="11" <?= ($sets['kop_font_size'] ?? '') === '11' ? 'selected' : '' ?>>11 pt (Sedang)</option>
                                <option value="12" <?= (!isset($sets['kop_font_size']) || $sets['kop_font_size'] === '12') ? 'selected' : '' ?>>12 pt (Standar Resmi)</option>
                                <option value="14" <?= ($sets['kop_font_size'] ?? '') === '14' ? 'selected' : '' ?>>14 pt (Besar)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- HTML Editor Box for Kop Surat Content -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Teks Isi KOP Surat (HTML Formatting)</label>
                    <textarea name="kop_sekolah_html" id="kop_sekolah_html" rows="5" oninput="updateKopPreview()" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-mono text-xs text-slate-800 leading-relaxed"><?= htmlspecialchars($sets['kop_sekolah_html'] ?? '<div style="text-align: center;"><h3 style="font-size: 14px; font-weight: bold; text-transform: uppercase; margin: 0;">PEMERINTAH PROVINSI JAWA TIMUR</h3><h3 style="font-size: 14px; font-weight: bold; text-transform: uppercase; margin: 0;">DINAS PENDIDIKAN</h3><h2 style="font-size: 16px; font-weight: bold; text-transform: uppercase; margin: 2px 0;">SEKOLAH MENENGAH KEJURUAN NEGERI 2 BONDOWOSO</h2><p style="font-size: 11px; margin: 0;">Jalan Jend. Ahmad Yani No. 48 Telp/Fax. (0332) 421286</p><p style="font-size: 10px; font-style: italic; margin: 0;">Email: smknbws2@yahoo.com Website: www.smkn2bondowoso.sch.id</p><h2 style="font-size: 14px; font-weight: bold; text-transform: uppercase; margin-top: 2px;">BONDOWOSO</h2></div>') ?></textarea>
                    <p class="text-[10px] text-slate-400 font-bold italic mt-1">Anda dapat menyesuaikan tag header h2, h3, p, style font-size, font-weight, dan margin sesuai kebutuhan dokumen.</p>
                </div>

                <!-- Live KOP Preview Box -->
                <div class="p-6 rounded-2xl border-2 border-dashed border-slate-300 bg-white space-y-2">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Live Preview KOP Dokumen Cetak</span>
                    <div id="kop_preview_container" class="p-4 border-b-4 border-black text-black min-h-[100px] flex items-center justify-between gap-4">
                        <!-- Left Logo -->
                        <div class="w-16 flex-shrink-0 text-center">
                            <?php if (!empty($sets['logo_kiri'])): ?>
                                <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($sets['logo_kiri']) ?>" class="max-h-16 max-w-full inline-block">
                            <?php else: ?>
                                <div class="text-[10px] text-slate-300 border border-dashed border-slate-200 p-2">Logo Kiri</div>
                            <?php endif; ?>
                        </div>

                        <!-- Middle Text -->
                        <div id="kop_preview_text" class="flex-1 text-center">
                            <?= $sets['kop_sekolah_html'] ?? '' ?>
                        </div>

                        <!-- Right Logo -->
                        <div class="w-16 flex-shrink-0 text-center">
                            <?php if (!empty($sets['logo_kanan'])): ?>
                                <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($sets['logo_kanan']) ?>" class="max-h-16 max-w-full inline-block">
                            <?php else: ?>
                                <div class="text-[10px] text-slate-300 border border-dashed border-slate-200 p-2">Logo Kanan</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Parameter Operasional & GPS -->
                <div class="border-b border-slate-100 pb-4 pt-4">
                    <h2 class="text-lg font-black text-slate-800 italic flex items-center gap-2">
                        <i class="fa fa-sliders-h text-indigo-600"></i> Parameter Operasional & Absensi
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-700">Status Absensi GPS Siswa</label>
                        <select name="siswa_gps_absen" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white transition-all font-bold text-xs text-slate-800">
                            <option value="aktif" <?= (isset($sets['siswa_gps_absen']) && $sets['siswa_gps_absen'] === 'aktif') ? 'selected' : '' ?>>Aktif (Menggunakan GPS)</option>
                            <option value="nonaktif" <?= (!isset($sets['siswa_gps_absen']) || $sets['siswa_gps_absen'] !== 'aktif') ? 'selected' : '' ?>>Nonaktif (Absensi Manual di Jurnal)</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-700">Pilih Tanggal saat Isi Jurnal</label>
                        <select name="pilih_tanggal_jurnal" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white transition-all font-bold text-xs text-slate-800">
                            <option value="aktif" <?= (isset($sets['pilih_tanggal_jurnal']) && $sets['pilih_tanggal_jurnal'] === 'aktif') ? 'selected' : '' ?>>Aktif (Bisa Memilih Tanggal)</option>
                            <option value="nonaktif" <?= (!isset($sets['pilih_tanggal_jurnal']) || $sets['pilih_tanggal_jurnal'] !== 'aktif') ? 'selected' : '' ?>>Nonaktif (Otomatis Hari Ini / Today)</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-4">
                    <label class="block text-xs font-bold text-slate-700 flex items-center">
                        <i class="fa fa-map-marker-alt mr-2 text-rose-500"></i> Lokasi GPS Sekolah
                    </label>
                    <div id="map" class="w-full h-72 rounded-2xl border-2 border-slate-100 shadow-inner z-10"></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Latitude</label>
                            <input type="text" id="school_lat" name="school_lat" value="<?= htmlspecialchars($sets['school_lat'] ?? '-7.9135') ?>" readonly class="w-full px-4 py-2 bg-slate-50 rounded-xl border border-slate-200 text-xs font-mono font-bold text-slate-600">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Longitude</label>
                            <input type="text" id="school_lng" name="school_lng" value="<?= htmlspecialchars($sets['school_lng'] ?? '113.8217') ?>" readonly class="w-full px-4 py-2 bg-slate-50 rounded-xl border border-slate-200 text-xs font-mono font-bold text-slate-600">
                        </div>
                        <div class="col-span-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Radius Absensi (Meter)</label>
                            <input type="number" name="radius_absen" value="<?= htmlspecialchars($sets['radius_absen'] ?? '30') ?>" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-xs text-slate-800">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-700">URL Web Service Dapodik</label>
                        <input type="text" id="dapodik_url" name="dapodik_url" value="<?= htmlspecialchars($sets['dapodik_url'] ?? '') ?>" placeholder="http://localhost:5774" class="w-full px-4 py-3 rounded-2xl border border-slate-200 font-bold text-xs text-slate-800">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-700">Token Dapodik</label>
                        <input type="password" id="dapodik_token" name="dapodik_token" value="<?= htmlspecialchars($sets['dapodik_token'] ?? '') ?>" placeholder="Masukkan Token..." class="w-full px-4 py-3 rounded-2xl border border-slate-200 font-bold text-xs text-slate-800">
                    </div>
                </div>

                <!-- Favicon Upload -->
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                    <label class="block text-xs font-bold text-slate-700 flex items-center">
                        <i class="fa fa-image mr-2 text-indigo-500"></i> Favicon / Logo Aplikasi Utama
                    </label>
                    <div class="flex items-center gap-6">
                        <div class="w-16 h-16 rounded-2xl bg-white border border-slate-200 flex items-center justify-center overflow-hidden shadow-sm p-2">
                            <?php if (!empty($sets['favicon'])): ?>
                                <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($sets['favicon']) ?>" class="max-w-full max-h-full object-contain">
                            <?php else: ?>
                                <i class="fa fa-school text-3xl text-slate-300"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1">
                            <input type="file" name="favicon" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 transition-all">
                            <p class="mt-1 text-[9px] text-slate-400 font-bold italic">Mendukung: PNG, JPG, SVG, ICO (Maks 2MB)</p>
                        </div>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full md:w-auto px-10 py-4 bg-indigo-600 text-white font-bold text-xs rounded-2xl hover:bg-indigo-700 shadow-xl shadow-indigo-100 transition-all flex items-center justify-center">
                        <i class="fa fa-save mr-2"></i> Simpan Seluruh Perubahan
                    </button>
                </div>
            </form>
        </div>

        <div class="lux-card p-8 bg-white border-t-4 border-amber-500 rounded-3xl shadow-xl">
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
                <p class="text-xs text-slate-600">Pastikan Web Service Dapodik sudah diaktifkan di komputer yang terinstall Dapodik dan Token sudah digenerate.</p>

                <div class="flex gap-3">
                    <button type="button" onclick="checkDapodikConnection('<?= get_csrf_token() ?>')" class="px-6 py-3 bg-slate-800 text-white font-bold rounded-xl hover:bg-slate-900 transition-all flex items-center text-xs shadow-lg shadow-slate-200">
                        <i class="fa fa-plug mr-2"></i> Cek Koneksi
                    </button>
                    <div id="conn_status" class="flex-1 flex items-center px-4 rounded-xl border border-dashed border-slate-200 text-xs font-bold text-slate-400 italic">
                        Klik tombol untuk memeriksa status koneksi...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="lux-card p-6 bg-gradient-to-br from-indigo-600 to-indigo-800 text-white rounded-3xl shadow-xl">
            <h3 class="text-xl font-bold mb-4 italic">Informasi Sistem</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center border-b border-white/10 pb-2">
                    <span class="text-white/60 text-xs font-bold uppercase tracking-wider">PHP Version</span>
                    <span class="font-mono font-bold text-xs"><?= phpversion() ?></span>
                </div>
                <div class="flex justify-between items-center border-b border-white/10 pb-2">
                    <span class="text-white/60 text-xs font-bold uppercase tracking-wider">Database</span>
                    <span class="font-mono font-bold text-xs">MySQL/MariaDB</span>
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

        <div class="lux-card p-6 border-t-4 border-slate-700 rounded-3xl bg-white shadow-xl">
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

            <div id="git_log_container" class="mt-6 hidden">
                <label class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Terminal Output</label>
                <pre id="git_log_box" class="w-full p-4 bg-slate-900 text-slate-200 text-[11px] font-mono rounded-xl overflow-x-auto max-h-48 whitespace-pre-wrap leading-relaxed shadow-inner"></pre>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
function updateKopPreview() {
    let htmlVal = '';
    if (typeof tinymce !== 'undefined' && tinymce.get('kop_sekolah_html')) {
        htmlVal = tinymce.get('kop_sekolah_html').getContent();
    } else {
        htmlVal = document.getElementById('kop_sekolah_html').value;
    }

    const fontFamily = document.getElementById('kop_font_family').value;
    const fontSize = document.getElementById('kop_font_size').value;

    const previewContainer = document.getElementById('kop_preview_container');
    const previewText = document.getElementById('kop_preview_text');

    if (previewText) previewText.innerHTML = htmlVal;
    if (previewContainer) {
        previewContainer.style.fontFamily = fontFamily;
        previewContainer.style.fontSize = fontSize + 'pt';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '#kop_sekolah_html',
            height: 260,
            menubar: false,
            plugins: 'code link lists textcolor colorpicker table align',
            toolbar: 'undo redo | fontselect fontsizeselect | bold italic underline forecolor | alignleft aligncenter alignright alignjustify | code',
            content_style: 'body { font-family: "Times New Roman", Times, serif; font-size: 12pt; text-align: center; }',
            font_formats: 'Times New Roman=times new roman,times; Arial=arial,helvetica,sans-serif; Calibri=calibri,sans-serif; Georgia=georgia,palatino;',
            fontsize_formats: '8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 20pt 24pt',
            setup: function(editor) {
                editor.on('keyup change init', function() {
                    editor.save();
                    updateKopPreview();
                });
            }
        });
    }

    updateKopPreview();

    var lat = <?= (isset($sets['school_lat']) && $sets['school_lat'] !== '') ? $sets['school_lat'] : '-7.9135' ?>;
    var lng = <?= (isset($sets['school_lng']) && $sets['school_lng'] !== '') ? $sets['school_lng'] : '113.8217' ?>;

    var map = L.map('map').setView([lat, lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var radiusAbsen = <?= (int)($sets['radius_absen'] ?? 30) ?>;
    var marker = L.marker([lat, lng], {draggable: true}).addTo(map);

    var circle = L.circle([lat, lng], {
        color: '#4F46E5',
        fillColor: '#4F46E5',
        fillOpacity: 0.15,
        radius: radiusAbsen,
        weight: 2
    }).addTo(map);

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

<!-- TinyMCE Rich Text Editor CDN -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<!-- Page Specific Scripts -->
<script src="<?= BASE_URL ?>assets/js/pengaturan.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
