// Custom JavaScript for the application will go here.
// This file is intentionally left empty for now.
// It's linked in footer.php to be used for global scripts.

document.addEventListener('DOMContentLoaded', function() {
    // Automatic subject code generation (only on mapel page)
    const namaMapelInput = document.getElementById('nama_mapel_tambah');
    const kodeMapelInput = document.getElementById('kode_mapel_tambah');

    if (namaMapelInput && kodeMapelInput) {
        let kodeMapelManuallyEdited = false;

        // Mark as manually edited
        kodeMapelInput.addEventListener('input', function() {
            kodeMapelManuallyEdited = true;
        });

        // Auto-generate code
        namaMapelInput.addEventListener('input', function() {
            if (!kodeMapelManuallyEdited) {
                let namaMapel = this.value;
                let kode = '';

                // Get first 3 letters
                kode = namaMapel.substring(0, 3).toUpperCase();

                // Add 3 random numbers for uniqueness
                const randomNum = Math.floor(100 + Math.random() * 900);
                kode += `-${randomNum}`;

                kodeMapelInput.value = kode;
            }
        });

        // Reset flag when modal is closed
        const tambahModal = document.getElementById('tambahModal');
        if (tambahModal) {
            tambahModal.addEventListener('hidden.bs.modal', function () {
                kodeMapelManuallyEdited = false;
                namaMapelInput.value = '';
                kodeMapelInput.value = '';
            });
        }
    }
});