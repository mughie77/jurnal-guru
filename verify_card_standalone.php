<?php
define('BASE_URL', './');
$siswa = [
    'nama_siswa' => 'Jules AI Engineer',
    'nis' => '20260001',
    'nisn' => '0012345678',
    'tempat_lahir' => 'Silicon Valley',
    'tanggal_lahir' => '2000-01-01',
    'jenis_kelamin' => 'L',
    'alamat' => 'Cloud Infrastructure Unit 7',
    'foto' => ''
];
$sets = [
    'nama_sekolah' => 'SMKN 2 BONDOWOSO',
    'favicon' => ''
];
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="assets/css/id-card.css">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen">
    <div class="card-id-wrapper">
        <div class="card-id">
            <div class="card-content">
                <div class="photo-area-new">
                    <div class="w-full h-full flex items-center justify-center text-slate-400 border border-dashed border-slate-300">
                        <i class="fa fa-user text-5xl"></i>
                    </div>
                </div>

                <div class="barcode-area">
                    <svg id="barcode"></svg>
                </div>

                <div class="info-area-new">
                    <div class="info-group">
                        <span class="info-label">NAMA</span>
                        <span class="info-value"><?= htmlspecialchars($siswa['nama_siswa']) ?></span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">NIS | NISN</span>
                        <span class="info-value"><?= htmlspecialchars($siswa['nis']) ?> | <?= htmlspecialchars($siswa['nisn'] ?? '-') ?></span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">TEMPAT. TANGGAL LAHIR</span>
                        <span class="info-value">
                            <?= htmlspecialchars($siswa['tempat_lahir'] ?? '-') ?>,
                            <?= !empty($siswa['tanggal_lahir']) ? date('d-m-Y', strtotime($siswa['tanggal_lahir'])) : '-' ?>
                        </span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">JENIS KELAMIN</span>
                        <span class="info-value"><?= ($siswa['jenis_kelamin'] == 'P') ? 'PEREMPUAN' : 'LAKI-LAKI' ?></span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">ALAMAT</span>
                        <span class="info-value"><?= htmlspecialchars($siswa['alamat'] ?? '-') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        JsBarcode("#barcode", "<?= $siswa['nis'] ?>", {
            format: "CODE128",
            width: 1,
            height: 35,
            displayValue: true,
            fontSize: 10,
            margin: 0,
            background: "#ffffff"
        });
    </script>
</body>
</html>
