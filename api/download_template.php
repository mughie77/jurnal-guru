<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSXGen.php';
use Shuchkin\SimpleXLSXGen;

authorize_role(['admin', 'waka']);

$type = $_GET['type'] ?? '';

switch ($type) {
    case 'guru':
        $filename = 'Template_Import_Guru.xlsx';
        $data = [
            ['Nama Lengkap', 'NIP', 'Alamat', 'No. Telp', 'Tempat Lahir', 'Tanggal Lahir'],
            ['Ahmad Fauzi, S.Pd', '198501012010011001', 'Jl. Merdeka No. 10', '08123456789', 'Bondowoso', '1985-01-01'],
        ];
        $xlsx = SimpleXLSXGen::fromArray($data);
        break;

    case 'siswa':
        $filename = 'Template_Import_Siswa.xlsx';
        $data = [
            ['NIS', 'NISN', 'Nama Siswa', 'L/P', 'Alamat', 'No. Telp', 'Tempat Lahir', 'Tanggal Lahir'],
            ['1234/1231.2331', '0012345678', 'Budi Santoso', 'L', 'Jl. Contoh No. 1', '08123456789', 'Bondowoso', '2005-01-01'],
            ['1235/1232.2332', '0012345679', 'Ani Wijaya', 'P', 'Jl. Contoh No. 2', '08123456780', 'Bondowoso', '2005-02-02'],
        ];
        $xlsx = SimpleXLSXGen::fromArray($data);
        break;

    case 'kelas':
        $filename = 'Template_Import_Kelas.xlsx';
        $data = [
            ['Nama Kelas'],
            ['X RPL 1'],
            ['X RPL 2'],
        ];
        $xlsx = SimpleXLSXGen::fromArray($data);
        break;

    case 'mapel':
        $filename = 'Template_Import_Mapel.xlsx';
        $data = [
            ['Nama Mata Pelajaran'],
            ['Pemrograman Web X'],
            ['Pemrograman Berorientasi Objek XI'],
        ];
        $xlsx = SimpleXLSXGen::fromArray($data);
        break;

    case 'jadwal':
        $filename = 'Template_Import_Jadwal_Pelajaran.xlsx';
        $sheet1_data = [
            ['Nama Kelas', 'Hari', 'NIP / Nama Guru', 'Kode / Nama Mapel', 'Jam Ke', 'Jam Mulai'],
            ['X RPL 1', 'Senin', '198501012010011001', 'Pemrograman Web', '1-2', '07:00'],
            ['X RPL 1', 'Senin', 'Ahmad Fauzi, S.Pd', 'Matematika', '3-4', '08:30'],
            ['X RPL 2', 'Selasa', '198802022012011002', 'Bahasa Indonesia', '1-2', '07:00'],
        ];

        // Fetch teachers list from DB
        $res_guru = mysqli_query($conn, "SELECT g.nip, u.nama_lengkap FROM guru g JOIN users u ON g.user_id = u.id ORDER BY u.nama_lengkap ASC");
        $teachers_list = [];
        if ($res_guru) {
            while ($g = mysqli_fetch_assoc($res_guru)) {
                $teachers_list[] = $g;
            }
        }

        // Fetch mapels list from DB
        $res_mapel = mysqli_query($conn, "SELECT nama_mapel, kode_mapel FROM mata_pelajaran ORDER BY nama_mapel ASC");
        $mapels_list = [];
        if ($res_mapel) {
            while ($m = mysqli_fetch_assoc($res_mapel)) {
                $mapels_list[] = $m;
            }
        }

        $sheet2_data = [
            ['DAFTAR GURU TERDAFTAR', '', '', 'DAFTAR MATA PELAJARAN TERDAFTAR', ''],
            ['No', 'Nama Lengkap Guru', 'NIP', 'Nama Mata Pelajaran', 'Kode Mapel']
        ];

        $max_rows = max(count($teachers_list), count($mapels_list));
        if ($max_rows === 0) {
            $sheet2_data[] = ['1', 'Belum ada data guru', '-', 'Belum ada data mapel', '-'];
        } else {
            for ($i = 0; $i < $max_rows; $i++) {
                $no = (string)($i + 1);
                $g_nama = $teachers_list[$i]['nama_lengkap'] ?? '';
                $g_nip = !empty($teachers_list[$i]['nip']) ? $teachers_list[$i]['nip'] : '-';
                $m_nama = $mapels_list[$i]['nama_mapel'] ?? '';
                $m_kode = !empty($mapels_list[$i]['kode_mapel']) ? $mapels_list[$i]['kode_mapel'] : '-';

                $sheet2_data[] = [$no, $g_nama, $g_nip, $m_nama, $m_kode];
            }
        }

        $xlsx = new SimpleXLSXGen();
        $xlsx->addSheet($sheet1_data, 'Template Jadwal');
        $xlsx->addSheet($sheet2_data, 'Referensi Guru & Mapel');
        break;

    default:
        die("Tipe template tidak valid.");
}

$xlsx->downloadAs($filename);
exit();
?>
