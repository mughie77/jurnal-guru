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

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    body { background-color: #f1f5f9; }

    .card-id-wrapper {
        perspective: 1000px;
    }

    .card-id {
        width: 340px;
        height: 600px;
        background: #002d5b;
        border-radius: 12px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.4);
        display: flex;
        flex-direction: column;
    }

    /* Template Shapes */
    .shape-top-orange {
        position: absolute;
        top: -150px;
        left: -150px;
        width: 400px;
        height: 380px;
        background: #f59e0b;
        transform: rotate(35deg);
        z-index: 1;
    }

    .bottom-orange-bar {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 15px;
        background: #f59e0b;
        z-index: 5;
    }

    .card-content {
        position: relative;
        z-index: 10;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .header-logo {
        padding-top: 25px;
        padding-left: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
    }

    .photo-area {
        margin-top: 30px;
        display: flex;
        justify-content: center;
        position: relative;
    }

    .photo-circle {
        width: 180px;
        height: 180px;
        border-radius: 50%;
        border: 6px solid white;
        overflow: hidden;
        background: #f1f5f9;
        box-shadow: 0 15px 35px rgba(0,0,0,0.3);
    }

    .photo-circle img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .info-area {
        padding: 30px;
        color: white;
        text-align: center;
    }

    .label-gold {
        color: #f59e0b;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-size: 14px;
        margin-top: 5px;
        display: block;
    }

    .details-table {
        margin-top: 30px;
        width: 100%;
        text-align: left;
    }

    .details-table td {
        padding: 4px 0;
        font-size: 13px;
        vertical-align: top;
    }

    .details-label {
        color: white;
        width: 80px;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 11px;
    }

    .details-separator {
        width: 20px;
    }

    .details-value {
        font-weight: 400;
        word-break: break-word;
    }

    .qr-footer {
        margin-top: auto;
        padding-bottom: 40px;
        display: flex;
        justify-content: center;
    }

    .qr-container {
        padding: 8px;
        background: white;
        border-radius: 4px;
    }

    @media print {
        body { background: none !important; }
        #sidebar, header, .no-print, .mb-8 { display: none !important; }
        .bg-slate-50 { background: none !important; }
        .lg\:ml-64 { margin: 0 !important; }
        main { padding: 0 !important; }

        .card-id-wrapper {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .card-id {
            box-shadow: none !important;
            border: 1px solid #eee !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<!-- QRCode Generator -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div class="bg-slate-50 min-h-screen pb-24 flex flex-col items-center p-6 no-print">
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-black italic text-slate-800 uppercase tracking-widest">Kartu Pelajar Digital</h1>
        <p class="text-slate-400 font-bold text-xs uppercase tracking-[0.3em] mt-1">Verifikasi Sistem CAKRA</p>
    </div>

    <div id="printableCard" class="card-id-wrapper animate-in zoom-in duration-500">
        <div class="card-id">
            <div class="shape-top-orange"></div>
            <div class="bottom-orange-bar"></div>

            <div class="card-content">
                <div class="header-logo">
                    <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center">
                        <?php if(!empty($sets['favicon'])): ?>
                            <img src="<?= BASE_URL ?>uploads/<?= $sets['favicon'] ?>" class="w-6 h-6 object-contain">
                        <?php else: ?>
                            <i class="fa fa-graduation-cap text-[#002d5b] text-xl"></i>
                        <?php endif; ?>
                    </div>
                    <h2 class="font-black text-lg tracking-wider uppercase leading-tight"><?= htmlspecialchars($sets['nama_sekolah'] ?? 'GOLDEN SUN') ?></h2>
                </div>

                <div class="photo-area">
                    <div class="photo-circle">
                        <?php if (!empty($siswa['foto'])): ?>
                            <img src="<?= BASE_URL ?>uploads/siswa/<?= $siswa['foto'] ?>">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-slate-400"><i class="fa fa-user text-7xl"></i></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-area">
                    <h1 class="text-2xl font-black uppercase leading-tight"><?= htmlspecialchars($siswa['nama_siswa']) ?></h1>
                    <span class="label-gold"><?= htmlspecialchars($siswa['nama_kelas']) ?></span>

                    <table class="details-table">
                        <tr>
                            <td class="details-label">NIS</td>
                            <td class="details-separator">:</td>
                            <td class="details-value"><?= htmlspecialchars($siswa['nis']) ?></td>
                        </tr>
                        <tr>
                            <td class="details-label">NISN</td>
                            <td class="details-separator">:</td>
                            <td class="details-value"><?= htmlspecialchars($siswa['nisn'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td class="details-label">Alamat</td>
                            <td class="details-separator">:</td>
                            <td class="details-value"><?= htmlspecialchars($siswa['alamat'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td class="details-label">Telp/HP</td>
                            <td class="details-separator">:</td>
                            <td class="details-value"><?= htmlspecialchars($siswa['no_telp'] ?? '-') ?></td>
                        </tr>
                    </table>
                </div>

                <div class="qr-footer">
                    <div class="qr-container">
                        <div id="qrcode"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-12 max-w-xs text-center no-print">
        <a href="<?= BASE_URL ?>siswa/download_kartu_pdf.php" class="px-8 py-4 bg-indigo-600 text-white font-black text-xs uppercase tracking-widest rounded-2xl shadow-xl hover:bg-indigo-700 transition-all flex items-center justify-center mx-auto group">
            <i class="fa fa-file-pdf mr-2 group-hover:scale-110 transition-transform"></i> Unduh PDF Kartu
        </a>
        <p class="mt-4 text-[10px] text-slate-400 font-bold italic leading-relaxed px-4">
            "Unduh kartu dalam format PDF berkualitas tinggi untuk dicetak. Format PDF memastikan tata letak tetap presisi saat dicetak."
        </p>
    </div>
</div>

<script>
    new QRCode(document.getElementById("qrcode"), {
        text: "<?= $siswa['nis'] ?>",
        width: 100,
        height: 100,
        colorDark : "#002d5b",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
