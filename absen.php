<?php
require_once 'config/database.php';
require_once 'includes/public_header.php';
?>

<div class="container-fluid">
    <div class="text-center">
        <div class="card shadow-lg" style="max-width: 500px;">
            <div class="card-body p-5">
                <h1 class="h4 text-gray-900 mb-4">Scan Barcode / Masukkan NIS</h1>
                <h6 class="text-muted mb-4">Sistem akan otomatis submit setelah 2 detik</h6>
                <form id="absensi-form">
                    <div class="mb-3">
                        <input type="text" class="form-control form-control-lg text-center" id="nis-input" placeholder="Arahkan kamera ke barcode atau ketik NIS" autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-check"></i> Submit Kehadiran
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('absensi-form');
    const nisInput = document.getElementById('nis-input');
    let typingTimer;
    const doneTypingInterval = 2000; // 2 detik

    // Fungsi yang akan dipanggil untuk submit form
    const submitAttendance = function(event) {
        if (event) event.preventDefault();

        // Batalkan timer jika ada, untuk mencegah double submit
        clearTimeout(typingTimer);

        const nis = nisInput.value.trim();

        if (nis === '') {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: 'NIS tidak boleh kosong!',
            });
            return;
        }

        // Kirim data ke API
        fetch('api/absensi.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
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
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: data.message,
                });
            }
            nisInput.value = ''; // Kosongkan input setelah submit
            nisInput.focus(); // Fokus kembali ke input
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Terjadi kesalahan saat menghubungi server!',
            });
            nisInput.value = '';
            nisInput.focus();
        });
    };

    // Event listener untuk submit manual (klik tombol)
    form.addEventListener('submit', submitAttendance);

    // Event listener untuk auto-submit setelah selesai mengetik
    nisInput.addEventListener('input', function () {
        clearTimeout(typingTimer);
        // Hanya set timer jika input tidak kosong, agar tidak submit saat field dihapus
        if (nisInput.value.trim() !== '') {
            typingTimer = setTimeout(() => submitAttendance(null), doneTypingInterval);
        }
    });

    // Auto-focus ke input field saat halaman dimuat
    nisInput.focus();
});
</script>

<?php require_once 'includes/footer.php'; ?>