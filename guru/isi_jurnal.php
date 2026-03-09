<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
if(mysqli_num_rows($guru_res) == 0) die("Error: Data guru tidak ditemukan.");
$guru_id = mysqli_fetch_assoc($guru_res)['id'];

if (!$active_tahun_id) die("Error: Tidak ada tahun pelajaran aktif.");

$mapels = mysqli_query($conn, "SELECT mp.id, mp.nama_mapel FROM mata_pelajaran mp JOIN guru_mapel gm ON mp.id = gm.mapel_id WHERE gm.guru_id = $guru_id ORDER BY mp.nama_mapel");
$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");

$message = ''; $message_type = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_jurnal'])) {
    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare($conn, "INSERT INTO jurnal (guru_id, mapel_id, kelas_id, tahun_pelajaran_id, tanggal, jam_ke, materi, jml_hadir, jml_sakit, jml_izin, jml_alfa, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iiiisssiiiis", $guru_id, $_POST['mapel_id'], $_POST['kelas_id'], $active_tahun_id, $_POST['tanggal'], $_POST['jam_ke'], $_POST['materi'], $_POST['jml_hadir'], $_POST['jml_sakit'], $_POST['jml_izin'], $_POST['jml_alfa'], $_POST['keterangan']);
        mysqli_stmt_execute($stmt);
        $jurnal_id = mysqli_insert_id($conn);

        if (!empty($_POST['absen'])) {
            $stmt_absen = mysqli_prepare($conn, "INSERT INTO absensi_jurnal (jurnal_id, siswa_id, status) VALUES (?, ?, ?)");
            foreach ($_POST['absen'] as $siswa_id => $status) {
                mysqli_stmt_bind_param($stmt_absen, "iis", $jurnal_id, $siswa_id, $status);
                mysqli_stmt_execute($stmt_absen);
            }
        }

        mysqli_commit($conn);
        $message = "Jurnal dan Absensi berhasil disimpan!"; $message_type = 'success';
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "Error: " . $e->getMessage(); $message_type = 'error';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>#sidebar, header { display: none; } .lg\:ml-64 { margin-left: 0; }</style>

<div class="max-w-5xl mx-auto pb-20 px-4">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Isi Jurnal & Absensi</h1>
            <p class="text-slate-500 font-medium tracking-wide uppercase text-xs"><?= date('l, d F Y') ?></p>
        </div>
        <a href="index.php" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
            <i class="fa fa-arrow-left"></i>
        </a>
    </div>

    <?php if ($message): ?>
    <script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= $message ?>' });</script>
    <?php endif; ?>

    <form action="" method="POST" class="space-y-8">
        <!-- Main Info Card -->
        <div class="lux-card p-8 bg-white shadow-2xl">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-6">
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Mata Pelajaran</label>
                        <select name="mapel_id" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold text-slate-700 transition-all">
                            <option value="">-- Pilih Mapel --</option>
                            <?php while($m = mysqli_fetch_assoc($mapels)): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Kelas</label>
                        <select name="kelas_id" id="kelas_id" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold text-slate-700 transition-all">
                            <option value="">-- Pilih Kelas --</option>
                            <?php while($k = mysqli_fetch_assoc($kelases)): ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Tanggal</label>
                            <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-slate-700">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Jam Ke-</label>
                            <input type="text" name="jam_ke" placeholder="1-3" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-slate-700">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Materi Pembahasan</label>
                        <textarea name="materi" rows="2" placeholder="Tuliskan pokok bahasan..." required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-medium text-slate-600"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Table Card -->
        <div id="attendance_card" class="lux-card p-8 bg-white shadow-2xl hidden animate-in slide-in-from-bottom-5 duration-500">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-xl font-black text-slate-800 italic">Daftar Absensi Siswa</h3>
                <div class="flex gap-4">
                    <div class="flex items-center text-xs font-bold text-slate-400 gap-4 bg-slate-50 px-4 py-2 rounded-xl">
                        <span>H: Hadir</span><span>S: Sakit</span><span>I: Izin</span><span>A: Alfa</span>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-separate border-spacing-y-2">
                    <thead>
                        <tr class="text-slate-400 text-[10px] font-black uppercase tracking-[0.2em]">
                            <th class="px-6 py-2">Nama Siswa</th>
                            <th class="px-6 py-2 text-center">Status Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody id="siswa_list">
                        <!-- Loaded via JS -->
                    </tbody>
                </table>
            </div>

            <!-- Summary Bar -->
            <div class="mt-8 grid grid-cols-2 md:grid-cols-4 gap-4 pt-8 border-t border-slate-100">
                <div class="bg-emerald-50 p-4 rounded-2xl border border-emerald-100 text-center">
                    <p class="text-[10px] font-black text-emerald-600 uppercase mb-1">Hadir</p>
                    <input type="number" name="jml_hadir" id="jml_hadir" value="0" readonly class="bg-transparent border-none w-full text-center font-black text-2xl text-emerald-700 outline-none">
                </div>
                <div class="bg-amber-50 p-4 rounded-2xl border border-amber-100 text-center">
                    <p class="text-[10px] font-black text-amber-600 uppercase mb-1">Sakit</p>
                    <input type="number" name="jml_sakit" id="jml_sakit" value="0" readonly class="bg-transparent border-none w-full text-center font-black text-2xl text-amber-700 outline-none">
                </div>
                <div class="bg-blue-50 p-4 rounded-2xl border border-blue-100 text-center">
                    <p class="text-[10px] font-black text-blue-600 uppercase mb-1">Izin</p>
                    <input type="number" name="jml_izin" id="jml_izin" value="0" readonly class="bg-transparent border-none w-full text-center font-black text-2xl text-blue-700 outline-none">
                </div>
                <div class="bg-rose-50 p-4 rounded-2xl border border-rose-100 text-center">
                    <p class="text-[10px] font-black text-rose-600 uppercase mb-1">Alfa</p>
                    <input type="number" name="jml_alfa" id="jml_alfa" value="0" readonly class="bg-transparent border-none w-full text-center font-black text-2xl text-rose-700 outline-none">
                </div>
            </div>
        </div>

        <div class="lux-card p-6 bg-slate-50/50 flex flex-col md:flex-row gap-6 items-center">
            <div class="flex-1 w-full">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Keterangan Tambahan (Opsional)</label>
                <input type="text" name="keterangan" placeholder="Contoh: 2 siswa terlambat..." class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-slate-100 bg-white">
            </div>
            <button type="submit" name="simpan_jurnal" class="w-full md:w-auto px-12 py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-2xl shadow-xl shadow-indigo-100 transition-all transform hover:-translate-y-1 active:scale-95 flex items-center justify-center gap-3">
                <i class="fa fa-save text-lg"></i> SIMPAN JURNAL & ABSENSI
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const kSelect = document.getElementById('kelas_id');
    const attCard = document.getElementById('attendance_card');
    const sList = document.getElementById('siswa_list');

    const hInp = document.getElementById('jml_hadir');
    const sInp = document.getElementById('jml_sakit');
    const iInp = document.getElementById('jml_izin');
    const aInp = document.getElementById('jml_alfa');

    const updateCounts = () => {
        const h = document.querySelectorAll('input[type="radio"]:checked[value="H"]').length;
        const s = document.querySelectorAll('input[type="radio"]:checked[value="S"]').length;
        const i = document.querySelectorAll('input[type="radio"]:checked[value="I"]').length;
        const a = document.querySelectorAll('input[type="radio"]:checked[value="A"]').length;
        hInp.value = h; sInp.value = s; iInp.value = i; aInp.value = a;
    };

    kSelect.addEventListener('change', function() {
        if (!this.value) { attCard.classList.add('hidden'); return; }

        fetch(`<?= BASE_URL ?>api/get_siswa_kelas.php?kelas_id=${this.value}`)
            .then(r => r.json())
            .then(data => {
                sList.innerHTML = '';
                data.forEach(s => {
                    const tr = document.createElement('tr');
                    tr.className = "bg-white border border-slate-100 rounded-2xl transition-all hover:bg-slate-50/50 group";
                    tr.innerHTML = `
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-700">${s.nama_siswa}</div>
                            <div class="text-[10px] text-slate-400 font-mono">${s.nis} | ${s.jenis_kelamin}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-center gap-2">
                                ${['H','S','I','A'].map(status => `
                                    <label class="relative flex items-center justify-center w-10 h-10 cursor-pointer group/item">
                                        <input type="radio" name="absen[${s.id}]" value="${status}" ${status=='H'?'checked':''} class="peer hidden">
                                        <div class="w-full h-full rounded-xl flex items-center justify-center font-black text-sm transition-all border-2
                                            ${status=='H'?'border-emerald-100 text-emerald-500 peer-checked:bg-emerald-500 peer-checked:text-white peer-checked:border-emerald-500 peer-checked:shadow-lg peer-checked:shadow-emerald-100':''}
                                            ${status=='S'?'border-amber-100 text-amber-500 peer-checked:bg-amber-500 peer-checked:text-white peer-checked:border-amber-500 peer-checked:shadow-lg peer-checked:shadow-amber-100':''}
                                            ${status=='I'?'border-blue-100 text-blue-500 peer-checked:bg-blue-500 peer-checked:text-white peer-checked:border-blue-500 peer-checked:shadow-lg peer-checked:shadow-blue-100':''}
                                            ${status=='A'?'border-rose-100 text-rose-500 peer-checked:bg-rose-500 peer-checked:text-white peer-checked:border-rose-500 peer-checked:shadow-lg peer-checked:shadow-rose-100':''}
                                        ">${status}</div>
                                    </label>
                                `).join('')}
                            </div>
                        </td>
                    `;
                    sList.appendChild(tr);
                });
                attCard.classList.remove('hidden');
                updateCounts();

                // Add event listeners to new radios
                document.querySelectorAll('input[type="radio"]').forEach(r => {
                    r.addEventListener('change', updateCounts);
                });
            });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
