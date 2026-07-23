<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = (int)$_SESSION['user_id'];

// 1. Fetch student's Wali Kelas
$query_wali = "SELECT g.id, u.nama_lengkap, g.foto, g.no_telp, k.nama_kelas
               FROM siswa_kelas sk
               JOIN kelas k ON sk.kelas_id = k.id
               JOIN guru g ON k.wali_kelas_id = g.id
               JOIN users u ON g.user_id = u.id
               WHERE sk.siswa_id = ? AND sk.tahun_pelajaran_id = ?
               LIMIT 1";
$stmt_wali = mysqli_prepare($conn, $query_wali);
mysqli_stmt_bind_param($stmt_wali, "ii", $siswa_id, $active_tahun_id);
mysqli_stmt_execute($stmt_wali);
$wali_kelas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_wali));
mysqli_stmt_close($stmt_wali);

// 2. Fetch BK Teachers
$query_bk = "SELECT g.id, u.nama_lengkap, g.foto, g.no_telp, GROUP_CONCAT(mp.nama_mapel SEPARATOR ', ') as mapel
             FROM guru g
             JOIN users u ON g.user_id = u.id
             JOIN guru_mapel gm ON g.id = gm.guru_id
             JOIN mata_pelajaran mp ON gm.mapel_id = mp.id
             WHERE mp.nama_mapel LIKE '%Bimbingan Konseling%' OR mp.nama_mapel LIKE '%BK%'
             GROUP BY g.id
             ORDER BY u.nama_lengkap ASC";
$bk_result = mysqli_query($conn, $query_bk);
$bk_teachers = [];
while ($row = mysqli_fetch_assoc($bk_result)) {
    $bk_teachers[] = $row;
}

$page_title = "Kontak Guru & BK";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    body { background-color: #F8FAFC; }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 lg:p-8 max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-black italic text-slate-800 tracking-tight text-teal-600">KONTAK BK & WALI</h1>
                <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mt-1">Direktori Kontak Wali Kelas dan Bimbingan Konseling</p>
            </div>
            <a href="index.php" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-slate-600 transition-all">
                <i class="fa fa-times"></i>
            </a>
        </div>

        <!-- Section 1: Wali Kelas -->
        <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center">
            <i class="fa fa-user-tie mr-2 text-teal-500"></i> Wali Kelas Anda
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <?php if (!$wali_kelas): ?>
                <div class="lux-card p-6 bg-white border-none shadow-xl col-span-2 text-center text-slate-400 font-bold italic text-sm">
                    Belum ada wali kelas yang diatur untuk kelas Anda.
                </div>
            <?php else: ?>
                <div class="lux-card p-6 bg-white border-none shadow-xl flex items-center gap-6 relative overflow-hidden group hover:border-teal-500 transition-all">
                    <div class="w-20 h-20 rounded-2xl bg-slate-100 flex items-center justify-center overflow-hidden shrink-0 shadow-inner">
                        <?php if (!empty($wali_kelas['foto'])): ?>
                            <img src="<?= BASE_URL ?>uploads/guru/<?= $wali_kelas['foto'] ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fa fa-user-tie text-slate-400 text-3xl"></i>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h4 class="font-black text-slate-800 text-base italic truncate"><?= htmlspecialchars($wali_kelas['nama_lengkap']) ?></h4>
                        <p class="text-[10px] font-black text-teal-500 uppercase tracking-widest mt-1">Wali Kelas: <?= htmlspecialchars($wali_kelas['nama_kelas']) ?></p>
                        <p class="text-slate-500 text-xs mt-2 font-bold flex items-center gap-1.5">
                            <i class="fa fa-phone text-slate-400"></i> <?= htmlspecialchars($wali_kelas['no_telp'] ?? 'Tidak ada nomor') ?>
                        </p>
                    </div>

                    <?php if (!empty($wali_kelas['no_telp'])): ?>
                        <?php $wa_wali = preg_replace('/[^0-9]/', '', $wali_kelas['no_telp']); ?>
                        <a href="https://wa.me/<?= $wa_wali ?>" target="_blank"
                           class="w-12 h-12 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white flex items-center justify-center absolute right-6 top-1/2 -translate-y-1/2 shadow-lg shadow-emerald-100 transition-all hover:scale-110 active:scale-95">
                            <i class="fab fa-whatsapp text-xl"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Section 2: Guru BK -->
        <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center">
            <i class="fa fa-comments mr-2 text-indigo-500"></i> Guru Bimbingan Konseling (BK)
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php if (empty($bk_teachers)): ?>
                <div class="lux-card p-6 bg-white border-none shadow-xl col-span-2 text-center text-slate-400 font-bold italic text-sm">
                    Belum ada guru Bimbingan Konseling (BK) yang terdaftar.
                </div>
            <?php else: ?>
                <?php foreach ($bk_teachers as $g): ?>
                    <div class="lux-card p-6 bg-white border-none shadow-xl flex items-center gap-6 relative overflow-hidden group hover:border-indigo-500 transition-all">
                        <div class="w-20 h-20 rounded-2xl bg-slate-100 flex items-center justify-center overflow-hidden shrink-0 shadow-inner">
                            <?php if (!empty($g['foto'])): ?>
                                <img src="<?= BASE_URL ?>uploads/guru/<?= $g['foto'] ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fa fa-user text-slate-400 text-3xl"></i>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4 class="font-black text-slate-800 text-base italic truncate"><?= htmlspecialchars($g['nama_lengkap']) ?></h4>
                            <p class="text-[10px] font-black text-indigo-500 uppercase tracking-widest mt-1 truncate" title="<?= htmlspecialchars($g['mapel']) ?>"><?= htmlspecialchars($g['mapel']) ?></p>
                            <p class="text-slate-500 text-xs mt-2 font-bold flex items-center gap-1.5">
                                <i class="fa fa-phone text-slate-400"></i> <?= htmlspecialchars($g['no_telp'] ?? 'Tidak ada nomor') ?>
                            </p>
                        </div>

                        <div class="flex flex-col gap-2 absolute right-6 top-1/2 -translate-y-1/2">
                            <?php if (!empty($g['no_telp'])): ?>
                                <?php $wa_bk = preg_replace('/[^0-9]/', '', $g['no_telp']); ?>
                                <a href="https://wa.me/<?= $wa_bk ?>" target="_blank"
                                   class="w-10 h-10 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white flex items-center justify-center shadow-lg shadow-emerald-100 transition-all hover:scale-110 active:scale-95"
                                   title="Chat via WhatsApp">
                                    <i class="fab fa-whatsapp text-lg"></i>
                                </a>
                            <?php endif; ?>
                            <a href="konsultasi.php?id=new&guru_id=<?= $g['id'] ?>"
                               class="w-10 h-10 rounded-full bg-indigo-500 hover:bg-indigo-600 text-white flex items-center justify-center shadow-lg shadow-indigo-100 transition-all hover:scale-110 active:scale-95"
                               title="Konsultasi Aplikasi">
                                <i class="fa fa-comments text-base"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
