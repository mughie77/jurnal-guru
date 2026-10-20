<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka', 'guru', 'guru_bk']);

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$page_title = "Form Laporan Kunjungan Rumah BK";

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

// Default values for Kunjungan Rumah
$data = [
    'tanggal' => date('Y-m-d'),
    'jenis_bimbingan' => 'Kunjungan Rumah',
    'siswa_id' => 0,
    'guru_id' => $guru_id,
    'kelas_id' => 0,
    'bidang_layanan' => 'Belajar / Pribadi',
    'topik_permasalahan' => '',
    'fungsi_layanan' => 'Pengentasan',
    'pihak_dilibatkan' => 'Wali Kelas',
    'tujuan_umum' => 'Siswa bisa kembali masuk sekolah seperti semula',
    'ringkasan_pembahasan' => '',
    'alamat_kunjungan' => '',
    'waktu' => '1 Jam',
    'anggota_keluarga' => 'Orang Tua',
    'evaluasi_proses' => 'Pemantauan secara berkala',
    'tindak_lanjut' => 'Konseling Pribadi',
    'catatan_khusus' => '-',
    'status' => 'Proses'
];

if ($is_edit) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM bk_laporan WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $edit_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($r = mysqli_fetch_assoc($res)) {
        foreach ($r as $k => $v) {
            if ($v !== null) {
                $data[$k] = $v;
            }
        }
    } else {
        die("Data laporan kunjungan rumah tidak ditemukan.");
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_kunjungan_rumah'])) {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) throw new Exception("Token Keamanan Tidak Valid.");

        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $siswa_id = (int)($_POST['siswa_id'] ?? 0);
        $post_guru_id = (int)($_POST['guru_id'] ?? $guru_id);
        if ($post_guru_id > 0) $guru_id = $post_guru_id;

        $bidang_layanan = trim($_POST['bidang_layanan'] ?? 'Belajar / Pribadi');
        $topik_permasalahan = trim($_POST['topik_permasalahan'] ?? '');
        $fungsi_layanan = trim($_POST['fungsi_layanan'] ?? 'Pengentasan');
        $pihak_dilibatkan = trim($_POST['pihak_dilibatkan'] ?? 'Wali Kelas');
        $tujuan_umum = trim($_POST['tujuan_umum'] ?? '');
        $ringkasan_pembahasan = trim($_POST['ringkasan_pembahasan'] ?? '');
        $alamat_kunjungan = trim($_POST['alamat_kunjungan'] ?? '');
        $waktu = trim($_POST['waktu'] ?? '1 Jam');
        $anggota_keluarga = trim($_POST['anggota_keluarga'] ?? 'Orang Tua');
        $evaluasi_proses = trim($_POST['evaluasi_proses'] ?? '');
        $tindak_lanjut = trim($_POST['tindak_lanjut'] ?? '');
        $catatan_khusus = trim($_POST['catatan_khusus'] ?? '-');
        $status = $_POST['status'] ?? 'Proses';

        if ($siswa_id <= 0) throw new Exception("Siswa wajib dipilih.");
        if (empty($topik_permasalahan)) throw new Exception("Topik / Permasalahan wajib diisi.");
        if (empty($ringkasan_pembahasan)) throw new Exception("Gambaran ringkas masalah wajib diisi.");

        // Auto fetch class ID for student
        $q_k = mysqli_query($conn, "SELECT sk.kelas_id FROM siswa_kelas sk JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id WHERE sk.siswa_id = $siswa_id AND tp.status = 'aktif' LIMIT 1");
        $kelas_id = ($r_k = mysqli_fetch_assoc($q_k)) ? (int)$r_k['kelas_id'] : 0;

        // Auto generate unique kode_konseli if empty (without class code)
        $q_s = mysqli_query($conn, "SELECT s.nama_siswa, s.alamat FROM siswa s WHERE s.id = $siswa_id LIMIT 1");
        $r_s = mysqli_fetch_assoc($q_s);
        if (empty($alamat_kunjungan) && !empty($r_s['alamat'])) {
            $alamat_kunjungan = $r_s['alamat'];
        }

        $kode_konseli = generate_unique_kode_konseli($conn, $siswa_id);

        if ($guru_id <= 0) {
            $q_first_g = mysqli_query($conn, "SELECT id FROM guru LIMIT 1");
            if ($r_fg = mysqli_fetch_assoc($q_first_g)) {
                $guru_id = (int)$r_fg['id'];
            }
        }

        $jenis_bimbingan = 'Kunjungan Rumah';

        if ($is_edit) {
            $stmt = mysqli_prepare($conn, "UPDATE bk_laporan SET
                tanggal=?, jenis_bimbingan=?, siswa_id=?, guru_id=?, kelas_id=?, kode_konseli=?, bidang_layanan=?,
                topik_permasalahan=?, fungsi_layanan=?, pihak_dilibatkan=?, tujuan_umum=?,
                ringkasan_pembahasan=?, alamat_kunjungan=?, waktu=?, anggota_keluarga=?,
                evaluasi_proses=?, tindak_lanjut=?, catatan_khusus=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "ssiiissssssssssssssi",
                $tanggal, $jenis_bimbingan, $siswa_id, $guru_id, $kelas_id, $kode_konseli, $bidang_layanan,
                $topik_permasalahan, $fungsi_layanan, $pihak_dilibatkan, $tujuan_umum,
                $ringkasan_pembahasan, $alamat_kunjungan, $waktu, $anggota_keluarga,
                $evaluasi_proses, $tindak_lanjut, $catatan_khusus, $status, $edit_id);
            if (mysqli_stmt_execute($stmt)) {
                header("Location: " . BASE_URL . "admin/bk_laporan.php?jenis=Kunjungan+Rumah&success_save=1");
                exit;
            } else {
                throw new Exception("Gagal memperbarui laporan Kunjungan Rumah: " . mysqli_error($conn));
            }
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO bk_laporan (
                tanggal, jenis_bimbingan, siswa_id, guru_id, kelas_id, kode_konseli, bidang_layanan,
                topik_permasalahan, fungsi_layanan, pihak_dilibatkan, tujuan_umum,
                ringkasan_pembahasan, alamat_kunjungan, waktu, anggota_keluarga,
                evaluasi_proses, tindak_lanjut, catatan_khusus, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssiiissssssssssssss",
                $tanggal, $jenis_bimbingan, $siswa_id, $guru_id, $kelas_id, $kode_konseli, $bidang_layanan,
                $topik_permasalahan, $fungsi_layanan, $pihak_dilibatkan, $tujuan_umum,
                $ringkasan_pembahasan, $alamat_kunjungan, $waktu, $anggota_keluarga,
                $evaluasi_proses, $tindak_lanjut, $catatan_khusus, $status);
            if (mysqli_stmt_execute($stmt)) {
                header("Location: " . BASE_URL . "admin/bk_laporan.php?jenis=Kunjungan+Rumah&success_save=1");
                exit;
            } else {
                throw new Exception("Gagal menyimpan laporan Kunjungan Rumah baru: " . mysqli_error($conn));
            }
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// Fetch active students & counselors
$students = mysqli_query($conn, "SELECT s.id, s.nama_siswa, s.nis, s.alamat, k.nama_kelas
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

<div class="max-w-4xl mx-auto px-4 sm:px-6 py-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight italic">
                <?= $is_edit ? 'Edit Laporan Kunjungan Rumah' : 'Tambah Laporan Kunjungan Rumah' ?>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">Formulir pencatatan hasil kunjungan ke rumah siswa (Home Visit).</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>admin/bk_laporan.php?jenis=Kunjungan+Rumah" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2.5 rounded-xl font-bold text-xs flex items-center shadow-md">
                <i class="fa fa-arrow-left mr-2"></i> Kembali ke Rekap Kunjungan Rumah
            </a>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="p-4 mb-6 rounded-2xl bg-rose-50 text-rose-700 text-xs font-bold border border-rose-200">
        <i class="fa fa-exclamation-circle mr-2"></i> <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Main Form -->
    <form action="" method="POST" class="bg-white rounded-3xl shadow-2xl p-6 sm:p-8 border border-slate-100 space-y-6">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

        <div class="border-b border-slate-100 pb-4">
            <h2 class="text-base font-black text-slate-800 uppercase tracking-wide flex items-center gap-2">
                <i class="fa fa-house-user text-amber-500"></i> Detail Laporan Kunjungan Rumah (14 Atribut BK)
            </h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">1. Nama Peserta Didik / Konseli <span class="text-rose-500">*</span></label>
                <select name="siswa_id" id="siswa_id" required onchange="autoFillAddress()" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-bold text-xs text-slate-700 bg-white">
                    <option value="">-- Pilih Siswa --</option>
                    <?php foreach ($student_map as $s): ?>
                        <option value="<?= $s['id'] ?>" data-alamat="<?= htmlspecialchars($s['alamat'] ?? '') ?>" <?= $s['id'] == $data['siswa_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['nama_siswa']) ?> (<?= htmlspecialchars($s['nama_kelas']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">3. Bidang Layanan</label>
                <input type="text" name="bidang_layanan" value="<?= htmlspecialchars($data['bidang_layanan']) ?>" placeholder="Belajar / Pribadi / Sosial" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-bold text-xs text-slate-700">
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-2">4. Topik / Permasalahan <span class="text-rose-500">*</span></label>
            <input type="text" name="topik_permasalahan" value="<?= htmlspecialchars($data['topik_permasalahan']) ?>" required placeholder="Contoh: Siswa tidak masuk sekolah tanpa keterangan" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-bold text-xs text-slate-800">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">5. Fungsi Layanan</label>
                <input type="text" name="fungsi_layanan" value="<?= htmlspecialchars($data['fungsi_layanan']) ?>" placeholder="Pengentasan / Pemahaman" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-bold text-xs text-slate-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">6. Pihak yang Terlibat</label>
                <input type="text" name="pihak_dilibatkan" value="<?= htmlspecialchars($data['pihak_dilibatkan']) ?>" placeholder="Wali Kelas, Guru BP, Orang Tua" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-bold text-xs text-slate-700">
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-2">7. Tujuan Kegiatan</label>
            <textarea name="tujuan_umum" rows="2" placeholder="e.g. Siswa bisa kembali masuk sekolah seperti semula" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['tujuan_umum']) ?></textarea>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-2">8. Gambaran Ringkas Masalah <span class="text-rose-500">*</span></label>
            <textarea name="ringkasan_pembahasan" rows="3" required placeholder="Siswa tidak masuk sekolah karena merasa tidak nyaman dengan suasana sekolah..." class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['ringkasan_pembahasan']) ?></textarea>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-2">9. Alamat Kunjungan</label>
            <input type="text" name="alamat_kunjungan" id="alamat_kunjungan" value="<?= htmlspecialchars($data['alamat_kunjungan']) ?>" placeholder="Alamat rumah lokasi kunjungan..." class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-bold text-xs text-slate-700">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">10. Tanggal Kunjungan <span class="text-rose-500">*</span></label>
                <input type="date" name="tanggal" value="<?= htmlspecialchars($data['tanggal']) ?>" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-bold text-xs text-slate-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Lama Kunjungan</label>
                <input type="text" name="waktu" value="<?= htmlspecialchars($data['waktu']) ?>" placeholder="e.g. 1 Jam" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-bold text-xs text-slate-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">11. Anggota Keluarga Dikunjungi</label>
                <input type="text" name="anggota_keluarga" value="<?= htmlspecialchars($data['anggota_keluarga']) ?>" placeholder="e.g. Orang Tua / Wali" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-bold text-xs text-slate-700">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">12. Rencana Evaluasi</label>
                <input type="text" name="evaluasi_proses" value="<?= htmlspecialchars($data['evaluasi_proses']) ?>" placeholder="e.g. Pemantauan secara berkala" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-bold text-xs text-slate-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">13. Tindak Lanjut</label>
                <input type="text" name="tindak_lanjut" value="<?= htmlspecialchars($data['tindak_lanjut']) ?>" placeholder="e.g. Konseling Pribadi" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-bold text-xs text-slate-700">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">14. Catatan Khusus</label>
                <input type="text" name="catatan_khusus" value="<?= htmlspecialchars($data['catatan_khusus']) ?>" placeholder="e.g. -" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-bold text-xs text-slate-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Status Kunjungan</label>
                <select name="status" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-bold text-xs text-slate-700 bg-white">
                    <option value="Selesai" <?= $data['status'] === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                    <option value="Proses" <?= $data['status'] === 'Proses' ? 'selected' : '' ?>>Dalam Proses</option>
                </select>
            </div>
        </div>

        <?php if ($role === 'admin' || $role === 'waka'): ?>
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-2">Guru BK / Petugas Kunjungan</label>
            <select name="guru_id" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-bold text-xs text-slate-700 bg-white">
                <option value="">-- Pilih Guru BK --</option>
                <?php mysqli_data_seek($gurus, 0); while ($g = mysqli_fetch_assoc($gurus)): ?>
                    <option value="<?= $g['id'] ?>" <?= $g['id'] == $data['guru_id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_lengkap']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="flex justify-between pt-6 border-t border-slate-100">
            <a href="<?= BASE_URL ?>admin/bk_laporan.php?jenis=Kunjungan+Rumah" class="px-6 py-3 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-2xl text-xs flex items-center">
                <i class="fa fa-arrow-left mr-2"></i> Batal
            </a>
            <button type="submit" name="simpan_kunjungan_rumah" class="px-8 py-3.5 bg-amber-600 hover:bg-amber-700 text-white font-black rounded-2xl text-xs shadow-xl shadow-amber-100 flex items-center">
                <i class="fa fa-save mr-2"></i> Simpan Laporan Kunjungan Rumah
            </button>
        </div>
    </form>
</div>

<script>
function autoFillAddress() {
    const sel = document.getElementById('siswa_id');
    const inputAlamat = document.getElementById('alamat_kunjungan');
    if (!sel || !inputAlamat) return;

    const opt = sel.options[sel.selectedIndex];
    if (!opt) return;

    const alamat = opt.getAttribute('data-alamat');
    if (alamat && (!inputAlamat.value || inputAlamat.value.trim() === '')) {
        inputAlamat.value = alamat;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
