<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru']);

$wali_info = get_wali_kelas_info();
if (!$wali_info) {
    die("<div class='p-8 text-center'><h1 class='text-xl font-black text-rose-600'>Akses Ditolak</h1><p class='text-slate-500'>Halaman ini hanya dapat diakses oleh Wali Kelas.</p><a href='index.php' class='mt-4 inline-block px-6 py-2 bg-slate-800 text-white font-bold rounded-xl'>Kembali</a></div>");
}

$kelas_id = $wali_info['kelas_id'];
$nama_kelas = $wali_info['nama_kelas'];
$message = '';
$message_type = '';

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Verification Action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_verifikasi'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Token CSRF tidak valid.";
        $message_type = "error";
    } else {
        $permit_id = (int)$_POST['permit_id'];
        $action = $_POST['action_verifikasi']; // 'disetujui' or 'ditolak'

        if (in_array($action, ['disetujui', 'ditolak'])) {
            // Verify that this permit belongs to a student of the Wali Kelas's class
            $chk = mysqli_query($conn, "SELECT ah.id FROM absensi_harian ah
                                        JOIN siswa_kelas sk ON ah.siswa_id = sk.siswa_id
                                        WHERE ah.id = $permit_id AND sk.kelas_id = $kelas_id");
            if (mysqli_num_rows($chk) > 0) {
                $stmt = mysqli_prepare($conn, "UPDATE absensi_harian SET status_verifikasi = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "si", $action, $permit_id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Pengajuan izin berhasil " . ($action == 'disetujui' ? 'disetujui!' : 'ditolak.');
                    $message_type = $action == 'disetujui' ? 'success' : 'info';
                } else {
                    $message = "Gagal memperbarui status verifikasi.";
                    $message_type = "error";
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Pengajuan tidak ditemukan atau bukan kelas Anda.";
                $message_type = "error";
            }
        }
    }
}

// Retrieve Permits for the homeroom class
$query = "SELECT ah.*, s.nama_siswa, s.nis, s.nisn
          FROM absensi_harian ah
          JOIN siswa s ON ah.siswa_id = s.id
          JOIN siswa_kelas sk ON s.id = sk.siswa_id
          WHERE sk.kelas_id = ? AND sk.tahun_pelajaran_id = ? AND ah.status IN ('Izin', 'Sakit')
          ORDER BY ah.tanggal DESC, ah.waktu_masuk DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $kelas_id, $active_tahun_id);
mysqli_stmt_execute($stmt);
$permits = mysqli_stmt_get_result($stmt);

$page_title = "Siswa Izin - " . $nama_kelas;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    body { background-color: #F8FAFC; }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 lg:p-8 max-w-6xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-black italic text-slate-800 tracking-tight">Pengajuan Izin Siswa</h1>
                <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mt-1">Kelas Wali: <?= htmlspecialchars($nama_kelas) ?></p>
            </div>
            <a href="index.php" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-slate-600 transition-all">
                <i class="fa fa-arrow-left"></i>
            </a>
        </div>

        <!-- List -->
        <div class="lux-card overflow-hidden border-none shadow-xl bg-white">
            <div class="p-6 border-b border-slate-50">
                <h3 class="font-black text-slate-800 italic uppercase tracking-widest text-sm">Daftar Pengajuan Izin & Sakit</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Siswa</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Waktu & Tipe</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Keterangan</th>
                            <th class="px-6 py-4 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">Lampiran</th>
                            <th class="px-6 py-4 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">Verifikasi</th>
                            <th class="px-6 py-4 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (mysqli_num_rows($permits) == 0): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-slate-400 font-bold italic">Belum ada pengajuan izin/sakit dari siswa.</td>
                            </tr>
                        <?php else: ?>
                            <?php while ($p = mysqli_fetch_assoc($permits)): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($p['nama_siswa']) ?></div>
                                        <div class="text-[10px] text-slate-400 font-bold">NIS: <?= htmlspecialchars($p['nis']) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-xs font-bold text-slate-600"><?= date('d M Y', strtotime($p['tanggal'])) ?></div>
                                        <div class="text-[10px] font-bold text-slate-400"><?= $p['waktu_masuk'] ?> WIB</div>
                                        <div class="mt-1">
                                            <?php if ($p['status'] == 'Sakit'): ?>
                                                <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-100">Sakit</span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-600 border border-indigo-100">Izin</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 max-w-xs">
                                        <div class="text-xs text-slate-700 italic leading-relaxed line-clamp-2"><?= htmlspecialchars($p['keterangan']) ?></div>
                                        <?php
                                        // Try to parse GPS coordinates from keterangan
                                        if (preg_match('/GPS:\s*(-?\d+\.\d+),\s*(-?\d+\.\d+)/', $p['keterangan'], $coords)):
                                            $lat = $coords[1];
                                            $lng = $coords[2];
                                        ?>
                                            <a href="https://www.google.com/maps/search/?api=1&query=<?= $lat ?>,<?= $lng ?>" target="_blank"
                                               class="inline-flex items-center gap-1 text-[9px] text-indigo-600 hover:text-indigo-800 font-black uppercase tracking-wider mt-1.5 bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100 transition-colors">
                                                <i class="fa fa-map-marker-alt"></i> Google Maps
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if (!empty($p['file_surat'])): ?>
                                            <a href="<?= BASE_URL ?>uploads/surat/<?= $p['file_surat'] ?>" target="_blank"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-800 text-[10px] font-black uppercase tracking-widest rounded-xl transition-colors">
                                                <i class="fa fa-file-invoice"></i> Lihat Surat
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-300 text-xs italic">Tidak ada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($p['status_verifikasi'] == 'pending'): ?>
                                            <span class="inline-block px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest bg-yellow-50 text-yellow-600 border border-yellow-100 animate-pulse">Pending</span>
                                        <?php elseif ($p['status_verifikasi'] == 'disetujui'): ?>
                                            <span class="inline-block px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest bg-emerald-50 text-emerald-600 border border-emerald-100">Disetujui</span>
                                        <?php else: ?>
                                            <span class="inline-block px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest bg-rose-50 text-rose-600 border border-rose-100">Ditolak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($p['status_verifikasi'] == 'pending'): ?>
                                            <div class="flex items-center justify-center gap-2">
                                                <form action="" method="POST" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="permit_id" value="<?= $p['id'] ?>">
                                                    <input type="hidden" name="action_verifikasi" value="disetujui">
                                                    <button type="submit" class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white text-[10px] font-black uppercase tracking-widest rounded-lg shadow-sm transition-all hover:scale-105 active:scale-95">Setujui</button>
                                                </form>
                                                <form action="" method="POST" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="permit_id" value="<?= $p['id'] ?>">
                                                    <input type="hidden" name="action_verifikasi" value="ditolak">
                                                    <button type="submit" class="px-3 py-1.5 bg-rose-500 hover:bg-rose-600 text-white text-[10px] font-black uppercase tracking-widest rounded-lg shadow-sm transition-all hover:scale-105 active:scale-95">Tolak</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex items-center justify-center gap-2">
                                                <form action="" method="POST" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="permit_id" value="<?= $p['id'] ?>">
                                                    <input type="hidden" name="action_verifikasi" value="<?= $p['status_verifikasi'] == 'disetujui' ? 'ditolak' : 'disetujui' ?>">
                                                    <button type="submit" class="text-[10px] font-black text-slate-400 hover:text-slate-600 uppercase tracking-widest underline transition-colors">
                                                        Ubah ke <?= $p['status_verifikasi'] == 'disetujui' ? 'Tolak' : 'Setujui' ?>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php if (!empty($message)): ?>
    Swal.fire({
        icon: '<?= $message_type ?>',
        title: '<?= $message_type == "success" ? "Berhasil" : ($message_type == "error" ? "Gagal" : "Info") ?>',
        text: '<?= $message ?>',
        confirmButtonColor: '#4f46e5',
        customClass: { popup: 'rounded-3xl', title: 'font-black italic' }
    });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
