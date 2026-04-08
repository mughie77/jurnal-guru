<?php
// Mock student data for verification
$siswa = [
    'nama_siswa' => 'AHMAD REZA MUBAROK',
    'nis' => '222310123',
    'nisn' => '0061234567',
    'tempat_lahir' => 'BONDOWOSO',
    'tanggal_lahir' => '2006-05-15',
    'jenis_kelamin' => 'L',
    'alamat' => 'Jl. Mastrip No. 123, Kel. Sukowiryo, Kec. Bondowoso, Kab. Bondowoso',
    'foto' => '' // Empty for placeholder
];
$BASE_URL = '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Card Updated Final</title>
    <link rel="stylesheet" href="../assets/css/id-card.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;0,800;1,200;1,300;1,400;1,500;1,600;1,700;1,800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
</head>
<body class="bg-slate-100 flex items-center justify-center min-h-screen">
    <div class="card-responsive-container">
        <div class="card-id-wrapper">
            <div class="card-id">
                <div class="card-content">
                    <div class="photo-area-new">
                        <div class="w-full h-full flex items-center justify-center text-slate-400 bg-slate-200">
                            <span style="font-size: 40px;">👤</span>
                        </div>
                    </div>

                    <div class="barcode-area-new">
                        <canvas id="barcode"></canvas>
                    </div>

                    <div class="info-area-new">
                        <div class="info-value val-nama"><?= htmlspecialchars($siswa['nama_siswa']) ?></div>
                        <div class="info-value val-nis"><?= htmlspecialchars($siswa['nis']) ?> / <?= htmlspecialchars($siswa['nisn'] ?? '-') ?></div>
                        <div class="info-value val-ttl">
                            <?= htmlspecialchars($siswa['tempat_lahir'] ?? '-') ?>,
                            <?= !empty($siswa['tanggal_lahir']) ? date('d-m-Y', strtotime($siswa['tanggal_lahir'])) : '-' ?>
                        </div>
                        <div class="info-value val-jk"><?= ($siswa['jenis_kelamin'] == 'P') ? 'Perempuan' : 'Laki-Laki' ?></div>
                        <div class="info-value val-alamat"><?= htmlspecialchars($siswa['alamat'] ?? '-') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        JsBarcode("#barcode", "<?= $siswa['nis'] ?>", {
            format: "CODE128",
            width: 1.5,
            height: 35,
            displayValue: true,
            fontSize: 10,
            fontOptions: "bold",
            margin: 2,
            background: "#ffffff"
        });
    </script>
</body>
</html>
