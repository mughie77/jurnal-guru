<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka', 'guru', 'guru_bk']);

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$page_title = "Form Rencana Pelaksanaan Konferensi Kasus BK";

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

// Default values for Konferensi Kasus
$data = [
    'tanggal' => date('Y-m-d'),
    'jenis_bimbingan' => 'Konferensi Kasus',
    'siswa_id' => 0,
    'guru_id' => $guru_id,
    'kelas_id' => 0,
    'kode_konseli' => '',
    'waktu' => '60 Menit',
    'topik_permasalahan' => '',
    'deskripsi_kasus' => '',
    'pihak_dilibatkan' => 'Kepala Sekolah, Waka Kesiswaan, Waka Kurikulum, Kaprogli, Wali Kelas, Guru BK',
    'urun_pendapat' => "- siswa diharapkan untuk tetap sekolah\n- siswa dapat merubah prilaku menjadi lebih baik\n- siswa diadakan tindak lanjut home visit\n- Siswa membuat pernyataan kesanggupan mematuhi tata tertib/ kesepakatan bersama",
    'keputusan_kasus' => "- siswa diberi kesempatan untuk masuk sekolah dengan menulis surat pernyataan.",
    'tindak_lanjut' => '',
    'status' => 'Proses'
];

if ($is_edit) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM bk_laporan WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $edit_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($r = mysqli_fetch_assoc($res)) {
        foreach ($r as $k => $v) {
            if ($v !== null) $data[$k] = $v;
        }
    } else {
        die("Data laporan tidak ditemukan.");
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_konferensi_kasus'])) {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) throw new Exception("Token Keamanan Tidak Valid.");

        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $siswa_id = (int)($_POST['siswa_id'] ?? 0);
        $post_guru_id = (int)($_POST['guru_id'] ?? $guru_id);
        if ($post_guru_id > 0) $guru_id = $post_guru_id;

        $kode_konseli = trim($_POST['kode_konseli'] ?? '');
        $waktu = trim($_POST['waktu'] ?? '60 Menit');
        $deskripsi_kasus = trim($_POST['deskripsi_kasus'] ?? '');
        $pihak_dilibatkan = trim($_POST['pihak_dilibatkan'] ?? '');
        $urun_pendapat = trim($_POST['urun_pendapat'] ?? '');
        $keputusan_kasus = trim($_POST['keputusan_kasus'] ?? '');
        $status = $_POST['status'] ?? 'Proses';
        $topik = trim($_POST['topik_permasalahan'] ?? '');
        if (empty($topik)) {
            $topik = !empty($deskripsi_kasus) ? mb_substr(str_replace(["\r", "\n"], ' ', $deskripsi_kasus), 0, 100) : 'Konferensi Kasus';
        }
        $ringkasan = !empty($keputusan_kasus) ? $keputusan_kasus : 'Hasil rapat konferensi kasus telah dirumuskan.';
        $tindak_lanjut = trim($_POST['tindak_lanjut'] ?? '');

        if ($siswa_id <= 0) throw new Exception("Siswa wajib dipilih.");

        // Auto-generate unique kode_konseli if empty
        if (empty($kode_konseli)) {
            $kode_konseli = generate_unique_kode_konseli($conn, $siswa_id);
        }

        // Get class ID
        $q_k = mysqli_query($conn, "SELECT sk.kelas_id FROM siswa_kelas sk JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id WHERE sk.siswa_id = $siswa_id AND tp.status = 'aktif' LIMIT 1");
        $kelas_id = ($r_k = mysqli_fetch_assoc($q_k)) ? (int)$r_k['kelas_id'] : 0;

        // Check if urun_pendapat and keputusan_kasus columns exist
        $has_col_up = false;
        $has_col_kk = false;
        $chk_cols = mysqli_query($conn, "SHOW COLUMNS FROM bk_laporan");
        if ($chk_cols) {
            while ($c = mysqli_fetch_assoc($chk_cols)) {
                if ($c['Field'] === 'urun_pendapat') $has_col_up = true;
                if ($c['Field'] === 'keputusan_kasus') $has_col_kk = true;
            }
        }

        // Auto-create columns if missing
        if (!$has_col_up) {
            mysqli_query($conn, "ALTER TABLE bk_laporan ADD COLUMN urun_pendapat TEXT DEFAULT NULL");
        }
        if (!$has_col_kk) {
            mysqli_query($conn, "ALTER TABLE bk_laporan ADD COLUMN keputusan_kasus TEXT DEFAULT NULL");
        }

        if ($is_edit) {
            $stmt = mysqli_prepare($conn, "UPDATE bk_laporan SET
                tanggal=?, siswa_id=?, guru_id=?, kelas_id=?, kode_konseli=?,
                waktu=?, topik_permasalahan=?, deskripsi_kasus=?, pihak_dilibatkan=?,
                urun_pendapat=?, keputusan_kasus=?, ringkasan_pembahasan=?, tindak_lanjut=?, status=?
                WHERE id=?");
            mysqli_stmt_bind_param($stmt, "siisssssssssssi",
                $tanggal, $siswa_id, $guru_id, $kelas_id, $kode_konseli,
                $waktu, $topik, $deskripsi_kasus, $pihak_dilibatkan,
                $urun_pendapat, $keputusan_kasus, $ringkasan, $tindak_lanjut, $status,
                $edit_id
            );
            if (!mysqli_stmt_execute($stmt)) throw new Exception("Gagal update laporan: " . mysqli_stmt_error($stmt));
            $target_url = BASE_URL . ($role === 'guru' ? 'guru/' : 'admin/') . "bk_laporan.php?jenis=Konferensi+Kasus&msg=updated";
            header("Location: $target_url");
            exit();
        } else {
            $jenis_bimbingan = 'Konferensi Kasus';
            $stmt = mysqli_prepare($conn, "INSERT INTO bk_laporan (
                tanggal, jenis_bimbingan, siswa_id, guru_id, kelas_id, kode_konseli,
                waktu, topik_permasalahan, deskripsi_kasus, pihak_dilibatkan,
                urun_pendapat, keputusan_kasus, ringkasan_pembahasan, tindak_lanjut, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssiisssssssssss",
                $tanggal, $jenis_bimbingan, $siswa_id, $guru_id, $kelas_id, $kode_konseli,
                $waktu, $topik, $deskripsi_kasus, $pihak_dilibatkan,
                $urun_pendapat, $keputusan_kasus, $ringkasan, $tindak_lanjut, $status
            );
            if (!mysqli_stmt_execute($stmt)) throw new Exception("Gagal membuat laporan: " . mysqli_stmt_error($stmt));
            $target_url = BASE_URL . ($role === 'guru' ? 'guru/' : 'admin/') . "bk_laporan.php?jenis=Konferensi+Kasus&msg=created";
            header("Location: $target_url");
            exit();
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// Fetch list of active students
$q_students = "SELECT s.id, s.nama_siswa, s.nis, s.alamat, k.nama_kelas, bl_k.kode_konseli
               FROM siswa s
               JOIN siswa_kelas sk ON s.id = sk.siswa_id
               JOIN kelas k ON sk.kelas_id = k.id
               JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id
               LEFT JOIN (
                   SELECT siswa_id, kode_konseli FROM bk_laporan WHERE kode_konseli IS NOT NULL AND kode_konseli != '' GROUP BY siswa_id
               ) bl_k ON s.id = bl_k.siswa_id
               WHERE tp.status = 'aktif'
               ORDER BY k.nama_kelas ASC, s.nama_siswa ASC";
$students = mysqli_query($conn, $q_students);

// Fetch BK teachers for admin/waka
$gurus = mysqli_query($conn, "SELECT g.id, u.nama_lengkap FROM guru g JOIN users u ON g.user_id = u.id ORDER BY u.nama_lengkap ASC");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight italic">
            <?= $is_edit ? 'Edit Rencana Konferensi Kasus' : 'Input Rencana Pelaksanaan Konferensi Kasus' ?>
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">Layanan Bimbingan Konseling - Konferensi Pembahasan Kasus Siswa</p>
    </div>
    <a href="<?= BASE_URL . ($role === 'guru' ? 'guru/' : 'admin/') ?>bk_laporan.php?jenis=Konferensi+Kasus" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-2xl text-xs flex items-center transition-all">
        <i class="fa fa-arrow-left mr-2"></i> Kembali
    </a>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-2">
    <i class="fa fa-exclamation-circle text-base"></i>
    <span><?= htmlspecialchars($message) ?></span>
</div>
<?php endif; ?>

<div class="lux-card p-6 sm:p-8 bg-white border border-slate-200/80 rounded-3xl shadow-sm">
    <form action="" method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

        <!-- Header Info Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">1. Pilih Peserta Didik / Konseli <span class="text-rose-500">*</span></label>
                <select name="siswa_id" id="siswa_id" required onchange="onStudentSelect()" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-purple-100 font-bold text-xs text-slate-700 bg-white">
                    <option value="">-- Pilih Siswa --</option>
                    <?php if ($students): while ($st = mysqli_fetch_assoc($students)): ?>
                        <option value="<?= $st['id'] ?>"
                            data-kelas="<?= htmlspecialchars($st['nama_kelas']) ?>"
                            data-kode="<?= htmlspecialchars($st['kode_konseli'] ?? '') ?>"
                            <?= $st['id'] == $data['siswa_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($st['nama_siswa']) ?> (<?= htmlspecialchars($st['nama_kelas']) ?> - <?= htmlspecialchars($st['nis']) ?>)
                        </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">2. Kelas</label>
                <input type="text" id="display_kelas" readonly placeholder="Otomatis dari siswa" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 font-bold text-xs text-slate-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Kode Konseli (Penyamaran)</label>
                <input type="text" name="kode_konseli" id="kode_konseli" value="<?= htmlspecialchars($data['kode_konseli']) ?>" placeholder="Otomatis jika dikosongkan" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-purple-100 font-bold text-xs text-slate-700 font-mono">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">3. Hari / Tanggal Pelaksanaan <span class="text-rose-500">*</span></label>
                <input type="date" name="tanggal" required value="<?= htmlspecialchars($data['tanggal']) ?>" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-purple-100 font-bold text-xs text-slate-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">4. Waktu / Durasi</label>
                <input type="text" name="waktu" value="<?= htmlspecialchars($data['waktu']) ?>" placeholder="Contoh: 60 Menit" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-purple-100 font-bold text-xs text-slate-700">
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-2">5. Deskripsi Kasus (Latar Belakang & Kronologi Kasus) <span class="text-rose-500">*</span></label>
            <textarea name="deskripsi_kasus" rows="4" required placeholder="Tuliskan gambaran/uraian permasalahan yang terjadi secara rinci..." class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-purple-100 font-medium text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($data['deskripsi_kasus']) ?></textarea>
            <p class="text-[11px] text-slate-400 mt-1">Uraian ini juga akan otomatis menjadi poin (7.a) Deskripsi Kasus pada langkah pelaksanaan.</p>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-2">6. Pihak-Pihak yang Terlibat <span class="text-rose-500">*</span></label>
            <input type="text" name="pihak_dilibatkan" required value="<?= htmlspecialchars($data['pihak_dilibatkan']) ?>" placeholder="Contoh: Kepala Sekolah, Waka Kesiswaan, Waka Kurikulum, Wali Kelas, Guru BK" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-purple-100 font-bold text-xs text-slate-700">
        </div>

        <!-- 7. Langkah-langkah Pelaksanaan -->
        <div class="p-6 bg-slate-50/80 rounded-3xl border border-slate-200 space-y-5">
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-purple-600 text-white font-black text-xs flex items-center justify-center">7</span>
                <h3 class="text-sm font-black text-slate-800">Langkah-Langkah Pelaksanaan Konferensi Kasus</h3>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">b. Urun Pendapat / Tanggapan</label>
                <textarea name="urun_pendapat" rows="4" placeholder="- siswa diharapkan untuk tetap sekolah&#10;- siswa dapat merubah prilaku menjadi lebih baik&#10;- siswa diadakan tindak lanjut home visit..." class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-purple-100 font-medium text-xs text-slate-700 leading-relaxed bg-white"><?= htmlspecialchars($data['urun_pendapat']) ?></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">c. Pengambilan Keputusan</label>
                <textarea name="keputusan_kasus" rows="3" placeholder="- siswa diberi kesempatan untuk masuk sekolah dengan menulis surat pernyataan..." class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-purple-100 font-medium text-xs text-slate-700 leading-relaxed bg-white"><?= htmlspecialchars($data['keputusan_kasus']) ?></textarea>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Rencana Tindak Lanjut</label>
                <input type="text" name="tindak_lanjut" value="<?= htmlspecialchars($data['tindak_lanjut']) ?>" placeholder="Contoh: Pemantauan kehadiran oleh wali kelas & guru BK" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-purple-100 font-bold text-xs text-slate-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Status Kasus</label>
                <select name="status" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-purple-100 font-bold text-xs text-slate-700 bg-white">
                    <option value="Proses" <?= $data['status'] === 'Proses' ? 'selected' : '' ?>>Dalam Proses</option>
                    <option value="Selesai" <?= $data['status'] === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                    <option value="Rujukan" <?= $data['status'] === 'Rujukan' ? 'selected' : '' ?>>Alih Tangan Kasus (Rujukan)</option>
                </select>
            </div>
        </div>

        <?php if ($role === 'admin' || $role === 'waka'): ?>
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-2">Guru BK / Konselor</label>
            <select name="guru_id" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-purple-100 font-bold text-xs text-slate-700 bg-white">
                <option value="">-- Pilih Guru BK --</option>
                <?php if ($gurus): mysqli_data_seek($gurus, 0); while ($g = mysqli_fetch_assoc($gurus)): ?>
                    <option value="<?= $g['id'] ?>" <?= $g['id'] == $data['guru_id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_lengkap']) ?></option>
                <?php endwhile; endif; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="flex justify-between pt-6 border-t border-slate-100">
            <a href="<?= BASE_URL . ($role === 'guru' ? 'guru/' : 'admin/') ?>bk_laporan.php?jenis=Konferensi+Kasus" class="px-6 py-3 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-2xl text-xs flex items-center">
                <i class="fa fa-arrow-left mr-2"></i> Batal
            </a>
            <button type="submit" name="simpan_konferensi_kasus" class="px-8 py-3.5 bg-purple-600 hover:bg-purple-700 text-white font-black rounded-2xl text-xs shadow-xl shadow-purple-100 flex items-center">
                <i class="fa fa-save mr-2"></i> Simpan Konferensi Kasus
            </button>
        </div>
    </form>
</div>

<script>
function onStudentSelect() {
    const sel = document.getElementById('siswa_id');
    const inputKelas = document.getElementById('display_kelas');
    const inputKode = document.getElementById('kode_konseli');
    if (!sel || !inputKelas) return;

    const opt = sel.options[sel.selectedIndex];
    if (!opt) return;

    const kelas = opt.getAttribute('data-kelas');
    const kode = opt.getAttribute('data-kode');

    if (kelas) inputKelas.value = kelas;
    if (kode && inputKode && (!inputKode.value || inputKode.value.trim() === '')) {
        inputKode.value = kode;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    onStudentSelect();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
