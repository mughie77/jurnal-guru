<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka', 'guru', 'guru_bk']);

$page_title = "Aplikasi Sosiogram";
$message = ''; $message_type = '';

// Handle Create New Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_sesi'])) {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) throw new Exception("Token Keamanan Tidak Valid.");

        $kelas_id = (int)($_POST['kelas_id'] ?? 0);
        $judul = trim($_POST['judul'] ?? '');
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $keterangan = trim($_POST['keterangan'] ?? '');

        if ($kelas_id <= 0 || empty($judul)) {
            throw new Exception("Kelas dan Judul Sesi wajib diisi.");
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO bk_sosiogram_sesi (kelas_id, tahun_pelajaran_id, judul, tanggal, keterangan) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iisss", $kelas_id, $active_tahun_id, $judul, $tanggal, $keterangan);
        if (mysqli_stmt_execute($stmt)) {
            $sesi_id = mysqli_insert_id($conn);
            $message = "Sesi Sosiogram berhasil dibuat! Silakan input pilihan siswa.";
            $message_type = 'success';
            header("Location: " . basename($_SERVER['PHP_SELF']) . "?sesi_id=$sesi_id&created=1");
            exit;
        } else {
            throw new Exception("Gagal membuat sesi sosiogram.");
        }
    } catch (Exception $e) {
        $message = "Gagal: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle Save Student Choices
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_pilihan_siswa'])) {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) throw new Exception("Token Keamanan Tidak Valid.");

        $sesi_id = (int)($_POST['sesi_id'] ?? 0);
        $pemilih_id = (int)($_POST['pemilih_siswa_id'] ?? 0);
        $pilihan = $_POST['dipilih_id'] ?? []; // Array of student IDs in order [urutan_1, urutan_2, urutan_3]

        if ($sesi_id <= 0 || $pemilih_id <= 0) {
            throw new Exception("Sesi dan Pemilih wajib valid.");
        }

        // Delete existing choices for this voter in this session
        mysqli_query($conn, "DELETE FROM bk_sosiogram_pilihan WHERE sesi_id = $sesi_id AND pemilih_siswa_id = $pemilih_id");

        $stmt = mysqli_prepare($conn, "INSERT INTO bk_sosiogram_pilihan (sesi_id, pemilih_siswa_id, dipilih_siswa_id, urutan) VALUES (?, ?, ?, ?)");
        $urutan = 1;
        foreach ($pilihan as $dipilih_id) {
            $d_int = (int)$dipilih_id;
            if ($d_int > 0 && $d_int !== $pemilih_id) {
                mysqli_stmt_bind_param($stmt, "iiii", $sesi_id, $pemilih_id, $d_int, $urutan);
                mysqli_stmt_execute($stmt);
                $urutan++;
            }
        }
        $message = "Pilihan sosiometri siswa berhasil diperbarui!";
        $message_type = 'success';
    } catch (Exception $e) {
        $message = "Gagal: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle Delete Session
if (isset($_GET['action']) && $_GET['action'] === 'delete_sesi') {
    $del_id = (int)($_GET['id'] ?? 0);
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) die("CSRF Token Invalid");

    mysqli_query($conn, "DELETE FROM bk_sosiogram_pilihan WHERE sesi_id = $del_id");
    mysqli_query($conn, "DELETE FROM bk_sosiogram_sesi WHERE id = $del_id");
    header("Location: " . basename($_SERVER['PHP_SELF']) . "?deleted=1");
    exit;
}

if (isset($_GET['deleted'])) {
    $message = "Sesi Sosiogram berhasil dihapus!";
    $message_type = 'success';
}

// Fetch Sesi Lists
$q_sesi_list = "SELECT ss.*, k.nama_kelas,
                      (SELECT COUNT(DISTINCT pemilih_siswa_id) FROM bk_sosiogram_pilihan WHERE sesi_id = ss.id) as total_pengisi,
                      (SELECT COUNT(*) FROM siswa_kelas sk WHERE sk.kelas_id = ss.kelas_id AND sk.tahun_pelajaran_id = $active_tahun_id) as total_siswa
                FROM bk_sosiogram_sesi ss
                JOIN kelas k ON ss.kelas_id = k.id
                ORDER BY ss.tanggal DESC, ss.id DESC";
$res_sesi = mysqli_query($conn, $q_sesi_list);
$sesi_all = [];
while ($row = mysqli_fetch_assoc($res_sesi)) {
    $sesi_all[] = $row;
}

// Active Selected Session Details
$selected_sesi_id = (int)($_GET['sesi_id'] ?? ($sesi_all[0]['id'] ?? 0));
$active_sesi = null;
$class_students = [];
$choices_matrix = [];
$incoming_votes = [];
$mutual_choices = [];

if ($selected_sesi_id > 0) {
    $q_active = mysqli_query($conn, "SELECT ss.*, k.nama_kelas FROM bk_sosiogram_sesi ss JOIN kelas k ON ss.kelas_id = k.id WHERE ss.id = $selected_sesi_id LIMIT 1");
    $active_sesi = mysqli_fetch_assoc($q_active);

    if ($active_sesi) {
        $kid = (int)$active_sesi['kelas_id'];
        // Fetch students in class
        $res_st = mysqli_query($conn, "SELECT s.id, s.nama_siswa, s.nis, s.jenis_kelamin
                                       FROM siswa s
                                       JOIN siswa_kelas sk ON s.id = sk.siswa_id
                                       WHERE sk.kelas_id = $kid AND sk.tahun_pelajaran_id = $active_tahun_id
                                       ORDER BY s.nama_siswa ASC");
        while ($st = mysqli_fetch_assoc($res_st)) {
            $class_students[$st['id']] = $st;
            $incoming_votes[$st['id']] = 0;
        }

        // Fetch all choices for this session
        $res_ch = mysqli_query($conn, "SELECT * FROM bk_sosiogram_pilihan WHERE sesi_id = $selected_sesi_id ORDER BY urutan ASC");
        $raw_pilihan = [];
        while ($ch = mysqli_fetch_assoc($res_ch)) {
            $pem = (int)$ch['pemilih_siswa_id'];
            $dip = (int)$ch['dipilih_siswa_id'];
            $choices_matrix[$pem][$dip] = $ch['urutan'];
            $incoming_votes[$dip] = ($incoming_votes[$dip] ?? 0) + 1;
            $raw_pilihan[$pem][$dip] = true;
        }

        // Calculate Mutual choices
        foreach ($raw_pilihan as $p1 => $targets) {
            foreach ($targets as $p2 => $val) {
                if (isset($raw_pilihan[$p2][$p1]) && $p1 < $p2) {
                    $mutual_choices[] = [$p1, $p2];
                }
            }
        }
    }
}

// Fetch classes for new session
$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Sistem Aplikasi Sosiogram</h1>
        <p class="text-slate-500 font-medium">Pemetaan dinamika hubungan sosial dan tingkat popularitas siswa dalam kelas.</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <button onclick="openModal('addSesiModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-100 transition-all flex items-center text-xs sm:text-sm">
            <i class="fa fa-plus mr-2"></i> Buat Sesi Sosiogram
        </button>
    </div>
</div>

<?php if ($message): ?>
<script>
    Swal.fire({
        icon: '<?= $message_type ?>',
        title: '<?= $message_type === 'success' ? 'Berhasil' : 'Gagal' ?>',
        text: '<?= addslashes($message) ?>',
        confirmButtonColor: '#4F46E5'
    });
</script>
<?php endif; ?>

<!-- Sesi Selection Bar -->
<div class="lux-card p-6 mb-8 bg-white border border-slate-200">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex-1 w-full">
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Pilih Sesi Sosiogram</label>
            <select onchange="window.location.href='<?= basename($_SERVER['PHP_SELF']) ?>?sesi_id='+this.value" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-slate-800 text-sm shadow-sm">
                <?php foreach ($sesi_all as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $s['id'] == $selected_sesi_id ? 'selected' : '' ?>>
                        [Kelas <?= htmlspecialchars($s['nama_kelas']) ?>] <?= htmlspecialchars($s['judul']) ?> - <?= date('d M Y', strtotime($s['tanggal'])) ?> (<?= $s['total_pengisi'] ?>/<?= $s['total_siswa'] ?> Siswa Mengisi)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($active_sesi): ?>
        <div class="flex items-center gap-2 shrink-0">
            <a href="<?= BASE_URL ?>admin/print_bk_sosiogram.php?sesi_id=<?= $selected_sesi_id ?>" target="_blank" class="px-4 py-3 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-xl text-xs flex items-center gap-2 shadow-md">
                <i class="fa fa-print"></i> Cetak Sosiogram
            </a>
            <button onclick="confirmDeleteSesi(<?= $selected_sesi_id ?>, '<?= get_csrf_token() ?>')" class="px-4 py-3 bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white font-bold rounded-xl text-xs transition-all border border-rose-100">
                <i class="fa fa-trash"></i> Hapus Sesi
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($active_sesi): ?>

<!-- Summary Cards for Active Session -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="lux-card p-6 bg-white border border-slate-100">
        <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Total Siswa Kelas</p>
        <h3 class="text-3xl font-black text-slate-800 mt-1"><?= count($class_students) ?> <span class="text-xs text-slate-400 font-normal">Siswa</span></h3>
    </div>

    <div class="lux-card p-6 bg-white border border-slate-100">
        <p class="text-[10px] font-black uppercase text-indigo-500 tracking-widest">Siswa Sudah Memilih</p>
        <h3 class="text-3xl font-black text-indigo-600 mt-1"><?= count($choices_matrix) ?> <span class="text-xs text-indigo-400 font-normal">Siswa</span></h3>
    </div>

    <div class="lux-card p-6 bg-white border border-slate-100">
        <p class="text-[10px] font-black uppercase text-emerald-500 tracking-widest">Siswa Terpopuler / Star</p>
        <?php
        $max_vote = !empty($incoming_votes) ? max($incoming_votes) : 0;
        $star_names = [];
        if ($max_vote > 0) {
            foreach ($incoming_votes as $sid => $vc) {
                if ($vc === $max_vote && isset($class_students[$sid])) {
                    $star_names[] = $class_students[$sid]['nama_siswa'];
                }
            }
        }
        ?>
        <h3 class="text-xl font-black text-emerald-600 mt-1 truncate" title="<?= htmlspecialchars(implode(', ', $star_names)) ?>">
            <?= !empty($star_names) ? htmlspecialchars($star_names[0]) : '-' ?>
        </h3>
        <p class="text-[10px] text-slate-400 font-bold mt-1"><?= $max_vote ?> Dipilih Teman</p>
    </div>

    <div class="lux-card p-6 bg-white border border-slate-100">
        <p class="text-[10px] font-black uppercase text-rose-500 tracking-widest">Siswa Terisolasi / Unchosen</p>
        <?php
        $isolated_names = [];
        foreach ($incoming_votes as $sid => $vc) {
            if ($vc === 0 && isset($class_students[$sid])) {
                $isolated_names[] = $class_students[$sid]['nama_siswa'];
            }
        }
        ?>
        <h3 class="text-3xl font-black text-rose-600 mt-1"><?= count($isolated_names) ?> <span class="text-xs text-rose-400 font-normal">Siswa</span></h3>
        <p class="text-[10px] text-slate-400 font-bold mt-1">Belum Ada Yang Memilih</p>
    </div>
</div>

<!-- Tabs Grid Layout -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">

    <!-- Form Input Pilihan Siswa -->
    <div class="lux-card p-6 bg-white border border-slate-200 space-y-4">
        <h3 class="text-base font-black italic text-slate-800 flex items-center gap-2">
            <i class="fa fa-user-pen text-indigo-600"></i> Input Pilihan Sosiometri Siswa
        </h3>
        <p class="text-xs text-slate-500">Pilih siswa pemilih dan tentukan 3 teman pilihan terbaiknya dalam kelas.</p>

        <form action="" method="POST" class="space-y-4 pt-2">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            <input type="hidden" name="sesi_id" value="<?= $selected_sesi_id ?>">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Siswa Pemilih</label>
                <select name="pemilih_siswa_id" id="pemilih_siswa_id" onchange="loadStudentChoiceForm(this.value)" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-xs">
                    <option value="">-- Pilih Siswa --</option>
                    <?php foreach ($class_students as $st): ?>
                        <option value="<?= $st['id'] ?>" <?= isset($choices_matrix[$st['id']]) ? 'class="font-bold text-indigo-600"' : '' ?>>
                            <?= htmlspecialchars($st['nama_siswa']) ?> <?= isset($choices_matrix[$st['id']]) ? '(Sudah Mengisi)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-3 pt-2">
                <div>
                    <label class="block text-[10px] font-black text-indigo-600 uppercase tracking-wider mb-1">Pilihan Pertama (Prioritas 1)</label>
                    <select name="dipilih_id[]" id="dipilih_1" class="w-full px-4 py-2 rounded-xl border border-slate-200 outline-none font-bold text-slate-700 text-xs">
                        <option value="">-- Pilih Teman Pilihan 1 --</option>
                        <?php foreach ($class_students as $st): ?>
                            <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['nama_siswa']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-indigo-600 uppercase tracking-wider mb-1">Pilihan Kedua (Prioritas 2)</label>
                    <select name="dipilih_id[]" id="dipilih_2" class="w-full px-4 py-2 rounded-xl border border-slate-200 outline-none font-bold text-slate-700 text-xs">
                        <option value="">-- Pilih Teman Pilihan 2 --</option>
                        <?php foreach ($class_students as $st): ?>
                            <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['nama_siswa']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-indigo-600 uppercase tracking-wider mb-1">Pilihan Ketiga (Prioritas 3)</label>
                    <select name="dipilih_id[]" id="dipilih_3" class="w-full px-4 py-2 rounded-xl border border-slate-200 outline-none font-bold text-slate-700 text-xs">
                        <option value="">-- Pilih Teman Pilihan 3 --</option>
                        <?php foreach ($class_students as $st): ?>
                            <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['nama_siswa']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" name="simpan_pilihan_siswa" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-lg shadow-indigo-100 transition-all">
                    Simpan Pilihan Sosiometri
                </button>
            </div>
        </form>
    </div>

    <!-- Pilihan Saling & Siswa Terisolasi Card -->
    <div class="lg:col-span-2 space-y-6">
        <div class="lux-card p-6 bg-white border border-slate-200">
            <h3 class="text-base font-black italic text-slate-800 mb-3 flex items-center gap-2">
                <i class="fa fa-handshake text-emerald-600"></i> Pilihan Saling (Mutual Choices)
            </h3>

            <?php if (empty($mutual_choices)): ?>
                <p class="text-xs text-slate-400 italic">Belum ada pilihan saling antar siswa.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php foreach ($mutual_choices as $mc):
                        $st1 = $class_students[$mc[0]]['nama_siswa'] ?? 'Siswa 1';
                        $st2 = $class_students[$mc[1]]['nama_siswa'] ?? 'Siswa 2';
                    ?>
                    <div class="p-3 bg-emerald-50 border border-emerald-100 rounded-xl flex items-center justify-between text-xs font-bold text-emerald-900">
                        <span><?= htmlspecialchars($st1) ?></span>
                        <i class="fa fa-exchange-alt text-emerald-500 mx-2"></i>
                        <span><?= htmlspecialchars($st2) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="lux-card p-6 bg-white border border-slate-200">
            <h3 class="text-base font-black italic text-slate-800 mb-3 flex items-center gap-2">
                <i class="fa fa-user-slash text-rose-600"></i> Daftar Siswa Terisolasi / Perlu Perhatian Khusus
            </h3>

            <?php if (empty($isolated_names)): ?>
                <p class="text-xs text-emerald-600 font-bold">Seluruh siswa dalam kelas mendapatkan minimal 1 pilihan dari temannya.</p>
            <?php else: ?>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($isolated_names as $in): ?>
                        <span class="px-3 py-1.5 bg-rose-50 border border-rose-200 rounded-xl text-xs font-bold text-rose-700 flex items-center gap-1.5">
                            <i class="fa fa-exclamation-circle text-rose-500"></i> <?= htmlspecialchars($in) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Matriks Tabel Sosiometri -->
<div class="lux-card overflow-hidden bg-white shadow-2xl rounded-3xl mb-8">
    <div class="p-6 bg-slate-900 text-white flex items-center justify-between">
        <h3 class="text-base font-black italic">Tabel Matriks Pilihan Sosiometri</h3>
        <span class="text-xs text-slate-400 font-bold">Angka 1, 2, 3 menunjukkan urutan prioritas pilihan</span>
    </div>

    <div class="overflow-x-auto p-4">
        <table class="w-full text-center border-collapse text-xs">
            <thead>
                <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold">
                    <th class="p-2 border border-slate-200 text-left min-w-[150px]">Nama Siswa (Pemilih \ Dipilih)</th>
                    <?php foreach ($class_students as $sid => $st): ?>
                        <th class="p-2 border border-slate-200 text-center font-bold" title="<?= htmlspecialchars($st['nama_siswa']) ?>">
                            <?= strtoupper(substr($st['nama_siswa'], 0, 3)) ?>
                        </th>
                    <?php endforeach; ?>
                    <th class="p-2 border border-slate-200 bg-indigo-50 text-indigo-800 font-black">Total Pilihan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($class_students as $pem_id => $pem_st): ?>
                <tr class="hover:bg-slate-50 border-b border-slate-100">
                    <td class="p-2 border border-slate-200 font-bold text-slate-800 text-left truncate max-w-[180px]" title="<?= htmlspecialchars($pem_st['nama_siswa']) ?>">
                        <?= htmlspecialchars($pem_st['nama_siswa']) ?>
                    </td>
                    <?php foreach ($class_students as $dip_id => $dip_st): ?>
                        <td class="p-2 border border-slate-200 font-bold <?= $pem_id === $dip_id ? 'bg-slate-200' : '' ?>">
                            <?php if ($pem_id === $dip_id): ?>
                                -
                            <?php elseif (isset($choices_matrix[$pem_id][$dip_id])): ?>
                                <span class="w-6 h-6 inline-flex items-center justify-center rounded-md bg-indigo-600 text-white font-black">
                                    <?= $choices_matrix[$pem_id][$dip_id] ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="p-2 border border-slate-200 font-black bg-indigo-50/50 text-indigo-700">
                        <?= $incoming_votes[$pem_id] ?? 0 ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const sessionChoicesMap = <?= json_encode($choices_matrix) ?>;

function loadStudentChoiceForm(pemilihId) {
    const p1 = document.getElementById('dipilih_1');
    const p2 = document.getElementById('dipilih_2');
    const p3 = document.getElementById('dipilih_3');

    p1.value = ''; p2.value = ''; p3.value = '';

    if (!pemilihId || !sessionChoicesMap[pemilihId]) return;

    const userChoices = sessionChoicesMap[pemilihId];
    for (const [dipilihId, urutan] of Object.entries(userChoices)) {
        if (urutan == 1) p1.value = dipilihId;
        if (urutan == 2) p2.value = dipilihId;
        if (urutan == 3) p3.value = dipilihId;
    }
}
</script>

<?php endif; ?>

<!-- Modal Add Sesi -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<div id="addSesiModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-md bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="bg-indigo-600 px-6 py-5 text-white font-bold italic text-lg sticky top-0 z-10 flex justify-between items-center">
        <span>Buat Sesi Sosiogram Baru</span>
        <button type="button" onclick="closeModal('addSesiModal')" class="text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>

    <form action="" method="POST" class="p-6 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Kelas</label>
            <select name="kelas_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-xs">
                <option value="">-- Pilih Kelas --</option>
                <?php mysqli_data_seek($kelases, 0); while ($k = mysqli_fetch_assoc($kelases)): ?>
                    <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Judul / Topik Sesi Sosiometri</label>
            <input type="text" name="judul" required placeholder="Contoh: Pemetaan Teman Belajar Kelompok" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-xs">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Pelaksanaan</label>
            <input type="date" name="tanggal" required value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-xs">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Keterangan / Tujuan</label>
            <textarea name="keterangan" rows="2" placeholder="Catatan opsional..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-medium text-slate-700 text-xs"></textarea>
        </div>

        <div class="pt-4 flex gap-3">
            <button type="button" onclick="closeModal('addSesiModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600 text-xs">Batal</button>
            <button type="submit" name="tambah_sesi" class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100 text-xs">Buat Sesi</button>
        </div>
    </form>
</div>

<script>
function openModal(id) {
    const modal = document.getElementById(id);
    const overlay = document.getElementById('modalOverlay');
    overlay.classList.remove('hidden');
    modal.classList.remove('hidden');
    setTimeout(() => {
        overlay.classList.add('opacity-100');
        modal.classList.remove('scale-95', 'opacity-0');
    }, 10);
}

function closeModal(id) {
    const modal = document.getElementById(id);
    const overlay = document.getElementById('modalOverlay');
    overlay.classList.remove('opacity-100');
    modal.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        overlay.classList.add('hidden');
        modal.classList.add('hidden');
    }, 300);
}

function closeAllModals() {
    document.querySelectorAll('.modal-content').forEach(m => {
        if (!m.classList.contains('hidden')) closeModal(m.id);
    });
}

function confirmDeleteSesi(id, csrfToken) {
    Swal.fire({
        title: 'Hapus Sesi Sosiogram?',
        text: 'Seluruh data pilihan sosiometri siswa pada sesi ini akan dihapus permanen!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus Sesi!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '<?= basename($_SERVER['PHP_SELF']) ?>?action=delete_sesi&id=' + id + '&csrf_token=' + csrfToken;
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
