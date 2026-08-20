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

$is_gps_disabled = (isset($sets['siswa_gps_absen']) && $sets['siswa_gps_absen'] === 'nonaktif');

// Check PKL Status
$q_pkl_check = "SELECT sp.*, tp.nama_tempat, tp.latitude as pkl_lat, tp.longitude as pkl_lng, tp.radius_absen as pkl_radius, tp.alamat as pkl_alamat, tp.pembimbing_dudi, u.nama_lengkap as nama_guru_pembimbing
                FROM siswa_pkl sp
                JOIN tempat_pkl tp ON sp.tempat_pkl_id = tp.id
                LEFT JOIN guru g ON tp.guru_pembimbing_id = g.id
                LEFT JOIN users u ON g.user_id = u.id
                WHERE sp.siswa_id = $siswa_id AND sp.tahun_pelajaran_id = $active_tahun_id AND sp.status = 'aktif'
                LIMIT 1";
$res_pkl = mysqli_query($conn, $q_pkl_check);
$pkl_info = ($res_pkl && mysqli_num_rows($res_pkl) > 0) ? mysqli_fetch_assoc($res_pkl) : null;
$is_pkl = ($pkl_info !== null);

// If PKL, GPS attendance is forced ACTIVE regardless of global setting, and uses PKL location
if ($is_pkl) {
    $is_gps_disabled = false;
    $school_lat = (!empty($pkl_info['pkl_lat'])) ? $pkl_info['pkl_lat'] : '-7.9135';
    $school_lng = (!empty($pkl_info['pkl_lng'])) ? $pkl_info['pkl_lng'] : '113.8217';
    $radius_absen = (int)($pkl_info['pkl_radius'] ?? 50);
} else {
    // Ensure coordinates are numeric and not empty for normal school attendance
    $school_lat = (isset($sets['school_lat']) && $sets['school_lat'] !== '') ? $sets['school_lat'] : '-7.9135';
    $school_lng = (isset($sets['school_lng']) && $sets['school_lng'] !== '') ? $sets['school_lng'] : '113.8217';
    $radius_absen = (int)(($sets['radius_absen'] ?? '') !== '' ? $sets['radius_absen'] : 30);
}

$page_title = "Absensi GPS Siswa";
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    #map-absensi { height: 350px; border-radius: 24px; margin-bottom: 20px; z-index: 10; background: #f1f5f9; }
    .leaflet-container { font-family: inherit; }
    .lux-card { background: white; border-radius: 24px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05); border: 1px solid rgba(226, 232, 240, 0.8); }

    /* Custom indicator for mobile */
    .gps-pulse {
        width: 12px; height: 12px;
        background: #4F46E5;
        border-radius: 50%;
        box-shadow: 0 0 0 rgba(79, 70, 229, 0.4);
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.4); }
        70% { box-shadow: 0 0 0 15px rgba(79, 70, 229, 0); }
        100% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0); }
    }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 lg:p-8 max-w-2xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-black italic text-slate-800 tracking-tight">Presensi Lokasi</h1>
                <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mt-1">
                    <?= $is_pkl ? "Presensi PKL: " . htmlspecialchars($pkl_info['nama_tempat']) : "Sistem Geofencing GPS Sekolah" ?>
                </p>
            </div>
            <a href="index.php" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-slate-600 transition-all">
                <i class="fa fa-times"></i>
            </a>
        </div>

        <?php if ($is_pkl): ?>
        <div class="lux-card p-5 mb-6 bg-gradient-to-r from-indigo-600 to-indigo-800 text-white shadow-xl">
            <div class="flex items-center justify-between mb-2">
                <span class="px-3 py-1 bg-white/20 rounded-full text-[9px] font-black uppercase tracking-widest border border-white/20">Status: Siswa PKL</span>
                <span class="text-[10px] font-bold text-indigo-100">Pembimbing: <?= htmlspecialchars($pkl_info['nama_guru_pembimbing'] ?? 'Guru Pembimbing') ?></span>
            </div>
            <h2 class="text-lg font-black italic"><?= htmlspecialchars($pkl_info['nama_tempat']) ?></h2>
            <p class="text-xs text-indigo-100/80 mt-1 line-clamp-1"><?= htmlspecialchars($pkl_info['pkl_alamat'] ?? 'Lokasi Praktik Kerja Lapangan') ?></p>
        </div>
        <?php endif; ?>

        <div id="map-absensi" class="shadow-2xl shadow-indigo-100/50 border-4 border-white overflow-hidden relative">
            <div id="map-loader" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-50 z-[1000] transition-opacity duration-500">
                <div class="w-10 h-10 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mb-4"></div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Memuat Peta...</p>
            </div>
        </div>

        <div class="lux-card p-6 mb-6">
            <div id="status-location" class="mb-6 flex flex-col items-center gap-3">
                <?php if ($is_gps_disabled): ?>
                    <div class="inline-flex items-center px-4 py-2 bg-rose-50 text-rose-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-rose-100">
                        <i class="fa fa-ban mr-2"></i> Absensi GPS Nonaktif
                    </div>
                <?php else: ?>
                    <div id="loc-indicator" class="inline-flex items-center px-4 py-2 bg-amber-50 text-amber-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-amber-100">
                        <div class="gps-pulse mr-2"></div> Mencari Lokasi Anda...
                    </div>
                    <div class="flex gap-2">
                        <button type="button" id="btn-manual-loc" class="px-4 py-2 bg-indigo-50 text-indigo-600 rounded-xl text-[10px] font-black uppercase tracking-widest border border-indigo-100 hover:bg-indigo-600 hover:text-white transition-all">
                            <i class="fa fa-crosshairs mr-2"></i> Update Lokasi
                        </button>
                        <button type="button" onclick="window.location.reload()" class="px-4 py-2 bg-slate-50 text-slate-600 rounded-xl text-[10px] font-black uppercase tracking-widest border border-slate-200 hover:bg-slate-200 transition-all">
                            <i class="fa fa-sync-alt mr-2"></i> Refresh
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-8">
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 text-center">
                    <div id="distance-text" class="text-3xl font-black text-slate-800 tracking-tighter">--</div>
                    <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mt-1">Jarak (Meter)</div>
                </div>
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 text-center">
                    <div id="accuracy-text" class="text-3xl font-black text-slate-800 tracking-tighter">--</div>
                    <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mt-1">Akurasi (M)</div>
                </div>
            </div>

            <p id="hint-text" class="text-[11px] text-slate-400 italic font-medium leading-relaxed mb-6 text-center px-4">
                <?php if ($is_gps_disabled): ?>
                    Sistem absensi GPS sedang dinonaktifkan oleh administrator.
                <?php else: ?>
                    Pastikan GPS aktif dan Anda berada dalam radius <b><?= $radius_absen ?> meter</b> dari lokasi sekolah.
                <?php endif; ?>
            </p>

            <?php if ($is_gps_disabled): ?>
                <button disabled class="w-full py-4 bg-slate-200 text-slate-400 font-black rounded-2xl shadow-xl transition-all flex items-center justify-center gap-3 cursor-not-allowed">
                    <i class="fa fa-ban text-xl"></i> ABSENSI DINONAKTIFKAN
                </button>
            <?php elseif ($is_already_absen): ?>
                <button disabled class="w-full py-4 bg-emerald-500 text-white font-black rounded-2xl shadow-xl transition-all flex items-center justify-center gap-3">
                    <i class="fa fa-check-double text-xl"></i> ANDA SUDAH ABSEN HARI INI
                </button>
            <?php else: ?>
                <button id="btn-absen" disabled class="w-full py-4 bg-slate-200 text-slate-400 font-black rounded-2xl shadow-xl transition-all flex items-center justify-center gap-3 cursor-not-allowed">
                    <i class="fa fa-fingerprint text-xl"></i> ABSEN SEKARANG
                </button>
            <?php endif; ?>
        </div>

        <div class="lux-card p-5 bg-indigo-50/50 border border-indigo-100 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white shrink-0 shadow-lg shadow-indigo-200">
                <i class="fa fa-info-circle"></i>
            </div>
            <div>
                <p class="text-[11px] text-indigo-900 font-bold leading-relaxed italic">
                    Waktu kedatangan dicatat otomatis. Batas jam masuk: <span class="text-indigo-600 underline font-black"><?= $sets['jam_masuk_sekolah'] ?? '07:00' ?></span>.
                    Lebih dari itu akan tercatat <span class="text-rose-600">Terlambat</span>.
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
    let userMarker, userCircle;
    let watchId = null;
    let initialZoomed = false;

    const btnAbsen = document.getElementById('btn-absen');
    const statusLoc = document.getElementById('status-location');
    const distText = document.getElementById('distance-text');
    const accText = document.getElementById('accuracy-text');
    const hintText = document.getElementById('hint-text');
    const mapLoader = document.getElementById('map-loader');

    function initMap() {
        if (map) return;
        try {
            map = L.map('map-absensi', {
                zoomControl: false,
                attributionControl: false
            }).setView(schoolPos, 17);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19
            }).addTo(map);

            // Add school marker
            const schoolIcon = L.divIcon({
                html: '<div class="w-8 h-8 bg-indigo-600 rounded-full border-4 border-white shadow-lg flex items-center justify-center text-white"><i class="fa fa-school text-xs"></i></div>',
                className: 'custom-div-icon',
                iconSize: [32, 32],
                iconAnchor: [16, 16]
            });

            L.marker(schoolPos, {icon: schoolIcon}).addTo(map);

            L.circle(schoolPos, {
                color: '#4F46E5',
                fillColor: '#4F46E5',
                fillOpacity: 0.1,
                radius: radiusAbsen,
                weight: 2
            }).addTo(map);

            // Force multiple refreshes for mobile
            const refreshMap = () => {
                map.invalidateSize();
                mapLoader.style.opacity = '0';
                setTimeout(() => mapLoader.style.display = 'none', 500);
            };

            requestAnimationFrame(refreshMap);
            setTimeout(refreshMap, 1000);
            setTimeout(refreshMap, 3000);
        } catch (e) {
            console.error("Map Init Error:", e);
            mapLoader.innerHTML = `<div class="p-6 text-center"><i class="fa fa-exclamation-triangle text-rose-500 text-3xl mb-2"></i><p class="text-rose-500 font-bold">Gagal memuat peta. Pastikan koneksi internet aktif.</p></div>`;
        }
    }

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

    function handleLocationUpdate(position) {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        const accuracy = position.coords.accuracy;

        // Anti-Fake GPS heuristic checks
        const isMocked = position.mocked || (position.coords && position.coords.mocked) || false;
        const isAutomated = navigator.webdriver;

        if (isMocked || isAutomated || accuracy <= 1) {
            Swal.fire({
                icon: 'error',
                title: 'Anti-Fake GPS Aktif',
                text: 'Sistem mendeteksi penggunaan Fake GPS, Mock Location, atau browser otomatis. Anda dilarang melakukan absensi!',
                allowOutsideClick: false
            }).then(() => {
                window.location.href = 'index.php';
            });
            if (watchId) navigator.geolocation.clearWatch(watchId);
            return;
        }

        const distance = calculateDistance(lat, lng, schoolPos[0], schoolPos[1]);

        distText.textContent = Math.round(distance);
        accText.textContent = Math.round(accuracy);

        if (userMarker) {
            userMarker.setLatLng([lat, lng]);
            userCircle.setLatLng([lat, lng]).setRadius(accuracy);
        } else {
            const userIcon = L.divIcon({
                html: '<div class="w-6 h-6 bg-emerald-500 rounded-full border-4 border-white shadow-lg relative"><div class="absolute inset-0 rounded-full animate-ping bg-emerald-400 opacity-75"></div></div>',
                className: 'user-div-icon',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });
            userMarker = L.marker([lat, lng], {icon: userIcon}).addTo(map).bindPopup('Lokasi Anda').openPopup();
            userCircle = L.circle([lat, lng], {
                radius: accuracy,
                color: '#10b981',
                fillColor: '#10b981',
                fillOpacity: 0.1,
                weight: 1
            }).addTo(map);
        }

        if (!initialZoomed) {
            const group = new L.featureGroup([L.marker(schoolPos), userMarker]);
            map.fitBounds(group.getBounds().pad(0.3));
            initialZoomed = true;
        }

        if (distance <= radiusAbsen) {
            statusLoc.querySelector('#loc-indicator').className = "inline-flex items-center px-4 py-2 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-emerald-100";
            statusLoc.querySelector('#loc-indicator').innerHTML = `<i class="fa fa-check-circle mr-2"></i> Anda di Area Sekolah`;

            if (btnAbsen) {
                btnAbsen.disabled = false;
                btnAbsen.classList.remove('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');
                btnAbsen.classList.add('bg-indigo-600', 'text-white', 'hover:bg-indigo-700', 'shadow-indigo-200');
                hintText.innerHTML = "Lokasi terverifikasi. Silakan tekan tombol <b>Absen Sekarang</b>.";
                hintText.className = "text-[11px] text-emerald-600 italic font-bold leading-relaxed mb-6 text-center px-4";
            }
        } else {
            statusLoc.querySelector('#loc-indicator').className = "inline-flex items-center px-4 py-2 bg-rose-50 text-rose-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-rose-100";
            statusLoc.querySelector('#loc-indicator').innerHTML = `<i class="fa fa-exclamation-circle mr-2"></i> Di Luar Jangkauan`;

            if (btnAbsen) {
                btnAbsen.disabled = true;
                btnAbsen.classList.add('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');
                btnAbsen.classList.remove('bg-indigo-600', 'text-white', 'hover:bg-indigo-700', 'shadow-indigo-200');
                hintText.innerHTML = `Anda harus berada dalam radius <b>${radiusAbsen}m</b> dari sekolah.`;
                hintText.className = "text-[11px] text-rose-500 italic font-medium leading-relaxed mb-6 text-center px-4";
            }
        }
    }

    function handleLocationError(error) {
        let title = 'Gagal Deteksi Lokasi';
        let errorMsg = 'Gagal mendapatkan lokasi GPS.';

        if (error.code == 1) {
            errorMsg = 'Izin lokasi ditolak. Aktifkan GPS dan berikan izin pada browser.';
            title = 'Izin Lokasi Ditolak';
        } else if (error.code == 2) {
            errorMsg = 'Posisi tidak tersedia. Coba keluar ruangan atau restart GPS.';
            title = 'Sinyal GPS Lemah';
        } else if (error.code == 3) {
            errorMsg = 'Timeout GPS. Sinyal terlalu lemah atau device butuh waktu lebih lama.';
            title = 'GPS Timeout';
        }

        const indicator = document.getElementById('loc-indicator');
        indicator.innerHTML = `<i class="fa fa-times-circle mr-2"></i> ${errorMsg}`;
        indicator.className = "inline-flex items-center px-4 py-2 bg-rose-50 text-rose-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-rose-100 text-center";

        console.error("Geolocation Error:", error);
    }

    function startGeolocation() {
        if (!("geolocation" in navigator)) {
            Swal.fire('Error', 'Browser Anda tidak mendukung Geolocation.', 'error');
            return;
        }

        const options = {
            enableHighAccuracy: true,
            maximumAge: 10000,
            timeout: 30000 // Increased timeout
        };

        if (watchId) navigator.geolocation.clearWatch(watchId);
        watchId = navigator.geolocation.watchPosition(handleLocationUpdate, handleLocationError, options);
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        <?php if ($is_gps_disabled): ?>
            Swal.fire({
                icon: 'warning',
                title: 'Absensi GPS Nonaktif',
                text: 'Absensi GPS Siswa sedang dinonaktifkan oleh Administrator.',
                confirmButtonText: 'Kembali ke Dashboard',
                allowOutsideClick: false
            }).then(() => {
                window.location.href = 'index.php';
            });
            return;
        <?php endif; ?>
        initMap();
        startGeolocation();
    });

    document.getElementById('btn-manual-loc').addEventListener('click', () => {
        const indicator = document.getElementById('loc-indicator');
        indicator.innerHTML = `<i class="fa fa-sync fa-spin mr-2"></i> Mencari Ulang...`;
        indicator.className = "inline-flex items-center px-4 py-2 bg-amber-50 text-amber-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-amber-100";

        navigator.geolocation.getCurrentPosition(handleLocationUpdate, handleLocationError, {
            enableHighAccuracy: true,
            timeout: 15000
        });
    });

    if (btnAbsen) {
        btnAbsen.addEventListener('click', function() {
            btnAbsen.disabled = true;
            btnAbsen.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i> Memproses...';

            navigator.geolocation.getCurrentPosition(function(pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                const accuracy = pos.coords.accuracy;
                const isMocked = pos.mocked || (pos.coords && pos.coords.mocked) || false;
                const isAutomated = navigator.webdriver;

                if (isMocked || isAutomated || accuracy <= 1) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Fake GPS Terdeteksi',
                        text: 'Sistem mendeteksi penggunaan Fake GPS atau browser otomatis. Anda dilarang melakukan absensi!',
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.href = 'index.php';
                    });
                    return;
                }

                // Accuracy Check
                if (accuracy > 150) {
                    Swal.fire('GPS Tidak Akurat', 'Akurasi GPS Anda terlalu rendah (' + Math.round(accuracy) + 'm). Mohon pindah ke area yang tidak terhalang bangunan.', 'warning');
                    btnAbsen.disabled = false;
                    btnAbsen.innerHTML = '<i class="fa fa-fingerprint text-xl"></i> ABSEN SEKARANG';
                    return;
                }

                const data = new FormData();
                data.append('lat', lat);
                data.append('lng', lng);
                data.append('accuracy', accuracy);
                data.append('mocked', isMocked ? '1' : '0');

                fetch('../api/submit_absensi_gps.php', {
                    method: 'POST',
                    body: data
                })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        const now = new Date();
                        const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                        const dateStr = now.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });

                        Swal.fire({
                            icon: 'success',
                            title: 'Absen Berhasil!',
                            html: `<div class="space-y-2">
                                <p class="text-slate-600">${res.message}</p>
                                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Waktu Tercatat</p>
                                    <p class="text-lg font-black text-indigo-600">${timeStr} WIB</p>
                                    <p class="text-xs font-bold text-slate-500">${dateStr}</p>
                                </div>
                            </div>`,
                            timer: 3500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = 'index.php';
                        });
                    } else {
                        Swal.fire('Gagal', res.message, 'error');
                        btnAbsen.disabled = false;
                        btnAbsen.innerHTML = '<i class="fa fa-fingerprint text-xl"></i> ABSEN SEKARANG';
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Error', 'Gagal menghubungi server.', 'error');
                    btnAbsen.disabled = false;
                    btnAbsen.innerHTML = '<i class="fa fa-fingerprint text-xl"></i> ABSEN SEKARANG';
                });
            }, function(err) {
                handleLocationError(err);
                btnAbsen.disabled = false;
                btnAbsen.innerHTML = '<i class="fa fa-fingerprint text-xl"></i> ABSEN SEKARANG';
            }, {
                enableHighAccuracy: true,
                timeout: 15000
            });
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
