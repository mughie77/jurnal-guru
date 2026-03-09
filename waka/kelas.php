<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['waka', 'admin']);
$page_title = "Data Kelas";

$query = "SELECT kelas.*, users.nama_lengkap as nama_wali_kelas
          FROM kelas LEFT JOIN guru ON kelas.wali_kelas_id = guru.id
          LEFT JOIN users ON guru.user_id = users.id ORDER BY kelas.nama_kelas ASC";
$result = mysqli_query($conn, $query);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Data Kelas</h1>
    <p class="text-slate-500">Informasi kelas dan kapasitas siswa.</p>
</div>

<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Nama Kelas</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Wali Kelas</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Laki-laki</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Perempuan</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-bold text-slate-700"><?= htmlspecialchars($row['nama_kelas']) ?></td>
                    <td class="px-6 py-4 text-sm text-slate-600"><?= htmlspecialchars($row['nama_wali_kelas'] ?? 'Belum Diatur') ?></td>
                    <td class="px-6 py-4 text-center font-bold text-blue-600"><?= $row['jumlah_siswa_L'] ?></td>
                    <td class="px-6 py-4 text-center font-bold text-pink-600"><?= $row['jumlah_siswa_P'] ?></td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-3 py-1 rounded-lg bg-slate-100 text-slate-800 font-extrabold text-sm border border-slate-200">
                            <?= $row['jumlah_siswa_L'] + $row['jumlah_siswa_P'] ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
