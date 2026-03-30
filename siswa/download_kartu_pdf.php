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
        $this->AddPage('P', [54, 86]); // CR-80 Standard Credit Card Size (54x86mm)
        $this->SetAutoPageBreak(false);

        // Corporate Blue Background
        $this->SetFillColor(0, 45, 91);
        $this->Rect(0, 0, 54, 86, 'F');

        // Top Orange Shape (Matching Web Template)
        $this->SetFillColor(245, 158, 11);
        $this->_out('q 1 0 0 1 0 0 cm');
        $this->_out('0.961 0.620 0.043 rg');
        $this->_out('-25 0 m 40 0 l -25 65 l f'); // Scaled down shape
        $this->_out('Q');

        // Bottom Orange Bar (Hidden according to template visual)
        // $this->SetFillColor(245, 158, 11);
        // $this->Rect(0, 84, 54, 2, 'F');

        // Logo Section
        $this->SetXY(5, 5);
        $this->SetFillColor(255, 255, 255);
        $this->ClippingCircle(8, 8, 3, true);
        $logo_path = __DIR__ . '/../uploads/' . ($sets['favicon'] ?? '');
        if (!empty($sets['favicon']) && file_exists($logo_path)) {
            $this->Image($logo_path, 6, 6, 4, 4);
        }
        $this->_out('Q');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 7);
        $this->SetXY(12, 6.5);
        $this->Cell(0, 3, strtoupper($sets['nama_sekolah'] ?? 'GOLDEN SUN'), 0, 0, 'L');

        // Photo (Circular Clipping)
        $foto_path = __DIR__ . '/../uploads/siswa/' . ($siswa['foto'] ?? '');
        $photo_x = 27;
        $photo_y = 25;
        $photo_r = 14;

        // Outer Circle (White border)
        $this->SetDrawColor(255, 255, 255);
        $this->SetLineWidth(1.5);
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
        $this->SetXY(2, 42);
        $this->SetFont('Helvetica', 'B', 11);
        $this->MultiCell(50, 4, strtoupper($siswa['nama_siswa']), 0, 'C');

        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor(245, 158, 11);
        $this->SetX(2);
        $this->Cell(50, 4, strtoupper($siswa['nama_kelas']), 0, 1, 'C');

        // Details Table
        $this->SetTextColor(255, 255, 255);
        $start_y = 52;
        $labels = ['NIS', 'NISN', 'Alamat', 'Telp'];
        $values = [$siswa['nis'], $siswa['nisn'] ?? '-', $siswa['alamat'] ?? '-', $siswa['no_telp'] ?? '-'];

        foreach ($labels as $i => $label) {
            $this->SetXY(6, $start_y);
            $this->SetFont('Helvetica', 'B', 6);
            $this->Cell(12, 3, strtoupper($label), 0, 0);
            $this->Cell(2, 3, ':', 0, 0);
            $this->SetFont('Helvetica', '', 6);
            if ($label == 'Alamat') {
                 $this->MultiCell(28, 3, $values[$i], 0, 'L');
                 $start_y = $this->GetY() + 0.5;
            } else {
                 $this->Cell(28, 3, $values[$i], 0, 1);
                 $start_y += 3.5;
            }
        }

        // QR Code Section
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . $siswa['nis'];
        $this->SetFillColor(255, 255, 255);
        $this->Rect(22, 70, 10, 10, 'F');
        $this->Image($qr_url, 22.5, 70.5, 9, 9, 'png');
    }
}

$pdf = new IDCardPDF();
$pdf->IDCard($siswa, $sets);
$pdf->Output('D', 'Kartu_Pelajar_' . $siswa['nis'] . '.pdf');
