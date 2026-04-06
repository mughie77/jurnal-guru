<?php
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/id-card.css">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        body { background: #f1f5f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
    </style>
</head>
<body>
    <div id="printableCard" class="card-id-wrapper">
        <div class="card-id">
            <div class="card-content">
                <div class="photo-area-new">
                    <div style="width:100%; height:100%; background:#ddd; display:flex; align-items:center; justify-content:center; color:#999; font-weight:bold; text-align:center; font-size: 10px;">
                        PHOTO AREA<br>(120x151px)
                    </div>
                </div>

                <div class="barcode-area">
                    <canvas id="barcode"></canvas>
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
    </div>
    <script>
        JsBarcode("#barcode", "<?= $siswa['nis'] ?>", {
            format: "CODE128",
            width: 1.5,
            height: 30,
            displayValue: true,
            fontSize: 8,
            fontOptions: "bold",
            margin: 0
        });
    </script>
</body>
</html>
