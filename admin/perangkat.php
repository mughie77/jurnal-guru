<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka']);
$page_title = "Data Perangkat Mengajar";

$guru_id = (int)($_GET['guru_id'] ?? 0);
$gurus = mysqli_query($conn, "SELECT g.id, u.nama_lengkap FROM guru g JOIN users u ON g.user_id = u.id ORDER BY u.nama_lengkap ASC");

$where = $guru_id > 0 ? "WHERE p.guru_id = $guru_id" : "";
$query = "SELECT p.*, u.nama_lengkap as nama_guru
          FROM perangkat p
          JOIN guru g ON p.guru_id = g.id
          JOIN users u ON g.user_id = u.id
          $where
          ORDER BY p.created_at DESC";
$perangkats = mysqli_query($conn, $query);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Monitoring Perangkat</h1>
        <p class="text-slate-500 font-medium">Tinjau semua file perangkat mengajar yang diunggah oleh guru.</p>
    </div>
</div>

<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white flex flex-wrap items-end gap-4">
    <form action="" method="GET" class="flex flex-wrap items-end gap-4 flex-1">
        <div class="flex-1 min-w-[250px]">
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2">Filter Berdasarkan Guru</label>
            <select name="guru_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold text-slate-700">
                <option value="">-- Semua Guru --</option>
                <?php mysqli_data_seek($gurus, 0); while($g = mysqli_fetch_assoc($gurus)): ?>
                    <option value="<?= $g['id'] ?>" <?= $g['id'] == $guru_id ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_lengkap']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <button type="submit" class="px-8 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100">Filter Data</button>
    </form>
</div>

<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Guru</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Nama Perangkat</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Jenis</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Waktu Unggah</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php while($p = mysqli_fetch_assoc($perangkats)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-bold text-slate-700 text-sm italic"><?= htmlspecialchars($p['nama_guru']) ?></td>
                    <td class="px-6 py-4 font-semibold text-slate-600 text-sm"><?= htmlspecialchars($p['nama_perangkat']) ?></td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-600 text-[9px] font-black uppercase border border-indigo-100"><?= $p['jenis_perangkat'] ?></span>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-400"><?= date('d M Y, H:i', strtotime($p['created_at'])) ?></td>
                    <td class="px-6 py-4">
                        <div class="flex justify-center">
                            <a href="<?= BASE_URL . $p['file_path'] ?>" target="_blank" class="w-9 h-9 flex items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white transition-all shadow-sm" title="Lihat/Download PDF">
                                <i class="fa fa-file-download"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if(mysqli_num_rows($perangkats) == 0): ?>
                    <tr><td colspan="5" class="px-6 py-20 text-center text-slate-400 italic">Tidak ada data perangkat ditemukan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
