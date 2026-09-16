<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
$guru_data = mysqli_fetch_assoc($guru_res);
$guru_id = $guru_data['id'] ?? 0;

$page_title = "Lokasi Bimbingan PKL";
$message = ''; $message_type = '';

// Handle POST actions for updating GPS pointing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_pointing'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    $id = (int)$_POST['id'];
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    $radius_absen = (int)($_POST['radius_absen'] ?? 50);

    // Ensure this teacher is assigned as pembimbing for this tempat_pkl
    $chk = mysqli_query($conn, "SELECT id FROM tempat_pkl WHERE id = $id AND guru_pembimbing_id = $guru_id");
    if (mysqli_num_rows($chk) == 0 && $_SESSION['role'] !== 'admin') {
        $message = "Anda tidak memiliki akses untuk mengubah lokasi PKL ini.";
        $message_type = 'error';
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE tempat_pkl SET latitude = ?, longitude = ?, radius_absen = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssii", $latitude, $longitude, $radius_absen, $id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Koordinat GPS Tempat PKL berhasil diperbarui!";
            $message_type = 'success';
        } else {
            $message = "Gagal memperbarui lokasi.";
            $message_type = 'error';
        }
    }
}

// Get Tempat PKL where this teacher is assigned as Pembimbing
$q_bimbingan = "SELECT tp.*,
               (SELECT COUNT(*) FROM siswa_pkl sp WHERE sp.tempat_pkl_id = tp.id AND sp.tahun_pelajaran_id = $active_tahun_id AND sp.status = 'aktif') as jml_siswa
               FROM tempat_pkl tp
               WHERE tp.guru_pembimbing_id = $guru_id
               ORDER BY tp.nama_tempat ASC";
$res_bimbingan = mysqli_query($conn, $q_bimbingan);
$bimbingans = [];
while ($b = mysqli_fetch_assoc($res_bimbingan)) $bimbingans[] = $b;

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Leaflet JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto pb-24">
    <div class="mb-8">
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Lokasi Bimbingan PKL Saya</h1>
        <p class="text-slate-500 font-medium">Point dan atur presisi lokasi GPS industri/DU-DI tempat bimbingan PKL Anda.</p>
    </div>

    <?php if ($message): ?>
    <script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= addslashes(htmlspecialchars($message)) ?>', confirmButtonColor: '#4f46e5' });</script>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <?php if (empty($bimbingans)): ?>
        <div class="col-span-full lux-card p-12 text-center text-slate-400 italic">
            <i class="fa fa-info-circle text-4xl mb-3 text-slate-300"></i>
            <p>Anda belum ditugaskan sebagai Guru Pembimbing di lokasi PKL manapun.</p>
        </div>
        <?php else: ?>
            <?php foreach ($bimbingans as $b): ?>
            <div class="lux-card p-6 bg-white shadow-2xl rounded-3xl border border-slate-100 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-600 border border-indigo-100">
                            <?= $b['jml_siswa'] ?> Siswa Bimbingan
                        </span>
                        <button type="button" onclick="openPointingModal(<?= htmlspecialchars(json_encode($b)) ?>)" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 shadow-md shadow-indigo-100 transition-all flex items-center gap-1.5">
                            <i class="fa fa-map-marker-alt"></i> Point Lokasi GPS
                        </button>
                    </div>

                    <h3 class="font-black text-slate-800 text-xl mb-1"><?= htmlspecialchars($b['nama_tempat']) ?></h3>
                    <p class="text-xs text-slate-500 italic mb-4 leading-relaxed"><?= htmlspecialchars($b['alamat'] ?? 'Alamat belum diisi') ?></p>

                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400 font-bold uppercase text-[10px]">Pembimbing DU/DI:</span>
                            <span class="font-bold text-slate-700"><?= htmlspecialchars($b['pembimbing_dudi'] ?? '-') ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400 font-bold uppercase text-[10px]">No. Telp DU/DI:</span>
                            <span class="font-bold text-slate-700"><?= htmlspecialchars($b['no_telp_dudi'] ?? '-') ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400 font-bold uppercase text-[10px]">Status Pointing:</span>
                            <?php if (!empty($b['latitude']) && !empty($b['longitude'])): ?>
                                <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold text-[10px]">Ter-point (<?= $b['radius_absen'] ?>m)</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded bg-rose-100 text-rose-700 font-bold text-[10px]">Belum di-point</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Pointing Lokasi GPS -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeModal('pointingModal')"></div>
<div id="pointingModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-2xl max-h-[90vh] overflow-y-auto bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0">
    <div class="bg-indigo-600 px-6 sm:px-8 py-5 text-white flex justify-between items-center sticky top-0 z-10">
        <h3 class="text-xl font-black italic">Point Lokasi GPS tempat PKL</h3>
        <button type="button" onclick="closeModal('pointingModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>
    <form action="" method="POST" class="p-6 sm:p-8 space-y-5">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <input type="hidden" name="id" id="point_id">

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Tempat PKL</label>
            <input type="text" id="point_nama" readonly class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 font-bold text-slate-700 text-sm">
        </div>

        <div class="space-y-3">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center">
                <i class="fa fa-map-marker-alt text-rose-500 mr-2"></i> Klik / Geser Marker di Peta
            </label>
            <div id="map_pointing" class="w-full h-64 rounded-2xl border border-slate-200 shadow-inner z-10"></div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase">Latitude</label>
                    <input type="text" id="point_lat" name="latitude" readonly class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-200 text-xs font-mono font-bold text-slate-600">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase">Longitude</label>
                    <input type="text" id="point_lng" name="longitude" readonly class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-200 text-xs font-mono font-bold text-slate-600">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase">Radius Absen (m)</label>
                    <input type="number" id="point_radius" name="radius_absen" required class="w-full px-3 py-2 rounded-xl border border-slate-200 font-bold text-xs">
                </div>
            </div>
            <p class="text-[10px] text-slate-400 italic">Pastikan lokasi berada tepat di lokasi industri tempat siswa melakukan PKL.</p>
        </div>

        <div class="pt-4 flex flex-col sm:flex-row gap-3">
            <button type="button" onclick="closeModal('pointingModal')" class="flex-1 px-4 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
            <button type="submit" name="update_pointing" class="flex-1 px-4 py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100 text-sm">Simpan Koordinat GPS</button>
        </div>
    </form>
</div>

<script>
const overlay = document.getElementById('modalOverlay');
let mapPointing, markerPointing, circlePointing;

function openModal(id) {
    const m = document.getElementById(id);
    overlay.classList.remove('hidden');
    m.classList.remove('hidden');
    setTimeout(() => {
        overlay.classList.add('opacity-100');
        m.classList.add('opacity-100', 'scale-100');
    }, 10);
}

function closeModal(id) {
    const m = document.getElementById(id);
    overlay.classList.remove('opacity-100');
    m.classList.remove('opacity-100', 'scale-100');
    setTimeout(() => {
        overlay.classList.add('hidden');
        m.classList.add('hidden');
    }, 300);
}

function openPointingModal(b) {
    document.getElementById('point_id').value = b.id;
    document.getElementById('point_nama').value = b.nama_tempat;

    const lat = parseFloat(b.latitude) || -7.9135;
    const lng = parseFloat(b.longitude) || 113.8217;
    const radius = parseInt(b.radius_absen) || 50;

    document.getElementById('point_lat').value = lat;
    document.getElementById('point_lng').value = lng;
    document.getElementById('point_radius').value = radius;

    openModal('pointingModal');

    setTimeout(() => {
        if (!mapPointing) {
            mapPointing = L.map('map_pointing').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapPointing);
            markerPointing = L.marker([lat, lng], {draggable: true}).addTo(mapPointing);
            circlePointing = L.circle([lat, lng], {radius: radius, color: '#4F46E5', fillColor: '#4F46E5', fillOpacity: 0.15}).addTo(mapPointing);

            markerPointing.on('dragend', function(e) {
                const pos = markerPointing.getLatLng();
                document.getElementById('point_lat').value = pos.lat.toFixed(6);
                document.getElementById('point_lng').value = pos.lng.toFixed(6);
                circlePointing.setLatLng(pos);
            });

            mapPointing.on('click', function(e) {
                markerPointing.setLatLng(e.latlng);
                document.getElementById('point_lat').value = e.latlng.lat.toFixed(6);
                document.getElementById('point_lng').value = e.latlng.lng.toFixed(6);
                circlePointing.setLatLng(e.latlng);
            });
        } else {
            mapPointing.setView([lat, lng], 15);
            markerPointing.setLatLng([lat, lng]);
            circlePointing.setLatLng([lat, lng]).setRadius(radius);
            mapPointing.invalidateSize();
        }
    }, 150);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
