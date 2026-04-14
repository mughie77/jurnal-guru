<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Check if face_image column exists to prevent fatal errors
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM `siswa` LIKE 'face_image'");
$col_exists = (mysqli_num_rows($check_col) > 0);

if (!$col_exists && $_SESSION['role'] === 'admin') {
    // This shouldn't happen for a student, but good for debugging if an admin mimics a student
    $error_msg = "Database belum diperbarui. Silakan jalankan <a href='../admin/update_db.php' class='underline'>Update Database</a>.";
} elseif (!$col_exists) {
    $error_msg = "Fitur pendaftaran wajah sedang dalam pemeliharaan. Silakan hubungi Administrator.";
}

// Handle Image Upload/Capture
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $col_exists) {
    if (!empty($_POST['face_data'])) {
        $img = $_POST['face_data'];
        $img = str_replace('data:image/jpeg;base64,', '', $img);
        $img = str_replace(' ', '+', $img);
        $data = base64_decode($img);

        $filename = 'face_' . $siswa_id . '_' . time() . '.jpg';
        $dir = __DIR__ . '/../uploads/siswa/face/';
        $filepath = $dir . $filename;

        // Ensure directory exists
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (file_put_contents($filepath, $data)) {
            // Update database
            $sql = "UPDATE siswa SET face_image = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "si", $filename, $siswa_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Wajah berhasil didaftarkan!";
            } else {
                $error_msg = "Gagal menyimpan data ke database.";
            }
        } else {
            $error_msg = "Gagal menyimpan file gambar.";
        }
    }
}

// Get Current Data
$siswa = ['face_image' => null, 'nama_siswa' => $_SESSION['nama_lengkap']];
if ($col_exists) {
    $stmt = mysqli_prepare($conn, "SELECT face_image, nama_siswa FROM siswa WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $siswa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $siswa = $row;
    }
}

$page_title = "Pendaftaran Wajah";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    body { background-color: #F8FAFC; }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 lg:p-8 max-w-2xl mx-auto">

        <div class="mb-8 flex items-center justify-between">
            <a href="index.php" class="w-12 h-12 rounded-2xl bg-white shadow-sm flex items-center justify-center text-slate-400 hover:text-indigo-600 transition-all">
                <i class="fa fa-arrow-left"></i>
            </a>
            <h1 class="text-xl font-black italic text-slate-800 uppercase tracking-widest">Pendaftaran Wajah</h1>
            <div class="w-12"></div>
        </div>

        <?php if ($success_msg): ?>
        <div class="mb-6 p-6 rounded-[32px] bg-emerald-50 border border-emerald-100 flex flex-col items-center text-center animate-in zoom-in duration-300">
            <div class="w-16 h-16 bg-emerald-500 text-white rounded-full flex items-center justify-center text-2xl mb-4 shadow-lg shadow-emerald-200">
                <i class="fa fa-check"></i>
            </div>
            <h3 class="font-black text-emerald-900 uppercase italic tracking-widest text-sm"><?= $success_msg ?></h3>
            <p class="text-emerald-600/70 text-[10px] font-bold mt-2 uppercase tracking-widest">Wajah Anda kini tersimpan dalam sistem CAKRA</p>
            <a href="index.php" class="mt-6 px-8 py-3 bg-emerald-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-xl shadow-emerald-100 active:scale-95 transition-all">Selesai</a>
        </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-rose-600 text-xs font-bold flex items-center italic">
            <i class="fa fa-exclamation-circle mr-3 text-lg"></i> <?= $error_msg ?>
        </div>
        <?php endif; ?>

        <?php if ($col_exists): ?>
        <!-- Instructions Card -->
        <div class="lux-card p-6 mb-8 border-l-4 border-amber-400">
            <h3 class="font-black text-slate-800 italic uppercase tracking-widest text-xs mb-4 flex items-center">
                <i class="fa fa-lightbulb mr-2 text-amber-500"></i> Petunjuk Pendaftaran
            </h3>
            <ul class="space-y-3">
                <li class="flex items-start gap-3">
                    <div class="w-5 h-5 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-black shrink-0 mt-0.5">1</div>
                    <p class="text-[10px] font-bold text-slate-500 leading-relaxed uppercase tracking-widest">Pastikan berada di ruangan dengan pencahayaan yang terang.</p>
                </li>
                <li class="flex items-start gap-3">
                    <div class="w-5 h-5 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-black shrink-0 mt-0.5">2</div>
                    <p class="text-[10px] font-bold text-slate-500 leading-relaxed uppercase tracking-widest">Lepaskan kacamata, masker, atau topi jika sedang mengenakannya.</p>
                </li>
                <li class="flex items-start gap-3">
                    <div class="w-5 h-5 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-black shrink-0 mt-0.5">3</div>
                    <p class="text-[10px] font-bold text-slate-500 leading-relaxed uppercase tracking-widest">Posisikan wajah tepat di tengah kotak kamera yang tersedia.</p>
                </li>
                <li class="flex items-start gap-3">
                    <div class="w-5 h-5 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-black shrink-0 mt-0.5">4</div>
                    <p class="text-[10px] font-bold text-slate-500 leading-relaxed uppercase tracking-widest">Jangan bergerak dan tekan tombol "Ambil Foto" saat sudah siap.</p>
                </li>
            </ul>
        </div>

        <!-- Camera Area -->
        <div class="relative w-full aspect-square max-w-[400px] mx-auto bg-slate-900 rounded-[48px] overflow-hidden shadow-2xl border-8 border-white group">
            <video id="video" class="w-full h-full object-cover transform scale-x-[-1]" autoplay playsinline></video>
            <canvas id="canvas" class="hidden"></canvas>

            <!-- Overlay Guide -->
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="w-[70%] h-[70%] border-2 border-dashed border-white/50 rounded-full flex flex-col items-center justify-center">
                    <div class="w-4 h-4 bg-indigo-500 rounded-full animate-ping mb-2"></div>
                    <span class="text-white/30 text-[8px] font-black uppercase tracking-[0.3em]">Posisikan Wajah Di Sini</span>
                </div>
            </div>

            <div id="cameraStatus" class="absolute inset-0 flex items-center justify-center bg-slate-900 text-white flex-col gap-4">
                <i class="fa fa-camera text-4xl animate-pulse text-indigo-400"></i>
                <span class="text-[10px] font-black uppercase tracking-[0.4em]">Mengaktifkan Kamera...</span>
            </div>

            <div id="capturedPreview" class="absolute inset-0 bg-slate-900 hidden">
                <img id="previewImg" class="w-full h-full object-cover transform scale-x-[-1]">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center flex-col gap-2">
                    <div class="w-12 h-12 bg-white text-indigo-600 rounded-full flex items-center justify-center shadow-lg">
                        <i class="fa fa-check text-xl"></i>
                    </div>
                    <span class="text-white font-black text-[10px] uppercase tracking-widest">Foto Siap Dikirim!</span>
                </div>
            </div>
        </div>

        <div class="mt-10 flex flex-col gap-4 max-w-[400px] mx-auto">
            <button id="snap" class="w-full py-5 bg-indigo-600 text-white rounded-[24px] font-black text-xs uppercase tracking-[0.2em] shadow-xl shadow-indigo-100 hover:bg-indigo-700 active:scale-95 transition-all flex items-center justify-center gap-3">
                <i class="fa fa-camera"></i>
                <span>Ambil Foto Wajah</span>
            </button>

            <form id="faceForm" method="POST" class="hidden">
                <input type="hidden" name="face_data" id="face_data">
                <div class="flex gap-4">
                    <button type="button" id="retake" class="flex-1 py-5 bg-slate-100 text-slate-500 rounded-[24px] font-black text-xs uppercase tracking-[0.2em] hover:bg-slate-200 transition-all">Ulangi</button>
                    <button type="submit" class="flex-[2] py-5 bg-emerald-600 text-white rounded-[24px] font-black text-xs uppercase tracking-[0.2em] shadow-xl shadow-emerald-100 hover:bg-emerald-700 active:scale-95 transition-all">Daftarkan Wajah</button>
                </div>
            </form>

            <p class="text-center text-[9px] font-black text-slate-400 uppercase tracking-widest mt-4">
                <i class="fa fa-shield-alt mr-1"></i> Data wajah hanya akan digunakan untuk verifikasi kehadiran.
            </p>
        </div>

        <?php if ($siswa['face_image']): ?>
        <div class="mt-12 pt-12 border-t border-slate-200 text-center">
            <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-6 italic">Wajah Terdaftar Saat Ini</h4>
            <div class="w-24 h-24 mx-auto rounded-3xl overflow-hidden border-4 border-white shadow-xl rotate-3">
                <img src="<?= BASE_URL ?>uploads/siswa/face/<?= $siswa['face_image'] ?>" class="w-full h-full object-cover">
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($col_exists): ?>
<script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const snap = document.getElementById('snap');
    const retake = document.getElementById('retake');
    const faceForm = document.getElementById('faceForm');
    const faceDataInput = document.getElementById('face_data');
    const cameraStatus = document.getElementById('cameraStatus');
    const capturedPreview = document.getElementById('capturedPreview');
    const previewImg = document.getElementById('previewImg');

    // Access Camera
    async function initCamera() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: "user", width: { ideal: 1080 }, height: { ideal: 1080 } }
            });
            video.srcObject = stream;
            video.onloadedmetadata = () => {
                cameraStatus.classList.add('hidden');
                video.play();
            };
        } catch (err) {
            console.error(err);
            cameraStatus.innerHTML = '<i class="fa fa-video-slash text-4xl text-rose-500 mb-4"></i><span class="text-[10px] font-black uppercase tracking-widest text-rose-500">Izin Kamera Ditolak</span>';
        }
    }

    initCamera();

    // Capture Photo
    snap.addEventListener('click', () => {
        const context = canvas.getContext('2d');
        canvas.width = 1080;
        canvas.height = 1080;

        // Draw the video frame to the canvas
        context.translate(canvas.width, 0);
        context.scale(-1, 1);
        context.drawImage(video, 0, 0, 1080, 1080);

        const dataURL = canvas.toDataURL('image/jpeg', 0.8);
        faceDataInput.value = dataURL;
        previewImg.src = dataURL;

        capturedPreview.classList.remove('hidden');
        snap.classList.add('hidden');
        faceForm.classList.remove('hidden');

        // Stop the camera stream to save battery
        if (video.srcObject) {
            const stream = video.srcObject;
            const tracks = stream.getTracks();
            tracks.forEach(track => track.stop());
        }
    });

    // Retake Photo
    retake.addEventListener('click', () => {
        capturedPreview.classList.add('hidden');
        snap.classList.remove('hidden');
        faceForm.classList.add('hidden');
        initCamera();
    });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
