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
    body { background-color: #F8FAFC; }

    .card-id-wrapper {
        perspective: 1000px;
    }

    .card-id {
        width: 320px;
        height: 500px;
        background: white;
        border-radius: 24px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.15);
        border: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
    }

    .card-header-bg {
        height: 180px;
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding-top: 10px;
        z-index: 1;
        position: relative;
    }

    .card-header-bg::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 30px;
        background: white;
        clip-path: ellipse(60% 100% at 50% 100%);
    }

    .photo-frame {
        width: 140px;
        height: 140px;
        background: #f1f5f9;
        border: 6px solid white;
        border-radius: 50%;
        margin-top: -50px;
        align-self: center;
        box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
        overflow: hidden;
        flex-shrink: 0;
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
            <!-- Background & Logo -->
            <div class="card-header-bg">
                <div class="w-14 h-14 rounded-2xl bg-white/10 backdrop-blur-xl flex items-center justify-center border border-white/20 mb-3 shadow-2xl">
                    <?php if(!empty($sets['favicon'])): ?>
                        <img src="<?= BASE_URL ?>uploads/<?= $sets['favicon'] ?>" class="max-w-[70%] max-h-[70%] object-contain drop-shadow-lg">
                    <?php else: ?>
                        <i class="fa fa-graduation-cap text-white text-2xl"></i>
                    <?php endif; ?>
                </div>
                <h3 class="text-white/80 font-bold text-[8px] uppercase tracking-[0.5em] mb-1">Kartu Pelajar Digital</h3>
                <h2 class="text-white font-black text-[12px] uppercase tracking-wider text-center px-10 leading-tight mb-2 drop-shadow-md"><?= htmlspecialchars($sets['nama_sekolah'] ?? 'SMK Negeri CAKRA') ?></h2>
            </div>

            <!-- Photo -->
            <div class="photo-frame">
                <?php if (!empty($siswa['foto'])): ?>
                    <img src="<?= BASE_URL ?>uploads/siswa/<?= $siswa['foto'] ?>" class="w-full h-full object-cover scale-110">
                <?php else: ?>
                    <i class="fa fa-user text-6xl text-slate-200"></i>
                <?php endif; ?>
            </div>

            <!-- Name Below Photo -->
            <div class="mt-2 w-full px-8 text-center flex-1">
                <h2 class="text-2xl font-black text-slate-900 uppercase tracking-tighter italic leading-none mb-1"><?= htmlspecialchars($siswa['nama_siswa']) ?></h2>
                <p class="text-indigo-600 font-black text-[11px] tracking-[0.3em] mb-4 uppercase"><?= htmlspecialchars($siswa['nama_kelas']) ?></p>

                <div class="grid grid-cols-2 gap-4 text-left border-t border-slate-100 pt-3">
                    <div>
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block mb-0.5">NIS</span>
                        <span class="text-[12px] font-bold text-slate-800 tracking-tight"><?= htmlspecialchars($siswa['nis']) ?></span>
                    </div>
                    <div>
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block mb-0.5">NISN</span>
                        <span class="text-[12px] font-bold text-slate-800 tracking-tight"><?= htmlspecialchars($siswa['nisn'] ?? '-') ?></span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block mb-0.5">Tahun Pelajaran</span>
                        <span class="text-[12px] font-bold text-indigo-600 italic"><?= htmlspecialchars($siswa['tahun_pelajaran']) ?></span>
                    </div>
                </div>
            </div>

            <!-- QR Code Section -->
            <div class="mt-auto bg-slate-50/80 backdrop-blur-sm border-t border-slate-100 px-8 py-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div id="qrcode" class="p-1.5 bg-white border border-slate-200 rounded-xl shadow-sm scale-110"></div>
                        <div class="ml-2">
                            <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Verified by</p>
                            <p class="text-[10px] font-black text-indigo-600 italic tracking-tighter uppercase leading-none">CAKRA SYSTEM</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-[8px] font-black text-white bg-indigo-600 px-3 py-1.5 rounded-full uppercase tracking-widest shadow-lg shadow-indigo-200">Official ID</span>
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
        width: 64,
        height: 64,
        colorDark : "#1e293b",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
