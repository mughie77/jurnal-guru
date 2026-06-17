<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];

// Get Student Profile
$query = "SELECT s.*, k.nama_kelas FROM siswa s
          JOIN siswa_kelas sk ON s.id = sk.siswa_id
          JOIN kelas k ON sk.kelas_id = k.id
          JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id
          WHERE s.id = ? AND tp.status = 'aktif'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $siswa_id);
mysqli_stmt_execute($stmt);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$page_title = "Barcode Absensi";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    body { background-color: #F8FAFC; }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div class="bg-slate-50 min-h-screen pb-24 flex flex-col items-center justify-center p-6">
    <div class="lux-card p-10 w-full max-w-sm text-center animate-in zoom-in duration-500">
        <h1 class="text-xl font-black italic text-slate-800 uppercase tracking-widest mb-2">Barcode Absensi</h1>
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-8">Scan untuk Kehadiran Harian</p>

        <div class="bg-slate-50 p-8 rounded-[32px] border border-slate-100 shadow-inner inline-block mb-8">
            <div id="qrcode_big" class="p-4 bg-white rounded-2xl shadow-xl border border-white"></div>
        </div>

        <h2 class="text-lg font-black text-indigo-600 uppercase tracking-tighter mb-1"><?= htmlspecialchars($siswa['nama_siswa']) ?></h2>
        <p class="text-sm font-bold text-slate-700 italic mb-1"><?= htmlspecialchars($siswa['nis']) ?></p>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest"><?= htmlspecialchars($siswa['nama_kelas']) ?></p>
    </div>

    <div class="mt-8 text-center px-8">
        <p class="text-xs text-slate-400 font-bold italic leading-relaxed">
            "Tunjukkan Barcode ini pada scanner di pintu masuk atau di depan meja piket untuk merekam kehadiran Anda secara otomatis."
        </p>
    </div>
</div>

<script>
    new QRCode(document.getElementById("qrcode_big"), {
        <?php
        $qr_val = $siswa['nis'];
        if (strpos($qr_val, '/') !== false) {
            $qr_val = explode('/', $qr_val)[0];
        }
        ?>
        text: "<?= $qr_val ?>",
        width: 180,
        height: 180,
        colorDark : "#1e293b",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
