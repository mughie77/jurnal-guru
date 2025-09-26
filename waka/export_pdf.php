<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fpdf.php';

// Otorisasi untuk admin dan waka
authorize_role(['admin', 'waka']);

// Logika Filter (sama persis seperti di jurnal.php)
$where_clauses = [];
$filter_info = []; // Untuk ditampilkan di header PDF

if (!empty($_GET['start_date'])) {
    $where_clauses[] = "jurnal.tanggal >= '" . mysqli_real_escape_string($conn, $_GET['start_date']) . "'";
    $filter_info[] = "Mulai: " . date('d/m/Y', strtotime($_GET['start_date']));
}
if (!empty($_GET['end_date'])) {
    $where_clauses[] = "jurnal.tanggal <= '" . mysqli_real_escape_string($conn, $_GET['end_date']) . "'";
    $filter_info[] = "Selesai: " . date('d/m/Y', strtotime($_GET['end_date']));
}
if (!empty($_GET['guru_id'])) {
    $where_clauses[] = "jurnal.guru_id = " . (int)$_GET['guru_id'];
    $guru_info = mysqli_query($conn, "SELECT users.nama_lengkap FROM guru JOIN users ON guru.user_id = users.id WHERE guru.id=".(int)$_GET['guru_id']);
    if($g = mysqli_fetch_assoc($guru_info)) $filter_info[] = "Guru: " . $g['nama_lengkap'];
}
if (!empty($_GET['mapel_id'])) {
    $where_clauses[] = "jurnal.mapel_id = " . (int)$_GET['mapel_id'];
    $mapel_info = mysqli_query($conn, "SELECT nama_mapel FROM mata_pelajaran WHERE id=".(int)$_GET['mapel_id']);
    if($m = mysqli_fetch_assoc($mapel_info)) $filter_info[] = "Mapel: " . $m['nama_mapel'];
}
if (!empty($_GET['kelas_id'])) {
    $where_clauses[] = "jurnal.kelas_id = " . (int)$_GET['kelas_id'];
    $kelas_info = mysqli_query($conn, "SELECT nama_kelas FROM kelas WHERE id=".(int)$_GET['kelas_id']);
    if($k = mysqli_fetch_assoc($kelas_info)) $filter_info[] = "Kelas: " . $k['nama_kelas'];
}
if (!empty($_GET['tahun_id'])) {
    $where_clauses[] = "jurnal.tahun_pelajaran_id = " . (int)$_GET['tahun_id'];
    $tahun_info = mysqli_query($conn, "SELECT tahun FROM tahun_pelajaran WHERE id=".(int)$_GET['tahun_id']);
    if($t = mysqli_fetch_assoc($tahun_info)) $filter_info[] = "Th. Pelajaran: " . $t['tahun'];
}

$sql = "SELECT jurnal.*, users.nama_lengkap, mata_pelajaran.nama_mapel, kelas.nama_kelas
        FROM jurnal
        JOIN guru ON jurnal.guru_id = guru.id
        JOIN users ON guru.user_id = users.id
        JOIN mata_pelajaran ON jurnal.mapel_id = mata_pelajaran.id
        JOIN kelas ON jurnal.kelas_id = kelas.id";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY jurnal.tanggal ASC, jurnal.created_at ASC";

$result = mysqli_query($conn, $sql);

// Kelas PDF Kustom
class PDF extends FPDF
{
    private $filter_info_text;

    function setFilterInfo($text) {
        $this->filter_info_text = $text;
    }

    // Page header
    function Header()
    {
        $this->SetFont('Helvetica','',14);
        $this->Cell(0,10,'Laporan Jurnal Mengajar',0,1,'C');
        $this->SetFont('Helvetica','',9);
        if(!empty($this->filter_info_text)){
            $this->Cell(0,5,'Filter: ' . $this->filter_info_text,0,1,'C');
        }
        $this->Ln(5);
    }

    // Page footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica','',8);
        $this->Cell(0,10,'Halaman '.$this->PageNo().'/{nb}',0,0,'C');
    }

    // Tabel data
    function FancyTable($header, $data)
    {
        // Colors, line width and bold font
        $this->SetFillColor(224,235,255);
        $this->SetTextColor(0);
        $this->SetDrawColor(128,0,0);
        $this->SetLineWidth(.3);
        $this->SetFont('','');

        // Header
        $w = array(20, 40, 35, 20, 20, 25, 60, 55); // Lebar kolom, total 275 untuk landscape A4
        for($i=0;$i<count($header);$i++)
            $this->Cell($w[$i],7,$header[$i],1,0,'C',true);
        $this->Ln();

        // Color and font restoration
        $this->SetFillColor(245,245,245);
        $this->SetTextColor(0);
        $this->SetFont('');

        // Data
        $fill = false;
        while($row = mysqli_fetch_assoc($data))
        {
            $this->Cell($w[0],6,date('d-m-Y', strtotime($row['tanggal'])),'LR',0,'L',$fill);
            $this->Cell($w[1],6,$row['nama_lengkap'],'LR',0,'L',$fill);
            $this->Cell($w[2],6,$row['nama_mapel'],'LR',0,'L',$fill);
            $this->Cell($w[3],6,$row['nama_kelas'],'LR',0,'C',$fill);
            $this->Cell($w[4],6,$row['jam_ke'],'LR',0,'C',$fill);
            $this->Cell($w[5],6,"{$row['jml_hadir']}/{$row['jml_sakit']}/{$row['jml_izin']}/{$row['jml_alfa']}",'LR',0,'C',$fill);

            // Simpan posisi Y
            $y = $this->GetY();
            $x = $this->GetX();

            // MultiCell untuk materi dan keterangan agar bisa wrap
            $this->MultiCell($w[6],6,$row['materi'],'LR','L',$fill);
            $this->SetXY($x + $w[6], $y); // Pindah posisi ke kolom berikutnya
            $this->MultiCell($w[7],6,$row['keterangan'],'LR','L',$fill);

            $fill = !$fill;
        }
        // Closing line
        $this->Cell(array_sum($w),0,'','T');
    }
}

// Inisialisasi PDF
$pdf = new PDF('L','mm','A4'); // L untuk Landscape
$pdf->setFilterInfo(implode(', ', $filter_info));
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Helvetica','',10);

// Header Tabel
$header = array('Tanggal', 'Nama Guru', 'Mapel', 'Kelas', 'Jam Ke-', 'Absensi', 'Materi', 'Keterangan');

// Buat tabel
$pdf->FancyTable($header, $result);

// Output
$pdf->Output('I', 'Laporan_Jurnal_Mengajar.pdf');
?>