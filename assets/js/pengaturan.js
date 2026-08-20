function checkDapodikConnection(csrfToken) {
    const url = document.getElementById('dapodik_url').value;
    const token = document.getElementById('dapodik_token').value;
    const statusDiv = document.getElementById('conn_status');

    if(!url || !token) {
        Swal.fire('Peringatan', 'URL dan Token harus diisi!', 'warning');
        return;
    }

    statusDiv.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i> Menghubungkan ke Dapodik...';
    statusDiv.className = "flex-1 flex items-center px-4 rounded-xl border border-dashed border-amber-200 text-sm font-bold text-amber-500 bg-amber-50";

    fetch('cek_koneksi_dapodik.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `url=${encodeURIComponent(url)}&token=${encodeURIComponent(token)}&csrf_token=${csrfToken}`
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error("Malformed JSON:", text);
            throw new Error("Server mengembalikan format non-JSON. Periksa logs.");
        }
    })
    .then(data => {
        if(data.success) {
            statusDiv.innerHTML = '<i class="fa fa-check-circle mr-2"></i> Koneksi Berhasil: ' + data.message;
            statusDiv.className = "flex-1 flex items-center px-4 rounded-xl border border-dashed border-emerald-200 text-sm font-bold text-emerald-600 bg-emerald-50";
            Swal.fire('Berhasil', 'Koneksi ke Dapodik berhasil terjalin!', 'success');
        } else {
            statusDiv.innerHTML = '<i class="fa fa-times-circle mr-2"></i> Gagal: ' + data.message;
            statusDiv.className = "flex-1 flex items-center px-4 rounded-xl border border-dashed border-rose-200 text-sm font-bold text-rose-600 bg-rose-50";
            Swal.fire('Gagal', data.message, 'error');
        }
    })
    .catch(err => {
        statusDiv.innerHTML = '<i class="fa fa-exclamation-triangle mr-2"></i> Terjadi kesalahan jaringan.';
        statusDiv.className = "flex-1 flex items-center px-4 rounded-xl border border-dashed border-rose-200 text-sm font-bold text-rose-600 bg-rose-50";
        Swal.fire('Error', err.message || 'Gagal melakukan request ke server.', 'error');
    });
}

function runGitAction(action, csrfToken, additionalData = {}) {
    const logContainer = document.getElementById('git_log_container');
    const logBox = document.getElementById('git_log_box');

    logContainer.classList.remove('hidden');
    logBox.textContent = "Menjalankan perintah Git... Silakan tunggu...\n";

    Swal.fire({
        title: 'Memproses Perintah Git...',
        text: 'Sedang berkomunikasi dengan repositori GitHub.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    let bodyParams = `action=${encodeURIComponent(action)}&csrf_token=${csrfToken}`;
    for (const key in additionalData) {
        bodyParams += `&${encodeURIComponent(key)}=${encodeURIComponent(additionalData[key])}`;
    }

    fetch('git_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: bodyParams
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error("Malformed Git JSON Response:", text);
            // Put raw html or text in logs for clear debugging
            logBox.textContent = text;
            throw new Error("Server mengembalikan format non-JSON. Periksa log box di bawah.");
        }
    })
    .then(data => {
        Swal.close();
        logBox.textContent = data.log || "Tidak ada output terminal.";
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: data.message,
                confirmButtonColor: '#4F46E5'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: data.message,
                confirmButtonColor: '#EF4444'
            });
        }
    })
    .catch(err => {
        Swal.close();
        if (!logBox.textContent || logBox.textContent.includes("Menjalankan perintah Git")) {
            logBox.textContent = "Terjadi kesalahan jaringan atau server error:\n" + err.message;
        }
        Swal.fire({
            icon: 'error',
            title: 'Gagal Memproses',
            text: err.message || 'Gagal menjalankan perintah Git.',
            confirmButtonColor: '#EF4444'
        });
    });
}

function confirmGitPush(csrfToken) {
    Swal.fire({
        title: 'Kirim Pembaruan ke GitHub?',
        text: 'Tindakan ini akan melakukan commit dan push semua perubahan kode saat ini ke repositori remote.',
        icon: 'warning',
        input: 'text',
        inputLabel: 'Pesan Commit (Opsional)',
        inputPlaceholder: 'Masukkan pesan commit jika ada...',
        showCancelButton: true,
        confirmButtonColor: '#4F46E5',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Push!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            const commitMsg = result.value || '';
            runGitAction('push', csrfToken, { commit_message: commitMsg });
        }
    });
}

function runDatabaseUpdate() {
    Swal.fire({
        title: 'Update Database Schema?',
        text: 'Sistem akan memeriksa dan menyinkronkan tabel serta kolom database aplikasi.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4F46E5',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Jalankan Update',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Menyinkronkan Database...',
                text: 'Proses migrasi sedang berlangsung.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('update_db.php')
                .then(r => r.text())
                .then(html => {
                    const temp = document.createElement('div');
                    temp.innerHTML = html;
                    const logContent = temp.querySelector('.space-y-4')?.innerHTML || html;

                    Swal.fire({
                        title: 'Hasil Migrasi Database',
                        html: `<div class="text-left max-h-60 overflow-y-auto p-2 text-xs space-y-2">${logContent}</div>`,
                        icon: 'info',
                        confirmButtonColor: '#4F46E5',
                        confirmButtonText: 'Selesai',
                        customClass: { popup: 'rounded-3xl' }
                    });
                })
                .catch(err => {
                    Swal.fire('Gagal Migrasi', err.message || 'Terjadi kesalahan saat menjalankan update database.', 'error');
                });
        }
    });
}
