<?php
require_once 'config/database.php';
require_once 'includes/public_header.php';
?>

<div class="container-fluid">
    <div class="text-center">
        <div class="card shadow-lg" style="max-width: 500px;">
            <div class="card-body p-5">
                <h1 class="h4 text-gray-900 mb-4">Scan Barcode / Masukkan NISN</h1>
                <form id="absensi-form">
                    <div class="mb-3">
                        <input type="text" class="form-control form-control-lg text-center" id="nisn-input" placeholder="Arahkan kamera ke barcode atau ketik NISN" autofocus>
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
    const nisnInput = document.getElementById('nisn-input');

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const nisn = nisnInput.value.trim();

        if (nisn === '') {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: 'NISN tidak boleh kosong!',
            });
            return;
        }

        // Kirim data ke API
        fetch('api/absensi.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ nisn: nisn }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    html: `Siswa dengan nama <strong>${data.nama_siswa}</strong> berhasil diabsen.`,
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
            nisnInput.value = ''; // Kosongkan input setelah submit
            nisnInput.focus(); // Fokus kembali ke input
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Terjadi kesalahan saat menghubungi server!',
            });
            nisnInput.value = '';
            nisnInput.focus();
        });
    });

    // Auto-focus ke input field saat halaman dimuat
    nisnInput.focus();
});
</script>

<?php require_once 'includes/footer.php'; ?>