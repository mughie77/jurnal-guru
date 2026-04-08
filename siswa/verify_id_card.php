<?php
// Mock file for verification
$siswa = [
    'nama_siswa' => 'ABDUL RAZZAQ AL-MUHAIMIN BIN SALMAN AL-FARISI',
    'nis' => '222310456',
    'nisn' => '0061234567',
    'tempat_lahir' => 'BONDOWOSO',
    'tanggal_lahir' => '2006-12-31',
    'jenis_kelamin' => 'L',
    'alamat' => 'PERUMAHAN PONDOK INDAH PERMAI BLOK A NO. 15, JL. RAYA JEMBER KM 5, KABUPATEN BONDOWOSO, PROVINSI JAWA TIMUR, KODE POS 68211',
    'foto' => ''
];
$sets = ['jam_masuk_sekolah' => '07:00'];
$BASE_URL = '../';
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../assets/css/id-card.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;0,800;1,200;1,300;1,400;1,500;1,600;1,700;1,800&display=swap" rel="stylesheet">
    <style>
        body { background: #f8fafc; display: flex; flex-direction: column; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
    </style>
</head>
<body>
    <h2 style="font-family: sans-serif; color: #1e293b;">Verifikasi ID Card - Nama & Alamat Panjang</h2>
    <div id="printableCard" class="card-responsive-container">
        <div class="card-id-wrapper">
            <div class="card-id" style="background-image: url('../assets/img/karpel.jpg')">
                <div class="card-content">
                    <div class="photo-area-new">
                        <div class="w-full h-full flex items-center justify-center text-slate-400 bg-slate-100">
                            <i class="fa fa-user text-5xl"></i>
                        </div>
                    </div>
                    <div class="barcode-area-new">
                        <div style="width: 100%; height: 40px; background: #eee; display: flex; align-items: center; justify-content: center; font-size: 8px; font-weight: bold;">BARCODE MOCK</div>
                    </div>
                    <div class="info-area-new">
                        <div class="info-value val-nama"><?= htmlspecialchars($siswa['nama_siswa']) ?></div>
                        <div class="info-value val-nis"><?= htmlspecialchars($siswa['nis']) ?> / <?= htmlspecialchars($siswa['nisn']) ?></div>
                        <div class="info-value val-ttl"><?= htmlspecialchars($siswa['tempat_lahir']) ?>, 31-12-2006</div>
                        <div class="info-value val-jk">Laki-Laki</div>
                        <div class="info-value val-alamat"><?= htmlspecialchars($siswa['alamat']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
