<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
$guru_id = mysqli_fetch_assoc($guru_res)['id'];

if (!$active_tahun_id) die("Error: Tidak ada tahun pelajaran aktif.");

// Check setting for date selection permission
$res_tgl_opt = mysqli_query($conn, "SELECT nilai_setting FROM pengaturan WHERE nama_setting = 'pilih_tanggal_jurnal'");
$allow_choose_date = 'nonaktif';
if ($row_tgl_opt = mysqli_fetch_assoc($res_tgl_opt)) {
    $allow_choose_date = $row_tgl_opt['nilai_setting'];
}

$raw_tgl = ($allow_choose_date === 'aktif' && !empty($_REQUEST['tanggal'])) ? $_REQUEST['tanggal'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_tgl)) {
    $raw_tgl = date('Y-m-d');
}
$tgl = mysqli_real_escape_string($conn, $raw_tgl);

// Convert date to Indonesian Day Name
$day_eng = date('l', strtotime($tgl));
$day_map = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];
$hari_ini = $day_map[$day_eng] ?? 'Senin';

// Fetch schedules for this teacher for today/selected day
$schedules = [];
$q_jadwal = "SELECT jp.*, k.nama_kelas, mp.nama_mapel, mp.kode_mapel
             FROM jadwal_pelajaran jp
             JOIN kelas k ON jp.kelas_id = k.id
             JOIN mata_pelajaran mp ON jp.mapel_id = mp.id
             WHERE jp.guru_id = $guru_id AND jp.hari = '$hari_ini'
             ORDER BY jp.jam_ke ASC";
$res_jadwal = mysqli_query($conn, $q_jadwal);
while ($row = mysqli_fetch_assoc($res_jadwal)) {
    $jp_id = (int)$row['id'];
    $k_id = (int)$row['kelas_id'];
    $m_id = (int)$row['mapel_id'];
    $jam_ke_esc = mysqli_real_escape_string($conn, $row['jam_ke']);

    // Check if journal entry already exists for this schedule on this date
    $chk = mysqli_query($conn, "SELECT id FROM jurnal WHERE (jadwal_id = $jp_id AND tanggal = '$tgl') OR (guru_id = $guru_id AND kelas_id = $k_id AND mapel_id = $m_id AND jam_ke = '$jam_ke_esc' AND tanggal = '$tgl')");
    $row['is_filled'] = (mysqli_num_rows($chk) > 0);
    $schedules[] = $row;
}

$message = ''; $message_type = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_absensi'])) {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) throw new Exception("Token Keamanan Tidak Valid.");

        $jadwal_id = (int)($_POST['jadwal_id'] ?? 0);
        $mid = (int)($_POST['mapel_id'] ?? 0);
        $kid = (int)($_POST['kelas_id'] ?? 0);
        $jam = trim($_POST['jam_ke'] ?? '');

        if ($jadwal_id <= 0 || $mid <= 0 || $kid <= 0 || empty($jam)) {
            throw new Exception("Silakan pilih jadwal mengajar terlebih dahulu.");
        }

        // Verify that schedule is not already filled
        $chk_double = mysqli_query($conn, "SELECT id FROM jurnal WHERE (jadwal_id = $jadwal_id AND tanggal = '$tgl') OR (guru_id = $guru_id AND kelas_id = $kid AND mapel_id = $mid AND jam_ke = '$jam' AND tanggal = '$tgl')");
        if (mysqli_num_rows($chk_double) > 0) {
            throw new Exception("Jadwal mengajar ini sudah diisi jurnalnya untuk tanggal " . date('d M Y', strtotime($tgl)) . ".");
        }

        if (empty($_POST['absen'])) {
            throw new Exception("Daftar absensi siswa wajib diisi. Silakan pilih kelas yang memiliki siswa aktif.");
        }

        // Simpan ke session sebagai draft jurnal
        $_SESSION['draft_jurnal'] = [
            'jadwal_id' => $jadwal_id,
            'guru_id' => $guru_id,
            'mapel_id' => $mid,
            'kelas_id' => $kid,
            'tahun_pelajaran_id' => $active_tahun_id,
            'tanggal' => $tgl,
            'jam_ke' => $jam,
            'absen' => $_POST['absen']
        ];

        // Success and Redirect
        echo "<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body style='font-family:sans-serif;'>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Absensi telah disimpan. Silakan lengkapi materi jurnal.',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                }).then(() => {
                    window.location.href = 'isi_jurnal.php';
                });
            });
        </script></body></html>";
        exit;
    } catch (Exception $e) {
        $message = "Gagal: " . $e->getMessage(); $message_type = 'error';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>#sidebar, header, nav.navbar { display: none !important; } .lg\:ml-64 { margin-left: 0 !important; } .main-content { margin-left: 0 !important; padding-top: 2rem !important; }</style>

<div class="max-w-4xl mx-auto pb-32 px-4">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Mulai Absensi & Jurnal</h1>
            <p class="text-slate-500 font-medium tracking-wide">Pilih Jadwal Mengajar Hari Ini (<?= htmlspecialchars($hari_ini) ?>, <?= date('d M Y', strtotime($tgl)) ?>)</p>
        </div>
        <a href="index.php" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
            <i class="fa fa-arrow-left"></i>
        </a>
    </div>

    <?php if ($message): ?>
    <script>
        Swal.fire({
            icon: '<?= $message_type ?>',
            title: 'Peringatan',
            text: '<?= addslashes($message) ?>',
            confirmButtonColor: '#4F46E5'
        });
    </script>
    <?php endif; ?>

    <form action="" method="POST" id="absensi_form" class="space-y-8">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <input type="hidden" name="jadwal_id" id="selected_jadwal_id" value="">
        <input type="hidden" name="mapel_id" id="selected_mapel_id" value="">
        <input type="hidden" name="kelas_id" id="selected_kelas_id" value="">
        <input type="hidden" name="jam_ke" id="selected_jam_ke" value="">

        <!-- Date selector if enabled -->
        <?php if ($allow_choose_date === 'aktif'): ?>
        <div class="lux-card p-6 bg-white shadow-xl mb-6">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Pilih Tanggal Mengajar</label>
                    <input type="date" name="tanggal" id="tanggal_absen" value="<?= htmlspecialchars($tgl) ?>" onchange="window.location.href='isi_absensi.php?tanggal='+this.value" class="px-4 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-700 outline-none focus:ring-4 focus:ring-indigo-100">
                </div>
                <div class="text-right">
                    <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-3 py-1.5 rounded-xl border border-indigo-100">
                        Hari: <strong class="uppercase"><?= $hari_ini ?></strong>
                    </span>
                </div>
            </div>
        </div>
        <?php else: ?>
            <input type="hidden" name="tanggal" id="tanggal_absen" value="<?= htmlspecialchars($tgl) ?>">
        <?php endif; ?>

        <!-- Schedule Selector Section -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-black text-slate-800 italic px-1">Jadwal Mengajar Anda</h3>
                <span class="text-xs font-bold text-slate-400"><?= count($schedules) ?> Sesi Terdaftar</span>
            </div>

            <?php if (empty($schedules)): ?>
                <div class="lux-card p-8 text-center bg-white shadow-xl rounded-3xl">
                    <div class="w-16 h-16 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center mx-auto text-2xl mb-4">
                        <i class="fa fa-calendar-times"></i>
                    </div>
                    <h4 class="text-lg font-bold text-slate-800 italic">Tidak Ada Jadwal Mengajar</h4>
                    <p class="text-slate-500 text-sm mt-1">Anda tidak memiliki jadwal mengajar terdaftar untuk hari <strong><?= $hari_ini ?></strong>.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="schedule_cards_container">
                    <?php foreach ($schedules as $s): ?>
                        <div
                            class="schedule-card lux-card p-5 bg-white border-2 rounded-2xl transition-all relative cursor-pointer
                            <?= $s['is_filled'] ? 'border-slate-200 bg-slate-50 opacity-60 cursor-not-allowed' : 'border-slate-200 hover:border-indigo-500 hover:shadow-lg' ?>"
                            data-id="<?= $s['id'] ?>"
                            data-kelas="<?= $s['kelas_id'] ?>"
                            data-mapel="<?= $s['mapel_id'] ?>"
                            data-jam="<?= htmlspecialchars($s['jam_ke']) ?>"
                            data-filled="<?= $s['is_filled'] ? '1' : '0' ?>"
                            onclick="selectSchedule(this)"
                        >
                            <div class="flex items-center justify-between mb-3">
                                <span class="px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-100">
                                    Jam Ke: <?= htmlspecialchars($s['jam_ke']) ?>
                                </span>
                                <?php if ($s['is_filled']): ?>
                                    <span class="px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 flex items-center gap-1">
                                        <i class="fa fa-check-circle"></i> Sudah Diisi
                                    </span>
                                <?php else: ?>
                                    <span class="schedule-status-badge px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-amber-50 text-amber-700 border border-amber-100">
                                        Pilih Jadwal
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="font-extrabold text-slate-800 text-lg leading-snug">
                                <?= htmlspecialchars($s['nama_kelas']) ?>
                            </div>
                            <div class="text-sm font-semibold text-slate-600 mt-0.5">
                                <?= htmlspecialchars($s['nama_mapel']) ?> <span class="text-xs text-slate-400 font-mono">(<?= htmlspecialchars($s['kode_mapel']) ?>)</span>
                            </div>

                            <?php if ($s['is_filled']): ?>
                                <div class="mt-3 pt-3 border-t border-slate-200 text-xs text-slate-400 italic">
                                    Jurnal untuk jadwal ini sudah diisi pada hari ini.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Attendance Section -->
        <div id="attendance_section" class="hidden space-y-6 pt-4">
            <div class="flex items-center justify-between border-t border-slate-200 pt-6">
                <div>
                    <h3 class="text-xl font-black text-slate-800 italic">Daftar Absensi Siswa</h3>
                    <p class="text-xs font-semibold text-indigo-600" id="selected_schedule_info"></p>
                </div>
            </div>

            <div id="siswa_grid" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>

            <div class="lux-card p-8 bg-slate-900 text-white shadow-2xl sticky bottom-24 z-20 rounded-3xl">
                <div class="grid grid-cols-4 gap-4 mb-6">
                    <?php foreach(['H'=>'Hadir','S'=>'Sakit','I'=>'Izin','A'=>'Alfa'] as $code => $label): ?>
                        <div class="text-center">
                            <div id="count_<?= $code ?>" class="text-2xl font-black text-indigo-400">0</div>
                            <div class="text-[8px] font-black uppercase opacity-50 tracking-widest"><?= $label ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="simpan_absensi" class="w-full py-4 bg-indigo-500 hover:bg-indigo-400 text-white font-black rounded-2xl shadow-xl transition-all flex items-center justify-center gap-3">
                    LANJUT KE ISI JURNAL <i class="fa fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
let selectedCardEl = null;

function selectSchedule(cardEl) {
    const isFilled = cardEl.getAttribute('data-filled') === '1';
    if (isFilled) {
        Swal.fire({
            icon: 'info',
            title: 'Jadwal Sudah Diisi',
            text: 'Jadwal mengajar ini sudah diisi jurnalnya untuk tanggal hari ini.',
            confirmButtonColor: '#4F46E5'
        });
        return;
    }

    // Deselect all cards
    document.querySelectorAll('.schedule-card').forEach(c => {
        if (c.getAttribute('data-filled') !== '1') {
            c.classList.remove('border-indigo-600', 'bg-indigo-50/30', 'ring-4', 'ring-indigo-100');
            c.classList.add('border-slate-200');
            const badge = c.querySelector('.schedule-status-badge');
            if (badge) {
                badge.className = 'schedule-status-badge px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-amber-50 text-amber-700 border border-amber-100';
                badge.textContent = 'Pilih Jadwal';
            }
        }
    });

    // Select clicked card
    cardEl.classList.remove('border-slate-200');
    cardEl.classList.add('border-indigo-600', 'bg-indigo-50/30', 'ring-4', 'ring-indigo-100');

    const badge = cardEl.querySelector('.schedule-status-badge');
    if (badge) {
        badge.className = 'schedule-status-badge px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-indigo-600 text-white';
        badge.textContent = 'Terpilih';
    }

    const jId = cardEl.getAttribute('data-id');
    const kId = cardEl.getAttribute('data-kelas');
    const mId = cardEl.getAttribute('data-mapel');
    const jam = cardEl.getAttribute('data-jam');

    document.getElementById('selected_jadwal_id').value = jId;
    document.getElementById('selected_kelas_id').value = kId;
    document.getElementById('selected_mapel_id').value = mId;
    document.getElementById('selected_jam_ke').value = jam;

    const kelasName = cardEl.querySelector('.font-extrabold').textContent.trim();
    const mapelName = cardEl.querySelector('.font-semibold').textContent.trim();
    document.getElementById('selected_schedule_info').textContent = `${kelasName} - ${mapelName} (Jam Ke: ${jam})`;

    fetchSiswa(kId);
}

function updateCounts() {
    ['H','S','I','A'].forEach(s => {
        const countEl = document.getElementById('count_'+s);
        if (countEl) {
            countEl.textContent = document.querySelectorAll('input[type="radio"]:checked[value="'+s+'"]').length;
        }
    });
}

function fetchSiswa(kelasId) {
    const tInput = document.getElementById('tanggal_absen');
    const attSection = document.getElementById('attendance_section');
    const sGrid = document.getElementById('siswa_grid');
    const tanggal = tInput ? tInput.value : '';

    if (!kelasId) {
        attSection.classList.add('hidden');
        return;
    }

    fetch(`../api/get_siswa_kelas.php?kelas_id=${kelasId}&tanggal=${tanggal}`)
        .then(async r => {
            const data = await r.json();
            if (!r.ok) throw new Error(data.error || 'Terjadi kesalahan sistem');
            return data;
        })
        .then(data => {
            sGrid.innerHTML = '';
            if (!data || data.length === 0) {
                sGrid.innerHTML = '<div class="col-span-full p-8 text-center text-slate-400 italic bg-slate-50 rounded-2xl">Tidak ada siswa terdaftar di kelas ini untuk tahun pelajaran aktif.</div>';
            } else {
                data.forEach(s => {
                    let checkedOption = 'H';
                    let badgeHtml = '';

                    if (s.status_verifikasi === 'disetujui') {
                        if (s.gps_status === 'Sakit') {
                            checkedOption = 'S';
                            badgeHtml = '<span class="px-2 py-0.5 rounded text-[8px] font-black bg-amber-100 text-amber-700 uppercase tracking-wider ml-2">Sakit (Disetujui)</span>';
                        } else if (s.gps_status === 'Izin') {
                            checkedOption = 'I';
                            badgeHtml = '<span class="px-2 py-0.5 rounded text-[8px] font-black bg-blue-100 text-blue-700 uppercase tracking-wider ml-2">Izin (Disetujui)</span>';
                        }
                    } else if (s.gps_active) {
                        if (s.gps_status === 'Hadir' || s.gps_status === 'Terlambat') {
                            checkedOption = 'H';
                        } else if (s.gps_status === 'Sakit' && s.status_verifikasi !== 'disetujui') {
                            checkedOption = 'H';
                        } else if (s.gps_status === 'Izin' && s.status_verifikasi !== 'disetujui') {
                            checkedOption = 'H';
                        } else {
                            checkedOption = 'A';
                        }
                    }

                    const card = document.createElement('div');
                    card.className = "lux-card p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white";
                    card.innerHTML = `
                        <div class="min-w-0 pr-4 flex items-center flex-wrap">
                            <div class="font-bold text-slate-700 break-words whitespace-normal text-sm">${s.nama_siswa}</div>
                            ${badgeHtml}
                        </div>
                        <div class="flex gap-1 justify-end">
                            ${['H','S','I','A'].map(st => `
                                <label class="w-8 h-8 flex items-center justify-center cursor-pointer">
                                    <input type="radio" name="absen[${s.id}]" value="${st}" ${st==checkedOption?'checked':''} class="peer hidden">
                                    <div class="w-full h-full rounded-lg border-2 flex items-center justify-center text-[10px] font-black transition-all
                                        ${st=='H'?'border-emerald-50 text-emerald-300 peer-checked:bg-emerald-500 peer-checked:text-white':''}
                                        ${st=='S'?'border-amber-50 text-amber-300 peer-checked:bg-amber-500 peer-checked:text-white':''}
                                        ${st=='I'?'border-blue-50 text-blue-300 peer-checked:bg-blue-500 peer-checked:text-white':''}
                                        ${st=='A'?'border-rose-50 text-rose-300 peer-checked:bg-rose-500 peer-checked:text-white':''}
                                    ">${st}</div>
                                </label>`).join('')}
                        </div>`;
                    sGrid.appendChild(card);
                });
            }
            attSection.classList.remove('hidden');
            updateCounts();
            document.querySelectorAll('input[type="radio"]').forEach(r => r.addEventListener('change', updateCounts));

            // Scroll smoothly to attendance section
            attSection.scrollIntoView({ behavior: 'smooth' });
        })
        .catch(err => {
            console.error('Error fetching students:', err);
            Swal.fire('Gagal Memuat Siswa', err.message, 'error');
            attSection.classList.add('hidden');
        });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
