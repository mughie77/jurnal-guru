<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['waka', 'admin']);
$page_title = "Laporan Jurnal Mengajar";

// Filter Logic
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$guru_id = (int)($_GET['guru_id'] ?? 0);
$kelas_id = (int)($_GET['kelas_id'] ?? 0);
$mapel_id = (int)($_GET['mapel_id'] ?? 0);
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_clauses = [];
if (!empty($start_date)) $where_clauses[] = "jurnal.tanggal >= '$start_date'";
if (!empty($end_date)) $where_clauses[] = "jurnal.tanggal <= '$end_date'";
if ($guru_id > 0) $where_clauses[] = "jurnal.guru_id = $guru_id";
if ($kelas_id > 0) $where_clauses[] = "jurnal.kelas_id = $kelas_id";
if ($mapel_id > 0) $where_clauses[] = "jurnal.mapel_id = $mapel_id";
if (!empty($search)) $where_clauses[] = "(jurnal.materi LIKE '%$search%' OR users.nama_lengkap LIKE '%$search%')";

$sql = "SELECT jurnal.*, users.nama_lengkap, mata_pelajaran.nama_mapel, kelas.nama_kelas, tahun_pelajaran.tahun
        FROM jurnal JOIN guru ON jurnal.guru_id = guru.id
        JOIN users ON guru.user_id = users.id
        JOIN mata_pelajaran ON jurnal.mapel_id = mata_pelajaran.id
        JOIN kelas ON jurnal.kelas_id = kelas.id
        JOIN tahun_pelajaran ON jurnal.tahun_pelajaran_id = tahun_pelajaran.id";

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(' AND ', $where_clauses);
}

$pagin = get_pagination_data($conn, "jurnal JOIN guru ON jurnal.guru_id = guru.id JOIN users ON guru.user_id = users.id", 15, $where_sql);

$sql .= $where_sql;
$sql .= " ORDER BY jurnal.tanggal DESC, jurnal.created_at DESC LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";

$guru_list = mysqli_query($conn, "SELECT g.id, u.nama_lengkap FROM guru g JOIN users u ON g.user_id = u.id ORDER BY u.nama_lengkap ASC");
$kelas_list = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
$mapel_list = mysqli_query($conn, "SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel ASC");
$result = mysqli_query($conn, $sql);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Laporan Jurnal</h1>
        <p class="text-slate-500">Tinjau aktivitas mengajar guru di seluruh unit.</p>
    </div>
    <div class="flex gap-3">
        <a href="export_excel.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-emerald-100 transition-all flex items-center">
            <i class="fa fa-file-excel mr-2"></i> Unduh Excel
        </a>
        <a href="export_pdf.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" target="_blank" class="bg-rose-600 hover:bg-rose-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-rose-100 transition-all flex items-center">
            <i class="fa fa-file-pdf mr-2"></i> Unduh PDF
        </a>
    </div>
</div>

<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cari Materi/Guru</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Materi atau Guru..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm">
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Pilih Guru</label>
            <select name="guru_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700">
                <option value="">-- Semua Guru --</option>
                <?php mysqli_data_seek($guru_list, 0); while($g = mysqli_fetch_assoc($guru_list)): ?>
                    <option value="<?= $g['id'] ?>" <?= $g['id'] == $guru_id ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_lengkap']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Pilih Kelas</label>
            <select name="kelas_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700">
                <option value="">-- Semua Kelas --</option>
                <?php mysqli_data_seek($kelas_list, 0); while($k = mysqli_fetch_assoc($kelas_list)): ?>
                    <option value="<?= $k['id'] ?>" <?= $k['id'] == $kelas_id ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Pilih Mapel</label>
            <select name="mapel_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700">
                <option value="">-- Semua Mapel --</option>
                <?php mysqli_data_seek($mapel_list, 0); while($m = mysqli_fetch_assoc($mapel_list)): ?>
                    <option value="<?= $m['id'] ?>" <?= $m['id'] == $mapel_id ? 'selected' : '' ?>><?= htmlspecialchars($m['nama_mapel']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Dari Tanggal</label>
            <input type="date" name="start_date" value="<?= $start_date ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm">
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Sampai Tanggal</label>
            <input type="date" name="end_date" value="<?= $end_date ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm">
        </div>
        <div class="lg:col-span-2 flex items-end gap-3">
            <button type="submit" class="flex-1 px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100">Terapkan Filter</button>
            <a href="jurnal.php" class="px-6 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-300 transition-all">Reset</a>
        </div>
    </form>
</div>

<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Tanggal</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Guru & Mapel</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Kelas</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Materi</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Absensi</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if(mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-slate-700"><?= date('d M Y', strtotime($row['tanggal'])) ?></span>
                                <?php if (!empty($row['latitude']) && !empty($row['longitude'])): ?>
                                    <a href="https://www.google.com/maps?q=<?= $row['latitude'] ?>,<?= $row['longitude'] ?>" target="_blank" class="text-rose-500 hover:text-rose-700 transition-colors" title="Lokasi Pengisian: <?= $row['latitude'] ?>, <?= $row['longitude'] ?>">
                                        <i class="fa fa-map-marker-alt"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="text-[10px] text-slate-400 font-bold" title="Waktu Submit Jurnal">Submit: <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?> WIB</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-800 text-sm leading-tight mb-0.5"><?= htmlspecialchars($row['nama_lengkap']) ?></div>
                            <div class="text-[10px] text-indigo-500 font-black uppercase tracking-tighter"><?= htmlspecialchars($row['nama_mapel']) ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 rounded bg-slate-100 text-slate-600 text-[10px] font-black uppercase border border-slate-200"><?= htmlspecialchars($row['nama_kelas']) ?></span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600 max-w-xs truncate" title="<?= htmlspecialchars($row['materi']) ?>"><?= htmlspecialchars($row['materi']) ?></td>
                        <td class="px-6 py-4">
                            <div class="flex justify-center gap-1">
                                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 text-[10px] font-black border border-emerald-100"><?= $row['jml_hadir'] ?></span>
                                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-amber-50 text-amber-600 text-[10px] font-black border border-amber-100"><?= $row['jml_sakit'] ?></span>
                                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 text-[10px] font-black border border-blue-100"><?= $row['jml_izin'] ?></span>
                                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-rose-50 text-rose-600 text-[10px] font-black border border-rose-100"><?= $row['jml_alfa'] ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button onclick="showJurnalDetail(<?= $row['id'] ?>)" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all shadow-sm border border-indigo-100" title="Lihat Detail Jurnal">
                                <i class="fa fa-eye text-xs"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="px-6 py-20 text-center text-slate-400 font-medium italic">Tidak ada data laporan jurnal.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>

<script>
function showJurnalDetail(jurnalId) {
    Swal.fire({
        title: 'Memuat Detail...',
        text: 'Mengambil data absensi siswa dan jurnal mengajar.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('../admin/get_jurnal_detail.php?id=' + jurnalId)
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (!data.success) {
            Swal.fire('Gagal', data.message, 'error');
            return;
        }

        const j = data.jurnal;
        let studentsHtml = '';

        if (data.students && data.students.length > 0) {
            data.students.forEach(s => {
                let badgeClass = 'bg-emerald-100 text-emerald-800';
                let statusLabel = 'Hadir';
                if (s.status === 'S') { badgeClass = 'bg-amber-100 text-amber-800'; statusLabel = 'Sakit'; }
                if (s.status === 'I') { badgeClass = 'bg-blue-100 text-blue-800'; statusLabel = 'Izin'; }
                if (s.status === 'A') { badgeClass = 'bg-rose-100 text-rose-800'; statusLabel = 'Alfa'; }

                studentsHtml += `
                    <tr class="border-b border-slate-100 text-left">
                        <td class="px-4 py-2 text-xs font-mono text-slate-500">${s.nis}</td>
                        <td class="px-4 py-2 text-xs font-bold text-slate-700">${s.nama_siswa}</td>
                        <td class="px-4 py-2 text-xs text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase ${badgeClass}">${statusLabel}</span>
                        </td>
                    </tr>
                `;
            });
        } else {
            studentsHtml = `<tr><td colspan="3" class="px-4 py-6 text-center text-xs text-slate-400 italic">Tidak ada siswa yang diabsen.</td></tr>`;
        }

        const detailHtml = `
            <div class="text-left space-y-4 max-h-[70vh] overflow-y-auto pr-1">
                <!-- Meta Grid -->
                <div class="grid grid-cols-2 gap-3 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <div>
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block">Guru Pengajar</span>
                        <span class="text-xs font-bold text-slate-800">${j.nama_lengkap}</span>
                    </div>
                    <div>
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block">Mata Pelajaran</span>
                        <span class="text-xs font-bold text-slate-800">${j.nama_mapel}</span>
                    </div>
                    <div>
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block">Kelas / Jam</span>
                        <span class="text-xs font-bold text-indigo-600">${j.nama_kelas} (Jam ke-${j.jam_ke})</span>
                    </div>
                    <div>
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block">Tanggal</span>
                        <span class="text-xs font-bold text-slate-800">${j.tanggal}</span>
                    </div>
                </div>

                <!-- Jurnal Content -->
                <div class="p-4 bg-indigo-50/50 rounded-2xl border border-indigo-100/50 space-y-2">
                    <div>
                        <span class="text-[8px] font-black text-indigo-500 uppercase tracking-widest block">Materi Pembahasan</span>
                        <p class="text-xs text-slate-700 font-semibold italic whitespace-pre-wrap leading-relaxed">${j.materi}</p>
                    </div>
                    <div>
                        <span class="text-[8px] font-black text-indigo-400 uppercase tracking-widest block mt-2">Catatan Tambahan</span>
                        <p class="text-xs text-slate-500">${j.keterangan}</p>
                    </div>
                    ${j.latitude ? `
                    <div class="pt-2 flex items-center gap-1.5">
                        <i class="fa fa-map-marker-alt text-rose-500 text-xs"></i>
                        <a href="https://www.google.com/maps?q=${j.latitude},${j.longitude}" target="_blank" class="text-[10px] font-black text-indigo-600 hover:underline">LIHAT LOKASI SUBMIT DI MAP</a>
                    </div>
                    ` : ''}
                </div>

                <!-- Student List -->
                <div>
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Daftar Kehadiran Siswa</span>
                    <div class="border border-slate-100 rounded-xl overflow-hidden shadow-sm">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-100 text-left">
                                    <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-wider">NIS</th>
                                    <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-wider">Siswa</th>
                                    <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-wider text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${studentsHtml}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;

        Swal.fire({
            title: '<span class="font-black italic text-slate-800 text-lg uppercase tracking-wider">Detail Jurnal Mengajar</span>',
            html: detailHtml,
            showCloseButton: true,
            confirmButtonText: 'Tutup',
            confirmButtonColor: '#4F46E5',
            width: '600px'
        });
    })
    .catch(err => {
        Swal.close();
        Swal.fire('Error', 'Gagal memuat detail jurnal: ' + err.message, 'error');
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
