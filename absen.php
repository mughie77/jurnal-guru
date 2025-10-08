<?php
require_once 'config/database.php';
require_once 'includes/public_header.php';
?>

<div class="container-fluid">
    <div class="text-center">
        <div class="card shadow-lg" style="max-width: 500px;">
            <div class="card-body p-5">
                <h1 class="h4 text-gray-900 mb-2">Pindai Barcode / Ketik NIS</h1>
                <p id="reader-status" class="text-muted mb-3">Posisikan barcode di depan kamera...</p>

                <div id="reader" style="width:100%;" class="mb-3"></div>

                <form id="absensi-form" class="mt-3">
                    <div class="mb-3">
                        <label for="nis-input" class="form-label visually-hidden">NIS (Hasil Pindai)</label>
                        <input type="text" class="form-control form-control-lg text-center" id="nis-input" placeholder="NIS akan muncul di sini" autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-check"></i> Submit Manual
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
    const readerStatus = document.getElementById('reader-status');
    let typingTimer;
    const doneTypingInterval = 1000; // 1 detik

    const submitAttendance = function(event) {
        if (event) event.preventDefault();
        clearTimeout(typingTimer);

        const nis = nisInput.value.trim();
        if (nis === '') {
            Swal.fire({ icon: 'error', title: 'Gagal', text: 'NIS tidak boleh kosong!' });
            return;
        }

        nisInput.disabled = true;

        fetch('api/absensi.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nis: nis }),
        })
        .then(response => response.json())
        .then(data => {
            const messageConfig = {
                timer: 2500,
                showConfirmButton: false
            };
            if (data.success) {
                Swal.fire({ ...messageConfig, icon: 'success', title: 'Berhasil!', html: `Siswa <strong>${data.nama_siswa}</strong> (${data.nama_kelas}) berhasil diabsen.` });
            } else {
                Swal.fire({ ...messageConfig, icon: 'error', title: 'Gagal!', text: data.message });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({ icon: 'error', title: 'Oops...', text: 'Terjadi kesalahan!' });
        })
        .finally(() => {
            nisInput.value = '';
            nisInput.disabled = false;
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

    const html5QrCode = new Html5Qrcode("reader");

    const qrCodeSuccessCallback = (decodedText, decodedResult) => {
        nisInput.value = decodedText;
        html5QrCode.stop().then(ignore => {
            submitAttendance(null);
            setTimeout(() => startScanner(), 2500); // Restart scanner after a delay
        }).catch(err => console.error("Gagal menghentikan scanner.", err));
    };

    const startScanner = () => {
        readerStatus.innerText = "Mencari kamera...";
        const formatsToSupport = [
            Html5QrcodeSupportedFormats.QR_CODE,
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.EAN_13,
        ];
        const config = {
            fps: 10,
            qrbox: { width: 280, height: 120 },
            supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA],
            formatsToSupport: formatsToSupport
        };

        Html5Qrcode.getCameras().then(cameras => {
            if (cameras && cameras.length) {
                readerStatus.innerText = "Kamera aktif, arahkan barcode ke area pindai.";
                const cameraId = cameras.length > 1 ? cameras[1].id : cameras[0].id;

                html5QrCode.start({ deviceId: { exact: cameraId } }, config, qrCodeSuccessCallback)
                .catch(err => {
                    html5QrCode.start({ deviceId: { exact: cameras[0].id } }, config, qrCodeSuccessCallback)
                    .catch(err => {
                        Swal.fire('Error', 'Tidak dapat memulai kamera.', 'error');
                        readerStatus.innerText = "Error: Gagal memulai kamera.";
                    });
                });
            } else {
                 Swal.fire('Error', 'Tidak ada kamera yang ditemukan.', 'error');
                 readerStatus.innerText = "Error: Tidak ada kamera ditemukan.";
            }
        }).catch(err => {
            Swal.fire('Error', 'Izin kamera ditolak atau tidak dapat diakses.', 'error');
            readerStatus.innerText = "Error: Izin kamera ditolak.";
        });
    }

    startScanner();
    nisInput.focus();
});
</script>

<?php require_once 'includes/footer.php'; ?>