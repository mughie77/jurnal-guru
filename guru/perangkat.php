<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
if(mysqli_num_rows($guru_res) == 0) die("Error: Data guru tidak ditemukan.");
$guru_id = mysqli_fetch_assoc($guru_res)['id'];

$message = ''; $message_type = '';

// Handle AJAX actions for Category Management
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action'])) {
    ob_start();
    header('Content-Type: application/json');
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'CSRF Token Invalid']);
        exit;
    }

    if ($_POST['ajax_action'] === 'tambah_kategori') {
        $nama = trim($_POST['nama_kategori']);
        if (empty($nama)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Nama kategori tidak boleh kosong.']);
            exit;
        }
        $stmt = mysqli_prepare($conn, "INSERT INTO kategori_perangkat (nama_kategori) VALUES (?) ON DUPLICATE KEY UPDATE nama_kategori = VALUES(nama_kategori)");
        mysqli_stmt_bind_param($stmt, "s", $nama);
        if (mysqli_stmt_execute($stmt)) {
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Kategori berhasil ditambahkan!']);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan kategori.']);
        }
    } elseif ($_POST['ajax_action'] === 'hapus_kategori_by_name') {
        $nama = trim($_POST['nama_kategori']);
        $stmt = mysqli_prepare($conn, "DELETE FROM kategori_perangkat WHERE nama_kategori = ?");
        mysqli_stmt_bind_param($stmt, "s", $nama);
        if (mysqli_stmt_execute($stmt)) {
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Kategori berhasil dihapus!']);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus kategori.']);
        }
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");
    $nama = mysqli_real_escape_string($conn, $_POST['nama_perangkat']);
    $jenis = mysqli_real_escape_string($conn, $_POST['jenis_perangkat']);
    $file = $_FILES['file_perangkat'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = time() . '_' . uniqid() . '.' . $ext;
    $target = __DIR__ . '/../uploads/perangkat/' . $filename;

    if (in_array($ext, ['pdf', 'doc', 'docx', 'mp4'])) {
        if (!is_dir(__DIR__ . '/../uploads/perangkat/')) mkdir(__DIR__ . '/../uploads/perangkat/', 0777, true);

        $kelas_ids = $_POST['kelas_ids'] ?? [];
        if (empty($kelas_ids)) {
            $message = "Harap pilih minimal satu kelas tujuan."; $message_type = 'error';
        } elseif (move_uploaded_file($file['tmp_name'], $target)) {
            mysqli_begin_transaction($conn);
            try {
                $path = 'uploads/perangkat/' . $filename;
                $stmt_p = mysqli_prepare($conn, "INSERT INTO perangkat (guru_id, nama_perangkat, jenis_perangkat, file_path) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt_p, "isss", $guru_id, $nama, $jenis, $path);
                mysqli_stmt_execute($stmt_p);
                $perangkat_id = mysqli_insert_id($conn);

                $stmt_pk = mysqli_prepare($conn, "INSERT INTO perangkat_kelas (perangkat_id, kelas_id) VALUES (?, ?)");
                foreach ($kelas_ids as $kid) {
                    $kid = (int)$kid;
                    mysqli_stmt_bind_param($stmt_pk, "ii", $perangkat_id, $kid);
                    mysqli_stmt_execute($stmt_pk);
                }
                mysqli_commit($conn);
                $message = "Media pembelajaran berhasil diunggah!"; $message_type = 'success';
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = "Gagal menyimpan data: " . $e->getMessage(); $message_type = 'error';
            }
        } else { $message = "Gagal mengunggah file."; $message_type = 'error'; }
    } else { $message = "Format file tidak didukung (PDF/Word/MP4)."; $message_type = 'error'; }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");
    $id = (int)$_POST['id'];
    $stmt_sel = mysqli_prepare($conn, "SELECT file_path FROM perangkat WHERE id = ? AND guru_id = ?");
    mysqli_stmt_bind_param($stmt_sel, "ii", $id, $guru_id);
    mysqli_stmt_execute($stmt_sel);
    $res = mysqli_stmt_get_result($stmt_sel);
    if ($row = mysqli_fetch_assoc($res)) {
        if(file_exists(__DIR__ . '/../' . $row['file_path'])) unlink(__DIR__ . '/../' . $row['file_path']);
        $stmt_del = mysqli_prepare($conn, "DELETE FROM perangkat WHERE id = ? AND guru_id = ?");
        mysqli_stmt_bind_param($stmt_del, "ii", $id, $guru_id);
        mysqli_stmt_execute($stmt_del);
        $message = "Perangkat dihapus!"; $message_type = 'success';
    }
}

$query_p = "SELECT p.*, GROUP_CONCAT(k.nama_kelas SEPARATOR ', ') as target_kelas
            FROM perangkat p
            LEFT JOIN perangkat_kelas pk ON p.id = pk.perangkat_id
            LEFT JOIN kelas k ON pk.kelas_id = k.id
            WHERE p.guru_id = $guru_id
            GROUP BY p.id
            ORDER BY p.created_at DESC";
$perangkats = mysqli_query($conn, $query_p);

$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
$kategori_list = mysqli_query($conn, "SELECT * FROM kategori_perangkat ORDER BY nama_kategori ASC");

require_once __DIR__ . '/../includes/header.php';
?>

<style>#sidebar, header, nav.navbar { display: none !important; } .lg\:ml-64 { margin-left: 0 !important; } .main-content { margin-left: 0 !important; padding-top: 2rem !important; }</style>

<div class="max-w-5xl mx-auto pb-32 px-4">
    <div class="flex items-center justify-between mb-12">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Perangkat Saya</h1>
            <p class="text-slate-500 font-medium">Kelola berkas administrasi dan modul ajar.</p>
        </div>
        <a href="index.php" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm transition-all"><i class="fa fa-arrow-left"></i></a>
    </div>

    <?php if ($message): ?>
    <script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= $message ?>' });</script>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 sm:gap-10">
        <div class="lg:col-span-1 order-2 lg:order-1">
            <div class="lux-card p-6 sm:p-8 bg-white shadow-2xl lg:sticky lg:top-8 border-none overflow-hidden relative">
                <div class="absolute top-0 right-0 p-3 opacity-5 pointer-events-none">
                    <i class="fa fa-cloud-upload-alt text-6xl text-indigo-900"></i>
                </div>
                <h3 class="text-xs sm:text-sm font-black text-slate-400 uppercase tracking-widest mb-6 italic relative z-10">Unggah Berkas Baru</h3>
                <form action="" method="POST" enctype="multipart/form-data" class="space-y-4 sm:space-y-5 relative z-10">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <div>
                        <label class="block text-[9px] sm:text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Nama Perangkat</label>
                        <input type="text" name="nama_perangkat" placeholder="Contoh: RPP Web Dasar" required class="w-full px-4 py-3 rounded-xl border border-slate-100 bg-slate-50 focus:bg-white border-transparent focus:border-indigo-100 focus:ring-4 focus:ring-indigo-50 transition-all font-bold text-slate-700 text-sm">
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2 ml-1">
                            <label class="block text-[9px] sm:text-[10px] font-black text-slate-500 uppercase tracking-widest mb-0">Jenis / Kategori</label>
                            <button type="button" onclick="kelolaKategori()" class="text-[9px] font-black text-indigo-600 hover:text-indigo-800 uppercase tracking-widest flex items-center gap-1">
                                <i class="fa fa-folder-plus text-xs"></i> Kelola Kategori
                            </button>
                        </div>
                        <select name="jenis_perangkat" required class="w-full px-4 py-3 rounded-xl border border-slate-100 bg-slate-50 focus:bg-white focus:ring-4 focus:ring-indigo-50 bg-white font-bold text-slate-700 text-sm appearance-none cursor-pointer transition-all">
                            <?php mysqli_data_seek($kategori_list, 0); while($kat = mysqli_fetch_assoc($kategori_list)): ?>
                                <option value="<?= htmlspecialchars($kat['nama_kategori']) ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[9px] sm:text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Ditujukan Untuk Kelas</label>
                        <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto p-3 bg-slate-50 rounded-xl border border-slate-100">
                            <?php while($k = mysqli_fetch_assoc($kelases)): ?>
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="checkbox" name="kelas_ids[]" value="<?= $k['id'] ?>" class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500">
                                    <span class="text-[10px] font-bold text-slate-600 group-hover:text-indigo-600 transition-colors"><?= htmlspecialchars($k['nama_kelas']) ?></span>
                                </label>
                            <?php endwhile; ?>
                        </div>
                        <p class="text-[8px] text-slate-400 font-bold italic mt-1 uppercase tracking-wider">*Bisa pilih lebih dari satu</p>
                    </div>
                    <div>
                        <label class="block text-[9px] sm:text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">File Berkas</label>
                        <div class="relative group">
                            <input type="file" name="file_perangkat" required class="hidden" id="file_perangkat" onchange="updateFileName(this)">
                            <label for="file_perangkat" class="flex items-center gap-3 px-4 py-3 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 group-hover:border-indigo-300 group-hover:bg-indigo-50 transition-all cursor-pointer">
                                <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center text-indigo-500 shadow-sm"><i class="fa fa-file-import"></i></div>
                                <div class="flex-1 overflow-hidden">
                                    <p id="file-chosen" class="text-[10px] font-bold text-slate-400 truncate">Pilih PDF/Word/MP4...</p>
                                </div>
                            </label>
                        </div>
                    </div>
                    <button type="submit" name="upload" class="w-full py-4 bg-slate-900 hover:bg-indigo-600 text-white font-black rounded-2xl shadow-xl shadow-slate-200 hover:shadow-indigo-100 transition-all active:scale-95 text-xs sm:text-sm tracking-widest">SIMPAN BERKAS</button>
                </form>
            </div>
        </div>

        <script>
        function updateFileName(input) {
            const fileName = input.files[0] ? input.files[0].name : "Pilih PDF/Word/MP4...";
            document.getElementById('file-chosen').textContent = fileName;
            document.getElementById('file-chosen').classList.add('text-indigo-600');
        }

        function kelolaKategori() {
            const select = document.querySelector('select[name="jenis_perangkat"]');
            const options = Array.from(select.options);

            let listHtml = '<ul class="text-left space-y-2 max-h-40 overflow-y-auto p-2 bg-slate-50 rounded-xl border border-slate-100 mb-4">';
            options.forEach(opt => {
                listHtml += `
                <li class="flex items-center justify-between p-2 bg-white rounded-lg shadow-sm border border-slate-100 text-xs font-bold text-slate-700">
                    <span>${opt.text}</span>
                    <button type="button" onclick="hapusKategoriByName('${opt.text}')" class="text-rose-500 hover:text-rose-700 transition-colors"><i class="fa fa-trash-alt"></i></button>
                </li>`;
            });
            listHtml += '</ul>';
            listHtml += '<input type="text" id="new_kat_input" placeholder="Nama kategori baru..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 text-sm font-bold text-slate-700">';

            Swal.fire({
                title: 'Manajemen Kategori',
                html: listHtml,
                showCancelButton: true,
                confirmButtonText: 'Tambah Kategori',
                cancelButtonText: 'Tutup',
                confirmButtonColor: '#4F46E5',
                preConfirm: () => {
                    const inputVal = document.getElementById('new_kat_input').value.trim();
                    if (!inputVal) {
                        Swal.showValidationMessage('Nama kategori baru tidak boleh kosong!');
                    }
                    return inputVal;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('ajax_action', 'tambah_kategori');
                    formData.append('nama_kategori', result.value);
                    formData.append('csrf_token', '<?= get_csrf_token() ?>');

                    fetch('perangkat.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            Swal.fire('Berhasil', res.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Gagal', res.message, 'error');
                        }
                    });
                }
            });
        }

        function hapusKategoriByName(namaKategori) {
            Swal.fire({
                title: 'Hapus Kategori?',
                text: `Apakah Anda yakin ingin menghapus kategori "${namaKategori}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('ajax_action', 'hapus_kategori_by_name');
                    formData.append('nama_kategori', namaKategori);
                    formData.append('csrf_token', '<?= get_csrf_token() ?>');

                    fetch('perangkat.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            Swal.fire('Berhasil', res.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Gagal', res.message, 'error');
                        }
                    });
                }
            });
        }
        </script>

        <div class="lg:col-span-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php while($p = mysqli_fetch_assoc($perangkats)): ?>
                <div class="lux-card p-6 bg-white border-none shadow-xl flex flex-col justify-between hover:scale-[1.02] transition-all group">
                    <div class="flex items-start justify-between mb-6">
                        <div class="w-12 h-12 rounded-2xl bg-slate-50 text-slate-300 flex items-center justify-center text-xl group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-all duration-500">
                            <?php
                            $icon = 'fa-file-alt';
                            if (strpos($p['file_path'], '.pdf') !== false) $icon = 'fa-file-pdf';
                            elseif (strpos($p['file_path'], '.mp4') !== false) $icon = 'fa-file-video';
                            elseif (strpos($p['file_path'], '.doc') !== false) $icon = 'fa-file-word';
                            ?>
                            <i class="fa <?= $icon ?>"></i>
                        </div>
                        <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-500 text-[9px] font-black uppercase border border-slate-200"><?= $p['jenis_perangkat'] ?></span>
                    </div>
                    <div class="mb-6">
                        <h4 class="font-bold text-slate-800 line-clamp-2 italic" title="<?= htmlspecialchars($p['nama_perangkat']) ?>"><?= htmlspecialchars($p['nama_perangkat']) ?></h4>
                        <div class="mt-3 flex flex-col gap-1">
                            <div class="flex items-center gap-1.5">
                                <i class="fa fa-users text-[8px] text-slate-400"></i>
                                <p class="text-[8px] font-black text-slate-500 uppercase tracking-tighter truncate" title="<?= htmlspecialchars($p['target_kelas'] ?? 'Semua Kelas') ?>">
                                    <?= htmlspecialchars($p['target_kelas'] ?? 'Semua Kelas') ?>
                                </p>
                            </div>
                            <p class="text-[8px] text-slate-400 font-bold uppercase tracking-widest"><?= date('d M Y', strtotime($p['created_at'])) ?></p>
                        </div>
                    </div>
                    <div class="flex gap-2 pt-4 border-t border-slate-50">
                        <a href="<?= BASE_URL . $p['file_path'] ?>" target="_blank" class="flex-1 flex items-center justify-center py-2 bg-indigo-50 text-indigo-600 text-[10px] font-black rounded-xl hover:bg-indigo-600 hover:text-white transition-all italic">BUKA FILE</a>
                        <form action="" method="POST" onsubmit="return confirm('Hapus berkas ini?')">
                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" name="hapus" class="w-9 h-9 flex items-center justify-center text-rose-300 hover:text-rose-600 transition-all"><i class="fa fa-trash-alt text-xs"></i></button>
                        </form>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php if(mysqli_num_rows($perangkats) == 0): ?>
                    <div class="col-span-full py-20 text-center lux-card bg-slate-50/30 border-dashed border-2 border-slate-100 shadow-none">
                        <i class="fa fa-folder-open text-4xl text-slate-100 mb-4"></i>
                        <p class="text-slate-300 font-bold italic tracking-widest text-xs uppercase">Belum ada berkas</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
