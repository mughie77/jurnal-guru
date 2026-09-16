<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin', 'waka']);
$page_title = "Manajemen Pengumuman";
$message = ''; $message_type = '';

$user_id = $_SESSION['user_id'];
$created_by = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Admin';

// Search Logic
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$target_filter = mysqli_real_escape_string($conn, $_GET['target'] ?? '');

$where_clauses = [];
if (!empty($search)) {
    $where_clauses[] = "(judul LIKE '%$search%' OR isi LIKE '%$search%')";
}
if (!empty($target_filter) && in_array($target_filter, ['semua', 'guru', 'siswa'])) {
    $where_clauses[] = "target = '$target_filter'";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Handling POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Token Invalid");
    }

    if (isset($_POST['tambah'])) {
        $judul = trim($_POST['judul'] ?? '');
        $isi = trim($_POST['isi'] ?? '');
        $target = $_POST['target'] ?? 'semua';
        if (!in_array($target, ['semua', 'guru', 'siswa'])) $target = 'semua';

        if (empty($judul) || empty($isi)) {
            $message = "Judul dan isi pengumuman wajib diisi.";
            $message_type = 'error';
        } else {
            $file_lampiran = null;
            if (!empty($_FILES['file_lampiran']['name'])) {
                $target_dir = __DIR__ . "/../uploads/pengumuman/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

                $file_ext = strtolower(pathinfo($_FILES["file_lampiran"]["name"], PATHINFO_EXTENSION));
                $allowed_ext = ['png', 'jpg', 'jpeg', 'pdf', 'doc', 'docx'];

                if (in_array($file_ext, $allowed_ext)) {
                    if ($_FILES["file_lampiran"]["size"] <= 5 * 1024 * 1024) {
                        $file_lampiran = "pengumuman_" . time() . "_" . rand(100, 999) . "." . $file_ext;
                        move_uploaded_file($_FILES["file_lampiran"]["tmp_name"], $target_dir . $file_lampiran);
                    } else {
                        $message = "Ukuran file lampiran maksimal 5MB.";
                        $message_type = 'error';
                    }
                } else {
                    $message = "Format lampiran tidak didukung (Gunakan PNG, JPG, PDF, DOC, DOCX).";
                    $message_type = 'error';
                }
            }

            if (empty($message_type)) {
                $stmt = mysqli_prepare($conn, "INSERT INTO pengumuman (judul, isi, target, file_lampiran, created_by) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "sssss", $judul, $isi, $target, $file_lampiran, $created_by);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Pengumuman berhasil diterbitkan!";
                    $message_type = 'success';
                } else {
                    $message = "Gagal menerbitkan pengumuman: " . mysqli_error($conn);
                    $message_type = 'error';
                }
            }
        }
    } elseif (isset($_POST['edit'])) {
        $id = (int)$_POST['id'];
        $judul = trim($_POST['judul'] ?? '');
        $isi = trim($_POST['isi'] ?? '');
        $target = $_POST['target'] ?? 'semua';
        if (!in_array($target, ['semua', 'guru', 'siswa'])) $target = 'semua';

        if (empty($judul) || empty($isi)) {
            $message = "Judul dan isi pengumuman wajib diisi.";
            $message_type = 'error';
        } else {
            // Get existing file
            $res_old = mysqli_query($conn, "SELECT file_lampiran FROM pengumuman WHERE id = $id");
            $old_row = mysqli_fetch_assoc($res_old);
            $file_lampiran = $old_row['file_lampiran'] ?? null;

            if (!empty($_FILES['file_lampiran']['name'])) {
                $target_dir = __DIR__ . "/../uploads/pengumuman/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

                $file_ext = strtolower(pathinfo($_FILES["file_lampiran"]["name"], PATHINFO_EXTENSION));
                $allowed_ext = ['png', 'jpg', 'jpeg', 'pdf', 'doc', 'docx'];

                if (in_array($file_ext, $allowed_ext)) {
                    if ($_FILES["file_lampiran"]["size"] <= 5 * 1024 * 1024) {
                        $new_file = "pengumuman_" . time() . "_" . rand(100, 999) . "." . $file_ext;
                        if (move_uploaded_file($_FILES["file_lampiran"]["tmp_name"], $target_dir . $new_file)) {
                            if ($file_lampiran && file_exists($target_dir . $file_lampiran)) {
                                @unlink($target_dir . $file_lampiran);
                            }
                            $file_lampiran = $new_file;
                        }
                    } else {
                        $message = "Ukuran file lampiran maksimal 5MB.";
                        $message_type = 'error';
                    }
                } else {
                    $message = "Format lampiran tidak didukung.";
                    $message_type = 'error';
                }
            }

            if (empty($message_type)) {
                $stmt = mysqli_prepare($conn, "UPDATE pengumuman SET judul = ?, isi = ?, target = ?, file_lampiran = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "ssssi", $judul, $isi, $target, $file_lampiran, $id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Pengumuman berhasil diperbarui!";
                    $message_type = 'success';
                } else {
                    $message = "Gagal memperbarui pengumuman.";
                    $message_type = 'error';
                }
            }
        }
    } elseif (isset($_POST['hapus'])) {
        $id = (int)$_POST['id'];
        $res = mysqli_query($conn, "SELECT file_lampiran FROM pengumuman WHERE id = $id");
        if ($row = mysqli_fetch_assoc($res)) {
            if ($row['file_lampiran'] && file_exists(__DIR__ . "/../uploads/pengumuman/" . $row['file_lampiran'])) {
                @unlink(__DIR__ . "/../uploads/pengumuman/" . $row['file_lampiran']);
            }
        }
        $stmt = mysqli_prepare($conn, "DELETE FROM pengumuman WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Pengumuman dihapus!";
            $message_type = 'success';
        } else {
            $message = "Gagal menghapus pengumuman.";
            $message_type = 'error';
        }
    }
}

$pagin = get_pagination_data($conn, "pengumuman", 10, $where_sql);
$result = mysqli_query($conn, "SELECT * FROM pengumuman $where_sql ORDER BY created_at DESC LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Manajemen Pengumuman</h1>
        <p class="text-slate-500 font-medium">Buat dan kelola pengumuman sekolah untuk guru dan siswa.</p>
    </div>
    <div>
        <button onclick="openModal('tambahModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-2xl font-bold shadow-lg shadow-indigo-200 transition-all flex items-center">
            <i class="fa fa-plus mr-2"></i> Buat Pengumuman
        </button>
    </div>
</div>

<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cari Pengumuman</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Judul atau Isi..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-semibold text-sm">
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Target Sasaran</label>
            <select name="target" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-semibold text-sm">
                <option value="">Semua Target</option>
                <option value="semua" <?= $target_filter == 'semua' ? 'selected' : '' ?>>Semua (Guru & Siswa)</option>
                <option value="guru" <?= $target_filter == 'guru' ? 'selected' : '' ?>>Guru</option>
                <option value="siswa" <?= $target_filter == 'siswa' ? 'selected' : '' ?>>Siswa</option>
            </select>
        </div>
        <div class="flex items-end gap-3">
            <button type="submit" class="px-8 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100 text-sm">Cari</button>
            <a href="pengumuman.php" class="px-6 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-300 transition-all text-sm">Reset</a>
        </div>
    </form>
</div>

<?php if ($message): ?>
<script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= addslashes(htmlspecialchars($message)) ?>', confirmButtonColor: '#4f46e5' });</script>
<?php endif; ?>

<div class="lux-card overflow-hidden bg-white shadow-2xl rounded-3xl">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Judul & Isi</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Target</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Lampiran</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Tanggal Dibuat</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (mysqli_num_rows($result) == 0): ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-bold italic">Belum ada pengumuman.</td>
                </tr>
                <?php else: ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 max-w-md">
                            <div class="font-bold text-slate-800 text-base mb-1"><?= htmlspecialchars($row['judul']) ?></div>
                            <div class="text-xs text-slate-500 line-clamp-2 leading-relaxed"><?= htmlspecialchars($row['isi']) ?></div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($row['target'] == 'guru'): ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-100">Guru</span>
                            <?php elseif ($row['target'] == 'siswa'): ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-sky-50 text-sky-600 border border-sky-100">Siswa</span>
                            <?php else: ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-600 border border-indigo-100">Semua</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php if (!empty($row['file_lampiran'])): ?>
                                <a href="<?= BASE_URL ?>uploads/pengumuman/<?= $row['file_lampiran'] ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-all">
                                    <i class="fa fa-paperclip text-indigo-500"></i> Lampiran
                                </a>
                            <?php else: ?>
                                <span class="text-xs text-slate-300 italic">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="text-xs font-bold text-slate-600"><?= date('d M Y', strtotime($row['created_at'])) ?></div>
                            <div class="text-[10px] font-bold text-slate-400"><?= date('H:i', strtotime($row['created_at'])) ?> WIB</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-center gap-2">
                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)" class="w-9 h-9 flex items-center justify-center rounded-xl bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white transition-all">
                                    <i class="fa fa-edit"></i>
                                </button>
                                <button onclick="openDeleteModal(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['judul'])) ?>')" class="w-9 h-9 flex items-center justify-center rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>

<!-- Modals Overlay -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<!-- Tambah Modal -->
<div id="tambahModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-xl max-h-[90vh] overflow-y-auto bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0">
    <div class="bg-indigo-600 px-6 sm:px-8 py-5 text-white flex justify-between items-center sticky top-0 z-10">
        <h3 class="text-xl font-black italic">Buat Pengumuman Baru</h3>
        <button type="button" onclick="closeModal('tambahModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>
    <form action="" method="POST" enctype="multipart/form-data" class="p-6 sm:p-8 space-y-5">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Judul Pengumuman</label>
            <input type="text" name="judul" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-semibold text-sm" placeholder="Contoh: Jadwal Ujian Akhir Semester">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Target Sasaran</label>
            <select name="target" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-semibold text-sm bg-white">
                <option value="semua">Semua (Guru & Siswa)</option>
                <option value="guru">Guru Saja</option>
                <option value="siswa">Siswa Saja</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Isi Pengumuman</label>
            <textarea name="isi" rows="5" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-medium text-sm" placeholder="Tuliskan detail pengumuman di sini..."></textarea>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">File Lampiran (Opsional)</label>
            <input type="file" name="file_lampiran" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 font-medium text-xs text-slate-600 bg-slate-50">
            <p class="text-[10px] text-slate-400 mt-1">Format: PNG, JPG, PDF, DOC, DOCX (Maks. 5MB)</p>
        </div>
        <div class="pt-4 flex flex-col sm:flex-row gap-3">
            <button type="button" onclick="closeModal('tambahModal')" class="flex-1 px-4 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
            <button type="submit" name="tambah" class="flex-1 px-4 py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100 text-sm">Terbitkan</button>
        </div>
    </form>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-xl max-h-[90vh] overflow-y-auto bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0">
    <div class="bg-amber-500 px-6 sm:px-8 py-5 text-white flex justify-between items-center sticky top-0 z-10">
        <h3 class="text-xl font-black italic">Edit Pengumuman</h3>
        <button type="button" onclick="closeModal('editModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>
    <form action="" method="POST" enctype="multipart/form-data" class="p-6 sm:p-8 space-y-5">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <input type="hidden" name="id" id="edit_id">
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Judul Pengumuman</label>
            <input type="text" name="judul" id="edit_judul" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 font-semibold text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Target Sasaran</label>
            <select name="target" id="edit_target" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 font-semibold text-sm bg-white">
                <option value="semua">Semua (Guru & Siswa)</option>
                <option value="guru">Guru Saja</option>
                <option value="siswa">Siswa Saja</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Isi Pengumuman</label>
            <textarea name="isi" id="edit_isi" rows="5" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-50 font-medium text-sm"></textarea>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Ganti Lampiran (Opsional)</label>
            <input type="file" name="file_lampiran" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 font-medium text-xs text-slate-600 bg-slate-50">
            <p class="text-[10px] text-slate-400 mt-1">Biarkan kosong jika tidak ingin mengubah file lampiran.</p>
        </div>
        <div class="pt-4 flex flex-col sm:flex-row gap-3">
            <button type="button" onclick="closeModal('editModal')" class="flex-1 px-4 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
            <button type="submit" name="edit" class="flex-1 px-4 py-3 rounded-xl bg-amber-500 text-white font-bold hover:bg-amber-600 shadow-lg shadow-amber-100 text-sm">Simpan Perubahan</button>
        </div>
    </form>
</div>

<!-- Delete Modal -->
<div id="hapusModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[90%] sm:w-full max-w-sm bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="p-8 text-center">
        <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl"><i class="fa fa-trash"></i></div>
        <h3 class="text-lg font-bold text-slate-800 mb-1">Hapus Pengumuman?</h3>
        <p class="text-sm text-slate-500 mb-6">Anda akan menghapus <span id="hapus_judul" class="font-bold"></span>.</p>
        <form action="" method="POST" class="flex gap-2">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            <input type="hidden" name="id" id="hapus_id">
            <button type="button" onclick="closeModal('hapusModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600 text-sm">Batal</button>
            <button type="submit" name="hapus" class="flex-1 px-4 py-2.5 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 transition-all text-sm">Hapus</button>
        </form>
    </div>
</div>

<script>
const overlay = document.getElementById('modalOverlay');
function openModal(id) {
    const m = document.getElementById(id);
    overlay.classList.remove('hidden');
    m.classList.remove('hidden');
    setTimeout(() => {
        overlay.classList.add('opacity-100');
        m.classList.add('opacity-100', 'scale-100');
    }, 10);
}
function closeModal(id) {
    const m = document.getElementById(id);
    overlay.classList.remove('opacity-100');
    m.classList.remove('opacity-100', 'scale-100');
    setTimeout(() => {
        overlay.classList.add('hidden');
        m.classList.add('hidden');
    }, 300);
}
function closeAllModals() {
    document.querySelectorAll('.modal-content').forEach(m => {
        if (!m.classList.contains('hidden')) closeModal(m.id);
    });
}
function openEditModal(d) {
    document.getElementById('edit_id').value = d.id;
    document.getElementById('edit_judul').value = d.judul;
    document.getElementById('edit_target').value = d.target;
    document.getElementById('edit_isi').value = d.isi;
    openModal('editModal');
}
function openDeleteModal(id, judul) {
    document.getElementById('hapus_id').value = id;
    document.getElementById('hapus_judul').textContent = judul;
    openModal('hapusModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
