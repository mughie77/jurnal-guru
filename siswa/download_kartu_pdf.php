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
    function IDCard($siswa, $sets) {
        // Standard vertical ID card size (approx CR80 size: 54x86mm, but let's use 85x130mm as per previous request for more space)
        $this->AddPage('P', [85, 130]);
        $this->SetAutoPageBreak(false);

        // Header Background
        $this->SetFillColor(79, 70, 229); // Indigo
        $this->Rect(0, 0, 85, 45, 'F');

        // Logo
        $logo_path = __DIR__ . '/../uploads/' . ($sets['favicon'] ?? '');
        if (!empty($sets['favicon']) && file_exists($logo_path)) {
            $this->Image($logo_path, 35, 5, 15, 15);
        }

        // Header Text
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);
        $this->SetXY(0, 22);
        $this->Cell(85, 5, 'KARTU PELAJAR DIGITAL', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 11);
        $this->SetX(0);
        $this->MultiCell(85, 5, strtoupper($sets['nama_sekolah'] ?? 'SMK NEGERI CAKRA'), 0, 'C');

        // Photo Frame (Centered)
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(255, 255, 255);
        $this->Rect(25, 45, 35, 45, 'FD');

        // Photo
        $foto_path = __DIR__ . '/../uploads/siswa/' . ($siswa['foto'] ?? '');
        if (!empty($siswa['foto']) && file_exists($foto_path)) {
            $this->Image($foto_path, 25, 45, 35, 45);
        } else {
            $this->SetTextColor(200, 200, 200);
            $this->SetFont('Arial', 'B', 8);
            $this->SetXY(25, 65);
            $this->Cell(35, 5, 'NO PHOTO', 0, 0, 'C');
        }

        // Name Section
        $this->SetTextColor(30, 41, 59);
        $this->SetXY(5, 95);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(75, 7, strtoupper($siswa['nama_siswa']), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(79, 70, 229);
        $this->Cell(75, 5, strtoupper($siswa['nama_kelas']), 0, 1, 'C');

        // Details
        $this->SetTextColor(100, 116, 139);
        $this->SetFont('Arial', 'B', 6);
        $this->SetXY(10, 108);
        $this->Cell(32, 4, 'NIS', 0, 0);
        $this->Cell(33, 4, 'NISN', 0, 1);

        $this->SetTextColor(30, 41, 59);
        $this->SetFont('Arial', 'B', 8);
        $this->SetX(10);
        $this->Cell(32, 5, $siswa['nis'], 0, 0);
        $this->Cell(33, 5, $siswa['nisn'] ?? '-', 0, 1);

        $this->SetTextColor(100, 116, 139);
        $this->SetFont('Arial', 'B', 6);
        $this->SetX(10);
        $this->Cell(0, 4, 'TAHUN PELAJARAN', 0, 1);
        $this->SetTextColor(30, 41, 59);
        $this->SetFont('Arial', 'BI', 8);
        $this->SetX(10);
        $this->Cell(0, 5, $siswa['tahun_pelajaran'], 0, 1);

        // QR Code
        $qr_url = "https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=" . $siswa['nis'] . "&choe=UTF-8";
        $this->Image($qr_url, 62, 105, 18, 18, 'PNG');

        // Footer Label
        $this->SetTextColor(150, 150, 150);
        $this->SetFont('Arial', 'I', 6);
        $this->SetXY(0, 122);
        $this->Cell(85, 4, 'VERIFIKASI CAKRA - OFFICIAL ACADEMIC ID', 0, 0, 'C');
    }
}

$pdf = new IDCardPDF();
$pdf->IDCard($siswa, $sets);
$pdf->Output('D', 'Kartu_Pelajar_' . $siswa['nis'] . '.pdf');
