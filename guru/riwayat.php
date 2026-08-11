<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);
$page_title = "Riwayat Jurnal Saya";

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
if(mysqli_num_rows($guru_res) == 0) die("Error: Data guru tidak ditemukan.");
$guru_id = mysqli_fetch_assoc($guru_res)['id'];

// Deletion Handler
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $del_id = (int)($_GET['id'] ?? 0);
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        die("CSRF Token Invalid");
    }

    // Ensure the journal belongs to this teacher or user is admin
    $check_del = mysqli_query($conn, "SELECT id FROM jurnal WHERE id = $del_id AND (guru_id = $guru_id OR '" . $_SESSION['role'] . "' = 'admin')");
    if (mysqli_num_rows($check_del) > 0) {
        mysqli_query($conn, "DELETE FROM absensi_jurnal WHERE jurnal_id = $del_id");
        mysqli_query($conn, "DELETE FROM jurnal WHERE id = $del_id");

        header("Location: riwayat.php?success_delete=1");
        exit;
    }
}

$where_clause = "jurnal.guru_id = $guru_id";
if ($active_tahun_id) $where_clause .= " AND jurnal.tahun_pelajaran_id = $active_tahun_id";
if (!empty($_GET['tanggal'])) {
    $tgl = mysqli_real_escape_string($conn, $_GET['tanggal']);
    $where_clause .= " AND jurnal.tanggal = '$tgl'";
}

$sql = "SELECT jurnal.*, mp.nama_mapel, k.nama_kelas
        FROM jurnal
        JOIN mata_pelajaran mp ON jurnal.mapel_id = mp.id
        JOIN kelas k ON jurnal.kelas_id = k.id
        WHERE $where_clause
        ORDER BY jurnal.tanggal DESC, jurnal.created_at DESC";
$result = mysqli_query($conn, $sql);

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Jurnal mengajar telah berhasil disimpan.',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
    });
</script>
<?php endif; ?>

<?php if (isset($_GET['success_delete'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Jurnal mengajar telah berhasil dihapus.',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
    });
</script>
<?php endif; ?>

<?php if (isset($_GET['success_edit'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Jurnal mengajar telah berhasil diperbarui.',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
    });
</script>
<?php endif; ?>

<style>#sidebar, header, nav.navbar { display: none !important; } .lg\:ml-64 { margin-left: 0 !important; } .main-content { margin-left: 0 !important; padding-top: 2rem !important; }</style>

<div class="max-w-full w-full mx-auto pb-20 px-4 sm:px-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Riwayat Jurnal</h1>
            <p class="text-slate-500">Kumpulan catatan aktivitas mengajar Anda.</p>
        </div>
        <div class="flex gap-3">
            <a href="index.php" class="px-6 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition-all bg-white shadow-sm flex items-center">
                <i class="fa fa-arrow-left mr-2"></i> Kembali
            </a>
        </div>
    </div>

    <div class="lux-card p-6 mb-8 bg-gradient-to-br from-emerald-50/50 to-white">
        <form action="" method="GET" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2 tracking-widest">Filter Tanggal</label>
                <input type="date" name="tanggal" value="<?= htmlspecialchars($_GET['tanggal'] ?? '') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-emerald-100 bg-white">
            </div>
            <button type="submit" class="px-8 py-2.5 bg-emerald-600 text-white font-bold rounded-xl hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-100">Cari Jurnal</button>
            <a href="riwayat.php" class="px-6 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-300 transition-all">Reset</a>
        </form>
    </div>

    <div class="lux-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Tanggal</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Mata Pelajaran</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Kelas</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Materi</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Kehadiran</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 text-sm font-bold text-slate-700 whitespace-nowrap"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-semibold text-indigo-600"><?= htmlspecialchars($row['nama_mapel']) ?></span>
                                <div class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter">Jam: <?= htmlspecialchars($row['jam_ke']) ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded bg-slate-100 text-slate-600 text-[10px] font-extrabold uppercase border border-slate-200"><?= htmlspecialchars($row['nama_kelas']) ?></span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600 max-w-xs truncate" title="<?= htmlspecialchars($row['materi']) ?>">
                                <?= htmlspecialchars($row['materi']) ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-center gap-1">
                                    <span class="px-2 py-1 rounded-md bg-emerald-50 text-emerald-600 text-[10px] font-black border border-emerald-100">H:<?= $row['jml_hadir'] ?></span>
                                    <span class="px-2 py-1 rounded-md bg-rose-50 text-rose-600 text-[10px] font-black border border-rose-100">A:<?= $row['jml_alfa'] ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-2">
                                    <button onclick="showJurnalDetail(<?= $row['id'] ?>)" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all shadow-sm border border-indigo-100" title="Detail Jurnal">
                                        <i class="fa fa-eye text-xs"></i>
                                    </button>
                                    <a href="edit_jurnal.php?id=<?= $row['id'] ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all shadow-sm border border-indigo-100" title="Edit Jurnal">
                                        <i class="fa fa-edit text-xs"></i>
                                    </a>
                                    <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= get_csrf_token() ?>')" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all shadow-sm border border-rose-100" title="Hapus Jurnal">
                                        <i class="fa fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="px-6 py-20 text-center text-slate-400 font-medium italic">Tidak ada data riwayat jurnal.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, csrfToken) {
    Swal.fire({
        title: 'Hapus Jurnal?',
        text: 'Apakah Anda yakin ingin menghapus jurnal ini? Tindakan ini tidak dapat dibatalkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'riwayat.php?action=delete&id=' + id + '&csrf_token=' + csrfToken;
        }
    });
}

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
                    <div class="border border-slate-100 rounded-xl overflow-hidden shadow-sm overflow-x-auto">
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
