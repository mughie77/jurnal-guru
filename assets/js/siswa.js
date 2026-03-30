function syncDapodik(csrfToken) {
    Swal.fire({
        title: 'Sinkronisasi Dapodik',
        text: "Sistem akan mengambil data siswa terbaru dari Web Service Dapodik Lokal. Lanjutkan?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Sinkronkan!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Sedang Sinkronisasi...',
                html: 'Mohon tunggu, proses ini mungkin memakan waktu beberapa menit.',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch(`sinkron_dapodik_siswa.php?csrf_token=${csrfToken}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Berhasil', data.message, 'success').then(() => { location.reload(); });
                    } else {
                        Swal.fire('Gagal', data.message, 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'Terjadi kesalahan sistem atau koneksi terputus.', 'error');
                });
        }
    });
}
