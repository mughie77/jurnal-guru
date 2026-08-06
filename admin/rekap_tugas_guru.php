<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin', 'waka']);

$page_title = "Rekap Tugas Guru Tidak Masuk";
$message = ''; $message_type = '';

// Handle status toggling from central rekap (Admin/Waka)
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status') {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        die("CSRF Token Invalid");
    }
    $task_id = (int)$_GET['id'];
    $new_status = (int)$_GET['status'];

    $stmt = mysqli_prepare($conn, "UPDATE tugas_kelas SET status_selesai = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $new_status, $task_id);
    if (mysqli_stmt_execute($stmt)) {
        header("Location: rekap_tugas_guru.php?success_toggle=1");
        exit;
    }
}

// Handle task deletion from central rekap (Admin only)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && $_SESSION['role'] === 'admin') {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        die("CSRF Token Invalid");
    }
    $task_id = (int)$_GET['id'];

    $stmt = mysqli_prepare($conn, "DELETE FROM tugas_kelas WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $task_id);
    if (mysqli_stmt_execute($stmt)) {
        header("Location: rekap_tugas_guru.php?success_delete=1");
        exit;
    }
}

if (isset($_GET['success_toggle'])) {
    $message = "Status tugas kelas berhasil diperbarui!";
    $message_type = "success";
}

if (isset($_GET['success_delete'])) {
    $message = "Tugas kelas berhasil dihapus dari rekap!";
    $message_type = "success";
}

// Filter Logic
$guru_filter = (int)($_GET['guru_id'] ?? 0);
$kelas_filter = (int)($_GET['kelas_id'] ?? 0);
$tanggal_filter = $_GET['tanggal'] ?? '';

$where_clauses = [];
if ($guru_filter > 0) $where_clauses[] = "tk.guru_id = $guru_filter";
if ($kelas_filter > 0) $where_clauses[] = "tk.kelas_id = $kelas_filter";
if (!empty($tanggal_filter)) $where_clauses[] = "tk.tanggal = '" . mysqli_real_escape_string($conn, $tanggal_filter) . "'";

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Pagination setup
$from_tables = "tugas_kelas tk
                JOIN kelas k ON tk.kelas_id = k.id
                JOIN guru g ON tk.guru_id = g.id
                JOIN users u ON g.user_id = u.id";

$pagin = get_pagination_data($conn, $from_tables, 15, $where_sql);

// Main query to load data
$query = "SELECT tk.*, k.nama_kelas, u.nama_lengkap as nama_guru
          FROM tugas_kelas tk
          JOIN kelas k ON tk.kelas_id = k.id
          JOIN guru g ON tk.guru_id = g.id
          JOIN users u ON g.user_id = u.id
          $where_sql
          ORDER BY tk.tanggal DESC, tk.created_at DESC
          LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";
$result = mysqli_query($conn, $query);

// Fetch selectors list
$teachers_list = mysqli_query($conn, "SELECT g.id, u.nama_lengkap FROM guru g JOIN users u ON g.user_id = u.id ORDER BY u.nama_lengkap ASC");
$classes_list = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Rekap Tugas Guru Tidak Masuk</h1>
        <p class="text-slate-500">Laporan dan pemantauan tugas yang diberikan oleh guru yang berhalangan hadir.</p>
    </div>
</div>

<?php if ($message): ?>
<script>
    Swal.fire({
        icon: '<?= $message_type ?>',
        title: 'Berhasil!',
        text: '<?= addslashes($message) ?>',
        confirmButtonColor: '#4F46E5',
        timer: 2000
    });
</script>
<?php endif; ?>

<!-- Filter Panel -->
<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Pilih Guru</label>
            <select name="guru_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700">
                <option value="">-- Semua Guru --</option>
                <?php while ($row = mysqli_fetch_assoc($teachers_list)): ?>
                    <option value="<?= $row['id'] ?>" <?= $row['id'] == $guru_filter ? 'selected' : '' ?>><?= htmlspecialchars($row['nama_lengkap']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Pilih Kelas</label>
            <select name="kelas_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700">
                <option value="">-- Semua Kelas --</option>
                <?php while ($row = mysqli_fetch_assoc($classes_list)): ?>
                    <option value="<?= $row['id'] ?>" <?= $row['id'] == $kelas_filter ? 'selected' : '' ?>><?= htmlspecialchars($row['nama_kelas']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Tanggal</label>
            <input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal_filter) ?>" class="w-full px-4 py-2 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm text-slate-700 font-semibold text-sm">
        </div>

        <div class="flex items-end gap-3 pt-2 md:pt-0">
            <button type="submit" class="flex-1 px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100">Cari</button>
            <a href="rekap_tugas_guru.php" class="px-6 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-300 transition-all">Reset</a>
        </div>
    </form>
</div>

<!-- Detailed Records Table -->
<div class="lux-card overflow-hidden bg-white">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Tanggal / Waktu</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Guru Pemberi</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Kelas</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Deskripsi / Lampiran</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Status Selesai</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr class="hover:bg-slate-50/50 transition-all">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-bold text-slate-800"><?= date('d M Y', strtotime($row['tanggal'])) ?></div>
                            <div class="text-[10px] text-slate-400 font-bold italic">Kirim: <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></div>
                        </td>
                        <td class="px-6 py-4 font-bold text-slate-800 text-sm"><?= htmlspecialchars($row['nama_guru']) ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-black uppercase"><?= htmlspecialchars($row['nama_kelas']) ?></span>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-600 max-w-sm">
                            <div class="italic leading-relaxed">"<?= htmlspecialchars($row['keterangan_tugas']) ?>"</div>
                            <?php if ($row['file_lampiran']): ?>
                                <a href="<?= BASE_URL ?>uploads/tugas/<?= $row['file_lampiran'] ?>" target="_blank" class="text-indigo-600 font-bold hover:underline inline-flex items-center gap-1 mt-1 text-[10px]">
                                    <i class="fa fa-download"></i> Unduh Lampiran
                                </a>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            <?php if ($row['status_selesai']): ?>
                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase rounded-full">Selesai</span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 bg-slate-100 text-slate-400 text-[10px] font-black uppercase rounded-full">Belum</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-center gap-2">
                                <?php if ($row['status_selesai']): ?>
                                    <a href="rekap_tugas_guru.php?action=toggle_status&id=<?= $row['id'] ?>&status=0&csrf_token=<?= get_csrf_token() ?>" class="w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:bg-slate-200 transition-all flex items-center justify-center border border-slate-100" title="Tandai Belum Selesai">
                                        <i class="fa fa-undo text-xs"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="rekap_tugas_guru.php?action=toggle_status&id=<?= $row['id'] ?>&status=1&csrf_token=<?= get_csrf_token() ?>" class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white transition-all flex items-center justify-center border border-emerald-100 shadow-sm" title="Tandai Selesai">
                                        <i class="fa fa-check text-xs"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= get_csrf_token() ?>')" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all flex items-center justify-center border border-rose-100 shadow-sm" title="Hapus Tugas">
                                        <i class="fa fa-trash text-xs"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="px-6 py-20 text-center text-slate-400 font-medium italic">Tidak ada rekap tugas guru tidak masuk yang tercatat.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>

<script>
function confirmDelete(id, csrfToken) {
    Swal.fire({
        title: 'Hapus Tugas?',
        text: 'Apakah Anda yakin ingin menghapus tugas ini dari rekap? Tindakan ini tidak dapat dibatalkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'rekap_tugas_guru.php?action=delete&id=' + id + '&csrf_token=' + csrfToken;
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
