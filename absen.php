<?php
require_once 'config/database.php';
require_once 'includes/public_header.php';
?>

<div class="container-fluid">
    <div class="text-center">
        <div class="card shadow-lg" style="max-width: 500px;">
            <div class="card-body p-5">
                <h1 class="h4 text-gray-900 mb-4">Scan Barcode / Masukkan NIS</h1>
                <h6 class="text-muted mb-4">Sistem akan otomatis submit setelah 1 detik</h6>

                <div id="qr-reader" style="width:100%;" class="mb-3"></div>

                <form id="absensi-form">
                    <div class="mb-3">
                        <input type="text" class="form-control form-control-lg text-center" id="nis-input" placeholder="Arahkan kamera ke barcode atau ketik NIS" autofocus>
                    </div>
                    <button type="button" id="start-scan-btn" class="btn btn-info mb-2 w-100">
                        <i class="fa fa-camera"></i> Pindai dengan Kamera
                    </button>
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-check"></i> Submit Kehadiran
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('absensi-form');
    const nisInput = document.getElementById('nis-input');
    const startScanBtn = document.getElementById('start-scan-btn');
    const qrReaderElement = document.getElementById('qr-reader');
    let typingTimer;
    const doneTypingInterval = 1000; // 1 detik

    // Fungsi yang akan dipanggil untuk submit form
    const submitAttendance = function(event) {
        if (event) event.preventDefault();
        clearTimeout(typingTimer);

        const nis = nisInput.value.trim();
        if (nis === '') {
            Swal.fire({ icon: 'error', title: 'Gagal', text: 'NIS tidak boleh kosong!' });
            return;
        }

        fetch('api/absensi.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nis: nis }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    html: `Siswa <strong>${data.nama_siswa}</strong> (${data.nama_kelas}) berhasil diabsen.`,
                    timer: 3000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal!', text: data.message });
            }
            nisInput.value = '';
            nisInput.focus();
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({ icon: 'error', title: 'Oops...', text: 'Terjadi kesalahan!' });
            nisInput.value = '';
            nisInput.focus();
        });
    };

    form.addEventListener('submit', submitAttendance);

    nisInput.addEventListener('input', function () {
        clearTimeout(typingTimer);
        if (nisInput.value.trim() !== '') {
            typingTimer = setTimeout(() => submitAttendance(null), doneTypingInterval);
        }
    });

    // Logika untuk scanner kamera
    let html5QrCode = null;

    startScanBtn.addEventListener('click', function() {
        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("qr-reader");
        }

        const qrCodeSuccessCallback = (decodedText, decodedResult) => {
            nisInput.value = decodedText;
            html5QrCode.stop().then(ignore => {
                // Berhasil scan, langsung submit
                submitAttendance(null);
            }).catch(err => console.error("Failed to stop scanner.", err));
        };

        const config = { fps: 10, qrbox: { width: 250, height: 250 } };

        Html5Qrcode.getCameras().then(cameras => {
            if (cameras && cameras.length) {
                // Pilih kamera belakang jika ada (lebih baik untuk mobile)
                const cameraId = cameras.length > 1 ? cameras[1].id : cameras[0].id;
                html5QrCode.start(cameraId, config, qrCodeSuccessCallback)
                    .catch(err => {
                        // Jika gagal dengan kamera belakang, coba kamera depan
                        html5QrCode.start(cameras[0].id, config, qrCodeSuccessCallback)
                            .catch(err => Swal.fire('Error', 'Tidak dapat memulai kamera.', 'error'));
                    });
            } else {
                 Swal.fire('Error', 'Tidak ada kamera yang ditemukan.', 'error');
            }
        }).catch(err => {
            Swal.fire('Error', 'Tidak dapat mengakses kamera.', 'error');
        });
    });

    nisInput.focus();
});
</script>

<?php require_once 'includes/footer.php'; ?>