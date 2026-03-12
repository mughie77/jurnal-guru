<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fpdf.php';

authorize_role(['siswa']);

$siswa_id = $_SESSION['user_id'];
$query = "SELECT s.*, k.nama_kelas, tp.tahun as tahun_pelajaran
          FROM siswa s
          JOIN siswa_kelas sk ON s.id = sk.siswa_id
          JOIN kelas k ON sk.kelas_id = k.id
          JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id
          WHERE s.id = ? AND tp.status = 'aktif'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $siswa_id);
mysqli_stmt_execute($stmt);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$res_set = mysqli_query($conn, "SELECT * FROM pengaturan");
$sets = [];
while ($r = mysqli_fetch_assoc($res_set)) $sets[$r['nama_setting']] = $r['nilai_setting'];

class IDCardPDF extends FPDF {
    function ClippingCircle($x, $y, $r, $outline=false) {
        $x *= $this->k;
        $y = ($this->h-$y)*$this->k;
        $r *= $this->k;
        $b = $r*0.552284749831;
        $this->_out(sprintf('q %.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c W %s',
            $x+$r,$y, $x+$r,$y+$b, $x+$b,$y+$r, $x,$y+$r,
            $x-$b,$y+$r, $x-$r,$y+$b, $x-$r,$y,
            $x-$r,$y-$b, $x-$b,$y-$r, $x,$y-$r,
            $x+$b,$y-$r, $x+$r,$y-$b, $x+$r,$y,
            $outline ? 'S' : 'n'));
    }

    function IDCard($siswa, $sets) {
        $this->AddPage('P', [85, 130]);
        $this->SetAutoPageBreak(false);

        // Header Background (Indigo)
        $this->SetFillColor(79, 70, 229);
        $this->Rect(0, 0, 85, 48, 'F');

        // Logo
        $logo_path = __DIR__ . '/../uploads/' . ($sets['favicon'] ?? '');
        if (!empty($sets['favicon']) && file_exists($logo_path)) {
            $this->Image($logo_path, 37.5, 6, 10, 10);
        }

        // Header Text
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 6);
        $this->SetXY(0, 18);
        $this->Cell(85, 4, 'KARTU PELAJAR DIGITAL', 0, 1, 'C');

        $this->SetFont('Helvetica', 'B', 10);
        $this->SetXY(10, 22);
        $this->MultiCell(65, 4, strtoupper($sets['nama_sekolah'] ?? 'SMK NEGERI CAKRA'), 0, 'C');

        // Photo (Circular Clipping)
        $foto_path = __DIR__ . '/../uploads/siswa/' . ($siswa['foto'] ?? '');
        $photo_x = 42.5; // Center X
        $photo_y = 52;   // Center Y
        $photo_r = 18;   // Radius

        // Outer Circle (White border)
        $this->SetDrawColor(255, 255, 255);
        $this->SetLineWidth(1.5);
        $this->ClippingCircle($photo_x, $photo_y, $photo_r, true);

        if (!empty($siswa['foto']) && file_exists($foto_path)) {
            // Adjust image to fit in circle (centered crop simulation)
            $this->Image($foto_path, $photo_x - $photo_r, $photo_y - $photo_r, $photo_r * 2, $photo_r * 2.5);
        } else {
            $this->SetFillColor(241, 245, 249);
            $this->Rect($photo_x - $photo_r, $photo_y - $photo_r, $photo_r * 2, $photo_r * 2, 'F');
            $this->SetTextColor(200, 200, 200);
            $this->SetFont('Helvetica', 'B', 8);
            $this->SetXY($photo_x - $photo_r, $photo_y - 2);
            $this->Cell($photo_r * 2, 5, 'NO PHOTO', 0, 0, 'C');
        }
        $this->_out('Q'); // End Clipping

        // Name Section
        $this->SetTextColor(30, 41, 59);
        $this->SetXY(5, 76);
        $this->SetFont('Helvetica', 'B', 14);
        $this->Cell(75, 7, strtoupper($siswa['nama_siswa']), 0, 1, 'C');
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(79, 70, 229);
        $this->Cell(75, 5, strtoupper($siswa['nama_kelas']), 0, 1, 'C');

        // Details
        $this->SetDrawColor(241, 245, 249);
        $this->Line(10, 90, 75, 90);

        $this->SetTextColor(100, 116, 139);
        $this->SetFont('Helvetica', 'B', 6);
        $this->SetXY(10, 92);
        $this->Cell(32, 4, 'NIS', 0, 0);
        $this->Cell(33, 4, 'NISN', 0, 1);

        $this->SetTextColor(30, 41, 59);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetX(10);
        $this->Cell(32, 5, $siswa['nis'], 0, 0);
        $this->Cell(33, 5, $siswa['nisn'] ?? '-', 0, 1);

        $this->SetTextColor(100, 116, 139);
        $this->SetFont('Helvetica', 'B', 6);
        $this->SetX(10);
        $this->Cell(0, 4, 'TAHUN PELAJARAN', 0, 1);
        $this->SetTextColor(30, 41, 59);
        $this->SetFont('Helvetica', 'BI', 9);
        $this->SetX(10);
        $this->Cell(0, 5, $siswa['tahun_pelajaran'], 0, 1);

        // QR Code & CAKRA Label
        $qr_url = "https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=" . $siswa['nis'] . "&choe=UTF-8";
        $this->Image($qr_url, 15, 105, 15, 15, 'PNG');

        $this->SetXY(32, 106);
        $this->SetTextColor(100, 116, 139);
        $this->SetFont('Helvetica', 'B', 5);
        $this->Cell(30, 3, 'VERIFIKASI', 0, 1);
        $this->SetX(32);
        $this->SetTextColor(79, 70, 229);
        $this->SetFont('Helvetica', 'BI', 7);
        $this->Cell(30, 4, 'CAKRA SYSTEM', 0, 1);

        // Bottom blue bar
        $this->SetFillColor(79, 70, 229);
        $this->Rect(25, 120, 35, 6, 'F');
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 6);
        $this->SetXY(25, 120);
        $this->Cell(35, 6, 'OFFICIAL ACADEMIC ID', 0, 0, 'C');
    }
}

$pdf = new IDCardPDF();
$pdf->IDCard($siswa, $sets);
$pdf->Output('D', 'Kartu_Pelajar_' . $siswa['nis'] . '.pdf');
