function syncDapodik(csrfToken) {
    Swal.fire({
        title: 'Sinkronisasi GTK Dapodik',
        text: "Sistem akan mengambil data Guru/Tendik terbaru dari Web Service Dapodik Lokal. Lanjutkan?",
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

            fetch(`sinkron_dapodik_guru.php?csrf_token=${csrfToken}`)
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

function openEditModal(data) {
    if (typeof data === 'string') {
        try { data = JSON.parse(data); } catch(e) { console.error("Invalid JSON", e); return; }
    }

    // Basic fields
    document.getElementById('edit_id').value = data.id || '';
    document.getElementById('edit_user_id').value = data.user_id || '';
    document.getElementById('edit_nama').value = data.nama_lengkap || '';
    document.getElementById('edit_nip').value = data.nip || '';
    document.getElementById('edit_alamat').value = data.alamat || '';
    document.getElementById('edit_telp').value = data.no_telp || '';
    document.getElementById('edit_tempat_lahir').value = data.tempat_lahir || '';
    document.getElementById('edit_tanggal_lahir').value = data.tanggal_lahir || '';

    // Set mapel selects
    const select = document.querySelector('#editModal select[name="mapel_ids[]"]');
    if (select) {
        // Clear all selections first
        Array.from(select.options).forEach(opt => opt.selected = false);

        if (data.mapel_diampu) {
            const mapels = data.mapel_diampu.split(', ');
            Array.from(select.options).forEach(opt => {
                // Trim to match precisely
                if (mapels.includes(opt.text.trim())) {
                    opt.selected = true;
                }
            });
        }
    }

    openModal('editModal');
}

function openDeleteModal(id, uid, nama) {
    document.getElementById('hapus_user_id').value = uid; document.getElementById('hapus_nama').textContent = nama;
    openModal('hapusModal');
}
