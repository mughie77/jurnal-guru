<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fpdf.php';

authorize_role(['waka', 'admin']);

class PDF extends FPDF {
    function Header() {
        $this->SetFont('Helvetica', 'B', 14);
        $this->Cell(0, 10, 'LAPORAN JURNAL MENGAJAR GURU', 0, 1, 'C');
        $this->SetFont('Helvetica', '', 10);
        $this->Cell(0, 10, 'Dicetak pada: ' . date('d/m/Y H:i'), 0, 1, 'R');
        $this->Ln(5);

        // Table Header
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('Helvetica', 'B', 9);
        $this->Cell(25, 10, 'Tanggal', 1, 0, 'C', true);
        $this->Cell(45, 10, 'Guru', 1, 0, 'C', true);
        $this->Cell(40, 10, 'Mata Pelajaran', 1, 0, 'C', true);
        $this->Cell(20, 10, 'Kelas', 1, 0, 'C', true);
        $this->Cell(60, 10, 'Materi', 1, 1, 'C', true);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Filter logic
$where_clauses = [];
if (!empty($_GET['start_date'])) $where_clauses[] = "jurnal.tanggal >= '" . mysqli_real_escape_string($conn, $_GET['start_date']) . "'";
if (!empty($_GET['end_date'])) $where_clauses[] = "jurnal.tanggal <= '" . mysqli_real_escape_string($conn, $_GET['end_date']) . "'";

$sql = "SELECT jurnal.*, users.nama_lengkap, mata_pelajaran.nama_mapel, kelas.nama_kelas
        FROM jurnal
        JOIN guru ON jurnal.guru_id = guru.id
        JOIN users ON guru.user_id = users.id
        JOIN mata_pelajaran ON jurnal.mapel_id = mata_pelajaran.id
        JOIN kelas ON jurnal.kelas_id = kelas.id";

if (!empty($where_clauses)) $sql .= " WHERE " . implode(' AND ', $where_clauses);
$sql .= " ORDER BY jurnal.tanggal DESC, jurnal.created_at DESC";
$result = mysqli_query($conn, $sql);

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Helvetica', '', 9);

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        // MultiCell for 'Materi' to handle wrapping
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $materi_width = 60;

        // Calculate height for current row
        $materi_text = str_replace(["\r", "\n"], " ", $row['materi']);
        $nb_lines = $pdf->GetStringWidth($materi_text) > ($materi_width - 2) ? 2 : 1; // Simplification
        $h = 10;

        $pdf->Cell(25, $h, date('d/m/Y', strtotime($row['tanggal'])), 1, 0, 'C');
        $pdf->Cell(45, $h, $row['nama_lengkap'], 1, 0, 'L');
        $pdf->Cell(40, $h, $row['nama_mapel'], 1, 0, 'L');
        $pdf->Cell(20, $h, $row['nama_kelas'], 1, 0, 'C');

        $startX = $pdf->GetX();
        $startY = $pdf->GetY();
        $pdf->MultiCell($materi_width, $h, $materi_text, 1, 'L');
        $pdf->SetXY($startX + $materi_width, $startY);
        $pdf->Ln($h);
    }
} else {
    $pdf->Cell(0, 10, 'Tidak ada data jurnal.', 1, 1, 'C');
}

$pdf->Output('I', 'Laporan_Jurnal_' . date('Ymd') . '.pdf');
exit();
?>
