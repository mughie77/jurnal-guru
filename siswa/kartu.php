<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];

// Get Student Profile
$query = "SELECT s.*, k.nama_kelas, tp.tahun_pelajaran
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

$page_title = "Kartu Pelajar";
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
        width: 340px;
        height: 520px;
        background: white;
        border-radius: 30px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    .card-header-bg {
        height: 140px;
        background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);
        border-radius: 0 0 50% 50% / 0 0 20% 20%;
    }

    .photo-frame {
        width: 130px;
        height: 160px;
        background: #f1f5f9;
        border: 4px solid white;
        border-radius: 20px;
        position: absolute;
        top: 60px;
        left: 50%;
        transform: translateX(-50%);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>

<div class="bg-slate-50 min-h-screen pb-24 flex flex-col items-center p-6">
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-black italic text-slate-800 uppercase tracking-widest">Kartu Pelajar Digital</h1>
        <p class="text-slate-400 font-bold text-xs uppercase tracking-[0.3em] mt-1">Sistem Informasi Akademik CAKRA</p>
    </div>

    <div class="card-id-wrapper animate-in zoom-in duration-500">
        <div class="card-id flex flex-col items-center">
            <!-- Background & Logo -->
            <div class="card-header-bg w-full flex flex-col items-center pt-4">
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-8 h-8 rounded-lg bg-white/20 backdrop-blur-md flex items-center justify-center border border-white/30">
                        <i class="fa fa-graduation-cap text-white text-sm"></i>
                    </div>
                    <span class="text-lg font-black text-white italic tracking-tighter uppercase">CAKRA</span>
                </div>
                <p class="text-[8px] font-black text-white/70 uppercase tracking-widest text-center px-8">Central Academic Knowledge & Record Application</p>
            </div>

            <!-- Photo -->
            <div class="photo-frame overflow-hidden">
                <i class="fa fa-user text-6xl text-slate-300"></i>
            </div>

            <!-- Info Section -->
            <div class="mt-24 w-full px-8 text-center">
                <h2 class="text-xl font-black text-slate-800 uppercase tracking-tighter italic leading-tight mb-1"><?= htmlspecialchars($siswa['nama_siswa']) ?></h2>
                <p class="text-indigo-600 font-black text-sm tracking-[0.2em] mb-6 uppercase"><?= htmlspecialchars($siswa['nama_kelas']) ?></p>

                <div class="space-y-4 text-left">
                    <div class="flex flex-col">
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">NIS / NISN</span>
                        <span class="text-sm font-bold text-slate-700"><?= htmlspecialchars($siswa['nis']) ?> / <?= htmlspecialchars($siswa['nisn'] ?? '-') ?></span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Sekolah</span>
                        <span class="text-sm font-bold text-slate-700 truncate"><?= htmlspecialchars($sets['nama_sekolah'] ?? 'Sekolah Menengah Kejuruan') ?></span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Tahun Pelajaran</span>
                        <span class="text-sm font-bold text-slate-700 italic"><?= htmlspecialchars($siswa['tahun_pelajaran']) ?></span>
                    </div>
                </div>
            </div>

            <!-- QR Code Placeholder -->
            <div class="mt-8 flex flex-col items-center">
                <div class="w-16 h-16 bg-slate-50 rounded-xl flex items-center justify-center border border-slate-100 p-2 grayscale">
                    <i class="fa fa-qrcode text-4xl text-slate-300"></i>
                </div>
                <p class="text-[6px] font-black text-slate-300 uppercase tracking-[0.3em] mt-2 italic">Official Academic ID</p>
            </div>

            <div class="absolute bottom-4 left-0 right-0 text-center">
                <p class="text-[8px] font-black text-indigo-100 bg-indigo-600 inline-block px-4 py-1 rounded-full uppercase tracking-[0.2em]">Verifikasi CAKRA</p>
            </div>
        </div>
    </div>

    <div class="mt-12 max-w-xs text-center">
        <p class="text-[10px] text-slate-400 font-bold italic leading-relaxed">
            "Kartu ini adalah tanda pengenal sah di lingkungan <?= htmlspecialchars($sets['nama_sekolah'] ?? 'Sekolah') ?>. Tunjukkan kartu ini saat melakukan absensi harian."
        </p>
        <button onclick="window.print()" class="mt-6 px-6 py-3 bg-white text-indigo-600 font-black text-xs uppercase tracking-widest rounded-xl shadow-sm border border-slate-200 hover:bg-slate-50 transition-all flex items-center justify-center mx-auto">
            <i class="fa fa-print mr-2"></i> Cetak Kartu
        </button>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
