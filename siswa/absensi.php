<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];

// Get School Info for GPS
$res_set = mysqli_query($conn, "SELECT * FROM pengaturan");
$sets = [];
while ($r = mysqli_fetch_assoc($res_set)) $sets[$r['nama_setting']] = $r['nilai_setting'];

$school_lat = $sets['school_lat'] ?? '-7.9135';
$school_lng = $sets['school_lng'] ?? '113.8217';

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

        <div id="map-absensi" class="shadow-xl shadow-indigo-100 border-4 border-white"></div>

        <div class="lux-card p-6 mb-6 text-center">
            <div id="status-location" class="mb-4">
                <div class="inline-flex items-center px-4 py-2 bg-amber-50 text-amber-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-amber-100 animate-pulse">
                    <i class="fa fa-location-dot mr-2"></i> Mencari Lokasi Anda...
                </div>
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
                Pastikan GPS aktif dan Anda berada dalam radius 30 meter dari lokasi sekolah untuk melakukan absensi.
            </p>

            <button id="btn-absen" disabled class="w-full py-4 bg-slate-200 text-slate-400 font-black rounded-2xl shadow-xl transition-all flex items-center justify-center gap-3 cursor-not-allowed">
                <i class="fa fa-fingerprint text-xl"></i> ABSEN SEKARANG
            </button>
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
    const map = L.map('map-absensi').setView(schoolPos, 17);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    // School Marker with 30m Radius
    L.marker(schoolPos).addTo(map).bindPopup('Lokasi Sekolah').openPopup();
    L.circle(schoolPos, {
        color: '#4F46E5',
        fillColor: '#4F46E5',
        fillOpacity: 0.1,
        radius: 30
    }).addTo(map);

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

    if ("geolocation" in navigator) {
        navigator.geolocation.watchPosition(function(position) {
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
                userCircle = L.circle([lat, lng], {radius: accuracy}).addTo(map);
            }

            if (distance <= 30) {
                statusLoc.innerHTML = `
                    <div class="inline-flex items-center px-4 py-2 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-emerald-100">
                        <i class="fa fa-check-circle mr-2"></i> Anda di Area Sekolah
                    </div>`;
                btnAbsen.disabled = false;
                btnAbsen.classList.remove('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');
                btnAbsen.classList.add('bg-indigo-600', 'text-white', 'hover:bg-indigo-700', 'shadow-indigo-200');
                hintText.classList.add('text-indigo-500');
                hintText.textContent = "Silakan tekan tombol di bawah untuk melakukan absensi.";
            } else {
                statusLoc.innerHTML = `
                    <div class="inline-flex items-center px-4 py-2 bg-rose-50 text-rose-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-rose-100">
                        <i class="fa fa-exclamation-circle mr-2"></i> Terlalu Jauh
                    </div>`;
                btnAbsen.disabled = true;
                btnAbsen.classList.add('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');
                btnAbsen.classList.remove('bg-indigo-600', 'text-white', 'hover:bg-indigo-700', 'shadow-indigo-200');
                hintText.classList.remove('text-indigo-500');
                hintText.textContent = "Anda harus berada dalam radius 30 meter dari sekolah.";
            }

        }, function(error) {
            Swal.fire('Gagal GPS', 'Pastikan izin lokasi diaktifkan pada browser Anda.', 'error');
        }, {
            enableHighAccuracy: true,
            maximumAge: 0,
            timeout: 5000
        });
    } else {
        Swal.fire('Error', 'Browser Anda tidak mendukung Geolocation.', 'error');
    }

    btnAbsen.addEventListener('click', function() {
        btnAbsen.disabled = true;
        btnAbsen.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i> Memproses...';

        navigator.geolocation.getCurrentPosition(function(pos) {
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
                        window.location.href = 'index.php';
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
