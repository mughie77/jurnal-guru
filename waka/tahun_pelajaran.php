<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['waka', 'admin']);
$page_title = "Data Tahun Pelajaran";

$result = mysqli_query($conn, "SELECT * FROM tahun_pelajaran ORDER BY tahun DESC");
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Tahun Pelajaran</h1>
    <p class="text-slate-500">Periode akademik yang terdaftar di sistem.</p>
</div>

<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Tahun Pelajaran</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-bold text-slate-700"><?= htmlspecialchars($row['tahun']) ?></td>
                    <td class="px-6 py-4 text-center">
                        <?php if ($row['status'] == 'aktif'): ?>
                            <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold uppercase tracking-widest border border-emerald-200">Aktif</span>
                        <?php else: ?>
                            <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-500 text-xs font-bold uppercase tracking-widest border border-slate-200">Tidak Aktif</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
