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
