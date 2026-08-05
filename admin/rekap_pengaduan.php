<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Helper check for Guru BK or Wali Kelas or Admin/Waka
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$is_allowed = false;
if (in_array($user_role, ['admin', 'waka'])) {
    $is_allowed = true;
} elseif ($user_role === 'guru') {
    // Check if Wali Kelas
    $wali_info = get_wali_kelas_info();
    if ($wali_info !== null) {
        $is_allowed = true;
    } else {
        // Check if Guru BK
        $q_guru = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = " . (int)$user_id);
        if ($g_data = mysqli_fetch_assoc($q_guru)) {
            $guru_id = (int)$g_data['id'];
            $q_bk = mysqli_query($conn, "SELECT COUNT(*) as count FROM guru_mapel gm
                                         JOIN mata_pelajaran mp ON gm.mapel_id = mp.id
                                         WHERE gm.guru_id = $guru_id AND (mp.nama_mapel LIKE '%Bimbingan Konseling%' OR mp.nama_mapel LIKE '%BK%')");
            $bk_count = mysqli_fetch_assoc($q_bk)['count'] ?? 0;
            if ($bk_count > 0) {
                $is_allowed = true;
            }
        }
    }
}

if (!$is_allowed) {
    header('Location: ' . BASE_URL . 'logout.php');
    exit();
}

// Fetch all pengaduan reports
$query = "SELECT p.*, k.nama_kelas
          FROM pengaduan p
          LEFT JOIN siswa_kelas sk ON p.siswa_id = sk.siswa_id AND sk.tahun_pelajaran_id = ?
          LEFT JOIN kelas k ON sk.kelas_id = k.id
          ORDER BY p.tanggal DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $active_tahun_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$reports = [];
while ($row = mysqli_fetch_assoc($result)) {
    $reports[] = $row;
}
mysqli_stmt_close($stmt);

$page_title = "Rekap Laporan Pengaduan Siswa";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 lg:p-8 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-black italic text-slate-800 tracking-tight text-rose-600">REKAP LAPORAN PENGADUAN SISWA</h1>
            <p class="text-slate-400 font-bold text-xs uppercase tracking-widest mt-1">Layanan Pantauan Keamanan & Laporan Pengaduan Siswa</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="export_pengaduan.php" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl shadow-lg shadow-emerald-100 transition-all flex items-center gap-2 text-xs uppercase tracking-wider">
                <i class="fa fa-file-excel text-sm"></i> Export Excel
            </a>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="mb-8 relative max-w-md">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
            <i class="fa fa-search"></i>
        </div>
        <input type="text" id="searchPengaduan" onkeyup="filterPengaduan()" placeholder="Cari nama siswa, kelas, atau keterangan..."
               class="w-full pl-12 pr-4 py-3 rounded-2xl border border-slate-200 bg-white focus:outline-none focus:ring-4 focus:ring-rose-50 focus:border-rose-500 font-bold text-slate-700 placeholder:text-slate-300 transition-all shadow-sm">
    </div>

    <!-- Table -->
    <div class="lux-card overflow-hidden border-none shadow-2xl bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Pelapor & Kelas</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Waktu Kejadian</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Kronologi / Keterangan</th>
                        <th class="px-6 py-4 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">Lokasi GPS & Akurasi</th>
                        <th class="px-6 py-4 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50" id="pengaduanTableBody">
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-bold italic">Belum ada laporan pengaduan yang masuk. Aman sejahtera!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $r): ?>
                            <tr class="pengaduan-row hover:bg-rose-50/10 transition-colors"
                                data-nama="<?= strtolower(htmlspecialchars($r['nama_siswa'])) ?>"
                                data-kelas="<?= strtolower(htmlspecialchars($r['nama_kelas'] ?? 'Tanpa Kelas')) ?>"
                                data-keterangan="<?= strtolower(htmlspecialchars($r['keterangan'])) ?>">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($r['nama_siswa']) ?></div>
                                    <div class="inline-block mt-1 px-2.5 py-0.5 bg-slate-100 border border-slate-200 text-slate-600 rounded text-[9px] font-black uppercase tracking-wider"><?= htmlspecialchars($r['nama_kelas'] ?? 'Tanpa Kelas') ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs font-bold text-slate-600"><?= date('d M Y', strtotime($r['tanggal'])) ?></div>
                                    <div class="text-[10px] font-black text-slate-400"><?= date('H:i', strtotime($r['tanggal'])) ?> WIB</div>
                                </td>
                                <td class="px-6 py-4 max-w-sm">
                                    <div class="text-xs text-rose-950 font-medium italic bg-rose-50/50 p-3 rounded-xl border border-rose-100/40 leading-relaxed">
                                        "<?= htmlspecialchars($r['keterangan']) ?>"
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="text-xs font-bold text-slate-700"><?= $r['latitude'] ?>, <?= $r['longitude'] ?></div>
                                    <div class="text-[9px] font-black text-rose-600 uppercase tracking-widest mt-1">
                                        <i class="fa fa-crosshairs mr-1"></i> Akurasi: ±<?= round($r['akurasi']) ?> meter
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button onclick="showPengaduanDetail('<?= addslashes(htmlspecialchars($r['nama_siswa'])) ?>', '<?= addslashes(htmlspecialchars($r['nama_kelas'] ?? 'Tanpa Kelas')) ?>', '<?= date('d M Y, H:i', strtotime($r['tanggal'])) ?>', '<?= addslashes(htmlspecialchars($r['keterangan'])) ?>', '<?= $r['latitude'] ?>', '<?= $r['longitude'] ?>', '<?= round($r['akurasi']) ?>')"
                                                class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all hover:scale-105 active:scale-95">
                                            <i class="fa fa-eye"></i> Detail
                                        </button>
                                        <a href="https://www.google.com/maps/search/?api=1&query=<?= $r['latitude'] ?>,<?= $r['longitude'] ?>" target="_blank"
                                           class="inline-flex items-center gap-1.5 px-3 py-2 bg-rose-600 hover:bg-rose-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-md shadow-rose-100 transition-all hover:scale-105 active:scale-95">
                                            <i class="fa fa-map-marker-alt"></i> Maps
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function filterPengaduan() {
    const query = document.getElementById('searchPengaduan').value.toLowerCase();
    const rows = document.getElementsByClassName('pengaduan-row');

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const nama = row.getAttribute('data-nama');
        const kelas = row.getAttribute('data-kelas');
        const keterangan = row.getAttribute('data-keterangan');

        if (nama.includes(query) || kelas.includes(query) || keterangan.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}

function showPengaduanDetail(nama, kelas, tanggal, keterangan, lat, lng, akurasi) {
    const detailHtml = `
        <div class="text-left space-y-4 max-h-[70vh] overflow-y-auto pr-1">
            <div class="grid grid-cols-2 gap-3 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <div>
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block">Pelapor / Siswa</span>
                    <span class="text-xs font-bold text-slate-800">${nama}</span>
                    <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase bg-slate-200 text-slate-700 block mt-1 w-max">${kelas}</span>
                </div>
                <div>
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block">Waktu Kejadian</span>
                    <span class="text-xs font-bold text-slate-800">${tanggal} WIB</span>
                </div>
            </div>
            <div class="p-4 bg-rose-50 rounded-2xl border border-rose-100 space-y-1">
                <span class="text-[8px] font-black text-rose-500 uppercase tracking-widest block">Kronologi / Laporan</span>
                <p class="text-xs text-rose-950 font-semibold italic whitespace-pre-wrap leading-relaxed">"${keterangan}"</p>
            </div>
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block mb-1">Koordinat GPS & Akurasi</span>
                <p class="text-xs font-mono text-slate-700">Latitude: ${lat}<br>Longitude: ${lng}</p>
                <div class="text-[10px] font-black text-rose-600 uppercase tracking-widest mt-2">
                    <i class="fa fa-crosshairs mr-1"></i> Akurasi GPS: ±${akurasi} meter
                </div>
                <div class="pt-3">
                    <a href="https://www.google.com/maps/search/?api=1&query=${lat},${lng}" target="_blank"
                       class="inline-flex items-center gap-1.5 px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-[9px] font-black uppercase tracking-widest rounded-xl shadow-md shadow-rose-100 transition-all hover:scale-105">
                        <i class="fa fa-map-marker-alt"></i> Buka Lokasi di Google Maps
                    </a>
                </div>
            </div>
        </div>
    `;

    Swal.fire({
        title: '<span class="font-black italic text-rose-600 text-lg uppercase tracking-wider">Detail Laporan Pengaduan</span>',
        html: detailHtml,
        showCloseButton: true,
        confirmButtonText: 'Tutup',
        confirmButtonColor: '#E11D48',
        width: '500px'
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
