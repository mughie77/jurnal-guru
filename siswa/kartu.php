<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];

// Get Student Profile
$query = "SELECT s.*, k.nama_kelas, tp.tahun as tahun_pelajaran
          FROM siswa s
          JOIN siswa_kelas sk ON s.id = sk.siswa_id
          JOIN kelas k ON sk.kelas_id = k.id
          JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id
          WHERE s.id = ? AND tp.status = 'aktif'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $siswa_id);
mysqli_stmt_execute($stmt);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Get School Info
$res_set = mysqli_query($conn, "SELECT * FROM pengaturan");
$sets = [];
while ($r = mysqli_fetch_assoc($res_set)) $sets[$r['nama_setting']] = $r['nilai_setting'];

$page_title = "Kartu Pelajar Digital";
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/id-card.css">
<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
</style>

<!-- JsBarcode & html2canvas -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<div class="bg-slate-50 min-h-screen pb-24 flex flex-col items-center p-6 no-print">
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-black italic text-slate-800 uppercase tracking-widest">Kartu Pelajar Digital</h1>
        <p class="text-slate-400 font-bold text-xs uppercase tracking-[0.3em] mt-1">Verifikasi Sistem CAKRA</p>
    </div>

    <div id="printableCard" class="card-id-wrapper animate-in zoom-in duration-500">
        <div class="card-id">
            <div class="card-content">
                <div class="photo-area-new">
                    <?php if (!empty($siswa['foto'])): ?>
                        <img src="<?= BASE_URL ?>uploads/siswa/<?= $siswa['foto'] ?>">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-slate-400 border border-dashed border-slate-300">
                            <i class="fa fa-user text-5xl"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="barcode-area-new">
                    <canvas id="barcode"></canvas>
                </div>

                <div class="info-area-new">
                    <div class="info-value val-nama"><?= htmlspecialchars($siswa['nama_siswa']) ?></div>
                    <div class="info-value val-nis"><?= htmlspecialchars($siswa['nis']) ?> / <?= htmlspecialchars($siswa['nisn'] ?? '-') ?></div>
                    <div class="info-value val-ttl">
                        <?= htmlspecialchars($siswa['tempat_lahir'] ?? '-') ?>,
                        <?= !empty($siswa['tanggal_lahir']) ? date('d-m-Y', strtotime($siswa['tanggal_lahir'])) : '-' ?>
                    </div>
                    <div class="info-value val-jk"><?= ($siswa['jenis_kelamin'] == 'P') ? 'Perempuan' : 'Laki-Laki' ?></div>
                    <div class="info-value val-alamat"><?= htmlspecialchars($siswa['alamat'] ?? '-') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-12 max-w-xs text-center no-print">
        <button id="downloadJpg" class="w-full px-8 py-4 bg-indigo-600 text-white font-black text-xs uppercase tracking-widest rounded-2xl shadow-xl hover:bg-indigo-700 transition-all flex items-center justify-center mx-auto group">
            <i class="fa fa-image mr-2 group-hover:scale-110 transition-transform"></i> Unduh Kartu (JPG)
        </button>
        <p class="mt-4 text-[10px] text-slate-400 font-bold italic leading-relaxed px-4">
            "Unduh kartu dalam format gambar (JPG) berkualitas tinggi. Kartu ini dapat Anda simpan di ponsel atau dicetak langsung."
        </p>
    </div>
</div>

<script>
    // Generate Barcode
    JsBarcode("#barcode", "<?= $siswa['nis'] ?>", {
        format: "CODE128",
        width: 1.5,
        height: 35,
        displayValue: true,
        fontSize: 10,
        fontOptions: "bold",
        margin: 2,
        background: "#ffffff"
    });

    // Handle Download JPG
    document.getElementById('downloadJpg').addEventListener('click', function() {
        const btn = this;
        const originalContent = btn.innerHTML;

        // Show loading state
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i> Memproses...';

        const card = document.querySelector('.card-id');

        // Use html2canvas to capture the card
        html2canvas(card, {
            scale: 5, // Even higher scale for 86x54mm high quality print
            useCORS: true,
            allowTaint: true,
            backgroundColor: null
        }).then(canvas => {
            // Convert to JPG
            const imgData = canvas.toDataURL('image/jpeg', 0.95);

            // Create download link
            const link = document.createElement('a');
            link.download = 'Kartu_Pelajar_<?= $siswa['nis'] ?>.jpg';
            link.href = imgData;
            link.click();

            // Restore button state
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }).catch(err => {
            console.error('Export failed:', err);
            alert('Gagal mengunduh kartu. Silakan coba lagi.');
            btn.disabled = false;
            btn.innerHTML = originalContent;
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
