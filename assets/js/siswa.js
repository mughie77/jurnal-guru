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

document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all-siswa');
    const studentCheckboxes = document.querySelectorAll('.siswa-checkbox');
    const selectedCountSpan = document.getElementById('selected-count');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            studentCheckboxes.forEach(cb => {
                cb.checked = isChecked;
            });
            updateSelectedState();
        });
    }

    studentCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (!this.checked && selectAllCheckbox) {
                selectAllCheckbox.checked = false;
            } else if (selectAllCheckbox) {
                const allChecked = Array.from(studentCheckboxes).every(c => c.checked);
                selectAllCheckbox.checked = allChecked;
            }
            updateSelectedState();
        });
    });

    function updateSelectedState() {
        const checkedCount = Array.from(studentCheckboxes).filter(cb => cb.checked).length;
        if (selectedCountSpan) {
            selectedCountSpan.textContent = checkedCount;
        }

        if (bulkDeleteBtn) {
            if (checkedCount > 0) {
                bulkDeleteBtn.disabled = false;
                bulkDeleteBtn.className = "px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition-all bg-rose-600 hover:bg-rose-700 text-white flex items-center gap-2 cursor-pointer shadow-lg shadow-rose-200";
            } else {
                bulkDeleteBtn.disabled = true;
                bulkDeleteBtn.className = "px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition-all bg-slate-200 text-slate-400 flex items-center gap-2 cursor-not-allowed";
            }
        }
    }
});

function confirmBulkDelete(event) {
    event.preventDefault();
    const form = document.getElementById('bulk-delete-form');
    const checkedCheckboxes = document.querySelectorAll('.siswa-checkbox:checked');
    const checkedCount = checkedCheckboxes.length;

    if (checkedCount === 0) {
        Swal.fire('Informasi', 'Silakan pilih siswa yang ingin dihapus terlebih dahulu.', 'info');
        return false;
    }

    Swal.fire({
        title: 'Hapus Masal Siswa?',
        text: `Apakah Anda yakin ingin menghapus ${checkedCount} data siswa yang dipilih secara masal? Tindakan ini tidak dapat dibatalkan!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus Semua!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
}
