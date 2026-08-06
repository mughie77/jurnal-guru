<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['waka', 'admin']);

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
        }
    }
}

// Retrieve Permits for ALL classes in the active school year
$query = "SELECT ah.*, s.nama_siswa, s.nis, s.nisn, k.nama_kelas
          FROM absensi_harian ah
          JOIN siswa s ON ah.siswa_id = s.id
          JOIN siswa_kelas sk ON s.id = sk.siswa_id
          JOIN kelas k ON sk.kelas_id = k.id
          WHERE sk.tahun_pelajaran_id = ? AND ah.status IN ('Izin', 'Sakit')
          ORDER BY ah.tanggal DESC, ah.waktu_masuk DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $active_tahun_id);
mysqli_stmt_execute($stmt);
$permits = mysqli_stmt_get_result($stmt);

$page_title = "Rekap Pengajuan Izin";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 lg:p-8 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-black italic text-slate-800 tracking-tight">REKAP PENGAJUAN IZIN</h1>
            <p class="text-slate-400 font-bold text-xs uppercase tracking-widest mt-1">Daftar Pengajuan Izin & Sakit Seluruh Siswa</p>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="mb-8 relative max-w-md">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
            <i class="fa fa-search"></i>
        </div>
        <input type="text" id="searchPermit" onkeyup="filterPermits()" placeholder="Cari berdasarkan nama, NIS, atau kelas..."
               class="w-full pl-12 pr-4 py-3 rounded-2xl border border-slate-200 bg-white focus:outline-none focus:ring-4 focus:ring-indigo-50 focus:border-indigo-500 font-bold text-slate-700 placeholder:text-slate-300 transition-all shadow-sm">
    </div>

    <!-- List Table -->
    <div class="lux-card overflow-hidden border-none shadow-2xl bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Siswa & Kelas</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Waktu & Tipe</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Keterangan</th>
                        <th class="px-6 py-4 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">Lampiran</th>
                        <th class="px-6 py-4 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">Verifikasi</th>
                        <th class="px-6 py-4 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50" id="permitTableBody">
                    <?php if (mysqli_num_rows($permits) == 0): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400 font-bold italic">Belum ada pengajuan izin/sakit dari siswa.</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($p = mysqli_fetch_assoc($permits)): ?>
                            <tr class="permit-row hover:bg-slate-50/50 transition-colors"
                                data-nama="<?= strtolower(htmlspecialchars($p['nama_siswa'])) ?>"
                                data-nis="<?= strtolower(htmlspecialchars($p['nis'])) ?>"
                                data-kelas="<?= strtolower(htmlspecialchars($p['nama_kelas'])) ?>">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($p['nama_siswa']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-bold">NIS: <?= htmlspecialchars($p['nis']) ?></div>
                                    <div class="inline-block mt-1 px-2 py-0.5 bg-slate-100 border border-slate-200 text-slate-600 rounded text-[9px] font-black uppercase tracking-wider"><?= htmlspecialchars($p['nama_kelas']) ?></div>
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

<script>
function filterPermits() {
    const query = document.getElementById('searchPermit').value.toLowerCase();
    const rows = document.getElementsByClassName('permit-row');

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const nama = row.getAttribute('data-nama');
        const nis = row.getAttribute('data-nis');
        const kelas = row.getAttribute('data-kelas');

        if (nama.includes(query) || nis.includes(query) || kelas.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}
</script>

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
