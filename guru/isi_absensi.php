<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
$guru_id = mysqli_fetch_assoc($guru_res)['id'];

if (!$active_tahun_id) die("Error: Tidak ada tahun pelajaran aktif.");

$mapels = mysqli_query($conn, "SELECT mp.id, mp.nama_mapel FROM mata_pelajaran mp JOIN guru_mapel gm ON mp.id = gm.mapel_id WHERE gm.guru_id = $guru_id ORDER BY mp.nama_mapel");
$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");

$message = ''; $message_type = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_absensi'])) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    mysqli_begin_transaction($conn);
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) throw new Exception("Token Keamanan Tidak Valid.");

        $mid = $_POST['mapel_id'];
        $kid = $_POST['kelas_id'];
        $tgl = $_POST['tanggal'];
        $jam = $_POST['jam_ke'];

        // 1. Create a "Pre-Journal" entry
        $stmt = mysqli_prepare($conn, "INSERT INTO jurnal (guru_id, mapel_id, kelas_id, tahun_pelajaran_id, tanggal, jam_ke, materi, jml_hadir, jml_sakit, jml_izin, jml_alfa) VALUES (?, ?, ?, ?, ?, ?, '', 0, 0, 0, 0)");
        mysqli_stmt_bind_param($stmt, "iiiiss", $guru_id, $mid, $kid, $active_tahun_id, $tgl, $jam);
        mysqli_stmt_execute($stmt);
        $jurnal_id = mysqli_insert_id($conn);

        if (!$jurnal_id) throw new Exception("Gagal membuat record jurnal.");

        // 2. Save individual attendance
        if (!empty($_POST['absen'])) {
            $stmt_absen = mysqli_prepare($conn, "INSERT INTO absensi_jurnal (jurnal_id, siswa_id, status) VALUES (?, ?, ?)");
            foreach ($_POST['absen'] as $siswa_id => $status) {
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

        mysqli_commit($conn);

        // 3. Success and Redirect
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
                    window.location.href = 'isi_jurnal.php?jid=$jurnal_id';
                });
            });
        </script></body></html>";
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "Gagal: " . $e->getMessage(); $message_type = 'error';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>#sidebar, header, nav.navbar { display: none !important; } .lg\:ml-64 { margin-left: 0 !important; } .main-content { margin-left: 0 !important; padding-top: 2rem !important; }</style>

<div class="max-w-4xl mx-auto pb-32 px-4">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Mulai Absensi</h1>
            <p class="text-slate-500 font-medium tracking-wide">Langkah 1: Absensi Siswa</p>
        </div>
        <a href="index.php" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
            <i class="fa fa-arrow-left"></i>
        </a>
    </div>

    <form action="" method="POST" class="space-y-8">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <div class="lux-card p-8 bg-white shadow-2xl">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Pilih Mata Pelajaran</label>
                    <select name="mapel_id" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold">
                        <option value="">-- Pilih Mapel --</option>
                        <?php while($m = mysqli_fetch_assoc($mapels)): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Pilih Kelas</label>
                    <select name="kelas_id" id="kelas_id" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold">
                        <option value="">-- Pilih Kelas --</option>
                        <?php mysqli_data_seek($kelases, 0); while($k = mysqli_fetch_assoc($kelases)): ?>
                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Tanggal</label>
                    <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Jam Ke-</label>
                    <input type="text" name="jam_ke" placeholder="Contoh: 1-3" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold">
                </div>
            </div>
        </div>

        <div id="attendance_section" class="hidden space-y-6">
            <h3 class="text-xl font-black text-slate-800 italic px-2">Daftar Absensi Siswa</h3>
            <div id="siswa_grid" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>

            <div class="lux-card p-8 bg-slate-900 text-white shadow-2xl sticky bottom-24 z-20">
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
document.addEventListener('DOMContentLoaded', function() {
    const kSelect = document.getElementById('kelas_id');
    const attSection = document.getElementById('attendance_section');
    const sGrid = document.getElementById('siswa_grid');

    const updateCounts = () => {
        ['H','S','I','A'].forEach(s => {
            document.getElementById('count_'+s).textContent = document.querySelectorAll('input[type="radio"]:checked[value="'+s+'"]').length;
        });
    };

    kSelect.addEventListener('change', function() {
        if (!this.value) { attSection.classList.add('hidden'); return; }
        fetch(`../api/get_siswa_kelas.php?kelas_id=${this.value}`)
            .then(r => {
                if (!r.ok) throw new Error('Network response was not ok');
                return r.json();
            })
            .then(data => {
                sGrid.innerHTML = '';
                if (data.length === 0) {
                    sGrid.innerHTML = '<div class="col-span-full p-8 text-center text-slate-400 italic bg-slate-50 rounded-2xl">Tidak ada siswa terdaftar di kelas ini untuk tahun pelajaran aktif.</div>';
                }
                data.forEach(s => {
                    const card = document.createElement('div');
                    card.className = "lux-card p-4 flex items-center justify-between";
                    card.innerHTML = `
                        <div class="min-w-0 pr-4"><div class="font-bold text-slate-700 truncate text-sm">${s.nama_siswa}</div></div>
                        <div class="flex gap-1">
                            ${['H','S','I','A'].map(st => `
                                <label class="w-8 h-8 flex items-center justify-center cursor-pointer">
                                    <input type="radio" name="absen[${s.id}]" value="${st}" ${st=='H'?'checked':''} class="peer hidden">
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
                attSection.classList.remove('hidden'); updateCounts();
                document.querySelectorAll('input[type="radio"]').forEach(r => r.addEventListener('change', updateCounts));
            })
            .catch(err => {
                console.error('Error fetching students:', err);
                Swal.fire('Error', 'Gagal memuat daftar siswa. Pastikan tahun pelajaran aktif sudah diatur.', 'error');
            });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
