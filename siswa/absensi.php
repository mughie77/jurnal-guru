<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];

// Check if already checked in today
$today = date('Y-m-d');
$check_abs = mysqli_query($conn, "SELECT id FROM absensi_harian WHERE siswa_id = $siswa_id AND tanggal = '$today'");
$is_already_absen = mysqli_num_rows($check_abs) > 0;

// Get School Info for GPS
$res_set = mysqli_query($conn, "SELECT * FROM pengaturan");
$sets = [];
while ($r = mysqli_fetch_assoc($res_set)) $sets[$r['nama_setting']] = $r['nilai_setting'];

$school_lat = $sets['school_lat'] ?? '-7.9135';
$school_lng = $sets['school_lng'] ?? '113.8217';
$radius_absen = (int)($sets['radius_absen'] ?? 30);

$page_title = "Absensi GPS Siswa";
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    #map-absensi { height: 300px; border-radius: 20px; margin-bottom: 20px; z-index: 10; }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 lg:p-8 max-w-2xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-black italic text-slate-800 tracking-tight">Absensi GPS</h1>
                <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mt-1">Presensi Berbasis Lokasi</p>
            </div>
            <a href="index.php" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-slate-600 transition-all">
                <i class="fa fa-times"></i>
            </a>
        </div>

        <div id="map-absensi" class="shadow-xl shadow-indigo-100 border-4 border-white overflow-hidden" style="min-height: 300px; background: #f1f5f9;"></div>

        <div class="lux-card p-6 mb-6 text-center">
            <div id="status-location" class="mb-4 flex flex-col items-center gap-3">
                <div id="loc-indicator" class="inline-flex items-center px-4 py-2 bg-amber-50 text-amber-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-amber-100 animate-pulse">
                    <i class="fa fa-location-dot mr-2"></i> Mencari Lokasi Anda...
                </div>
                <button type="button" id="btn-manual-loc" class="px-4 py-2 bg-indigo-50 text-indigo-600 rounded-xl text-[10px] font-black uppercase tracking-widest border border-indigo-100 hover:bg-indigo-600 hover:text-white transition-all">
                    <i class="fa fa-crosshairs mr-2"></i> Deteksi Lokasi Manual
                </button>
                <button type="button" onclick="window.location.reload()" class="text-[9px] font-bold text-slate-400 hover:text-slate-600 underline">
                    <i class="fa fa-sync-alt mr-1"></i> Refresh Halaman
                </button>
            </div>

            <div class="flex items-center justify-center gap-8 mb-6">
                <div class="text-center">
                    <div id="distance-text" class="text-3xl font-black text-slate-800 tracking-tighter">--</div>
                    <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Jarak ke Sekolah</div>
                </div>
                <div class="w-px h-10 bg-slate-100"></div>
                <div class="text-center">
                    <div id="accuracy-text" class="text-3xl font-black text-slate-800 tracking-tighter">--</div>
                    <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Akurasi GPS (m)</div>
                </div>
            </div>

            <p id="hint-text" class="text-[11px] text-slate-400 italic font-medium leading-relaxed mb-6">
                Pastikan GPS aktif dan Anda berada dalam radius <?= $radius_absen ?> meter dari lokasi sekolah untuk melakukan absensi.
            </p>

            <?php if ($is_already_absen): ?>
                <button disabled class="w-full py-4 bg-emerald-500 text-white font-black rounded-2xl shadow-xl transition-all flex items-center justify-center gap-3">
                    <i class="fa fa-check-double text-xl"></i> ANDA SUDAH ABSEN
                </button>
            <?php else: ?>
                <button id="btn-absen" disabled class="w-full py-4 bg-slate-200 text-slate-400 font-black rounded-2xl shadow-xl transition-all flex items-center justify-center gap-3 cursor-not-allowed">
                    <i class="fa fa-fingerprint text-xl"></i> ABSEN SEKARANG
                </button>
            <?php endif; ?>
        </div>

        <div class="lux-card p-4 bg-indigo-50 border border-indigo-100 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white shrink-0 shadow-lg shadow-indigo-200">
                <i class="fa fa-info-circle"></i>
            </div>
            <div>
                <p class="text-[11px] text-indigo-900 font-bold leading-relaxed italic">
                    "Sistem secara otomatis mencatat waktu kedatangan Anda. Jika lewat dari pukul <?= $sets['jam_masuk_sekolah'] ?? '07:00' ?>, maka akan tercatat sebagai Terlambat."
                </p>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    const schoolPos = [<?= $school_lat ?>, <?= $school_lng ?>];
    const radiusAbsen = <?= $radius_absen ?>;
    let map;

    // Initialize map with a slight delay to ensure container is ready
    function initMap() {
        if (map) return;
        map = L.map('map-absensi').setView(schoolPos, 17);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19
        }).addTo(map);

        // School Marker with Radius
        L.marker(schoolPos).addTo(map).bindPopup('Lokasi Sekolah').openPopup();
        L.circle(schoolPos, {
            color: '#4F46E5',
            fillColor: '#4F46E5',
            fillOpacity: 0.1,
            radius: radiusAbsen
        }).addTo(map);

        // Force layout recalculation
        setTimeout(() => {
            map.invalidateSize();
            // Sometimes one invalidate is not enough for dynamic layouts
            window.dispatchEvent(new Event('resize'));
        }, 500);
    }

    // Multiple triggers for map init to be safe
    window.addEventListener('load', initMap);
    document.addEventListener('DOMContentLoaded', initMap);
    // Trigger immediately just in case
    initMap();

    let userMarker, userCircle;
    const btnAbsen = document.getElementById('btn-absen');
    const statusLoc = document.getElementById('status-location');
    const distText = document.getElementById('distance-text');
    const accText = document.getElementById('accuracy-text');
    const hintText = document.getElementById('hint-text');

    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371e3; // metres
        const φ1 = lat1 * Math.PI/180;
        const φ2 = lat2 * Math.PI/180;
        const Δφ = (lat2-lat1) * Math.PI/180;
        const Δλ = (lon2-lon1) * Math.PI/180;

        const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                Math.cos(φ1) * Math.cos(φ2) *
                Math.sin(Δλ/2) * Math.sin(Δλ/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

        return R * c; // in metres
    }

    let watchId = null;
    let initialZoomed = false;

    function startGeolocation() {
        if (!("geolocation" in navigator)) {
            Swal.fire('Error', 'Browser Anda tidak mendukung Geolocation.', 'error');
            return;
        }

        if (watchId) navigator.geolocation.clearWatch(watchId);

        watchId = navigator.geolocation.watchPosition(function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;

            const distance = calculateDistance(lat, lng, schoolPos[0], schoolPos[1]);

            distText.textContent = Math.round(distance) + "m";
            accText.textContent = Math.round(accuracy);

            if (userMarker) {
                userMarker.setLatLng([lat, lng]);
                userCircle.setLatLng([lat, lng]).setRadius(accuracy);
            } else {
                userMarker = L.marker([lat, lng]).addTo(map).bindPopup('Lokasi Anda');
                userCircle = L.circle([lat, lng], {
                    radius: accuracy,
                    color: '#10b981',
                    fillColor: '#10b981',
                    fillOpacity: 0.15
                }).addTo(map);
            }

            if (!initialZoomed) {
                const group = new L.featureGroup([L.marker(schoolPos), userMarker]);
                map.fitBounds(group.getBounds().pad(0.1));
                initialZoomed = true;
            }

            if (distance <= radiusAbsen) {
                statusLoc.innerHTML = `
                    <div class="inline-flex items-center px-4 py-2 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-emerald-100">
                        <i class="fa fa-check-circle mr-2"></i> Anda di Area Sekolah
                    </div>`;
                if (btnAbsen) {
                    btnAbsen.disabled = false;
                    btnAbsen.classList.remove('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');
                    btnAbsen.classList.add('bg-indigo-600', 'text-white', 'hover:bg-indigo-700', 'shadow-indigo-200');
                    hintText.classList.add('text-indigo-500');
                    hintText.textContent = "Silakan tekan tombol di bawah untuk melakukan absensi.";
                }
            } else {
                statusLoc.innerHTML = `
                    <div class="inline-flex items-center px-4 py-2 bg-rose-50 text-rose-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-rose-100">
                        <i class="fa fa-exclamation-circle mr-2"></i> Terlalu Jauh
                    </div>`;
                if (btnAbsen) {
                    btnAbsen.disabled = true;
                    btnAbsen.classList.add('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');
                    btnAbsen.classList.remove('bg-indigo-600', 'text-white', 'hover:bg-indigo-700', 'shadow-indigo-200');
                    hintText.classList.remove('text-indigo-500');
                    hintText.textContent = `Anda harus berada dalam radius ${radiusAbsen} meter dari sekolah.`;
                }
            }

        }, function(error) {
            let errorMsg = 'Gagal mendapatkan lokasi GPS.';
            if (error.code == 1) errorMsg = 'Izin lokasi ditolak. Silakan aktifkan GPS di browser Anda.';
            else if (error.code == 2) errorMsg = 'Posisi tidak tersedia. Coba keluar ruangan atau restart GPS.';
            else if (error.code == 3) errorMsg = 'Waktu permintaan GPS habis. Klik tombol deteksi manual.';

            document.getElementById('loc-indicator').innerHTML = `<i class="fa fa-times-circle mr-2"></i> ${errorMsg}`;
            document.getElementById('loc-indicator').className = "inline-flex items-center px-4 py-2 bg-rose-50 text-rose-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-rose-100";

            console.error("Geolocation Error:", error);
        }, {
            enableHighAccuracy: true,
            maximumAge: 10000,
            timeout: 15000
        });
    }

    document.getElementById('btn-manual-loc').addEventListener('click', startGeolocation);

    // Auto-start on load
    startGeolocation();

    if (btnAbsen) {
        btnAbsen.addEventListener('click', function() {
            btnAbsen.disabled = true;
            btnAbsen.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i> Memproses...';

            navigator.geolocation.getCurrentPosition(function(pos) {
                // Anti Fake GPS: Accuracy must be better than 100m
                if (pos.coords.accuracy > 100) {
                    Swal.fire('GPS Tidak Akurat', 'Akurasi GPS Anda terlalu rendah (' + Math.round(pos.coords.accuracy) + 'm). Pastikan Anda berada di luar ruangan.', 'error');
                    btnAbsen.disabled = false;
                    btnAbsen.innerHTML = '<i class="fa fa-fingerprint text-xl"></i> ABSEN SEKARANG';
                    return;
                }

                // Check for Mock Location (if supported by browser/platform)
                if (pos.mocked) {
                    Swal.fire('Fake GPS Terdeteksi', 'Dilarang menggunakan aplikasi manipulasi lokasi!', 'error');
                    return;
                }

                const data = new FormData();
            data.append('lat', pos.coords.latitude);
            data.append('lng', pos.coords.longitude);

            fetch('../api/submit_absensi_gps.php', {
                method: 'POST',
                body: data
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Absen Berhasil!',
                        text: res.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        btnAbsen.disabled = true;
                        btnAbsen.innerHTML = '<i class="fa fa-check-double text-xl"></i> SUDAH ABSEN';
                        btnAbsen.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                        btnAbsen.classList.add('bg-emerald-500');
                        setTimeout(() => window.location.href = 'index.php', 1000);
                    });
                } else {
                    Swal.fire('Gagal', res.message, 'error');
                    btnAbsen.disabled = false;
                    btnAbsen.innerHTML = '<i class="fa fa-fingerprint text-xl"></i> ABSEN SEKARANG';
                }
            });
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
