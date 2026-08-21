<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];

// Fetch PKL contact details
$q_pkl = "SELECT sp.*, tp.nama_tempat, tp.alamat, tp.pembimbing_dudi, tp.no_telp_dudi, u.nama_lengkap as nama_guru_pembimbing, u.no_telp as no_telp_guru
          FROM siswa_pkl sp
          JOIN tempat_pkl tp ON sp.tempat_pkl_id = tp.id
          LEFT JOIN guru g ON tp.guru_pembimbing_id = g.id
          LEFT JOIN users u ON g.user_id = u.id
          WHERE sp.siswa_id = $siswa_id AND sp.tahun_pelajaran_id = $active_tahun_id AND sp.status = 'aktif'
          LIMIT 1";
$res_pkl = mysqli_query($conn, $q_pkl);
$pkl_info = ($res_pkl && mysqli_num_rows($res_pkl) > 0) ? mysqli_fetch_assoc($res_pkl) : null;
$is_pkl = ($pkl_info !== null);

$page_title = "Kontak Pembimbing PKL";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-black italic text-slate-800 tracking-tight">Kontak Pembimbing PKL</h1>
                <p class="text-slate-400 font-bold text-xs uppercase tracking-widest mt-1">Direktori Pembimbing Sekolah & DU/DI</p>
            </div>
            <a href="pkl_jurnal.php" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-slate-600 transition-all">
                <i class="fa fa-arrow-left"></i>
            </a>
        </div>

        <?php if (!$is_pkl): ?>
        <div class="lux-card p-12 text-center bg-white rounded-3xl shadow-xl border border-slate-100">
            <p class="text-slate-400 italic">Anda belum terdaftar dalam program PKL aktif.</p>
        </div>
        <?php else: ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Guru Pembimbing Sekolah Card -->
            <div class="lux-card p-6 bg-white shadow-xl rounded-3xl border border-slate-100 flex flex-col justify-between">
                <div>
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl mb-4 shadow-inner">
                        <i class="fa fa-user-tie"></i>
                    </div>
                    <span class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Guru Pembimbing Sekolah</span>
                    <h3 class="text-xl font-black text-slate-800 italic mt-1"><?= htmlspecialchars($pkl_info['nama_guru_pembimbing'] ?? 'Belum Ditentukan') ?></h3>
                    <p class="text-xs text-slate-500 font-medium mt-1">Perwakilan Guru Pendamping dari Sekolah</p>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100">
                    <?php if (!empty($pkl_info['no_telp_guru'])): ?>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $pkl_info['no_telp_guru']) ?>" target="_blank" class="w-full py-3 bg-emerald-600 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-100 hover:bg-emerald-700 transition-all flex items-center justify-center gap-2">
                        <i class="fab fa-whatsapp text-lg"></i> Hubungi Guru Pembimbing
                    </a>
                    <?php else: ?>
                    <button disabled class="w-full py-3 bg-slate-100 text-slate-400 font-bold text-xs rounded-xl cursor-not-allowed">No. Telp Tidak Tersedia</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pembimbing Industri DU/DI Card -->
            <div class="lux-card p-6 bg-white shadow-xl rounded-3xl border border-slate-100 flex flex-col justify-between">
                <div>
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl mb-4 shadow-inner">
                        <i class="fa fa-building"></i>
                    </div>
                    <span class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Pembimbing Industri (DU/DI)</span>
                    <h3 class="text-xl font-black text-slate-800 italic mt-1"><?= htmlspecialchars($pkl_info['pembimbing_dudi'] ?? 'Belum Ditentukan') ?></h3>
                    <p class="text-xs text-indigo-600 font-bold mt-1"><?= htmlspecialchars($pkl_info['nama_tempat']) ?></p>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100">
                    <?php if (!empty($pkl_info['no_telp_dudi'])): ?>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $pkl_info['no_telp_dudi']) ?>" target="_blank" class="w-full py-3 bg-emerald-600 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-100 hover:bg-emerald-700 transition-all flex items-center justify-center gap-2">
                        <i class="fab fa-whatsapp text-lg"></i> Hubungi Pembimbing DU/DI
                    </a>
                    <?php else: ?>
                    <button disabled class="w-full py-3 bg-slate-100 text-slate-400 font-bold text-xs rounded-xl cursor-not-allowed">No. Telp Tidak Tersedia</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
