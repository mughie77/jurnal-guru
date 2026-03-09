<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['waka', 'admin']);
$page_title = "Data Mata Pelajaran";

$result = mysqli_query($conn, "SELECT * FROM mata_pelajaran ORDER BY nama_mapel ASC");
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Mata Pelajaran</h1>
    <p class="text-slate-500">Informasi kurikulum dan kode mata pelajaran.</p>
</div>

<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Kode Mapel</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Nama Mata Pelajaran</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-mono text-sm text-indigo-600 font-bold"><?= htmlspecialchars($row['kode_mapel']) ?></td>
                    <td class="px-6 py-4 font-semibold text-slate-700"><?= htmlspecialchars($row['nama_mapel']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
