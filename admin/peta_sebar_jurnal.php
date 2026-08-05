<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka']);

$page_title = "Peta Sebaran Jurnal Mengajar";
$date_filter = $_GET['tanggal'] ?? date('Y-m-d');

// Fetch all journals with valid GPS coordinates for the selected date
$query = "SELECT j.id, j.tanggal, j.jam_ke, j.materi, j.created_at, j.latitude, j.longitude,
                 u.nama_lengkap, mp.nama_mapel, k.nama_kelas
          FROM jurnal j
          JOIN guru g ON j.guru_id = g.id
          JOIN users u ON g.user_id = u.id
          JOIN mata_pelajaran mp ON j.mapel_id = mp.id
          JOIN kelas k ON j.kelas_id = k.id
          WHERE j.tanggal = '" . mysqli_real_escape_string($conn, $date_filter) . "'
            AND j.latitude IS NOT NULL AND j.latitude != ''
            AND j.longitude IS NOT NULL AND j.longitude != ''";
$res = mysqli_query($conn, $query);

$locations = [];
while ($row = mysqli_fetch_assoc($res)) {
    $locations[] = [
        'id' => (int)$row['id'],
        'nama_lengkap' => $row['nama_lengkap'],
        'nama_mapel' => $row['nama_mapel'],
        'nama_kelas' => $row['nama_kelas'],
        'jam_ke' => $row['jam_ke'],
        'materi' => $row['materi'],
        'created_at' => date('H:i', strtotime($row['created_at'])),
        'lat' => (float)$row['latitude'],
        'lng' => (float)$row['longitude']
    ];
}

// Get school geofence center to focus map
$res_set = mysqli_query($conn, "SELECT nilai_setting FROM pengaturan WHERE nama_setting = 'school_lat'");
$school_lat = (float)(mysqli_fetch_assoc($res_set)['nilai_setting'] ?? -7.9135);
$res_set = mysqli_query($conn, "SELECT nilai_setting FROM pengaturan WHERE nama_setting = 'school_lng'");
$school_lng = (float)(mysqli_fetch_assoc($res_set)['nilai_setting'] ?? 113.8217);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Leaflet JS & CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Peta Sebar Jurnal</h1>
        <p class="text-slate-500">Visualisasi sebaran posisi GPS guru saat melakukan pengisian jurnal mengajar.</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <button onclick="exportPetaSebarToJPEG()" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-100 transition-all flex items-center text-xs uppercase tracking-wider gap-2">
            <i class="fa fa-camera text-sm"></i> Ekspor JPEG
        </button>
        <form action="" method="GET" class="flex items-center gap-2">
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest hidden sm:block">Tanggal:</label>
            <input type="date" name="tanggal" value="<?= htmlspecialchars($date_filter) ?>" onchange="this.form.submit()" class="px-4 py-2 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700 text-xs">
        </form>
    </div>
</div>

<!-- Capture/Export Area Container -->
<div id="sebaran-export-area" class="p-1 sm:p-6 bg-slate-50 rounded-[32px]">

<!-- Map Container Area -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-8 mb-4">
    <div class="lg:col-span-3">
        <div class="lux-card overflow-hidden p-1 bg-white relative">
            <div id="sebaran-map" class="w-full h-[600px] rounded-3xl z-10"></div>
        </div>
    </div>

    <!-- Right info card list -->
    <div class="lg:col-span-1 space-y-4 max-h-[610px] overflow-y-auto pr-1">
        <div class="lux-card p-6 bg-gradient-to-br from-slate-800 to-slate-900 text-white border-none">
            <h3 class="text-sm font-black uppercase tracking-widest text-slate-400 mb-2">Sebaran Aktif</h3>
            <div class="text-4xl font-black italic text-emerald-400"><?= count($locations) ?> Lokasi</div>
            <p class="text-xs text-slate-400 mt-2 font-medium">Koordinat terdeteksi untuk pengisian jurnal hari ini.</p>
        </div>

        <div class="space-y-3">
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Guru Mengajar Aktif</label>
            <?php if (empty($locations)): ?>
                <div class="p-6 text-center text-xs text-slate-400 italic bg-white rounded-2xl border border-slate-100">
                    Tidak ada sebaran pengisian jurnal hari ini.
                </div>
            <?php else: ?>
                <?php foreach ($locations as $loc): ?>
                    <div onclick="focusMarker(<?= $loc['lat'] ?>, <?= $loc['lng'] ?>, '<?= $loc['nama_lengkap'] ?>')"
                         class="p-4 bg-white hover:bg-slate-50 border border-slate-100 rounded-2xl shadow-sm transition-all cursor-pointer flex items-center justify-between group">
                        <div class="truncate max-w-[80%]">
                            <span class="text-xs font-black text-slate-800 leading-tight block group-hover:text-indigo-600 transition-colors"><?= htmlspecialchars($loc['nama_lengkap']) ?></span>
                            <span class="text-[9px] font-black uppercase text-indigo-500 block mt-1"><?= htmlspecialchars($loc['nama_kelas']) ?> • <?= htmlspecialchars($loc['nama_mapel']) ?></span>
                        </div>
                        <span class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-all text-xs shrink-0"><i class="fa fa-chevron-right"></i></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</div>

<!-- html2canvas library for perfect image capture -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" integrity="sha512-BNaRQnYcabRBOA6yXSUDq5gOPa62sK6B6sEHNXXnKID77+qesEdfI5y6859dYQgSxcGvB8vG9h97YwT7dD54eg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
function exportPetaSebarToJPEG() {
    Swal.fire({
        title: 'Mempersiapkan Gambar...',
        text: 'Sedang mengekspor peta sebaran jurnal ke format JPEG.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const exportArea = document.getElementById('sebaran-export-area');

    html2canvas(exportArea, {
        useCORS: true,
        scale: 2, // High definition
        backgroundColor: '#F8FAFC' // bg-slate-50
    }).then(canvas => {
        Swal.close();

        // Convert to JPEG format
        const imgData = canvas.toDataURL('image/jpeg', 0.95);

        // Auto trigger download
        const link = document.createElement('a');
        link.download = 'Peta_Sebaran_Jurnal_' + '<?= $date_filter ?>' + '.jpg';
        link.href = imgData;
        link.click();

        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Gambar peta sebaran jurnal berhasil diekspor dan diunduh.',
            confirmButtonColor: '#4F46E5',
            timer: 2000
        });
    }).catch(err => {
        Swal.close();
        Swal.fire('Gagal', 'Terjadi kesalahan saat mengekspor gambar: ' + err.message, 'error');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize map
    const map = L.map('sebaran-map').setView([<?= $school_lat ?>, <?= $school_lng ?>], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // School Marker
    const schoolIcon = L.icon({
        iconUrl: 'https://cdn-icons-png.flaticon.com/512/8074/8074788.png',
        iconSize: [38, 38],
        iconAnchor: [19, 38],
        popupAnchor: [0, -38]
    });
    L.marker([<?= $school_lat ?>, <?= $school_lng ?>], { icon: schoolIcon })
        .addTo(map)
        .bindPopup('<div class="text-left font-sans"><b>Pusat Sekolah</b><br><span class="text-xs text-slate-500">Koordinat acuan validasi radius geofence.</span></div>');

    // Store markers in an object for easy programmatic access
    window.sebaranMarkers = {};

    const locations = <?= json_encode($locations) ?>;

    const teacherIcon = L.icon({
        iconUrl: 'https://cdn-icons-png.flaticon.com/512/3082/3082383.png',
        iconSize: [32, 32],
        iconAnchor: [16, 32],
        popupAnchor: [0, -32]
    });

    locations.forEach(loc => {
        const popupContent = `
            <div class="text-left font-sans min-w-[200px] space-y-2 p-1">
                <div class="border-b border-slate-100 pb-1.5 mb-1.5">
                    <span class="text-[9px] font-black text-indigo-500 uppercase tracking-widest block">Guru Pengajar</span>
                    <span class="text-xs font-bold text-slate-800 block">${loc.nama_lengkap}</span>
                </div>
                <div class="space-y-1">
                    <p class="text-[10px] text-slate-600"><i class="fa fa-book mr-1 text-slate-400"></i> Mapel: <b>${loc.nama_mapel}</b></p>
                    <p class="text-[10px] text-slate-600"><i class="fa fa-clock mr-1 text-slate-400"></i> Jam ke: <b>${loc.jam_ke}</b> (Kelas ${loc.nama_kelas})</p>
                    <p class="text-[10px] text-slate-600"><i class="fa fa-check-circle mr-1 text-slate-400"></i> Submit: <b>${loc.created_at} WIB</b></p>
                </div>
                <div class="p-2 bg-indigo-50/50 rounded-xl border border-indigo-100/50 text-[11px] text-slate-700 italic mt-2 whitespace-pre-wrap leading-relaxed">
                    "materi: ${loc.materi}"
                </div>
            </div>
        `;

        const marker = L.marker([loc.lat, loc.lng], { icon: teacherIcon })
            .addTo(map)
            .bindPopup(popupContent);

        // Store references
        window.sebaranMarkers[loc.nama_lengkap] = marker;
    });

    // Helper method to zoom and focus
    window.focusMarker = function(lat, lng, name) {
        map.setView([lat, lng], 18, { animate: true, duration: 1 });
        const marker = window.sebaranMarkers[name];
        if (marker) {
            marker.openPopup();
        }
    };
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
