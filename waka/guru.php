<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['waka', 'admin']);
$page_title = "Data Guru";

$query = "SELECT guru.*, users.nama_lengkap, GROUP_CONCAT(mata_pelajaran.nama_mapel SEPARATOR ', ') as mapel_diampu
          FROM guru JOIN users ON guru.user_id = users.id
          LEFT JOIN guru_mapel ON guru.id = guru_mapel.guru_id
          LEFT JOIN mata_pelajaran ON guru_mapel.mapel_id = mata_pelajaran.id
          GROUP BY guru.id ORDER BY users.nama_lengkap ASC";
$result = mysqli_query($conn, $query);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Data Guru</h1>
    <p class="text-slate-500">Daftar tenaga pengajar aktif di sekolah.</p>
</div>

<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">NIP</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Nama Lengkap</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Mata Pelajaran</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-mono text-sm text-indigo-600 font-bold"><?= htmlspecialchars($row['nip']) ?></td>
                    <td class="px-6 py-4 font-semibold text-slate-700"><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                    <td class="px-6 py-4 text-sm text-slate-500">
                        <?php if($row['mapel_diampu']): ?>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach(explode(', ', $row['mapel_diampu']) as $m): ?>
                                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[10px] font-bold border border-slate-200"><?= htmlspecialchars($m) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="italic text-slate-400">Belum ada mapel</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
