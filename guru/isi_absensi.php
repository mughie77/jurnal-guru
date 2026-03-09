<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
$guru_id = mysqli_fetch_assoc($guru_res)['id'];

if (!$active_tahun_id) die("Error: Tidak ada tahun pelajaran aktif.");

$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");

$message = ''; $message_type = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_absensi'])) {
    $jurnal_id = (int)$_POST['jurnal_id'];
    mysqli_begin_transaction($conn);
    try {
        if (!empty($_POST['absen'])) {
            mysqli_query($conn, "DELETE FROM absensi_jurnal WHERE jurnal_id = $jurnal_id");
            $stmt_absen = mysqli_prepare($conn, "INSERT INTO absensi_jurnal (jurnal_id, siswa_id, status) VALUES (?, ?, ?)");
            foreach ($_POST['absen'] as $siswa_id => $status) {
                mysqli_stmt_bind_param($stmt_absen, "iis", $jurnal_id, $siswa_id, $status);
                mysqli_stmt_execute($stmt_absen);
            }

            // Sync counts back to journal table
            $qH = mysqli_query($conn, "SELECT COUNT(*) as jml FROM absensi_jurnal WHERE jurnal_id = $jurnal_id AND status = 'H'");
            $qS = mysqli_query($conn, "SELECT COUNT(*) as jml FROM absensi_jurnal WHERE jurnal_id = $jurnal_id AND status = 'S'");
            $qI = mysqli_query($conn, "SELECT COUNT(*) as jml FROM absensi_jurnal WHERE jurnal_id = $jurnal_id AND status = 'I'");
            $qA = mysqli_query($conn, "SELECT COUNT(*) as jml FROM absensi_jurnal WHERE jurnal_id = $jurnal_id AND status = 'A'");

            $h = mysqli_fetch_assoc($qH)['jml'];
            $s = mysqli_fetch_assoc($qS)['jml'];
            $i = mysqli_fetch_assoc($qI)['jml'];
            $a = mysqli_fetch_assoc($qA)['jml'];

            mysqli_query($conn, "UPDATE jurnal SET jml_hadir = $h, jml_sakit = $s, jml_izin = $i, jml_alfa = $a WHERE id = $jurnal_id");
        }
        mysqli_commit($conn);
        $message = "Absensi berhasil disimpan!"; $message_type = 'success';
    } catch (Exception $e) { mysqli_rollback($conn); $message = "Error: " . $e->getMessage(); $message_type = 'error'; }
}

// Get journals of today for attendance selection
$today = date('Y-m-d');
$jurnals_today = mysqli_query($conn, "SELECT j.id, mp.nama_mapel, k.nama_kelas, j.jam_ke FROM jurnal j JOIN mata_pelajaran mp ON j.mapel_id = mp.id JOIN kelas k ON j.kelas_id = k.id WHERE j.guru_id = $guru_id AND j.tanggal = '$today'");

require_once __DIR__ . '/../includes/header.php';
?>

<style>#sidebar, header { display: none; } .lg\:ml-64 { margin-left: 0; }</style>

<div class="max-w-4xl mx-auto pb-20 px-4">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Input Absensi</h1>
            <p class="text-slate-500 font-medium tracking-wide"><?= date('l, d F Y') ?></p>
        </div>
        <a href="index.php" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
            <i class="fa fa-arrow-left"></i>
        </a>
    </div>

    <?php if ($message): ?>
    <script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= $message ?>' });</script>
    <?php endif; ?>

    <form action="" method="POST" class="space-y-8">
        <div class="lux-card p-8 bg-white shadow-2xl">
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-3 ml-1">Pilih Jam Pelajaran / Kelas</label>
            <select name="jurnal_id" id="jurnal_id" required class="w-full px-4 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold text-slate-700 transition-all">
                <option value="">-- Pilih Jurnal Hari Ini --</option>
                <?php while($j = mysqli_fetch_assoc($jurnals_today)): ?>
                    <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['nama_kelas']) ?> | <?= htmlspecialchars($j['nama_mapel']) ?> (Jam: <?= htmlspecialchars($j['jam_ke']) ?>)</option>
                <?php endwhile; ?>
            </select>
            <?php if(mysqli_num_rows($jurnals_today) == 0): ?>
                <p class="mt-4 p-4 rounded-xl bg-amber-50 text-amber-700 text-xs font-bold border border-amber-100 flex items-center italic">
                    <i class="fa fa-exclamation-triangle mr-2"></i> Belum ada jurnal yang diisi hari ini. Silakan isi jurnal terlebih dahulu.
                </p>
            <?php endif; ?>
        </div>

        <div id="attendance_section" class="hidden space-y-6">
            <div class="flex items-center justify-between px-2">
                <h3 class="text-xl font-black text-slate-800 italic">Daftar Siswa</h3>
                <div class="text-[10px] font-black text-slate-400 uppercase flex gap-4">
                    <span>H: Hadir</span><span>S: Sakit</span><span>I: Izin</span><span>A: Alfa</span>
                </div>
            </div>

            <div id="siswa_grid" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Loaded via JS -->
            </div>

            <div class="lux-card p-8 bg-slate-900 text-white shadow-2xl sticky bottom-4 z-20">
                <div class="grid grid-cols-4 gap-4 mb-8">
                    <div class="text-center">
                        <div id="count_H" class="text-2xl font-black text-emerald-400">0</div>
                        <div class="text-[8px] font-black uppercase opacity-50 tracking-widest">Hadir</div>
                    </div>
                    <div class="text-center">
                        <div id="count_S" class="text-2xl font-black text-amber-400">0</div>
                        <div class="text-[8px] font-black uppercase opacity-50 tracking-widest">Sakit</div>
                    </div>
                    <div class="text-center">
                        <div id="count_I" class="text-2xl font-black text-blue-400">0</div>
                        <div class="text-[8px] font-black uppercase opacity-50 tracking-widest">Izin</div>
                    </div>
                    <div class="text-center">
                        <div id="count_A" class="text-2xl font-black text-rose-400">0</div>
                        <div class="text-[8px] font-black uppercase opacity-50 tracking-widest">Alfa</div>
                    </div>
                </div>
                <button type="submit" name="simpan_absensi" class="w-full py-4 bg-indigo-500 hover:bg-indigo-400 text-white font-black rounded-2xl shadow-xl transition-all transform hover:-translate-y-1 active:scale-95 flex items-center justify-center gap-3 tracking-widest">
                    <i class="fa fa-check-circle text-lg"></i> KONFIRMASI ABSENSI
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const jSelect = document.getElementById('jurnal_id');
    const attSection = document.getElementById('attendance_section');
    const sGrid = document.getElementById('siswa_grid');

    const updateCounts = () => {
        ['H','S','I','A'].forEach(s => {
            document.getElementById('count_'+s).textContent = document.querySelectorAll('input[type="radio"]:checked[value="'+s+'"]').length;
        });
    };

    jSelect.addEventListener('change', function() {
        if (!this.value) { attSection.classList.add('hidden'); return; }

        // Fetch class id from the selection (we need an API to get journal details or just use class id directly)
        // For simplicity, let's assume we fetch by the journal's class
        fetch(`<?= BASE_URL ?>api/get_siswa_by_jurnal.php?jurnal_id=${this.value}`)
            .then(r => r.json())
            .then(data => {
                sGrid.innerHTML = '';
                data.forEach(s => {
                    const card = document.createElement('div');
                    card.className = "lux-card p-4 flex items-center justify-between group transition-all duration-300 hover:border-indigo-200";
                    card.innerHTML = `
                        <div class="flex-1 min-w-0 pr-4">
                            <div class="font-bold text-slate-700 truncate text-sm">${s.nama_siswa}</div>
                            <div class="text-[10px] text-slate-400 font-mono">${s.nis}</div>
                        </div>
                        <div class="flex gap-1.5">
                            ${['H','S','I','A'].map(st => `
                                <label class="w-8 h-8 flex items-center justify-center cursor-pointer">
                                    <input type="radio" name="absen[${s.id}]" value="${st}" ${st=='H'?'checked':''} class="peer hidden">
                                    <div class="w-full h-full rounded-lg border-2 flex items-center justify-center text-[10px] font-black transition-all
                                        ${st=='H'?'border-emerald-50 text-emerald-300 peer-checked:bg-emerald-500 peer-checked:text-white peer-checked:border-emerald-500':''}
                                        ${st=='S'?'border-amber-50 text-amber-300 peer-checked:bg-amber-500 peer-checked:text-white peer-checked:border-amber-500':''}
                                        ${st=='I'?'border-blue-50 text-blue-300 peer-checked:bg-blue-500 peer-checked:text-white peer-checked:border-blue-500':''}
                                        ${st=='A'?'border-rose-50 text-rose-300 peer-checked:bg-rose-500 peer-checked:text-white peer-checked:border-rose-500':''}
                                    ">${st}</div>
                                </label>
                            `).join('')}
                        </div>
                    `;
                    sGrid.appendChild(card);
                });
                attSection.classList.remove('hidden');
                updateCounts();
                document.querySelectorAll('input[type="radio"]').forEach(r => r.addEventListener('change', updateCounts));
            });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
