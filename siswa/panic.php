<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];
$nama_siswa = $_SESSION['nama_lengkap'];
$message = '';
$message_type = '';

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get student's class name for validation / logging if needed
$q_class = mysqli_query($conn, "SELECT k.nama_kelas FROM siswa_kelas sk JOIN kelas k ON sk.kelas_id = k.id WHERE sk.siswa_id = $siswa_id AND sk.tahun_pelajaran_id = $active_tahun_id LIMIT 1");
$student_class = mysqli_fetch_assoc($q_class)['nama_kelas'] ?? 'Tanpa Kelas';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['trigger_panic'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Token CSRF tidak valid.";
        $message_type = "error";
    } else {
        $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
        $lat = $_POST['lat'] ?? null;
        $lng = $_POST['lng'] ?? null;
        $accuracy = $_POST['accuracy'] ?? null;

        if (empty($keterangan)) {
            $message = "Harap masukkan keterangan kejadian perundungan/bullying.";
            $message_type = "error";
        } elseif (empty($lat) || empty($lng)) {
            $message = "Koordinat lokasi GPS diperlukan. Harap aktifkan GPS ponsel Anda.";
            $message_type = "error";
        } else {
            $accuracy_val = !empty($accuracy) ? (float)$accuracy : 0.0;

            $stmt = mysqli_prepare($conn, "INSERT INTO panic_button (siswa_id, nama_siswa, keterangan, latitude, longitude, akurasi) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "issssd", $siswa_id, $nama_siswa, $keterangan, $lat, $lng, $accuracy_val);

            if (mysqli_stmt_execute($stmt)) {
                $message = "Laporan darurat berhasil dikirim! Tim konseling / kesiswaan akan segera menindaklanjuti.";
                $message_type = "success";
            } else {
                $message = "Gagal mengirim laporan: " . mysqli_error($conn);
                $message_type = "error";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

$page_title = "Anti-Bullying Panic Button";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    body { background-color: #FBFBFB; }
    @keyframes pulse-red {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 20px rgba(220, 38, 38, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
    }
    .btn-panic-pulse {
        animation: pulse-red 2s infinite;
    }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 lg:p-8 max-w-2xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-black italic text-slate-800 tracking-tight text-rose-600">PANIC BUTTON</h1>
                <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mt-1">Layanan Pengaduan Darurat Anti-Bullying</p>
            </div>
            <a href="index.php" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-slate-600 transition-all">
                <i class="fa fa-times"></i>
            </a>
        </div>

        <div class="lux-card p-8 mb-8 bg-gradient-to-br from-rose-600 to-red-800 text-white relative overflow-hidden text-center rounded-[32px]">
            <div class="relative z-10 flex flex-col items-center">
                <div class="w-24 h-24 rounded-full bg-white text-rose-600 flex items-center justify-center text-4xl shadow-2xl mb-6 btn-panic-pulse">
                    <i class="fa fa-bell"></i>
                </div>
                <h2 class="text-2xl font-black italic tracking-tight leading-tight mb-2">Anda Mengalami / Melihat Perundungan?</h2>
                <p class="text-rose-100 font-bold text-xs max-w-md mx-auto leading-relaxed">
                    Jangan takut, Anda tidak sendiri! Tekan dan laporkan kejadian perundungan (bullying) sekarang juga. Lokasi presisi Anda akan langsung terkirim ke tim bimbingan konseling dan kesiswaan untuk segera ditolong.
                </p>
            </div>
            <i class="fa fa-shield-alt absolute -bottom-10 -right-10 text-[200px] opacity-10"></i>
        </div>

        <!-- Form Card -->
        <div class="lux-card p-6 bg-white border-none shadow-xl">
            <form id="panicForm" action="" method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="lat" id="panicLat">
                <input type="hidden" name="lng" id="panicLng">
                <input type="hidden" name="accuracy" id="panicAccuracy">

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Keterangan Kejadian</label>
                    <textarea name="keterangan" rows="4" required
                              placeholder="Ceritakan kejadian secara singkat (siapa korbannya, di mana, dan apa yang sedang terjadi)..."
                              class="w-full px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-rose-50 focus:border-rose-500 font-medium text-slate-700 transition-all italic text-sm"></textarea>
                </div>

                <!-- GPS Tracker Badge -->
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex items-center gap-4">
                    <div id="gpsIcon" class="w-10 h-10 rounded-xl bg-slate-200 text-slate-400 flex items-center justify-center text-lg animate-pulse">
                        <i class="fa fa-map-marker-alt"></i>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Akurasi GPS Anda</p>
                        <p id="gpsStatus" class="font-bold text-slate-500 text-xs mt-0.5">Sedang mendeteksi lokasi presisi...</p>
                    </div>
                </div>

                <button type="submit" name="trigger_panic" id="submitPanicBtn"
                        class="w-full py-4 bg-rose-600 hover:bg-rose-700 text-white font-black rounded-2xl shadow-xl shadow-rose-100 transition-all flex items-center justify-center gap-3 uppercase tracking-widest italic">
                    <i class="fa fa-exclamation-triangle text-xl"></i> Kirim Laporan Darurat
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Fetch user coordinates and accuracy
    if ("geolocation" in navigator) {
        navigator.geolocation.getCurrentPosition(function(position) {
            if (position.mocked) {
                Swal.fire({
                    icon: 'error',
                    title: 'Aplikasi Palsu Terdeteksi',
                    text: 'Dilarang keras menggunakan fake GPS/mocked location!',
                    confirmButtonColor: '#dc2626'
                });
                return;
            }

            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;

            document.getElementById('panicLat').value = lat;
            document.getElementById('panicLng').value = lng;
            document.getElementById('panicAccuracy').value = accuracy;

            // Update GPS badge
            const statusText = document.getElementById('gpsStatus');
            const iconDiv = document.getElementById('gpsIcon');

            statusText.innerText = `Terdeteksi (Akurasi: ±${Math.round(accuracy)} meter)`;
            statusText.classList.remove('text-slate-500');
            statusText.classList.add('text-rose-600');

            iconDiv.classList.remove('bg-slate-200', 'text-slate-400', 'animate-pulse');
            iconDiv.classList.add('bg-rose-50', 'text-rose-500');
        }, function(error) {
            console.error("GPS error:", error);
            document.getElementById('gpsStatus').innerText = "Gagal mendeteksi lokasi. Pastikan GPS aktif.";
            document.getElementById('gpsStatus').classList.add('text-red-500');
        }, { enableHighAccuracy: true, timeout: 15000 });
    } else {
        document.getElementById('gpsStatus').innerText = "Ponsel Anda tidak mendukung pendeteksian lokasi.";
    }

    const form = document.getElementById('panicForm');
    form.addEventListener('submit', function(e) {
        const lat = document.getElementById('panicLat').value;
        if (!lat) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Mendeteksi Lokasi...',
                text: 'Harap tunggu beberapa saat hingga lokasi GPS presisi Anda selesai dideteksi.',
                confirmButtonColor: '#dc2626'
            });
        }
    });

    <?php if (!empty($message)): ?>
    Swal.fire({
        icon: '<?= $message_type ?>',
        title: '<?= $message_type == "success" ? "Laporan Terkirim!" : "Gagal!" ?>',
        text: '<?= $message ?>',
        confirmButtonColor: '#dc2626',
        customClass: { popup: 'rounded-[32px]', title: 'font-black italic text-rose-600' }
    });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
