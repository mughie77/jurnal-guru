<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka', 'guru', 'guru_bk']);

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$page_title = "Form RPL Bimbingan Kelompok BK";

$message = ''; $message_type = '';
$edit_id = (int)($_GET['id'] ?? 0);
$is_edit = $edit_id > 0;

// Fetch guru ID
$guru_id = 0;
if ($role === 'guru') {
    $res_g = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id LIMIT 1");
    if ($res_g && $r_g = mysqli_fetch_assoc($res_g)) {
        $guru_id = (int)$r_g['id'];
    }
}

// Default values for Bimbingan Kelompok
$data = [
    'tanggal' => date('Y-m-d'),
    'jenis_bimbingan' => 'Kelompok',
    'siswa_ids' => [],
    'kelas_id' => 0,
    'guru_id' => $guru_id,
    'waktu' => '45 menit',
    'tempat' => 'Ruang BK / Kelas',
    'kompetensi_layanan' => 'Pengembangan Pribadi & Sosial',
    'bidang_layanan' => 'Pribadi / Sosial',
    'fungsi_layanan' => 'Pemahaman dan Pengembangan',
    'topik_permasalahan' => '',
    'tujuan_umum' => '',
    'tujuan_khusus' => '',
    'sasaran_layanan' => 'Siswa Anggota Kelompok',
    'materi_layanan' => '',
    'sumber_layanan' => 'Buku Bimbingan Kelompok, Internet, Jurnal',
    'metode_teknik' => 'Diskusi Kelompok, Dynamic Group, Brainstorming',
    'media_alat' => 'PPT, Video Pembelajaran, Kertas Kerja',
    'pelaksanaan' => [
        'pembentukan' => "• Mengucapkan salam dan menyapa anggota kelompok\n• Berdoa bersama sebelum memulai kegiatan\n• Menjelaskan pengertian dan tujuan Bimbingan Kelompok\n• Menjelaskan cara pelaksanaan bimbingan kelompok\n• Menjelaskan asas-asas bimbingan kelompok (kerahasiaan, keterbukaan, kesukarelaan)\n• Perkenalan dan permainan pemanasan (ice breaking)",
        'peralihan' => "• Menjelaskan kembali kegiatan yang akan ditempuh pada tahap selanjutnya\n• Memastikan kesiapan anggota kelompok untuk memasuki tahap inti\n• Mengaitkan materi pemanasan dengan topik yang akan dibahas",
        'inti' => "• Gurub BK/Konselor menyampaikan topik bimbingan kelompok\n• Anggota kelompok mendiskusikan topik secara aktif\n• Mengemukakan pendapat, tanggapan, dan masukan antar anggota\n• Konselor memberikan penguatan dan fasilitasi diskusi agar berjalan kondusif",
        'pengakhiran' => "• Konselor menginformasikan bahwa kegiatan bimbingan kelompok akan segera berakhir\n• Anggota kelompok menyampaikan kesan dan pesan serta kesimpulan hasil diskusi\n• Merencanakan kegiatan tindak lanjut apabila diperlukan\n• Mengucapkan terima kasih dan berdoa bersama diakhiri salam"
    ],
    'evaluasi_proses' => "Mengamati sikap, antusiasme, keaktifan, dan keterbukaan anggota kelompok selama proses bimbingan berlangsung.",
    'evaluasi_hasil' => "Mengukur pemahaman baru, perasaan positif, dan rencana tindakan yang akan dilakukan oleh masing-masing anggota kelompok.",
    'status' => 'Selesai'
];

if ($is_edit) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM bk_laporan WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $edit_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($r = mysqli_fetch_assoc($res)) {
        foreach ($r as $k => $v) {
            if ($k === 'siswa_ids_json' && !empty($v)) {
                $decoded = json_decode($v, true);
                if (is_array($decoded)) $data['siswa_ids'] = array_map('intval', $decoded);
            } elseif ($k === 'pelaksanaan_json' && !empty($v)) {
                $decoded = json_decode($v, true);
                if (is_array($decoded)) $data['pelaksanaan'] = $decoded;
            } elseif ($v !== null) {
                $data[$k] = $v;
            }
        }
    } else {
        die("Data laporan bimbingan kelompok tidak ditemukan.");
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_rpl_kelompok'])) {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) throw new Exception("Token Keamanan Tidak Valid.");

        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $siswa_ids_input = $_POST['siswa_ids'] ?? [];
        $siswa_ids = array_map('intval', is_array($siswa_ids_input) ? $siswa_ids_input : []);
        $post_guru_id = (int)($_POST['guru_id'] ?? $guru_id);
        if ($post_guru_id > 0) $guru_id = $post_guru_id;

        $kelas_id = (int)($_POST['kelas_id'] ?? 0);
        $waktu = trim($_POST['waktu'] ?? '45 menit');
        $tempat = trim($_POST['tempat'] ?? 'Ruang BK / Kelas');

        $kompetensi_layanan = trim($_POST['kompetensi_layanan'] ?? '');
        $bidang_layanan = trim($_POST['bidang_layanan'] ?? '');
        $fungsi_layanan = trim($_POST['fungsi_layanan'] ?? '');
        $topik_permasalahan = trim($_POST['topik_permasalahan'] ?? '');
        $tujuan_umum = trim($_POST['tujuan_umum'] ?? '');
        $tujuan_khusus = trim($_POST['tujuan_khusus'] ?? '');
        $sasaran_layanan = trim($_POST['sasaran_layanan'] ?? '');
        $materi_layanan = trim($_POST['materi_layanan'] ?? '');
        $sumber_layanan = trim($_POST['sumber_layanan'] ?? '');
        $metode_teknik = trim($_POST['metode_teknik'] ?? '');
        $media_alat = trim($_POST['media_alat'] ?? '');
        $status = $_POST['status'] ?? 'Selesai';

        $pelaksanaan_input = $_POST['pelaksanaan'] ?? [];
        $pelaksanaan_arr = [
            'pembentukan' => trim($pelaksanaan_input['pembentukan'] ?? ''),
            'peralihan' => trim($pelaksanaan_input['peralihan'] ?? ''),
            'inti' => trim($pelaksanaan_input['inti'] ?? ''),
            'pengakhiran' => trim($pelaksanaan_input['pengakhiran'] ?? '')
        ];
        $pelaksanaan_json = json_encode($pelaksanaan_arr, JSON_UNESCAPED_UNICODE);

        $evaluasi_proses = trim($_POST['evaluasi_proses'] ?? '');
        $evaluasi_hasil = trim($_POST['evaluasi_hasil'] ?? '');

        if (empty($siswa_ids)) throw new Exception("Minimal pilih 1 siswa anggota kelompok.");
        if (empty($topik_permasalahan)) throw new Exception("Topik / Bahasan Bimbingan Kelompok wajib diisi.");

        $siswa_ids_json = json_encode($siswa_ids);
        $first_siswa_id = $siswa_ids[0]; // representative for legacy joins
        $ringkasan = "Sesi Bimbingan Kelompok dengan topik: " . $topik_permasalahan;

        if ($is_edit) {
            $stmt = mysqli_prepare($conn, "UPDATE bk_laporan SET
                tanggal=?, siswa_id=?, guru_id=?, kelas_id=?, waktu=?, tempat=?,
                topik_permasalahan=?, ringkasan_pembahasan=?, status=?,
                kompetensi_layanan=?, bidang_layanan=?, fungsi_layanan=?,
                tujuan_umum=?, tujuan_khusus=?, sasaran_layanan=?, materi_layanan=?,
                sumber_layanan=?, metode_teknik=?, media_alat=?,
                siswa_ids_json=?, pelaksanaan_json=?, evaluasi_proses=?, evaluasi_hasil=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "siiisssssssssssssssssssi",
                $tanggal, $first_siswa_id, $guru_id, $kelas_id, $waktu, $tempat,
                $topik_permasalahan, $ringkasan, $status,
                $kompetensi_layanan, $bidang_layanan, $fungsi_layanan,
                $tujuan_umum, $tujuan_khusus, $sasaran_layanan, $materi_layanan,
                $sumber_layanan, $metode_teknik, $media_alat,
                $siswa_ids_json, $pelaksanaan_json, $evaluasi_proses, $evaluasi_hasil, $edit_id);
            if (mysqli_stmt_execute($stmt)) {
                header("Location: " . BASE_URL . "admin/bk_laporan.php?jenis=Kelompok&success_save=1");
                exit;
            } else {
                throw new Exception("Gagal memperbarui laporan RPL Bimbingan Kelompok.");
            }
        } else {
            $jenis_bimbingan = 'Kelompok';
            $stmt = mysqli_prepare($conn, "INSERT INTO bk_laporan (
                tanggal, jenis_bimbingan, siswa_id, guru_id, kelas_id, waktu, tempat,
                topik_permasalahan, ringkasan_pembahasan, status,
                kompetensi_layanan, bidang_layanan, fungsi_layanan,
                tujuan_umum, tujuan_khusus, sasaran_layanan, materi_layanan,
                sumber_layanan, metode_teknik, media_alat,
                siswa_ids_json, pelaksanaan_json, evaluasi_proses, evaluasi_hasil
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssiiisssssssssssssssssss",
                $tanggal, $jenis_bimbingan, $first_siswa_id, $guru_id, $kelas_id, $waktu, $tempat,
                $topik_permasalahan, $ringkasan, $status,
                $kompetensi_layanan, $bidang_layanan, $fungsi_layanan,
                $tujuan_umum, $tujuan_khusus, $sasaran_layanan, $materi_layanan,
                $sumber_layanan, $metode_teknik, $media_alat,
                $siswa_ids_json, $pelaksanaan_json, $evaluasi_proses, $evaluasi_hasil);
            if (mysqli_stmt_execute($stmt)) {
                header("Location: " . BASE_URL . "admin/bk_laporan.php?jenis=Kelompok&success_save=1");
                exit;
            } else {
                throw new Exception("Gagal menyimpan laporan RPL Bimbingan Kelompok baru.");
            }
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// Fetch active classes & students
$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
$students = mysqli_query($conn, "SELECT s.id, s.nama_siswa, s.nis, k.nama_kelas, sk.kelas_id
                                 FROM siswa s
                                 JOIN siswa_kelas sk ON s.id = sk.siswa_id
                                 JOIN kelas k ON sk.kelas_id = k.id
                                 JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id
                                 WHERE tp.status = 'aktif'
                                 ORDER BY k.nama_kelas ASC, s.nama_siswa ASC");

$student_list = [];
while ($st = mysqli_fetch_assoc($students)) {
    $student_list[] = $st;
}

$gurus = mysqli_query($conn, "SELECT g.id, u.nama_lengkap FROM guru g JOIN users u ON g.user_id = u.id ORDER BY u.nama_lengkap ASC");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 py-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight italic">
                <?= $is_edit ? 'Edit RPL Bimbingan Kelompok' : 'Tambah RPL Bimbingan Kelompok' ?>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">Formulir Rencana Pelaksanaan Layanan (RPL) Bimbingan Kelompok secara komprehensif.</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>admin/bk_laporan.php?jenis=Kelompok" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2.5 rounded-xl font-bold text-xs flex items-center shadow-md">
                <i class="fa fa-arrow-left mr-2"></i> Kembali ke Rekap BK
            </a>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="p-4 mb-6 rounded-2xl bg-rose-50 text-rose-700 text-xs font-bold border border-rose-200">
        <i class="fa fa-exclamation-circle mr-2"></i> <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Step Wizard Navigation Indicator -->
    <div class="bg-white rounded-3xl shadow-xl p-4 sm:p-6 mb-8 border border-slate-100">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center">
            <button type="button" onclick="goToStep(1)" id="step-btn-1" class="step-btn py-3 px-2 rounded-2xl font-bold text-xs transition-all bg-teal-600 text-white shadow-md shadow-teal-100 flex flex-col items-center justify-center gap-1">
                <span class="w-6 h-6 rounded-full bg-white/20 flex items-center justify-center text-[11px] font-black">1</span>
                <span>Identitas & Anggota</span>
            </button>
            <button type="button" onclick="goToStep(2)" id="step-btn-2" class="step-btn py-3 px-2 rounded-2xl font-bold text-xs transition-all bg-slate-100 text-slate-500 hover:bg-slate-200 flex flex-col items-center justify-center gap-1">
                <span class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center text-[11px] font-black">2</span>
                <span>Tujuan & Materi</span>
            </button>
            <button type="button" onclick="goToStep(3)" id="step-btn-3" class="step-btn py-3 px-2 rounded-2xl font-bold text-xs transition-all bg-slate-100 text-slate-500 hover:bg-slate-200 flex flex-col items-center justify-center gap-1">
                <span class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center text-[11px] font-black">3</span>
                <span>Tahap Pelaksanaan</span>
            </button>
            <button type="button" onclick="goToStep(4)" id="step-btn-4" class="step-btn py-3 px-2 rounded-2xl font-bold text-xs transition-all bg-slate-100 text-slate-500 hover:bg-slate-200 flex flex-col items-center justify-center gap-1">
                <span class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center text-[11px] font-black">4</span>
                <span>Evaluasi & Simpan</span>
            </button>
        </div>
    </div>

    <!-- Main Form -->
    <form action="" method="POST" id="rplKelompokForm" class="bg-white rounded-3xl shadow-2xl p-6 sm:p-8 border border-slate-100">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

        <!-- STEP 1: Identitas Layanan & Anggota Kelompok -->
        <div id="step-1" class="step-content space-y-5">
            <div class="border-b border-slate-100 pb-4 mb-4">
                <h2 class="text-lg font-black text-slate-800 italic">Tahap 1: Identitas Sesi & Anggota Kelompok</h2>
                <p class="text-xs text-slate-400">Tentukan jadwal, kelas target, dan pilih siswa-siswi yang menjadi anggota kelompok.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Tanggal Sesi <span class="text-rose-500">*</span></label>
                    <input type="date" name="tanggal" value="<?= htmlspecialchars($data['tanggal']) ?>" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-bold text-xs text-slate-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Kelas Target / Utama</label>
                    <select name="kelas_id" id="kelas_id" onchange="filterStudentsByClass()" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-bold text-xs text-slate-700 bg-white">
                        <option value="0">-- Semua Kelas / Lintas Kelas --</option>
                        <?php mysqli_data_seek($kelases, 0); while ($k = mysqli_fetch_assoc($kelases)): ?>
                            <option value="<?= $k['id'] ?>" <?= $k['id'] == $data['kelas_id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Alokasi Waktu Sesi</label>
                    <input type="text" name="waktu" value="<?= htmlspecialchars($data['waktu']) ?>" placeholder="e.g. 1 x 45 menit" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-bold text-xs text-slate-700">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Tempat Pelaksanaan</label>
                    <input type="text" name="tempat" value="<?= htmlspecialchars($data['tempat']) ?>" placeholder="e.g. Ruang BK" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-bold text-xs text-slate-700">
                </div>

                <?php if ($role === 'admin' || $role === 'waka'): ?>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Guru BK / Pemimpin Kelompok</label>
                    <select name="guru_id" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-bold text-xs text-slate-700 bg-white">
                        <option value="">-- Pilih Konselor --</option>
                        <?php mysqli_data_seek($gurus, 0); while ($g = mysqli_fetch_assoc($gurus)): ?>
                            <option value="<?= $g['id'] ?>" <?= $g['id'] == $data['guru_id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_lengkap']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <!-- Student Selection Grid -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-bold text-slate-700">Pilih Siswa Anggota Kelompok <span class="text-rose-500">*</span></label>
                    <span id="selected-count" class="text-[11px] font-bold text-teal-600 bg-teal-50 px-2.5 py-1 rounded-full border border-teal-100">0 Siswa Terpilih</span>
                </div>
                <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 max-h-60 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-2" id="student_list_box">
                    <?php foreach ($student_list as $st):
                        $isChecked = in_array((int)$st['id'], $data['siswa_ids']);
                    ?>
                    <label class="student-item flex items-center gap-3 p-2.5 rounded-xl border border-slate-200 bg-white hover:border-teal-300 transition-all cursor-pointer" data-kelas-id="<?= $st['kelas_id'] ?>">
                        <input type="checkbox" name="siswa_ids[]" value="<?= $st['id'] ?>" <?= $isChecked ? 'checked' : '' ?> onchange="updateSelectedCount()" class="student-checkbox accent-teal-600 w-4 h-4 rounded">
                        <div class="text-xs">
                            <div class="font-extrabold text-slate-800"><?= htmlspecialchars($st['nama_siswa']) ?></div>
                            <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($st['nama_kelas']) ?> | NIS: <?= htmlspecialchars($st['nis']) ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex justify-end pt-4">
                <button type="button" onclick="goToStep(2)" class="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-2xl text-xs shadow-lg shadow-teal-100 flex items-center">
                    Lanjut ke Tahap 2 <i class="fa fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- STEP 2: Parameter Layanan, Tujuan & Materi -->
        <div id="step-2" class="step-content space-y-5 hidden">
            <div class="border-b border-slate-100 pb-4 mb-4">
                <h2 class="text-lg font-black text-slate-800 italic">Tahap 2: Bidang, Topik, Tujuan & Media</h2>
                <p class="text-xs text-slate-400">Spesifikasi bidang layanan, rumusan tujuan umum/khusus, dan materi pendukung.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Kompetensi Layanan</label>
                    <input type="text" name="kompetensi_layanan" value="<?= htmlspecialchars($data['kompetensi_layanan']) ?>" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-bold text-xs text-slate-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Bidang Layanan</label>
                    <select name="bidang_layanan" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-bold text-xs text-slate-700 bg-white">
                        <option value="Pribadi" <?= $data['bidang_layanan'] === 'Pribadi' ? 'selected' : '' ?>>Pribadi</option>
                        <option value="Sosial" <?= $data['bidang_layanan'] === 'Sosial' ? 'selected' : '' ?>>Sosial</option>
                        <option value="Belajar" <?= $data['bidang_layanan'] === 'Belajar' ? 'selected' : '' ?>>Belajar</option>
                        <option value="Karir" <?= $data['bidang_layanan'] === 'Karir' ? 'selected' : '' ?>>Karir</option>
                        <option value="Pribadi / Sosial" <?= $data['bidang_layanan'] === 'Pribadi / Sosial' ? 'selected' : '' ?>>Pribadi / Sosial</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Fungsi Layanan</label>
                    <input type="text" name="fungsi_layanan" value="<?= htmlspecialchars($data['fungsi_layanan']) ?>" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-bold text-xs text-slate-700">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Topik / Bahasan Bimbingan Kelompok <span class="text-rose-500">*</span></label>
                <input type="text" name="topik_permasalahan" value="<?= htmlspecialchars($data['topik_permasalahan']) ?>" required placeholder="e.g. Pentingnya Menjaga Komunikasi dan Kedisiplinan Kelompok" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-bold text-xs text-slate-800">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Tujuan Umum</label>
                    <textarea name="tujuan_umum" rows="3" placeholder="Uraian tujuan umum Bimbingan Kelompok..." class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['tujuan_umum']) ?></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Tujuan Khusus</label>
                    <textarea name="tujuan_khusus" rows="3" placeholder="Uraian tujuan khusus Bimbingan Kelompok..." class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['tujuan_khusus']) ?></textarea>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Materi Bimbingan Kelompok</label>
                <textarea name="materi_layanan" rows="3" placeholder="Poin ringkas materi yang didiskusikan dalam kelompok..." class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['materi_layanan']) ?></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Metode & Teknik</label>
                    <input type="text" name="metode_teknik" value="<?= htmlspecialchars($data['metode_teknik']) ?>" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-bold text-xs text-slate-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Media & Alat</label>
                    <input type="text" name="media_alat" value="<?= htmlspecialchars($data['media_alat']) ?>" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-bold text-xs text-slate-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Sumber Bacaan / Ref</label>
                    <input type="text" name="sumber_layanan" value="<?= htmlspecialchars($data['sumber_layanan']) ?>" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-bold text-xs text-slate-700">
                </div>
            </div>

            <div class="flex justify-between pt-4">
                <button type="button" onclick="goToStep(1)" class="px-6 py-3 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-2xl text-xs flex items-center">
                    <i class="fa fa-arrow-left mr-2"></i> Kembali
                </button>
                <button type="button" onclick="goToStep(3)" class="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-2xl text-xs shadow-lg shadow-teal-100 flex items-center">
                    Lanjut ke Tahap Pelaksanaan <i class="fa fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- STEP 3: Tahap Pelaksanaan (Pembentukan, Peralihan, Inti, Pengakhiran) -->
        <div id="step-3" class="step-content space-y-5 hidden">
            <div class="border-b border-slate-100 pb-4 mb-4">
                <h2 class="text-lg font-black text-slate-800 italic">Tahap 3: Pelaksanaan Sesi Bimbingan Kelompok</h2>
                <p class="text-xs text-slate-400">Langkah operasional kegiatan kelompok dari awal hingga pengakhiran.</p>
            </div>

            <div>
                <label class="block text-xs font-black text-teal-700 uppercase tracking-wider mb-2">1. Tahap Pembentukan (Orientation)</label>
                <textarea name="pelaksanaan[pembentukan]" rows="4" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['pelaksanaan']['pembentukan'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="block text-xs font-black text-teal-700 uppercase tracking-wider mb-2">2. Tahap Peralihan (Transition)</label>
                <textarea name="pelaksanaan[peralihan]" rows="3" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['pelaksanaan']['peralihan'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="block text-xs font-black text-teal-700 uppercase tracking-wider mb-2">3. Tahap Inti / Kegiatan (Working)</label>
                <textarea name="pelaksanaan[inti]" rows="4" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['pelaksanaan']['inti'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="block text-xs font-black text-teal-700 uppercase tracking-wider mb-2">4. Tahap Pengakhiran (Termination)</label>
                <textarea name="pelaksanaan[pengakhiran]" rows="3" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['pelaksanaan']['pengakhiran'] ?? '') ?></textarea>
            </div>

            <div class="flex justify-between pt-4">
                <button type="button" onclick="goToStep(2)" class="px-6 py-3 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-2xl text-xs flex items-center">
                    <i class="fa fa-arrow-left mr-2"></i> Kembali
                </button>
                <button type="button" onclick="goToStep(4)" class="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-2xl text-xs shadow-lg shadow-teal-100 flex items-center">
                    Lanjut ke Evaluasi <i class="fa fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- STEP 4: Evaluasi & Simpan -->
        <div id="step-4" class="step-content space-y-5 hidden">
            <div class="border-b border-slate-100 pb-4 mb-4">
                <h2 class="text-lg font-black text-slate-800 italic">Tahap 4: Evaluasi & Konfirmasi Simpan</h2>
                <p class="text-xs text-slate-400">Rencana evaluasi proses dan hasil bimbingan kelompok.</p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Evaluasi Proses</label>
                <textarea name="evaluasi_proses" rows="3" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['evaluasi_proses']) ?></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Evaluasi Hasil</label>
                <textarea name="evaluasi_hasil" rows="3" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['evaluasi_hasil']) ?></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Status Layanan</label>
                <select name="status" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-teal-100 font-bold text-xs text-slate-700 bg-white">
                    <option value="Selesai" <?= $data['status'] === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                    <option value="Proses" <?= $data['status'] === 'Proses' ? 'selected' : '' ?>>Dalam Proses / Berlanjut</option>
                </select>
            </div>

            <div class="flex justify-between pt-6 border-t border-slate-100">
                <button type="button" onclick="goToStep(3)" class="px-6 py-3 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-2xl text-xs flex items-center">
                    <i class="fa fa-arrow-left mr-2"></i> Kembali
                </button>
                <button type="submit" name="simpan_rpl_kelompok" class="px-8 py-3.5 bg-teal-600 hover:bg-teal-700 text-white font-black rounded-2xl text-xs shadow-xl shadow-teal-100 flex items-center">
                    <i class="fa fa-save mr-2"></i> Simpan RPL Bimbingan Kelompok
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function goToStep(step) {
    document.querySelectorAll('.step-content').forEach(el => el.classList.add('hidden'));
    document.getElementById('step-' + step).classList.remove('hidden');

    for (let i = 1; i <= 4; i++) {
        const btn = document.getElementById('step-btn-' + i);
        if (i === step) {
            btn.className = 'step-btn py-3 px-2 rounded-2xl font-bold text-xs transition-all bg-teal-600 text-white shadow-md shadow-teal-100 flex flex-col items-center justify-center gap-1';
        } else {
            btn.className = 'step-btn py-3 px-2 rounded-2xl font-bold text-xs transition-all bg-slate-100 text-slate-500 hover:bg-slate-200 flex flex-col items-center justify-center gap-1';
        }
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.student-checkbox:checked');
    const badge = document.getElementById('selected-count');
    if (badge) badge.textContent = checkboxes.length + ' Siswa Terpilih';
}

function filterStudentsByClass() {
    const classId = document.getElementById('kelas_id').value;
    const items = document.querySelectorAll('.student-item');

    items.forEach(item => {
        const itemClass = item.getAttribute('data-kelas-id');
        if (classId === '0' || itemClass === classId) {
            item.classList.remove('hidden');
        } else {
            item.classList.add('hidden');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    updateSelectedCount();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
