<?php
define('BASE_URL', 'http://localhost:8000/');

$siswa = [
    'nama_siswa' => 'JULES AI ENGINEER',
    'nis' => '20260001',
    'nisn' => '0012345678',
    'tempat_lahir' => 'SILICON VALLEY',
    'tanggal_lahir' => '2000-01-01',
    'jenis_kelamin' => 'L',
    'alamat' => 'CLOUD INFRASTRUCTURE UNIT 7, SERVER ROOM B1',
    'foto' => ''
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verify Card Refined V3</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/id-card.css">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        /* Override for standalone preview to match background labels */
        .info-area-new {
            left: 350px;
            top: 104px;
        }
        .info-group {
            margin-bottom: 23px;
        }
        .info-value {
            line-height: 1.1;
        }
    </style>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen">
    <div class="card-id">
        <div class="card-content">
            <div class="photo-area-new">
                <div class="w-full h-full flex items-center justify-center text-slate-400 bg-slate-200">
                    <span class="text-xs font-bold text-center">PHOTO AREA<br>(184x230px)</span>
                </div>
            </div>

            <div class="barcode-area">
                <svg id="barcode"></svg>
            </div>

            <div class="info-area-new">
                <div class="info-group">
                    <span class="info-value"><?= htmlspecialchars($siswa['nama_siswa']) ?></span>
                </div>

                <div class="info-group">
                    <span class="info-value"><?= htmlspecialchars($siswa['nis']) ?> | <?= htmlspecialchars($siswa['nisn']) ?></span>
                </div>

                <div class="info-group">
                    <span class="info-value">
                        <?= htmlspecialchars($siswa['tempat_lahir']) ?>,
                        <?= date('d-m-Y', strtotime($siswa['tanggal_lahir'])) ?>
                    </span>
                </div>

                <div class="info-group">
                    <span class="info-value"><?= ($siswa['jenis_kelamin'] == 'P') ? 'PEREMPUAN' : 'LAKI-LAKI' ?></span>
                </div>

                <div class="info-group">
                    <span class="info-value"><?= htmlspecialchars($siswa['alamat']) ?></span>
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
