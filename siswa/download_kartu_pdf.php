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
    // Variable for clipping
    var $extgstates = array();

    // Code 128 barcode function
    function Code128($x, $y, $code, $w, $h) {
        $v = array(' '=>0, '!'=>1, '"'=>2, '#'=>3, '$'=>4, '%'=>5, '&'=>6, "'"=>7, '('=>8, ')'=>9, '*'=>10, '+'=>11, ','=>12, '-'=>13, '.'=>14, '/'=>15, '0'=>16, '1'=>17, '2'=>18, '3'=>19, '4'=>20, '5'=>21, '6'=>22, '7'=>23, '8'=>24, '9'=>25, ':'=>26, ';'=>27, '<'=>28, '='=>29, '>'=>30, '?'=>31, '@'=>32, 'A'=>33, 'B'=>34, 'C'=>35, 'D'=>36, 'E'=>37, 'F'=>38, 'G'=>39, 'H'=>40, 'I'=>41, 'J'=>42, 'K'=>43, 'L'=>44, 'M'=>45, 'N'=>46, 'O'=>47, 'P'=>48, 'Q'=>49, 'R'=>50, 'S'=>51, 'T'=>52, 'U'=>53, 'V'=>54, 'W'=>55, 'X'=>56, 'Y'=>57, 'Z'=>58, '['=>59, '\\'=>60, ']'=>61, '^'=>62, '_'=>63, '`'=>64, 'a'=>65, 'b'=>66, 'c'=>67, 'd'=>68, 'e'=>69, 'f'=>70, 'g'=>71, 'h'=>72, 'i'=>73, 'j'=>74, 'k'=>75, 'l'=>76, 'm'=>77, 'n'=>78, 'o'=>79, 'p'=>80, 'q'=>81, 'r'=>82, 's'=>83, 't'=>84, 'u'=>85, 'v'=>86, 'w'=>87, 'x'=>88, 'y'=>89, 'z'=>90, '{'=>91, '|'=>92, '}'=>93, '~'=>94, 'DEL'=>95, 'FNC 3'=>96, 'FNC 2'=>97, 'SHIFT'=>98, 'CODE C'=>99, 'CODE B'=>100, 'FNC 4'=>101, 'FNC 1'=>102, 'Start A'=>103, 'Start B'=>104, 'Start C'=>105, 'STOP'=>106);
        $t = array(array(2,1,2,2,2,2), array(2,2,2,1,2,2), array(2,2,2,2,2,1), array(1,2,1,2,2,3), array(1,2,1,3,2,2), array(1,3,1,2,2,2), array(1,2,2,2,1,3), array(1,2,2,3,1,2), array(1,3,2,2,1,2), array(2,2,1,2,1,3), array(2,2,1,3,1,2), array(2,3,1,2,1,2), array(1,1,2,2,3,2), array(1,2,2,1,3,2), array(1,2,2,2,3,1), array(1,1,3,2,2,2), array(1,2,3,1,2,2), array(1,2,3,2,2,1), array(2,2,3,2,1,1), array(2,2,1,1,3,2), array(2,2,1,2,3,1), array(2,1,3,2,1,2), array(2,2,3,1,1,2), array(3,1,2,1,3,1), array(3,1,1,2,2,2), array(3,2,1,1,2,2), array(3,2,1,2,2,1), array(3,1,2,2,1,2), array(3,2,2,1,1,2), array(3,2,2,2,1,1), array(2,1,2,1,2,3), array(2,1,2,3,2,1), array(2,3,2,1,2,1), array(1,1,1,3,2,3), array(1,3,1,1,2,3), array(1,3,1,3,2,1), array(1,1,2,3,1,3), array(1,3,2,1,1,3), array(1,3,2,3,1,1), array(2,1,1,3,1,3), array(2,3,1,1,1,3), array(2,3,1,3,1,1), array(1,1,2,1,3,3), array(1,1,2,3,3,1), array(1,3,2,1,3,1), array(1,1,3,1,2,3), array(1,1,3,3,2,1), array(1,3,3,1,2,1), array(3,1,3,1,2,1), array(2,1,1,3,3,1), array(2,3,1,1,3,1), array(2,1,3,1,1,3), array(2,1,3,3,1,1), array(2,1,3,1,3,1), array(3,1,1,1,2,3), array(3,1,1,3,2,1), array(3,3,1,1,2,1), array(3,1,2,1,1,3), array(3,1,2,3,1,1), array(3,3,2,1,1,1), array(3,1,4,1,1,1), array(2,2,1,4,1,1), array(4,3,1,1,1,1), array(1,1,1,2,2,4), array(1,1,1,4,2,2), array(1,2,1,1,2,4), array(1,2,1,4,2,1), array(1,4,1,1,2,2), array(1,4,1,2,2,1), array(1,1,2,2,1,4), array(1,1,2,4,1,2), array(1,2,2,1,1,4), array(1,2,2,4,1,1), array(1,4,2,1,1,2), array(1,4,2,2,1,1), array(2,4,1,2,1,1), array(2,2,1,1,1,4), array(4,1,3,1,1,1), array(2,4,1,1,1,2), array(1,3,4,1,1,1), array(1,1,1,2,4,2), array(1,2,1,1,4,2), array(1,2,1,2,4,1), array(1,1,4,2,1,2), array(1,2,4,1,1,2), array(1,2,4,2,1,1), array(4,1,1,2,1,2), array(4,2,1,1,1,2), array(4,2,1,2,1,1), array(2,1,2,1,4,1), array(2,1,4,1,2,1), array(4,1,2,1,2,1), array(1,1,1,1,4,3), array(1,1,1,3,4,1), array(1,3,1,1,4,1), array(1,1,4,1,1,3), array(1,1,4,3,1,1), array(4,1,1,1,1,3), array(4,1,1,3,1,1), array(1,1,3,1,4,1), array(1,1,4,1,3,1), array(3,1,1,1,4,1), array(4,1,1,1,3,1), array(2,1,1,4,1,2), array(2,1,1,2,1,4), array(2,1,1,2,3,2), array(2,3,3,1,1,1,2));

        $code = (string)$code;
        $len = strlen($code);
        $char = array();
        $char[] = $t[$v['Start B']];
        $sum = $v['Start B'];
        for($i=0;$i<$len;$i++){
            $char[] = $t[$v[$code[$i]]];
            $sum += ($i+1)*$v[$code[$i]];
        }
        $check = $sum % 103;
        $char[] = $t[$check];
        $char[] = $t[$v['STOP']];

        $total_width = 0;
        foreach($char as $c) foreach($c as $bar) $total_width += $bar;
        $unit_w = $w / $total_width;

        $this->SetFillColor(0,0,0);
        $curr_x = $x;
        foreach($char as $c){
            foreach($c as $i=>$bar){
                if($i % 2 == 0) $this->Rect($curr_x, $y, $bar*$unit_w, $h, 'F');
                $curr_x += $bar*$unit_w;
            }
        }
    }

    // Clipping and rounded corners
    function ClippingRoundedRect($x, $y, $w, $h, $r, $outline=false) {
        $k = $this->k;
        $hp = $this->h;
        $op = ($outline ? 'S' : 'n');
        $MyArc = 4/3 * (sqrt(2) - 1);

        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-($y))*$k));
        $xc = $x+$w-$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k));

        $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
        $xc = $x+$w-$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
        $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);

        $xc = $x+$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
        $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);

        $xc = $x+$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k));
        $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out('W ' . $op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }

    function IDCard($siswa, $sets) {
        // Landscape ID Card Size (86x54mm)
        $this->AddPage('L', [86, 54]);
        $this->SetAutoPageBreak(false);

        // Background Template
        $bg_path = __DIR__ . '/../assets/img/karpel.jpg';
        if (file_exists($bg_path)) {
            $this->Image($bg_path, 0, 0, 86, 54);
        }

        // Student Photo (with Rounded Corners)
        $foto_path = __DIR__ . '/../uploads/siswa/' . ($siswa['foto'] ?? '');
        $photo_x = 6.4;
        $photo_y = 15;
        $photo_w = 26.3;
        $photo_h = 32.8;
        $photo_r = 1.7; // Approx 12px in mm

        if (!empty($siswa['foto']) && file_exists($foto_path)) {
            $this->SaveState();
            $this->ClippingRoundedRect($photo_x, $photo_y, $photo_w, $photo_h, $photo_r);
            $this->Image($foto_path, $photo_x, $photo_y, $photo_w, $photo_h);
            $this->RestoreState();
        } else {
            $this->SetFillColor(226, 232, 240);
            $this->Rect($photo_x, $photo_y, $photo_w, $photo_h, 'F');
        }

        // Barcode (Precision fitting the white box)
        $barcode_x = 6;
        $barcode_y = 44.2;
        $barcode_w = 27;
        $barcode_h = 8.5; // Taller barcode to fill box
        $this->SetFillColor(255, 255, 255);
        $this->Rect($barcode_x, $barcode_y, $barcode_w, $barcode_h, 'F');
        $this->Code128($barcode_x + 0.5, $barcode_y + 0.5, $siswa['nis'], $barcode_w - 1, $barcode_h - 3);

        // Barcode Text
        $this->SetFont('Helvetica', 'B', 4);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY($barcode_x, $barcode_y + 5.8);
        $this->Cell($barcode_w, 2, $siswa['nis'], 0, 0, 'C');

        // Info Area
        $info_x = 50.5;
        $start_y = 12.5;
        $line_h = 7.5;

        // Name
        $this->SetTextColor(30, 41, 59);
        $this->SetFont('Helvetica', 'B', 6);
        $this->SetXY($info_x, $start_y + 2.0); // Precision top alignment
        $this->MultiCell(33, 2.5, strtoupper($siswa['nama_siswa']), 0, 'L');

        // NIS | NISN
        $start_y += $line_h;
        $this->SetXY($info_x, $start_y + 2.0);
        $this->Cell(33, 2.5, $siswa['nis'] . ' | ' . ($siswa['nisn'] ?? '-'), 0, 1, 'L');

        // Birth
        $start_y += $line_h;
        $this->SetXY($info_x, $start_y + 2.0);
        $ttl = ($siswa['tempat_lahir'] ?? '-') . ', ' . (!empty($siswa['tanggal_lahir']) ? date('d-m-Y', strtotime($siswa['tanggal_lahir'])) : '-');
        $this->Cell(33, 2.5, strtoupper($ttl), 0, 1, 'L');

        // Gender
        $start_y += $line_h;
        $this->SetXY($info_x, $start_y + 2.0);
        $jk = ($siswa['jenis_kelamin'] == 'P') ? 'PEREMPUAN' : 'LAKI-LAKI';
        $this->Cell(33, 2.5, $jk, 0, 1, 'L');

        // Address
        $start_y += $line_h;
        $this->SetXY($info_x, $start_y + 2.0);
        $this->SetFont('Helvetica', 'B', 5.5);
        $this->MultiCell(33, 2.2, strtoupper($siswa['alamat'] ?? '-'), 0, 'L');
    }

    // Helper functions for state
    var $state;
    function SaveState() {
        $this->_out('q');
    }
    function RestoreState() {
        $this->_out('Q');
    }
}

$pdf = new IDCardPDF();
$pdf->IDCard($siswa, $sets);
$pdf->Output('D', 'Kartu_Pelajar_' . $siswa['nis'] . '.pdf');
