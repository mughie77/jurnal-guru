<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/SimpleXLSXGen.php';
use Shuchkin\SimpleXLSXGen;

authorize_role(['admin']);

$type = $_GET['type'] ?? '';

switch ($type) {
    case 'guru':
        $filename = 'Template_Import_Guru.xlsx';
        $data = [
            ['Nama Lengkap', 'NIP', 'Alamat', 'No. Telp', 'Tempat Lahir', 'Tanggal Lahir'],
            ['Ahmad Fauzi, S.Pd', '198501012010011001', 'Jl. Merdeka No. 10', '08123456789', 'Bondowoso', '1985-01-01'],
        ];
        break;
    case 'siswa':
        $filename = 'Template_Import_Siswa.xlsx';
        $data = [
            ['NIS', 'NISN', 'Nama Siswa', 'L/P', 'Alamat', 'No. Telp', 'Tempat Lahir', 'Tanggal Lahir'],
            ['12345', '0012345678', 'Budi Santoso', 'L', 'Jl. Contoh No. 1', '08123456789', 'Bondowoso', '2005-01-01'],
            ['12346', '0012345679', 'Ani Wijaya', 'P', 'Jl. Contoh No. 2', '08123456780', 'Bondowoso', '2005-02-02'],
        ];
        break;
    case 'kelas':
        $filename = 'Template_Import_Kelas.xlsx';
        $data = [
            ['Nama Kelas'],
            ['X RPL 1'],
            ['X RPL 2'],
        ];
        break;
    case 'mapel':
        $filename = 'Template_Import_Mapel.xlsx';
        $data = [
            ['Nama Mata Pelajaran'],
            ['Pemrograman Web X'],
            ['Pemrograman Berorientasi Objek XI'],
        ];
        break;
    default:
        die("Tipe template tidak valid.");
}

$xlsx = SimpleXLSXGen::fromArray($data);
$xlsx->downloadAs($filename);
exit();
?>
