<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka']);

// Fetch all panic reports
$query = "SELECT pb.*, k.nama_kelas
          FROM panic_button pb
          LEFT JOIN siswa_kelas sk ON pb.siswa_id = sk.siswa_id AND sk.tahun_pelajaran_id = ?
          LEFT JOIN kelas k ON sk.kelas_id = k.id
          ORDER BY pb.tanggal DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $active_tahun_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$reports = [];
while ($row = mysqli_fetch_assoc($result)) {
    $reports[] = $row;
}
mysqli_stmt_close($stmt);

$page_title = "Rekap Laporan Bullying (Panic Button)";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 lg:p-8 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-black italic text-slate-800 tracking-tight text-rose-600">REKAP LAPORAN BULLYING</h1>
            <p class="text-slate-400 font-bold text-xs uppercase tracking-widest mt-1">Layanan Pantauan Keamanan Darurat Anti-Bullying</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="export_panic.php" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl shadow-lg shadow-emerald-100 transition-all flex items-center gap-2 text-xs uppercase tracking-wider">
                <i class="fa fa-file-excel text-sm"></i> Export Excel
            </a>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="mb-8 relative max-w-md">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
            <i class="fa fa-search"></i>
        </div>
        <input type="text" id="searchPanic" onkeyup="filterPanic()" placeholder="Cari nama siswa, kelas, atau keterangan..."
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
                <tbody class="divide-y divide-slate-50" id="panicTableBody">
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-bold italic">Belum ada laporan bullying yang masuk. Aman sejahtera!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $r): ?>
                            <tr class="panic-row hover:bg-rose-50/10 transition-colors"
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
                                    <a href="https://www.google.com/maps/search/?api=1&query=<?= $r['latitude'] ?>,<?= $r['longitude'] ?>" target="_blank"
                                       class="inline-flex items-center gap-1.5 px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-md shadow-rose-100 transition-all hover:scale-105 active:scale-95">
                                        <i class="fa fa-map-marker-alt"></i> Buka Maps
                                    </a>
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
function filterPanic() {
    const query = document.getElementById('searchPanic').value.toLowerCase();
    const rows = document.getElementsByClassName('panic-row');

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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
