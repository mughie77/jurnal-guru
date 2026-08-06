<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];
$today_date = date('Y-m-d');

// Get student's active class
$query_profile = "SELECT sk.kelas_id, k.nama_kelas
                  FROM siswa_kelas sk
                  JOIN kelas k ON sk.kelas_id = k.id
                  JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id
                  WHERE sk.siswa_id = ? AND tp.status = 'aktif' LIMIT 1";
$stmt = mysqli_prepare($conn, $query_profile);
mysqli_stmt_bind_param($stmt, "i", $siswa_id);
mysqli_stmt_execute($stmt);
$res_p = mysqli_stmt_get_result($stmt);
$profile = mysqli_fetch_assoc($res_p);
$kelas_id = (int)($profile['kelas_id'] ?? 0);
$nama_kelas = $profile['nama_kelas'] ?? 'N/A';
mysqli_stmt_close($stmt);

$page_title = "Tugas Guru Hari Ini";

// Fetch today's tasks for this class
$tasks = [];
if ($kelas_id > 0) {
    $q_tasks = "SELECT tk.*, u.nama_lengkap as nama_guru
                FROM tugas_kelas tk
                JOIN guru g ON tk.guru_id = g.id
                JOIN users u ON g.user_id = u.id
                WHERE tk.kelas_id = $kelas_id AND tk.tanggal = '$today_date'
                ORDER BY tk.created_at DESC";
    $res_t = mysqli_query($conn, $q_tasks);
    while ($row = mysqli_fetch_assoc($res_t)) {
        $tasks[] = $row;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    body { background-color: #F8FAFC; }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 lg:p-8 max-w-full w-full mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight italic">Tugas Guru Hari Ini</h1>
                <p class="text-slate-400 font-bold text-xs uppercase tracking-widest mt-1">Kelas: <?= htmlspecialchars($nama_kelas) ?> • Tanggal: <?= date('d M Y') ?></p>
            </div>
            <a href="index.php" class="w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center rounded-xl sm:rounded-2xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
                <i class="fa fa-arrow-left"></i>
            </a>
        </div>

        <!-- Task List Area -->
        <div class="space-y-6">
            <?php if (empty($tasks)): ?>
                <div class="lux-card p-12 text-center text-slate-400 italic bg-white rounded-3xl border border-dashed border-slate-200">
                    <i class="fa fa-check-circle text-5xl text-emerald-400 mb-4 animate-bounce"></i>
                    <p class="font-bold text-base text-slate-700 not-italic mb-1">Semua Guru Hadir!</p>
                    <p class="text-xs">Tidak ada tugas guru tidak masuk untuk kelas Anda hari ini. Selamat belajar!</p>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $t): ?>
                    <div class="lux-card p-6 sm:p-8 bg-white shadow-xl relative overflow-hidden border-t-4 border-amber-500">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div>
                                <span class="text-[9px] font-black text-amber-600 uppercase tracking-widest bg-amber-50 px-2.5 py-1 rounded-full border border-amber-100">GURU TIDAK MASUK</span>
                                <h3 class="text-lg font-black text-slate-800 italic mt-2">Guru: <?= htmlspecialchars($t['nama_guru']) ?></h3>
                            </div>
                            <div class="shrink-0 text-right">
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Status Tugas</span>
                                <?php if ($t['status_selesai']): ?>
                                    <span class="inline-block mt-1 px-3 py-1 bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase rounded-full">Selesai</span>
                                <?php else: ?>
                                    <span class="inline-block mt-1 px-3 py-1 bg-slate-100 text-slate-400 text-[10px] font-black uppercase rounded-full">Proses</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100/60 leading-relaxed text-slate-700 text-sm italic font-medium mb-6">
                            "<?= htmlspecialchars($t['keterangan_tugas']) ?>"
                        </div>

                        <?php if ($t['file_lampiran']): ?>
                            <div class="flex items-center justify-between p-4 bg-indigo-50/50 rounded-2xl border border-indigo-100/50">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center text-indigo-600 shadow-sm border border-indigo-50"><i class="fa fa-file-download text-lg"></i></div>
                                    <div>
                                        <span class="text-[9px] font-black text-indigo-400 uppercase tracking-widest block">Dokumen Lampiran</span>
                                        <span class="text-xs font-bold text-slate-700 truncate max-w-[200px] block"><?= htmlspecialchars($t['file_lampiran']) ?></span>
                                    </div>
                                </div>
                                <a href="<?= BASE_URL ?>uploads/tugas/<?= $t['file_lampiran'] ?>" target="_blank" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-[10px] uppercase tracking-wider rounded-xl transition-all shadow-md shadow-indigo-100">Unduh</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
