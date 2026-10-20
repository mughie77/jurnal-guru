<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka', 'guru', 'guru_bk']);

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$page_title = "Form RPL Konseling Individual BK";

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

// Default values
$data = [
    'tanggal' => date('Y-m-d'),
    'jenis_bimbingan' => 'Individu',
    'siswa_id' => 0,
    'guru_id' => $guru_id,
    'kode_konseli' => '',
    'pertemuan_ke' => 1,
    'waktu' => '45 menit',
    'tempat' => 'Ruang BK',
    'topik_permasalahan' => '',
    'ringkasan_pembahasan' => '',
    'tindak_lanjut' => '',
    'status' => 'Proses',
    'deskripsi_kasus' => '',
    'tujuan_konseling' => '',
    'tahapan_konseling' => "1. Rasional strategi\n2. Identifikasi keadaan\n3. Memahami pentingnya penerapan pola hidup sehat\n4. Melaksanakan latihan/praktik\n5. Mengulang latihan\n6. Tugas rumah dan tindak lanjut\n7. Terminasi",
    'jumlah_pertemuan' => '1 kali pertemuan',
    'durasi_pertemuan' => '45 menit',
    'media_sumber' => '-',
    'pihak_dilibatkan' => 'Wali Kelas',
    'rumusan_masalah' => '',
    'masalah_analisis' => '',
    'pendekatan_teknik' => 'Teori Behavioral dengan teknik konseling Eksplorasi',
    'hasil_dicapai' => '',
    'langkah_kegiatan' => [
        ['langkah' => 'Langkah 1 : Rasional strategi', 'isi' => "• Konselor memberikan rasional/menjelaskan maksud penggunaan strategi\n• Konselor memberikan overview tahapan-tahapan implementasi strategi"],
        ['langkah' => 'Langkah 2 : Identifikasi keadaan yang menimbulkan persoalan', 'isi' => "• Konselor meminta konseli menceritakan secara terbuka permasalahan yang dihadapi dan sesuatu yang dilakukan atau dipikirkan pada saat permasalahan timbul"],
        ['langkah' => 'Langkah 3 : Pemahaman penerapan pola hidup sehat untuk kondisi kesehatan', 'isi' => "• Konselor memberi pemahaman tentang bagaimana mengukur tingkat ideal berat badan dengan tinggi badan\n• Penerapan pola diet harus memenuhi kebutuhan gizi, nutrisi yang diperlukan tubuh\n• Tindakan yang dilakukan dapat merugikan Kesehatan di masa yang akan datang"],
        ['langkah' => 'Langkah 4 : Bermain peran, pemberian umpan balik serta pemberian model pembelajaran yang lebih baik', 'isi' => "• Konseli bermain peran sesuai dengan permasalahan yang dihadapi\n• Konselor memberi umpan balik secara verbal\n• Pemberian model perilaku yang lebih baik\n• Pemberian penguatan positif dan penghargaan"],
        ['langkah' => 'Langkah 5 : Melaksanakan latihan dan praktik', 'isi' => "• Konseli mengisi AUM /DCM sesuai dengan target perilaku yang diharapkan"],
        ['langkah' => 'Langkah 6 : Mengulang latihan', 'isi' => "• Konseli mengulang latihan kembali tanpa bantuan pembimbing"],
        ['langkah' => 'Langkah 7 : Tugas rumah dan tindak lanjut', 'isi' => "• Konselor memberi tugas rumah pada konseli, dan meminta konseli mempraktikkan perilaku target apakah sudah dilakukan dalam kehidupan sehari-hari"],
        ['langkah' => 'Langkah 8 : Terminasi', 'isi' => "• Konselor menghentikan program bantuan"]
    ],
    'rencana_evaluasi' => [
        'tujuan_evaluasi' => 'Untuk mengetahui sejauh mana konseli merubah pola hidup dalam kehidupan sehari-hari',
        'jenis_evaluasi' => [
            'proses' => 'yang dievaluasi antara lain performen konselor, jalannya proses konseling, media/alat yang digunakan, keaktifan/keterbukaan konseli, ruang konseling',
            'hasil' => 'hasil dari sesi pertemuan dicatat sebagai acuan untuk laporan'
        ],
        'teknik_instrumen' => 'Instrumen yang digunakan adalah Data hasil wawancara'
    ]
];

if ($is_edit) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM bk_laporan WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $edit_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($r = mysqli_fetch_assoc($res)) {
        foreach ($r as $k => $v) {
            if ($k === 'langkah_kegiatan_json' && !empty($v)) {
                $decoded = json_decode($v, true);
                if (is_array($decoded)) $data['langkah_kegiatan'] = $decoded;
            } elseif ($k === 'rencana_evaluasi_json' && !empty($v)) {
                $decoded = json_decode($v, true);
                if (is_array($decoded)) $data['rencana_evaluasi'] = $decoded;
            } elseif ($v !== null) {
                $data[$k] = $v;
            }
        }
    } else {
        die("Data laporan tidak ditemukan.");
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_rpl'])) {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) throw new Exception("Token Keamanan Tidak Valid.");

        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $siswa_id = (int)($_POST['siswa_id'] ?? 0);
        $post_guru_id = (int)($_POST['guru_id'] ?? $guru_id);
        if ($post_guru_id > 0) $guru_id = $post_guru_id;

        $kode_konseli = trim($_POST['kode_konseli'] ?? '');
        $pertemuan_ke = (int)($_POST['pertemuan_ke'] ?? 1);
        $waktu = trim($_POST['waktu'] ?? '45 menit');
        $tempat = trim($_POST['tempat'] ?? 'Ruang BK');
        $topik = trim($_POST['topik_permasalahan'] ?? '');
        $ringkasan = trim($_POST['ringkasan_pembahasan'] ?? '');
        $tindak_lanjut = trim($_POST['tindak_lanjut'] ?? '');
        $status = $_POST['status'] ?? 'Proses';

        $deskripsi_kasus = trim($_POST['deskripsi_kasus'] ?? '');
        $tujuan_konseling = trim($_POST['tujuan_konseling'] ?? '');
        $tahapan_konseling = trim($_POST['tahapan_konseling'] ?? '');
        $jumlah_pertemuan = trim($_POST['jumlah_pertemuan'] ?? '1 kali pertemuan');
        $durasi_pertemuan = trim($_POST['durasi_pertemuan'] ?? '45 menit');
        $media_sumber = trim($_POST['media_sumber'] ?? '-');
        $pihak_dilibatkan = trim($_POST['pihak_dilibatkan'] ?? 'Wali Kelas');

        $rumusan_masalah = trim($_POST['rumusan_masalah'] ?? '');
        $masalah_analisis = trim($_POST['masalah_analisis'] ?? '');
        $pendekatan_teknik = trim($_POST['pendekatan_teknik'] ?? '');
        $hasil_dicapai = trim($_POST['hasil_dicapai'] ?? '');

        // Parse langkah kegiatan 1-8
        $langkah_input = $_POST['langkah_kegiatan'] ?? [];
        $langkah_arr = [];
        if (is_array($langkah_input)) {
            foreach ($langkah_input as $lk) {
                if (!empty($lk['langkah']) || !empty($lk['isi'])) {
                    $langkah_arr[] = [
                        'langkah' => trim($lk['langkah'] ?? ''),
                        'isi' => trim($lk['isi'] ?? '')
                    ];
                }
            }
        }
        $langkah_json = json_encode($langkah_arr, JSON_UNESCAPED_UNICODE);

        // Parse rencana evaluasi
        $eval_input = $_POST['rencana_evaluasi'] ?? [];
        $eval_arr = [
            'tujuan_evaluasi' => trim($eval_input['tujuan_evaluasi'] ?? ''),
            'jenis_evaluasi' => [
                'proses' => trim($eval_input['jenis_evaluasi']['proses'] ?? ''),
                'hasil' => trim($eval_input['jenis_evaluasi']['hasil'] ?? '')
            ],
            'teknik_instrumen' => trim($eval_input['teknik_instrumen'] ?? '')
        ];
        $eval_json = json_encode($eval_arr, JSON_UNESCAPED_UNICODE);

        if ($siswa_id <= 0) throw new Exception("Siswa wajib dipilih.");
        if (empty($topik)) $topik = !empty($rumusan_masalah) ? $rumusan_masalah : "Konseling Individual";
        if (empty($ringkasan)) $ringkasan = !empty($masalah_analisis) ? $masalah_analisis : "Sesi konseling telah dilaksanakan.";

        // Auto-generate kode_konseli if empty
        if (empty($kode_konseli)) {
            $q_s = mysqli_query($conn, "SELECT s.id, s.nama_siswa, k.nama_kelas FROM siswa s JOIN siswa_kelas sk ON s.id = sk.siswa_id JOIN kelas k ON sk.kelas_id = k.id JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id WHERE s.id = $siswa_id AND tp.status = 'aktif' LIMIT 1");
            if ($row_s = mysqli_fetch_assoc($q_s)) {
                $thn = date('Y');
                $k_kls = preg_replace('/[^A-Z]/', '', strtoupper($row_s['nama_kelas']));
                if (empty($k_kls)) $k_kls = 'BK';
                $kode_konseli = "{$thn}. {$k_kls}. {$siswa_id}";
            } else {
                $kode_konseli = date('Y') . ". BK. " . $siswa_id;
            }
        }

        // Get class ID
        $q_k = mysqli_query($conn, "SELECT sk.kelas_id FROM siswa_kelas sk JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id WHERE sk.siswa_id = $siswa_id AND tp.status = 'aktif' LIMIT 1");
        $kelas_id = ($r_k = mysqli_fetch_assoc($q_k)) ? (int)$r_k['kelas_id'] : 0;

        if ($is_edit) {
            $stmt = mysqli_prepare($conn, "UPDATE bk_laporan SET
                tanggal=?, siswa_id=?, guru_id=?, kelas_id=?, kode_konseli=?, pertemuan_ke=?, waktu=?, tempat=?,
                topik_permasalahan=?, ringkasan_pembahasan=?, tindak_lanjut=?, status=?, deskripsi_kasus=?,
                tujuan_konseling=?, tahapan_konseling=?, jumlah_pertemuan=?, durasi_pertemuan=?, media_sumber=?,
                pihak_dilibatkan=?, rumusan_masalah=?, masalah_analisis=?, pendekatan_teknik=?,
                langkah_kegiatan_json=?, rencana_evaluasi_json=?, hasil_dicapai=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "siiisisssssssssssssssssssi",
                $tanggal, $siswa_id, $guru_id, $kelas_id, $kode_konseli, $pertemuan_ke, $waktu, $tempat,
                $topik, $ringkasan, $tindak_lanjut, $status, $deskripsi_kasus,
                $tujuan_konseling, $tahapan_konseling, $jumlah_pertemuan, $durasi_pertemuan, $media_sumber,
                $pihak_dilibatkan, $rumusan_masalah, $masalah_analisis, $pendekatan_teknik,
                $langkah_json, $eval_json, $hasil_dicapai, $edit_id);
            if (mysqli_stmt_execute($stmt)) {
                header("Location: " . BASE_URL . "admin/bk_laporan.php?success_save=1");
                exit;
            } else {
                throw new Exception("Gagal mengupdate laporan RPL BK.");
            }
        } else {
            $jenis_bimbingan = 'Individu';
            $stmt = mysqli_prepare($conn, "INSERT INTO bk_laporan (
                tanggal, jenis_bimbingan, siswa_id, guru_id, kelas_id, kode_konseli, pertemuan_ke, waktu, tempat,
                topik_permasalahan, ringkasan_pembahasan, tindak_lanjut, status, deskripsi_kasus,
                tujuan_konseling, tahapan_konseling, jumlah_pertemuan, durasi_pertemuan, media_sumber,
                pihak_dilibatkan, rumusan_masalah, masalah_analisis, pendekatan_teknik,
                langkah_kegiatan_json, rencana_evaluasi_json, hasil_dicapai
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssiiisisssssssssssssssssss",
                $tanggal, $jenis_bimbingan, $siswa_id, $guru_id, $kelas_id, $kode_konseli, $pertemuan_ke, $waktu, $tempat,
                $topik, $ringkasan, $tindak_lanjut, $status, $deskripsi_kasus,
                $tujuan_konseling, $tahapan_konseling, $jumlah_pertemuan, $durasi_pertemuan, $media_sumber,
                $pihak_dilibatkan, $rumusan_masalah, $masalah_analisis, $pendekatan_teknik,
                $langkah_json, $eval_json, $hasil_dicapai);
            if (mysqli_stmt_execute($stmt)) {
                header("Location: " . BASE_URL . "admin/bk_laporan.php?success_save=1");
                exit;
            } else {
                throw new Exception("Gagal menyimpan laporan RPL BK baru.");
            }
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// Fetch active students & counselors
$students = mysqli_query($conn, "SELECT s.id, s.nama_siswa, s.nis, k.nama_kelas
                                 FROM siswa s
                                 JOIN siswa_kelas sk ON s.id = sk.siswa_id
                                 JOIN kelas k ON sk.kelas_id = k.id
                                 JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id
                                 WHERE tp.status = 'aktif'
                                 ORDER BY k.nama_kelas ASC, s.nama_siswa ASC");

$student_map = [];
while ($st = mysqli_fetch_assoc($students)) {
    $student_map[] = $st;
}

$gurus = mysqli_query($conn, "SELECT g.id, u.nama_lengkap FROM guru g JOIN users u ON g.user_id = u.id ORDER BY u.nama_lengkap ASC");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 py-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight italic">
                <?= $is_edit ? 'Edit RPL Konseling Individual' : 'Tambah RPL Konseling Individual' ?>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">Formulir Rencana Pelaksanaan Layanan (RPL) Konseling Individual BK secara bertahap.</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>admin/bk_laporan.php" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2.5 rounded-xl font-bold text-xs flex items-center shadow-md">
                <i class="fa fa-arrow-left mr-2"></i> Kembali ke Daftar Laporan
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
            <button type="button" onclick="goToStep(1)" id="step-btn-1" class="step-btn py-3 px-2 rounded-2xl font-bold text-xs transition-all bg-indigo-600 text-white shadow-md shadow-indigo-100 flex flex-col items-center justify-center gap-1">
                <span class="w-6 h-6 rounded-full bg-white/20 flex items-center justify-center text-[11px] font-black">1</span>
                <span>Identitas & Info</span>
            </button>
            <button type="button" onclick="goToStep(2)" id="step-btn-2" class="step-btn py-3 px-2 rounded-2xl font-bold text-xs transition-all bg-slate-100 text-slate-500 hover:bg-slate-200 flex flex-col items-center justify-center gap-1">
                <span class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center text-[11px] font-black">2</span>
                <span>Kasus & Tahapan</span>
            </button>
            <button type="button" onclick="goToStep(3)" id="step-btn-3" class="step-btn py-3 px-2 rounded-2xl font-bold text-xs transition-all bg-slate-100 text-slate-500 hover:bg-slate-200 flex flex-col items-center justify-center gap-1">
                <span class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center text-[11px] font-black">3</span>
                <span>Langkah 1–8 RPL</span>
            </button>
            <button type="button" onclick="goToStep(4)" id="step-btn-4" class="step-btn py-3 px-2 rounded-2xl font-bold text-xs transition-all bg-slate-100 text-slate-500 hover:bg-slate-200 flex flex-col items-center justify-center gap-1">
                <span class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center text-[11px] font-black">4</span>
                <span>Evaluasi & Simpan</span>
            </button>
        </div>
    </div>

    <!-- Main Form -->
    <form action="" method="POST" id="rplForm" class="bg-white rounded-3xl shadow-2xl p-6 sm:p-8 border border-slate-100">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

        <!-- STEP 1: Identitas & Informasi Dasar -->
        <div id="step-1" class="step-content space-y-5">
            <div class="border-b border-slate-100 pb-4 mb-4">
                <h2 class="text-lg font-black text-slate-800 italic">Tahap 1: Identitas Siswa & Parameter Konseling</h2>
                <p class="text-xs text-slate-400">Pilih siswa dan tentukan kode konseli serta jadwal pertemuan.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Pilih Siswa / Konseli <span class="text-rose-500">*</span></label>
                    <select name="siswa_id" id="siswa_id" required onchange="generateKodeKonseli()" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-xs text-slate-700 bg-white">
                        <option value="">-- Pilih Siswa --</option>
                        <?php foreach ($student_map as $s): ?>
                            <option value="<?= $s['id'] ?>" data-nama="<?= htmlspecialchars($s['nama_siswa']) ?>" data-kelas="<?= htmlspecialchars($s['nama_kelas']) ?>" <?= $s['id'] == $data['siswa_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['nama_siswa']) ?> (<?= htmlspecialchars($s['nama_kelas']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">
                        Kode Konseli <span class="text-indigo-600 text-[10px] font-normal">(Otomatis/Dapat disesuaikan)</span>
                    </label>
                    <input type="text" name="kode_konseli" id="kode_konseli" value="<?= htmlspecialchars($data['kode_konseli']) ?>" placeholder="Contoh: 2022. APH. 14" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-mono font-bold text-xs text-slate-800 bg-slate-50">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Hari & Tanggal <span class="text-rose-500">*</span></label>
                    <input type="date" name="tanggal" value="<?= htmlspecialchars($data['tanggal']) ?>" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-xs text-slate-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Pertemuan Ke-</label>
                    <input type="number" name="pertemuan_ke" value="<?= (int)$data['pertemuan_ke'] ?>" min="1" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-xs text-slate-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Waktu Sesi</label>
                    <input type="text" name="waktu" value="<?= htmlspecialchars($data['waktu']) ?>" placeholder="e.g. 45 menit" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-xs text-slate-700">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Tempat Konseling</label>
                    <input type="text" name="tempat" value="<?= htmlspecialchars($data['tempat']) ?>" placeholder="e.g. Ruang BK" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-xs text-slate-700">
                </div>

                <?php if ($role === 'admin' || $role === 'waka'): ?>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Guru BK / Konselor</label>
                    <select name="guru_id" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-xs text-slate-700 bg-white">
                        <option value="">-- Pilih Konselor --</option>
                        <?php mysqli_data_seek($gurus, 0); while ($g = mysqli_fetch_assoc($gurus)): ?>
                            <option value="<?= $g['id'] ?>" <?= $g['id'] == $data['guru_id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_lengkap']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <div class="flex justify-end pt-4">
                <button type="button" onclick="goToStep(2)" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-2xl text-xs shadow-lg shadow-indigo-100 flex items-center">
                    Lanjut ke Tahap 2 <i class="fa fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- STEP 2: Deskripsi Kasus & Parameter RPL -->
        <div id="step-2" class="step-content space-y-5 hidden">
            <div class="border-b border-slate-100 pb-4 mb-4">
                <h2 class="text-lg font-black text-slate-800 italic">Tahap 2: Deskripsi Kasus, Tujuan & Parameter</h2>
                <p class="text-xs text-slate-400">Rincian keluhan konseli, tujuan konseling, dan durasi pelaksanaan.</p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Deskripsi Kasus / Gejala yang Nampak (Keluhan)</label>
                <textarea name="deskripsi_kasus" rows="3" placeholder="e.g. • Konseli terlihat kurus, kondisi fisik tidak fresh&#10;• Konseli memiliki rasa kukuh terhadap pendiriannya" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['deskripsi_kasus']) ?></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Tujuan Konseling</label>
                <textarea name="tujuan_konseling" rows="3" placeholder="e.g. • Konseli bisa instrospeksi dengan penerapan pola hidup yang dijalani&#10;• Konseli dapat berkonsultasi kepada ahli gizi/psikolog" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['tujuan_konseling']) ?></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Jumlah Pertemuan</label>
                    <input type="text" name="jumlah_pertemuan" value="<?= htmlspecialchars($data['jumlah_pertemuan']) ?>" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-xs text-slate-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Durasi per Pertemuan</label>
                    <input type="text" name="durasi_pertemuan" value="<?= htmlspecialchars($data['durasi_pertemuan']) ?>" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-xs text-slate-700">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Media & Sumber Bantuan</label>
                    <input type="text" name="media_sumber" value="<?= htmlspecialchars($data['media_sumber']) ?>" placeholder="e.g. -" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-xs text-slate-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Pihak Lain yang Dilibatkan</label>
                    <input type="text" name="pihak_dilibatkan" value="<?= htmlspecialchars($data['pihak_dilibatkan']) ?>" placeholder="e.g. Wali Kelas" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-xs text-slate-700">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Tahapan Konseling (Ringkasan Point 1-7)</label>
                <textarea name="tahapan_konseling" rows="4" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['tahapan_konseling']) ?></textarea>
            </div>

            <div class="flex justify-between pt-4">
                <button type="button" onclick="goToStep(1)" class="px-6 py-3 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-2xl text-xs flex items-center">
                    <i class="fa fa-arrow-left mr-2"></i> Kembali
                </button>
                <button type="button" onclick="goToStep(3)" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-2xl text-xs shadow-lg shadow-indigo-100 flex items-center">
                    Lanjut ke Langkah RPL <i class="fa fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- STEP 3: Lampiran RPL Langkah Kegiatan 1-8 -->
        <div id="step-3" class="step-content space-y-5 hidden">
            <div class="border-b border-slate-100 pb-4 mb-4">
                <h2 class="text-lg font-black text-slate-800 italic">Tahap 3: Lampiran - Analisis & Langkah Kegiatan 1–8</h2>
                <p class="text-xs text-slate-400">Rincian rumusan masalah, pendekatan, dan tabel aktivitas konselor-konseli.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Rumusan Masalah</label>
                    <input type="text" name="rumusan_masalah" value="<?= htmlspecialchars($data['rumusan_masalah']) ?>" placeholder="e.g. Konseli menerapkan pola diet ketat" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-xs text-slate-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Pendekatan / Teknik Pemecahan</label>
                    <input type="text" name="pendekatan_teknik" value="<?= htmlspecialchars($data['pendekatan_teknik']) ?>" placeholder="e.g. Teori Behavioral dengan teknik konseling Eksplorasi" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-xs text-slate-700">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Masalah dan Analisis</label>
                <textarea name="masalah_analisis" rows="3" placeholder="Uraian analisis masalah siswa oleh Guru BK..." class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['masalah_analisis']) ?></textarea>
            </div>

            <!-- Tabel Langkah 1 - 8 -->
            <div class="pt-2">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest mb-3">Tabel Tahap / Proses Konseling (Langkah 1 - 8)</h3>
                <div class="space-y-4" id="langkah_container">
                    <?php
                    $langkah_list = $data['langkah_kegiatan'];
                    foreach ($langkah_list as $idx => $lk):
                    ?>
                    <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-extrabold text-indigo-600">Langkah <?= $idx + 1 ?></span>
                        </div>
                        <input type="text" name="langkah_kegiatan[<?= $idx ?>][langkah]" value="<?= htmlspecialchars($lk['langkah'] ?? '') ?>" placeholder="Judul langkah (e.g. Langkah 1 : Rasional strategi)" class="w-full px-3 py-2 rounded-xl border border-slate-200 font-bold text-xs text-slate-800 bg-white">
                        <textarea name="langkah_kegiatan[<?= $idx ?>][isi]" rows="3" placeholder="Isi kegiatan..." class="w-full px-3 py-2 rounded-xl border border-slate-200 font-medium text-xs text-slate-700 bg-white leading-relaxed"><?= htmlspecialchars($lk['isi'] ?? '') ?></textarea>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex justify-between pt-4">
                <button type="button" onclick="goToStep(2)" class="px-6 py-3 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-2xl text-xs flex items-center">
                    <i class="fa fa-arrow-left mr-2"></i> Kembali
                </button>
                <button type="button" onclick="goToStep(4)" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-2xl text-xs shadow-lg shadow-indigo-100 flex items-center">
                    Lanjut ke Rencana Evaluasi <i class="fa fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- STEP 4: Rencana Evaluasi & Simpan -->
        <div id="step-4" class="step-content space-y-5 hidden">
            <div class="border-b border-slate-100 pb-4 mb-4">
                <h2 class="text-lg font-black text-slate-800 italic">Tahap 4: Rencana Evaluasi & Status Akhir</h2>
                <p class="text-xs text-slate-400">Pengisian parameter evaluasi proses & hasil serta penyimpanan dokumen RPL.</p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Tujuan Evaluasi</label>
                <input type="text" name="rencana_evaluasi[tujuan_evaluasi]" value="<?= htmlspecialchars($data['rencana_evaluasi']['tujuan_evaluasi'] ?? '') ?>" placeholder="e.g. Untuk mengetahui sejauh mana konseli merubah pola hidup..." class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-xs text-slate-700">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Evaluasi Proses</label>
                    <textarea name="rencana_evaluasi[jenis_evaluasi][proses]" rows="3" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['rencana_evaluasi']['jenis_evaluasi']['proses'] ?? '') ?></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Evaluasi Hasil</label>
                    <textarea name="rencana_evaluasi[jenis_evaluasi][hasil]" rows="3" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['rencana_evaluasi']['jenis_evaluasi']['hasil'] ?? '') ?></textarea>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Teknik dan Instrumen Evaluasi</label>
                <input type="text" name="rencana_evaluasi[teknik_instrumen]" value="<?= htmlspecialchars($data['rencana_evaluasi']['teknik_instrumen'] ?? '') ?>" placeholder="e.g. Instrumen yang digunakan adalah Data hasil wawancara" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-xs text-slate-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Hasil yang Dicapai (Untuk Laporan Konseling Individu)</label>
                <textarea name="hasil_dicapai" rows="2" placeholder="e.g. Siswa merefleksi kesalahan yang dilakukan dan berkomitmen untuk memperbaiki kehadiran di sekolah." class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['hasil_dicapai'] ?? '') ?></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 pt-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Topik / Judul Laporan Ringkas</label>
                    <input type="text" name="topik_permasalahan" value="<?= htmlspecialchars($data['topik_permasalahan']) ?>" placeholder="e.g. Permasalahan Pola Makan & Konseling Behavioral" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-xs text-slate-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Status Konseling</label>
                    <select name="status" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-xs text-slate-700 bg-white">
                        <option value="Proses" <?= $data['status'] === 'Proses' ? 'selected' : '' ?>>Dalam Proses</option>
                        <option value="Selesai" <?= $data['status'] === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                        <option value="Rujukan" <?= $data['status'] === 'Rujukan' ? 'selected' : '' ?>>Rujukan (Alih Tangan Kasus)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Rencana Tindak Lanjut Ringkas</label>
                <textarea name="tindak_lanjut" rows="2" placeholder="Tindak lanjut singkat..." class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['tindak_lanjut']) ?></textarea>
            </div>

            <div class="flex justify-between pt-6 border-t border-slate-100">
                <button type="button" onclick="goToStep(3)" class="px-6 py-3 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-2xl text-xs flex items-center">
                    <i class="fa fa-arrow-left mr-2"></i> Kembali
                </button>
                <button type="submit" name="simpan_rpl" class="px-8 py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl text-xs shadow-xl shadow-emerald-100 flex items-center">
                    <i class="fa fa-save mr-2"></i> Simpan Dokumen RPL
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
            btn.className = 'step-btn py-3 px-2 rounded-2xl font-bold text-xs transition-all bg-indigo-600 text-white shadow-md shadow-indigo-100 flex flex-col items-center justify-center gap-1';
        } else {
            btn.className = 'step-btn py-3 px-2 rounded-2xl font-bold text-xs transition-all bg-slate-100 text-slate-500 hover:bg-slate-200 flex flex-col items-center justify-center gap-1';
        }
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function generateKodeKonseli() {
    const sel = document.getElementById('siswa_id');
    const inputKode = document.getElementById('kode_konseli');
    if (!sel || !inputKode) return;

    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;

    const siswaId = opt.value;
    const kelas = opt.getAttribute('data-kelas') || 'BK';
    const cleanKelas = kelas.replace(/[^a-zA-Z0-9]/g, '').toUpperCase() || 'BK';
    const year = new Date().getFullYear();

    // Auto-fill if empty or matches standard pattern
    if (!inputKode.value || inputKode.value.includes('.')) {
        inputKode.value = `${year}. ${cleanKelas}. ${siswaId}`;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
