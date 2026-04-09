<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Check if already checked in today
$check_abs = mysqli_query($conn, "SELECT id FROM absensi_harian WHERE siswa_id = $siswa_id AND tanggal = '$today'");
$is_already_absen = mysqli_num_rows($check_abs) > 0;

$page_title = "Pengajuan Izin";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 lg:p-8 max-w-2xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-black italic text-slate-800 tracking-tight">Pengajuan Izin</h1>
                <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mt-1">Sakit / Kepentingan Penting</p>
            </div>
            <a href="index.php" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-slate-600 transition-all">
                <i class="fa fa-times"></i>
            </a>
        </div>

        <?php if ($is_already_absen): ?>
            <div class="lux-card p-12 text-center">
                <div class="w-20 h-20 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl">
                    <i class="fa fa-check-double"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-800 mb-2">Sudah Ada Data Absensi</h2>
                <p class="text-slate-500 italic">Anda sudah melakukan absensi atau pengajuan izin untuk hari ini.</p>
                <a href="index.php" class="mt-8 inline-block px-8 py-3 bg-slate-800 text-white font-bold rounded-xl hover:bg-slate-900 transition-all">Kembali ke Dashboard</a>
            </div>
        <?php else: ?>
            <div class="lux-card p-8">
                <form id="form-izin" enctype="multipart/form-data" class="space-y-6">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Jenis Izin</label>
                        <select name="status" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 transition-all font-bold text-slate-700">
                            <option value="Izin">Izin (Kepentingan Penting)</option>
                            <option value="Sakit">Sakit</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Keterangan / Alasan</label>
                        <textarea name="keterangan" rows="3" required placeholder="Tuliskan alasan izin Anda secara singkat..." class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 transition-all italic text-sm"></textarea>
                    </div>

                    <div class="p-6 rounded-2xl bg-slate-50 border border-slate-100 space-y-4">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">Upload Surat Keterangan / Bukti</label>
                        <input type="file" name="file_surat" accept="image/*,application/pdf" required class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 transition-all">
                        <p class="text-[9px] text-slate-400 font-bold italic uppercase tracking-wider">Format: JPG, PNG, atau PDF (Maks 2MB)</p>
                    </div>

                    <input type="hidden" name="lat" id="izin_lat">
                    <input type="hidden" name="lng" id="izin_lng">

                    <button type="submit" id="btn-submit-izin" class="w-full py-4 bg-indigo-600 text-white font-black rounded-2xl shadow-xl shadow-indigo-100 hover:bg-indigo-700 transition-all flex items-center justify-center gap-3">
                        <i class="fa fa-paper-plane text-xl"></i> AJUKAN IZIN SEKARANG
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    if ("geolocation" in navigator) {
        navigator.geolocation.getCurrentPosition(function(position) {
            // Anti Fake GPS Heuristic
            if (position.coords.accuracy > 100) {
                console.warn("GPS Accuracy low:", position.coords.accuracy);
            }
            if (position.mocked) {
                Swal.fire('Fake GPS Terdeteksi', 'Dilarang menggunakan aplikasi manipulasi lokasi!', 'error');
                return;
            }
            document.getElementById('izin_lat').value = position.coords.latitude;
            document.getElementById('izin_lng').value = position.coords.longitude;
        }, function(error) {
            console.error("Geolocation error:", error);
        }, { enableHighAccuracy: true });
    }

    const form = document.getElementById('form-izin');
    const btn = document.getElementById('btn-submit-izin');

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!document.getElementById('izin_lat').value) {
                Swal.fire('Lokasi Diperlukan', 'Harap aktifkan GPS Anda untuk mengajukan izin.', 'warning');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i> Mengirim...';

            const formData = new FormData(this);

            fetch('../api/submit_izin.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const now = new Date();
                    const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                    const dateStr = now.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });

                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        html: `<div class="space-y-2">
                            <p class="text-slate-600">${res.message}</p>
                            <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Waktu Pengajuan</p>
                                <p class="text-lg font-black text-indigo-600">${timeStr} WIB</p>
                                <p class="text-xs font-bold text-slate-500">${dateStr}</p>
                            </div>
                        </div>`,
                        timer: 3500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'index.php';
                    });
                } else {
                    Swal.fire('Gagal', res.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-paper-plane text-xl"></i> AJUKAN IZIN SEKARANG';
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Terjadi kesalahan sistem.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-paper-plane text-xl"></i> AJUKAN IZIN SEKARANG';
            });
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
