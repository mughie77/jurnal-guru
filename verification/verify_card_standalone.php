<?php
define('BASE_URL', '../');
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../assets/css/id-card.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { background: #f1f5f9; padding: 50px; display: flex; justify-content: center; }
    </style>
</head>
<body>
    <div class="card-id-wrapper">
        <div class="card-id" id="capture">
            <div class="card-content">
                <div class="photo-area-new">
                    <div style="width:100%; height:100%; background:#ccc; display:flex; align-items:center; justify-content:center; flex-direction:column; color:#666; font-size:12px; font-weight:bold;">
                        PHOTO AREA
                        <span>(168x210px)</span>
                    </div>
                </div>

                <div class="barcode-area">
                    <div style="width:100%; height:100%; background:white; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                         <div style="width:90%; height:45px; background:repeating-linear-gradient(90deg, #000, #000 2px, #fff 2px, #fff 4px);"></div>
                         <div style="font-size:10px; font-weight:bold; margin-top:0px;">20260001</div>
                    </div>
                </div>

                <div class="info-area-new">
                    <div class="info-group">
                        <span class="info-label">NAMA</span>
                        <span class="info-value">JULES AI ENGINEER</span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">NIS | NISN</span>
                        <span class="info-value">20260001 | 0012345678</span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">TEMPAT. TANGGAL LAHIR</span>
                        <span class="info-value">SILICON VALLEY, 01-01-2000</span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">JENIS KELAMIN</span>
                        <span class="info-value">LAKI-LAKI</span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">ALAMAT</span>
                        <span class="info-value">CLOUD INFRASTRUCTURE UNIT 7, SERVER ROOM B1</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
