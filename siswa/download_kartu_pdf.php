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
        $this->AddPage('P', [85, 150]);
        $this->SetAutoPageBreak(false);

        // Corporate Blue Background
        $this->SetFillColor(0, 45, 91);
        $this->Rect(0, 0, 85, 150, 'F');

        // Top Orange Shape (Matching Web Template)
        $this->SetFillColor(245, 158, 11);
        $this->_out('q 1 0 0 1 0 0 cm');
        $this->_out('0.961 0.620 0.043 rg');
        $this->_out('-40 0 m 65 0 l -40 105 l f'); // Simulate the tilted top shape
        $this->_out('Q');

        // Bottom Orange Bar
        $this->SetFillColor(245, 158, 11);
        $this->Rect(0, 146, 85, 4, 'F');

        // Logo Section
        $this->SetXY(8, 8);
        $this->SetFillColor(255, 255, 255);
        $this->ClippingCircle(13, 13, 5, true); // Circle background for logo
        $logo_path = __DIR__ . '/../uploads/' . ($sets['favicon'] ?? '');
        if (!empty($sets['favicon']) && file_exists($logo_path)) {
            $this->Image($logo_path, 9, 9, 8, 8);
        }
        $this->_out('Q');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetXY(19, 11);
        $this->Cell(0, 5, strtoupper($sets['nama_sekolah'] ?? 'GOLDEN SUN'), 0, 0, 'L');

        // Photo (Circular Clipping)
        $foto_path = __DIR__ . '/../uploads/siswa/' . ($siswa['foto'] ?? '');
        $photo_x = 42.5;
        $photo_y = 45;
        $photo_r = 22;

        // Outer Circle (White border)
        $this->SetDrawColor(255, 255, 255);
        $this->SetLineWidth(2);
        $this->ClippingCircle($photo_x, $photo_y, $photo_r, true);

        if (!empty($siswa['foto']) && file_exists($foto_path)) {
            $this->Image($foto_path, $photo_x - $photo_r, $photo_y - $photo_r, $photo_r * 2, $photo_r * 2.5);
        } else {
            $this->SetFillColor(226, 232, 240);
            $this->Rect($photo_x - $photo_r, $photo_y - $photo_r, $photo_r * 2, $photo_r * 2, 'F');
        }
        $this->_out('Q'); // End Clipping

        // Name & Class
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(5, 72);
        $this->SetFont('Helvetica', 'B', 15);
        $this->MultiCell(75, 6, strtoupper($siswa['nama_siswa']), 0, 'C');

        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(245, 158, 11);
        $this->SetX(5);
        $this->Cell(75, 6, strtoupper($siswa['nama_kelas']), 0, 1, 'C');

        // Details Table
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 8);
        $start_y = 92;
        $labels = ['NIS', 'NISN', 'Alamat', 'Telp/HP'];
        $values = [$siswa['nis'], $siswa['nisn'] ?? '-', $siswa['alamat'] ?? '-', $siswa['no_telp'] ?? '-'];

        foreach ($labels as $i => $label) {
            $this->SetXY(15, $start_y);
            $this->SetFont('Helvetica', 'B', 7);
            $this->Cell(15, 5, strtoupper($label), 0, 0);
            $this->Cell(3, 5, ':', 0, 0);
            $this->SetFont('Helvetica', '', 8);
            if ($label == 'Alamat') {
                 $this->MultiCell(45, 4, $values[$i], 0, 'L');
                 $current_h = $this->GetY() - $start_y;
                 $start_y += max(5, $current_h);
            } else {
                 $this->Cell(45, 5, $values[$i], 0, 1);
                 $start_y += 6;
            }
        }

        // QR Code Section
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . $siswa['nis'];
        $this->SetFillColor(255, 255, 255);
        $this->Rect(33, 122, 19, 19, 'F');
        $this->Image($qr_url, 35, 124, 15, 15, 'png');
    }
}

$pdf = new IDCardPDF();
$pdf->IDCard($siswa, $sets);
$pdf->Output('D', 'Kartu_Pelajar_' . $siswa['nis'] . '.pdf');
