<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka']);

$page_title = "Status Pengisian Jurnal Guru";

// Filters
$tgl_raw = $_GET['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_raw)) {
    $tgl_raw = date('Y-m-d');
}
$tgl = mysqli_real_escape_string($conn, $tgl_raw);
$kelas_id = (int)($_GET['kelas_id'] ?? 0);
$status_filter = $_GET['status'] ?? '';

// Indonesian Day Name
$day_eng = date('l', strtotime($tgl));
$day_map = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];
$hari_ini = $day_map[$day_eng] ?? 'Senin';

// Fetch all classes for dropdown
$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");

// Build query
$where_kelas = ($kelas_id > 0) ? " AND jp.kelas_id = $kelas_id " : "";

$query = "SELECT jp.*, k.nama_kelas, mp.nama_mapel, mp.kode_mapel, u.nama_lengkap as nama_guru,
                 j.id as jurnal_id, j.created_at as waktu_isi, j.materi
          FROM jadwal_pelajaran jp
          JOIN kelas k ON jp.kelas_id = k.id
          JOIN mata_pelajaran mp ON jp.mapel_id = mp.id
          JOIN guru g ON jp.guru_id = g.id
          JOIN users u ON g.user_id = u.id
          LEFT JOIN jurnal j ON ((j.jadwal_id = jp.id AND j.tanggal = '$tgl') OR (j.guru_id = jp.guru_id AND j.kelas_id = jp.kelas_id AND j.mapel_id = jp.mapel_id AND j.jam_ke = jp.jam_ke AND j.tanggal = '$tgl'))
          WHERE jp.hari = '$hari_ini' $where_kelas
          ORDER BY k.nama_kelas ASC, jp.jam_ke ASC";

$res = mysqli_query($conn, $query);

$raw_schedules = [];
$total_jadwal = 0;
$total_sudah = 0;
$total_belum = 0;

while ($row = mysqli_fetch_assoc($res)) {
    $row['is_filled'] = !empty($row['jurnal_id']);
    $total_jadwal++;
    if ($row['is_filled']) {
        $total_sudah++;
    } else {
        $total_belum++;
    }

    // Filter by status if specified
    if ($status_filter === 'sudah' && !$row['is_filled']) continue;
    if ($status_filter === 'belum' && $row['is_filled']) continue;

    $raw_schedules[] = $row;
}

$persen_sudah = $total_jadwal > 0 ? round(($total_sudah / $total_jadwal) * 100) : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Status Pengisian Jurnal Guru</h1>
        <p class="text-slate-500 font-medium">Monitoring real-time keterisian jurnal mengajar sesuai jadwal harian.</p>
    </div>
    <div class="flex items-center gap-2">
        <span class="px-4 py-2 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-700 font-black text-xs uppercase tracking-wider">
            Hari: <?= $hari_ini ?>, <?= date('d M Y', strtotime($tgl)) ?>
        </span>
    </div>
</div>

<!-- Filter Form -->
<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/40 to-white border border-indigo-100/50">
    <form action="" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Tanggal</label>
            <input type="date" name="tanggal" value="<?= htmlspecialchars($tgl) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold text-xs text-slate-700">
        </div>

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Kelas</label>
            <select name="kelas_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold text-xs text-slate-700">
                <option value="">-- Semua Kelas --</option>
                <?php mysqli_data_seek($kelases, 0); while($k = mysqli_fetch_assoc($kelases)): ?>
                    <option value="<?= $k['id'] ?>" <?= $k['id'] == $kelas_id ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Status Pengisian</label>
            <select name="status" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold text-xs text-slate-700">
                <option value="">-- Semua Status --</option>
                <option value="sudah" <?= $status_filter === 'sudah' ? 'selected' : '' ?>>Sudah Mengisi</option>
                <option value="belum" <?= $status_filter === 'belum' ? 'selected' : '' ?>>Belum Mengisi</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 py-2.5 bg-indigo-600 text-white font-bold rounded-xl text-xs hover:bg-indigo-700 transition-all shadow-md shadow-indigo-100">Filter</button>
            <a href="status_jurnal_jadwal.php" class="px-4 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl text-xs hover:bg-slate-300 transition-all">Reset</a>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="lux-card p-6 bg-white border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Total Jadwal Hari Ini</p>
            <h3 class="text-3xl font-black text-slate-800 mt-1"><?= $total_jadwal ?> <span class="text-xs font-normal text-slate-400">Sesi</span></h3>
        </div>
        <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-xl font-bold">
            <i class="fa fa-calendar-alt"></i>
        </div>
    </div>

    <div class="lux-card p-6 bg-white border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-[10px] font-black uppercase text-emerald-500 tracking-widest">Sudah Mengisi Jurnal</p>
            <h3 class="text-3xl font-black text-emerald-600 mt-1"><?= $total_sudah ?> <span class="text-xs font-semibold text-emerald-500">(<?= $persen_sudah ?>%)</span></h3>
        </div>
        <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-xl font-bold">
            <i class="fa fa-check-circle"></i>
        </div>
    </div>

    <div class="lux-card p-6 bg-white border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-[10px] font-black uppercase text-rose-500 tracking-widest">Belum Mengisi Jurnal</p>
            <h3 class="text-3xl font-black text-rose-600 mt-1"><?= $total_belum ?> <span class="text-xs font-semibold text-rose-500">(<?= $total_jadwal > 0 ? (100 - $persen_sudah) : 0 ?>%)</span></h3>
        </div>
        <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-xl font-bold">
            <i class="fa fa-clock"></i>
        </div>
    </div>
</div>

<!-- Table -->
<div class="lux-card overflow-hidden bg-white shadow-2xl rounded-3xl mb-8">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Kelas</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Jam Ke-</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Guru Pengajar</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Mata Pelajaran</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Status</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi / Detail</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (empty($raw_schedules)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-slate-400 font-bold italic">
                        Tidak ada jadwal terdaftar untuk kriteria ini pada hari <?= $hari_ini ?>.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($raw_schedules as $item): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 font-bold text-slate-800 text-sm">
                            <span class="px-3 py-1 rounded-lg bg-slate-100 border border-slate-200 text-slate-700">
                                <?= htmlspecialchars($item['nama_kelas']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 font-bold text-indigo-600 text-xs">
                            Jam Ke: <?= htmlspecialchars($item['jam_ke']) ?>
                        </td>
                        <td class="px-6 py-4 font-bold text-slate-800 text-sm">
                            <?= htmlspecialchars($item['nama_guru']) ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-700 text-sm"><?= htmlspecialchars($item['nama_mapel']) ?></div>
                            <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($item['kode_mapel']) ?></div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($item['is_filled']): ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 inline-flex items-center gap-1">
                                    <i class="fa fa-check-circle"></i> Sudah Mengisi
                                </span>
                            <?php else: ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 inline-flex items-center gap-1">
                                    <i class="fa fa-clock"></i> Belum Mengisi
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($item['is_filled']): ?>
                                <button onclick="showDetailJurnal(<?= $item['jurnal_id'] ?>)" class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-100 transition-all inline-flex items-center gap-1.5">
                                    <i class="fa fa-eye"></i> Detail
                                </button>
                            <?php else: ?>
                                <span class="text-xs text-slate-400 italic">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function showDetailJurnal(jurnalId) {
    Swal.fire({
        title: 'Memuat Detail Jurnal...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch(`<?= BASE_URL ?>admin/get_jurnal_detail.php?id=${jurnalId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                Swal.fire('Gagal', data.message || 'Tidak dapat memuat data jurnal.', 'error');
                return;
            }

            const j = data.jurnal;
            let studentRows = '';
            data.students.forEach((s, idx) => {
                let badgeClass = 'bg-emerald-100 text-emerald-800';
                let statusLabel = 'Hadir';
                if (s.status === 'S') { badgeClass = 'bg-amber-100 text-amber-800'; statusLabel = 'Sakit'; }
                else if (s.status === 'I') { badgeClass = 'bg-blue-100 text-blue-800'; statusLabel = 'Izin'; }
                else if (s.status === 'A') { badgeClass = 'bg-rose-100 text-rose-800'; statusLabel = 'Alfa'; }

                studentRows += `
                    <tr class="border-b border-slate-100 text-left">
                        <td class="p-2 text-xs text-slate-500 font-mono">${s.nis || '-'}</td>
                        <td class="p-2 text-xs font-bold text-slate-700">${s.nama_siswa}</td>
                        <td class="p-2 text-center"><span class="px-2 py-0.5 rounded text-[10px] font-black uppercase ${badgeClass}">${statusLabel}</span></td>
                    </tr>`;
            });

            const htmlContent = `
                <div class="text-left space-y-4 max-h-[65vh] overflow-y-auto pr-1">
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 grid grid-cols-2 gap-3 text-xs">
                        <div><strong class="text-slate-400 uppercase text-[9px] block">Guru Pengajar</strong> <span class="font-bold text-slate-800">${j.nama_lengkap}</span></div>
                        <div><strong class="text-slate-400 uppercase text-[9px] block">Kelas & Jam</strong> <span class="font-bold text-slate-800">${j.nama_kelas} (Jam Ke: ${j.jam_ke})</span></div>
                        <div><strong class="text-slate-400 uppercase text-[9px] block">Mata Pelajaran</strong> <span class="font-bold text-slate-800">${j.nama_mapel}</span></div>
                        <div><strong class="text-slate-400 uppercase text-[9px] block">Waktu Pengisian</strong> <span class="font-bold text-indigo-600">${j.created_at}</span></div>
                    </div>

                    <div>
                        <strong class="text-slate-400 uppercase text-[9px] block mb-1">Materi Pembahasan</strong>
                        <div class="p-3 bg-indigo-50/50 border border-indigo-100 rounded-xl text-xs text-slate-700 font-medium leading-relaxed whitespace-pre-line">${j.materi}</div>
                    </div>

                    ${j.keterangan !== '-' ? `
                        <div>
                            <strong class="text-slate-400 uppercase text-[9px] block mb-1">Catatan / Keterangan</strong>
                            <div class="p-3 bg-slate-50 border border-slate-100 rounded-xl text-xs text-slate-600 font-medium">${j.keterangan}</div>
                        </div>
                    ` : ''}

                    <div>
                        <strong class="text-slate-400 uppercase text-[9px] block mb-2">Rekap Kehadiran Siswa (${data.students.length} Siswa)</strong>
                        <div class="flex gap-2 mb-3 text-[10px] font-black">
                            <span class="px-2.5 py-1 rounded bg-emerald-100 text-emerald-800">Hadir: ${j.jml_hadir}</span>
                            <span class="px-2.5 py-1 rounded bg-amber-100 text-amber-800">Sakit: ${j.jml_sakit}</span>
                            <span class="px-2.5 py-1 rounded bg-blue-100 text-blue-800">Izin: ${j.jml_izin}</span>
                            <span class="px-2.5 py-1 rounded bg-rose-100 text-rose-800">Alfa: ${j.jml_alfa}</span>
                        </div>
                        <div class="max-h-40 overflow-y-auto border border-slate-200 rounded-xl">
                            <table class="w-full">
                                <thead class="bg-slate-100 text-[10px] uppercase font-bold text-slate-500">
                                    <tr>
                                        <th class="p-2 text-left">NIS</th>
                                        <th class="p-2 text-left">Nama Siswa</th>
                                        <th class="p-2 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${studentRows || '<tr><td colspan="3" class="p-4 text-center text-slate-400 italic">Tidak ada data siswa.</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>`;

            Swal.fire({
                title: 'Detail Jurnal Mengajar',
                html: htmlContent,
                width: '600px',
                confirmButtonText: 'Tutup',
                confirmButtonColor: '#4F46E5'
            });
        })
        .catch(err => {
            console.error('Error fetching jurnal detail:', err);
            Swal.fire('Error', 'Terjadi kesalahan saat memuat data jurnal.', 'error');
        });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
