<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

if (!isset($_SESSION['draft_jurnal'])) {
    header("Location: isi_absensi.php");
    exit;
}

$draft = $_SESSION['draft_jurnal'];
$guru_id = $draft['guru_id'];
$mid = (int)$draft['mapel_id'];
$kid = (int)$draft['kelas_id'];
$tahun_pelajaran_id = $draft['tahun_pelajaran_id'];
$tanggal = $draft['tanggal'];
$jam_ke = $draft['jam_ke'];
$absen_list = $draft['absen'];

$res_meta = mysqli_query($conn, "SELECT mp.nama_mapel, k.nama_kelas
                                  FROM mata_pelajaran mp, kelas k
                                  WHERE mp.id = $mid AND k.id = $kid");
$meta = mysqli_fetch_assoc($res_meta);

$count_hadir = 0;
foreach ($absen_list as $status) {
    if ($status === 'H') $count_hadir++;
}

$nama_mapel = $meta['nama_mapel'] ?? '';
$is_pjok = false;
$lower_mapel = strtolower($nama_mapel);
if (strpos($lower_mapel, 'pjok') !== false || strpos($lower_mapel, 'olahraga') !== false || strpos($lower_mapel, 'penjas') !== false || strpos($lower_mapel, 'penjaskes') !== false) {
    $is_pjok = true;
}

$j = [
    'nama_mapel' => $nama_mapel,
    'nama_kelas' => $meta['nama_kelas'] ?? '',
    'jam_ke' => $jam_ke,
    'jml_hadir' => $count_hadir
];

// Ambil siswa yang tidak hadir (S/I/A)
$tidak_hadir = [];
$absent_student_ids = [];
$student_status_map = [];
foreach ($absen_list as $sid => $status) {
    if ($status !== 'H') {
        $absent_student_ids[] = (int)$sid;
        $student_status_map[(int)$sid] = $status;
    }
}
if (!empty($absent_student_ids)) {
    $ids_str = implode(',', $absent_student_ids);
    $res_stud = mysqli_query($conn, "SELECT id, nama_siswa FROM siswa WHERE id IN ($ids_str) ORDER BY nama_siswa ASC");
    while ($row = mysqli_fetch_assoc($res_stud)) {
        $tidak_hadir[] = [
            'nama_siswa' => $row['nama_siswa'],
            'status' => $student_status_map[$row['id']]
        ];
    }
}

// Ambil seluruh siswa di kelas ini untuk modal Buku Kejadian
$res_all_siswa = mysqli_query($conn, "SELECT s.id, s.nama_siswa, s.nis FROM siswa s JOIN siswa_kelas sk ON s.id = sk.siswa_id WHERE sk.kelas_id = $kid ORDER BY s.nama_siswa ASC");
$all_siswa = [];
while ($row_s = mysqli_fetch_assoc($res_all_siswa)) {
    $all_siswa[] = $row_s;
}

// Format Hari
$day_eng = date('l', strtotime($tanggal));
$day_map = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];
$hari_str = $day_map[$day_eng] ?? 'Senin';

// Ambil setting lokasi sekolah dan radius absensi
$res_set = mysqli_query($conn, "SELECT * FROM pengaturan");
$sets = [];
while ($r = mysqli_fetch_assoc($res_set)) {
    $sets[$r['nama_setting']] = $r['nilai_setting'];
}
$school_lat = (float)($sets['school_lat'] ?? -7.9135);
$school_lng = (float)($sets['school_lng'] ?? 113.8217);
$radius_absen = (int)($sets['radius_absen'] ?? 30);

function vincentyGreatCircleDistance($lat1, $lon1, $lat2, $lon2, $earthRadius = 6371000) {
    $latFrom = deg2rad($lat1);
    $lonFrom = deg2rad($lon1);
    $latTo = deg2rad($lat2);
    $lonTo = deg2rad($lon2);

    $lonDelta = $lonTo - $lonFrom;
    $a = pow(cos($latTo) * sin($lonDelta), 2) + pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
    $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

    $angle = atan2(sqrt($a), $b);
    return $angle * $earthRadius;
}

$message = ''; $message_type = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_jurnal'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");
    $materi = mysqli_real_escape_string($conn, $_POST['materi']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    $latitude = mysqli_real_escape_string($conn, $_POST['latitude'] ?? '');
    $longitude = mysqli_real_escape_string($conn, $_POST['longitude'] ?? '');

    if (trim($materi) === '') {
        $message = "Materi pembahasan wajib diisi!";
        $message_type = 'error';
    } else if (empty($latitude) || empty($longitude)) {
        $message = "Akses lokasi GPS Anda wajib aktif dan terdeteksi untuk mengisi jurnal!";
        $message_type = 'error';
    } else {
        $distance = vincentyGreatCircleDistance((float)$latitude, (float)$longitude, $school_lat, $school_lng);
        if (!$is_pjok && $distance > ($radius_absen + 5)) { // 5m buffer for GPS jitter
            $message = "Anda berada di luar radius lokasi sekolah (" . round($distance) . "m dari sekolah). Pengisian jurnal wajib dilakukan di dalam area sekolah (maksimal " . $radius_absen . "m)!";
            $message_type = 'error';
        } else {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            mysqli_begin_transaction($conn);
        try {
            // 1. Insert into jurnal
            $stmt = mysqli_prepare($conn, "INSERT INTO jurnal (guru_id, mapel_id, kelas_id, tahun_pelajaran_id, tanggal, jam_ke, materi, keterangan, latitude, longitude, jml_hadir, jml_sakit, jml_izin, jml_alfa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0)");
            mysqli_stmt_bind_param($stmt, "iiiissssss", $guru_id, $mid, $kid, $tahun_pelajaran_id, $tanggal, $jam_ke, $materi, $keterangan, $latitude, $longitude);
            mysqli_stmt_execute($stmt);
            $jurnal_id = mysqli_insert_id($conn);

            if (!$jurnal_id) throw new Exception("Gagal membuat record jurnal.");

            // 2. Save individual attendance
            if (!empty($absen_list)) {
                $stmt_absen = mysqli_prepare($conn, "INSERT INTO absensi_jurnal (jurnal_id, siswa_id, status) VALUES (?, ?, ?)");
                foreach ($absen_list as $siswa_id => $status) {
                    mysqli_stmt_bind_param($stmt_absen, "iis", $jurnal_id, $siswa_id, $status);
                    mysqli_stmt_execute($stmt_absen);
                }

                // Sync counts
                $res_counts = mysqli_query($conn, "SELECT
                    COUNT(CASE WHEN status='H' THEN 1 END) as h,
                    COUNT(CASE WHEN status='S' THEN 1 END) as s,
                    COUNT(CASE WHEN status='I' THEN 1 END) as i,
                    COUNT(CASE WHEN status='A' THEN 1 END) as a
                    FROM absensi_jurnal WHERE jurnal_id = $jurnal_id");
                $c = mysqli_fetch_assoc($res_counts);
                mysqli_query($conn, "UPDATE jurnal SET jml_hadir={$c['h']}, jml_sakit={$c['s']}, jml_izin={$c['i']}, jml_alfa={$c['a']} WHERE id = $jurnal_id");
            }

            // 3. Save Buku Kejadian if submitted
            if (isset($_POST['is_kejadian']) && $_POST['is_kejadian'] == '1') {
                $uraian_kejadian = mysqli_real_escape_string($conn, $_POST['uraian_kejadian'] ?? '');
                $tindak_lanjut = mysqli_real_escape_string($conn, $_POST['tindak_lanjut'] ?? '');
                $pilih_semua = isset($_POST['pilih_semua_siswa']) ? 1 : 0;
                $selected_ids = $_POST['kejadian_siswa_ids'] ?? [];

                $siswa_id_arr = [];
                $siswa_nama_arr = [];

                if ($pilih_semua == 1) {
                    foreach ($all_siswa as $as) {
                        $siswa_id_arr[] = $as['id'];
                        $siswa_nama_arr[] = $as['nama_siswa'];
                    }
                } else if (!empty($selected_ids)) {
                    foreach ($selected_ids as $sid_raw) {
                        $sid_int = (int)$sid_raw;
                        $siswa_id_arr[] = $sid_int;
                        foreach ($all_siswa as $as) {
                            if ($as['id'] == $sid_int) {
                                $siswa_nama_arr[] = $as['nama_siswa'];
                                break;
                            }
                        }
                    }
                }

                $siswa_ids_str = implode(',', $siswa_id_arr);
                $siswa_nama_str = implode(', ', $siswa_nama_arr);

                $stmt_bk = mysqli_prepare($conn, "INSERT INTO buku_kejadian (jurnal_id, guru_id, kelas_id, mapel_id, tahun_pelajaran_id, tanggal, hari, siswa_ids, nama_siswa_list, uraian_kejadian, tindak_lanjut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt_bk, "iiiiissssss", $jurnal_id, $guru_id, $kid, $mid, $tahun_pelajaran_id, $tanggal, $hari_str, $siswa_ids_str, $siswa_nama_str, $uraian_kejadian, $tindak_lanjut);
                mysqli_stmt_execute($stmt_bk);
            }

            mysqli_commit($conn);

            // Bersihkan draft dari session setelah sukses disimpan
            unset($_SESSION['draft_jurnal']);

            header("Location: riwayat.php?success=1");
            exit;

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Gagal menyimpan jurnal: " . $e->getMessage();
            $message_type = 'error';
        }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>#sidebar, header, nav.navbar { display: none !important; } .lg\:ml-64 { margin-left: 0 !important; } .main-content { margin-left: 0 !important; padding-top: 2rem !important; }</style>

<div class="max-w-full w-full mx-auto pb-32 px-2 sm:px-4">
    <!-- Progress Indicator -->
    <div class="flex items-center gap-2 mb-6 sm:mb-10 overflow-hidden rounded-full bg-slate-200 h-2">
        <div class="w-1/2 h-full bg-emerald-500"></div>
        <div class="w-1/2 h-full bg-indigo-500 animate-pulse"></div>
    </div>

    <div class="flex items-center justify-between mb-6 sm:mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight italic">Langkah 2: Isi Jurnal</h1>
            <p class="text-slate-500 font-medium tracking-wide uppercase text-[8px] sm:text-[10px] tracking-[0.2em] sm:tracking-[0.3em]">Finalisasi Catatan Mengajar</p>
        </div>
        <a href="index.php" class="w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center rounded-xl sm:rounded-2xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
            <i class="fa fa-arrow-left"></i>
        </a>
    </div>

    <div class="lux-card p-6 sm:p-10 bg-white shadow-2xl relative overflow-hidden">
        <div class="absolute top-0 left-0 w-1 h-full bg-indigo-600"></div>

        <div class="mb-8 sm:mb-10 grid grid-cols-1 xs:grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6 pb-6 sm:pb-8 border-b border-slate-50">
            <div>
                <p class="text-[8px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5 sm:mb-1">Mata Pelajaran</p>
                <p class="text-sm sm:text-base font-bold text-slate-800 italic">
                    <?= htmlspecialchars($j['nama_mapel']) ?>
                    <?php if ($is_pjok): ?>
                        <span class="inline-block ml-2 px-2 py-0.5 bg-rose-100 text-rose-700 text-[9px] font-black uppercase rounded">Bebas GPS</span>
                    <?php endif; ?>
                </p>
            </div>
            <div><p class="text-[8px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5 sm:mb-1">Kelas</p><p class="text-sm sm:text-base font-bold text-slate-800"><?= htmlspecialchars($j['nama_kelas']) ?></p></div>
            <div><p class="text-[8px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5 sm:mb-1">Jam Ke-</p><p class="text-sm sm:text-base font-bold text-slate-800"><?= htmlspecialchars($j['jam_ke']) ?></p></div>
            <div><p class="text-[8px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5 sm:mb-1">Kehadiran</p><p class="text-sm sm:text-base font-bold text-emerald-600"><?= $j['jml_hadir'] ?> Siswa</p></div>
        </div>

        <?php if (!empty($tidak_hadir)): ?>
        <div class="mb-10 p-6 rounded-2xl bg-rose-50 border border-rose-100">
            <h3 class="text-xs font-black text-rose-700 uppercase tracking-widest mb-4 flex items-center">
                <i class="fa fa-user-times mr-2"></i> Siswa Tidak Hadir
            </h3>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($tidak_hadir as $th): ?>
                    <span class="px-3 py-1.5 bg-white rounded-xl border border-rose-200 shadow-sm text-sm font-bold text-slate-700">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-md text-[10px] font-black mr-2
                            <?= $th['status'] == 'S' ? 'bg-amber-500 text-white' : ($th['status'] == 'I' ? 'bg-blue-500 text-white' : 'bg-rose-500 text-white') ?>">
                            <?= $th['status'] ?>
                        </span>
                        <?= htmlspecialchars($th['nama_siswa']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <form action="" method="POST" id="jurnal-form" class="space-y-6 sm:space-y-8">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            <input type="hidden" name="latitude" id="lat-input">
            <input type="hidden" name="longitude" id="lng-input">
            <div>
                <label class="block text-[10px] sm:text-xs font-black text-slate-400 uppercase tracking-widest mb-2 sm:mb-3 ml-1">Materi Pembahasan Hari Ini</label>
                <textarea name="materi" rows="5" required placeholder="Jelaskan pokok bahasan, kompetensi dasar, atau aktivitas yang dilakukan..." class="w-full px-4 sm:px-6 py-3 sm:py-4 rounded-2xl sm:rounded-3xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-medium text-slate-600 text-base sm:text-lg transition-all"></textarea>
            </div>

            <div>
                <label class="block text-[10px] sm:text-xs font-black text-slate-400 uppercase tracking-widest mb-2 sm:mb-3 ml-1">Catatan Tambahan (Opsional)</label>
                <input type="text" name="keterangan" placeholder="Media pembelajaran, kendala, dll." class="w-full px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-slate-100 font-medium text-slate-500 text-sm sm:text-base">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <button type="button" onclick="openBukuKejadianModal()" class="w-full py-4 sm:py-5 bg-amber-500 hover:bg-amber-600 text-white font-black rounded-2xl sm:rounded-3xl shadow-xl shadow-amber-100 transition-all transform hover:-translate-y-1 active:scale-95 flex items-center justify-center gap-2 sm:gap-3 tracking-[0.1em] text-sm sm:text-base">
                    <i class="fa fa-book-bookmark text-lg"></i> BUKU KEJADIAN
                </button>

                <button type="submit" name="simpan_jurnal" class="w-full py-4 sm:py-5 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-2xl sm:rounded-3xl shadow-2xl shadow-indigo-200 transition-all transform hover:-translate-y-1 active:scale-95 flex items-center justify-center gap-2 sm:gap-3 tracking-[0.1em] text-sm sm:text-base">
                    <i class="fa fa-save text-lg"></i> SELESAIKAN JURNAL
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Buku Kejadian Overlay -->
<div id="bkModalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeBukuKejadianModal()"></div>

<!-- Modal Form Buku Kejadian -->
<div id="bkModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[94vw] sm:w-full max-w-xl max-h-[85vh] overflow-y-auto bg-white rounded-2xl sm:rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0">
    <div class="bg-amber-500 px-4 sm:px-6 py-4 text-white flex justify-between items-center sticky top-0 z-10 rounded-t-2xl sm:rounded-t-3xl shadow-md">
        <div>
            <h3 class="text-base sm:text-lg font-black italic">Buku Kejadian Kelas</h3>
            <p class="text-[9px] sm:text-[10px] text-amber-100 font-bold uppercase tracking-widest mt-0.5"><?= $hari_str ?>, <?= date('d F Y', strtotime($tanggal)) ?> | Kelas: <?= htmlspecialchars($j['nama_kelas']) ?></p>
        </div>
        <button type="button" onclick="closeBukuKejadianModal()" class="text-white/80 hover:text-white text-base sm:text-lg p-1"><i class="fa fa-times"></i></button>
    </div>

    <div class="p-4 sm:p-6 space-y-4 sm:space-y-6">
        <div>
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <label class="block text-[11px] sm:text-xs font-black text-slate-700 uppercase tracking-wider">Pilih Siswa Terlibat</label>
                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                    <input type="checkbox" id="check-all-siswa" onchange="toggleSelectAllSiswa(this)" class="w-3.5 h-3.5 rounded text-amber-600 focus:ring-amber-500 border-slate-300">
                    <span class="text-[10px] sm:text-xs font-bold text-amber-700 uppercase">Pilih Semua Siswa</span>
                </label>
            </div>

            <div class="max-h-36 sm:max-h-48 overflow-y-auto p-2 sm:p-3 bg-slate-50 border border-slate-200 rounded-xl sm:rounded-2xl grid grid-cols-1 xs:grid-cols-2 gap-1.5 sm:gap-2 pr-1">
                <?php foreach ($all_siswa as $as): ?>
                <label class="flex items-center gap-2 p-1.5 sm:p-2 bg-white rounded-lg sm:rounded-xl border border-slate-100 shadow-sm cursor-pointer hover:border-amber-300 transition-colors">
                    <input type="checkbox" class="cb-siswa-kejadian w-3.5 h-3.5 sm:w-4 sm:h-4 rounded text-amber-600 focus:ring-amber-500 border-slate-300 shrink-0" value="<?= $as['id'] ?>">
                    <div class="truncate">
                        <span class="text-[11px] sm:text-xs font-bold text-slate-800 block truncate"><?= htmlspecialchars($as['nama_siswa']) ?></span>
                        <span class="text-[8px] sm:text-[9px] text-slate-400 font-mono block">NIS: <?= htmlspecialchars($as['nis']) ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <label class="block text-[11px] sm:text-xs font-black text-slate-700 uppercase tracking-wider mb-1.5">Uraian Kejadian</label>
            <textarea id="modal_uraian_kejadian" rows="3" placeholder="Jelaskan secara lengkap kronologi atau uraian kejadian yang terjadi di kelas..." class="w-full px-3.5 py-2.5 sm:px-4 sm:py-3 rounded-xl sm:rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-medium text-xs text-slate-700"></textarea>
        </div>

        <div>
            <label class="block text-[11px] sm:text-xs font-black text-slate-700 uppercase tracking-wider mb-1.5">Tindak Lanjut / Pembinaan</label>
            <textarea id="modal_tindak_lanjut" rows="2.5" placeholder="Langkah penanganan, pembinaan, atau tindak lanjut yang diberikan..." class="w-full px-3.5 py-2.5 sm:px-4 sm:py-3 rounded-xl sm:rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-amber-100 font-medium text-xs text-slate-700"></textarea>
        </div>

        <div class="pt-1 flex flex-col-reverse sm:flex-row gap-2 sm:gap-3">
            <button type="button" onclick="closeBukuKejadianModal()" class="w-full sm:flex-1 px-4 py-2.5 sm:py-3 rounded-xl sm:rounded-2xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50 text-xs">Batal</button>
            <button type="button" onclick="saveBukuKejadianTemp()" class="w-full sm:flex-1 px-4 py-2.5 sm:py-3 rounded-xl sm:rounded-2xl bg-amber-500 text-white font-bold hover:bg-amber-600 shadow-lg shadow-amber-100 text-xs">
                Simpan Ke Jurnal
            </button>
        </div>
    </div>
</div>

<script>
function openBukuKejadianModal() {
    const m = document.getElementById('bkModal');
    const o = document.getElementById('bkModalOverlay');
    o.classList.remove('hidden');
    m.classList.remove('hidden');
    setTimeout(() => {
        o.classList.add('opacity-100');
        m.classList.add('opacity-100', 'scale-100');
    }, 10);
}

function closeBukuKejadianModal() {
    const m = document.getElementById('bkModal');
    const o = document.getElementById('bkModalOverlay');
    o.classList.remove('opacity-100');
    m.classList.remove('opacity-100', 'scale-100');
    setTimeout(() => {
        o.classList.add('hidden');
        m.classList.add('hidden');
    }, 300);
}

function toggleSelectAllSiswa(master) {
    const checkboxes = document.querySelectorAll('.cb-siswa-kejadian');
    checkboxes.forEach(cb => {
        cb.checked = master.checked;
    });
}

function saveBukuKejadianTemp() {
    const uraian = document.getElementById('modal_uraian_kejadian').value.trim();
    if (!uraian) {
        Swal.fire({ icon: 'warning', title: 'Uraian Kejadian Kosong', text: 'Tuliskan uraian kejadian terlebih dahulu.', confirmButtonColor: '#F59E0B' });
        return;
    }

    const form = document.getElementById('jurnal-form');

    // Remove old hidden inputs for kejadian if re-saved
    const oldInputs = form.querySelectorAll('.temp-bk-input');
    oldInputs.forEach(i => i.remove());

    // Flag kejadian as active
    const inputFlag = document.createElement('input');
    inputFlag.type = 'hidden';
    inputFlag.name = 'is_kejadian';
    inputFlag.value = '1';
    inputFlag.className = 'temp-bk-input';
    form.appendChild(inputFlag);

    // Uraian input
    const inputUraian = document.createElement('input');
    inputUraian.type = 'hidden';
    inputUraian.name = 'uraian_kejadian';
    inputUraian.value = uraian;
    inputUraian.className = 'temp-bk-input';
    form.appendChild(inputUraian);

    // Tindak Lanjut input
    const inputTL = document.createElement('input');
    inputTL.type = 'hidden';
    inputTL.name = 'tindak_lanjut';
    inputTL.value = document.getElementById('modal_tindak_lanjut').value.trim();
    inputTL.className = 'temp-bk-input';
    form.appendChild(inputTL);

    // Master check
    const isAll = document.getElementById('check-all-siswa').checked;
    if (isAll) {
        const inputAll = document.createElement('input');
        inputAll.type = 'hidden';
        inputAll.name = 'pilih_semua_siswa';
        inputAll.value = '1';
        inputAll.className = 'temp-bk-input';
        form.appendChild(inputAll);
    } else {
        const selectedCbs = document.querySelectorAll('.cb-siswa-kejadian:checked');
        selectedCbs.forEach(cb => {
            const inputS = document.createElement('input');
            inputS.type = 'hidden';
            inputS.name = 'kejadian_siswa_ids[]';
            inputS.value = cb.value;
            inputS.className = 'temp-bk-input';
            form.appendChild(inputS);
        });
    }

    closeBukuKejadianModal();

    Swal.fire({
        icon: 'success',
        title: 'Buku Kejadian Disimpan',
        text: 'Catatan kejadian berhasil dilampirkan. Klik SELESAIKAN JURNAL untuk menyimpan seluruh data.',
        confirmButtonColor: '#F59E0B'
    });
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('jurnal-form');
    const latInput = document.getElementById('lat-input');
    const lngInput = document.getElementById('lng-input');
    let hasLocation = false;

    const schoolPos = [<?= $school_lat ?>, <?= $school_lng ?>];
    const radiusAbsen = <?= $radius_absen ?>;
    const isPjok = <?= $is_pjok ? 'true' : 'false' ?>;

    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371000; // metres
        const φ1 = lat1 * Math.PI/180;
        const φ2 = lat2 * Math.PI/180;
        const Δφ = (lat2-lat1) * Math.PI/180;
        const Δλ = (lon2-lon1) * Math.PI/180;

        const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                Math.cos(φ1) * Math.cos(φ2) *
                Math.sin(Δλ/2) * Math.sin(Δλ/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

        return R * c; // in metres
    }

    function verifyAntiFakeGPS(position) {
        const accuracy = position.coords.accuracy;
        const isMocked = position.mocked || (position.coords && position.coords.mocked) || false;
        const isAutomated = navigator.webdriver;

        if (isMocked || isAutomated || accuracy <= 1) {
            Swal.fire({
                icon: 'error',
                title: 'Fake GPS Terdeteksi',
                text: 'Sistem mendeteksi penggunaan Fake GPS atau browser otomatis. Anda dilarang melakukan submit jurnal!',
                confirmButtonColor: '#4F46E5'
            });
            return false;
        }
        return true;
    }

    // Pre-fetch location on page load to speed up submission
    if ("geolocation" in navigator) {
        navigator.geolocation.getCurrentPosition(function(position) {
            if (verifyAntiFakeGPS(position)) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const distance = calculateDistance(lat, lng, schoolPos[0], schoolPos[1]);
                if (isPjok || distance <= (radiusAbsen + 5)) {
                    latInput.value = lat;
                    lngInput.value = lng;
                    hasLocation = true;
                }
            }
        }, function(error) {
            console.warn("Pre-fetch location failed:", error);
        }, { enableHighAccuracy: true, timeout: 10000 });
    }

    if (form) {
        form.addEventListener('submit', function(e) {
            if (!form.reportValidity()) {
                e.preventDefault();
                return false;
            }

            if (hasLocation && latInput.value && lngInput.value) {
                return true;
            }

            e.preventDefault(); // Stop submission to obtain location

            if (!("geolocation" in navigator)) {
                Swal.fire({
                    icon: 'error',
                    title: 'GPS Tidak Didukung',
                    text: 'Browser Anda tidak mendukung deteksi lokasi (Geolocation). Gunakan browser modern.',
                    confirmButtonColor: '#4F46E5'
                });
                return false;
            }

            Swal.fire({
                title: 'Mendeteksi Lokasi...',
                text: 'Mengambil koordinat GPS Anda untuk mencatat lokasi pengisian jurnal.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            navigator.geolocation.getCurrentPosition(function(position) {
                Swal.close();
                if (verifyAntiFakeGPS(position)) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const distance = calculateDistance(lat, lng, schoolPos[0], schoolPos[1]);

                    if (!isPjok && distance > (radiusAbsen + 5)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Di Luar Radius Sekolah',
                            text: 'Akses ditolak: Anda berada ' + Math.round(distance) + 'm dari sekolah. Pengisian jurnal wajib dilakukan di dalam area sekolah (maksimal ' + radiusAbsen + 'm)!',
                            confirmButtonColor: '#4F46E5'
                        });
                        return false;
                    }

                    latInput.value = lat;
                    lngInput.value = lng;
                    hasLocation = true;
                    form.submit(); // Resubmit the form
                }
            }, function(error) {
                Swal.close();
                let errorMsg = 'Gagal mendapatkan koordinat lokasi GPS Anda.';
                if (error.code === 1) {
                    errorMsg = 'Akses lokasi ditolak. Silakan aktifkan GPS dan izinkan akses lokasi pada browser Anda.';
                } else if (error.code === 2) {
                    errorMsg = 'Sinyal lokasi/GPS tidak tersedia atau lemah.';
                } else if (error.code === 3) {
                    errorMsg = 'Waktu pengambilan lokasi habis (timeout).';
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Lokasi Tidak Ditemukan',
                    text: errorMsg + ' Pengisian jurnal memerlukan lokasi GPS aktif.',
                    confirmButtonColor: '#4F46E5'
                });
            }, {
                enableHighAccuracy: true,
                timeout: 15000
            });
        });
    }
});
</script>

<?php if ($message !== ''): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?= $message_type ?>',
        title: '<?= $message_type === 'success' ? 'Berhasil' : 'Peringatan' ?>',
        text: '<?= addslashes($message) ?>',
        confirmButtonColor: '#4F46E5'
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
