<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin', 'waka']);
$page_title = "Data Perangkat Mengajar";

$guru_id = (int)($_GET['guru_id'] ?? 0);
$kelas_id = (int)($_GET['kelas_id'] ?? 0);
$jenis_perangkat = mysqli_real_escape_string($conn, $_GET['jenis_perangkat'] ?? '');
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');

$where_clauses = [];
if ($guru_id > 0) $where_clauses[] = "p.guru_id = $guru_id";
if ($kelas_id > 0) $where_clauses[] = "pk.kelas_id = $kelas_id";
if (!empty($jenis_perangkat)) $where_clauses[] = "p.jenis_perangkat = '$jenis_perangkat'";
if (!empty($search)) $where_clauses[] = "p.nama_perangkat LIKE '%$search%'";

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$query_base = "perangkat p
               JOIN guru g ON p.guru_id = g.id
               JOIN users u ON g.user_id = u.id
               LEFT JOIN perangkat_kelas pk ON p.id = pk.perangkat_id";

$pagin = get_pagination_data($conn, $query_base, 15, $where_sql, "DISTINCT p.id");

$gurus = mysqli_query($conn, "SELECT g.id, u.nama_lengkap FROM guru g JOIN users u ON g.user_id = u.id ORDER BY u.nama_lengkap ASC");
$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
$categories = mysqli_query($conn, "SELECT id, nama_kategori FROM kategori_perangkat ORDER BY nama_kategori ASC");

$query = "SELECT p.*, u.nama_lengkap as nama_guru, GROUP_CONCAT(k.nama_kelas SEPARATOR ', ') as target_kelas
          FROM perangkat p
          JOIN guru g ON p.guru_id = g.id
          JOIN users u ON g.user_id = u.id
          LEFT JOIN perangkat_kelas pk ON p.id = pk.perangkat_id
          LEFT JOIN kelas k ON pk.kelas_id = k.id
          $where_sql
          GROUP BY p.id
          ORDER BY p.created_at DESC LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";
$perangkats = mysqli_query($conn, $query);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Monitoring Perangkat</h1>
        <p class="text-slate-500 font-medium">Tinjau semua file perangkat mengajar yang diunggah oleh guru.</p>
    </div>
</div>

<div class="lux-card p-6 mb-8 bg-gradient-to-br from-indigo-50/50 to-white">
    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cari Perangkat</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama berkas..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm">
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Filter Guru</label>
            <select name="guru_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700">
                <option value="">-- Semua Guru --</option>
                <?php mysqli_data_seek($gurus, 0); while($g = mysqli_fetch_assoc($gurus)): ?>
                    <option value="<?= $g['id'] ?>" <?= $g['id'] == $guru_id ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_lengkap']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Filter Kelas</label>
            <select name="kelas_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700">
                <option value="">-- Semua Kelas --</option>
                <?php mysqli_data_seek($kelases, 0); while($k = mysqli_fetch_assoc($kelases)): ?>
                    <option value="<?= $k['id'] ?>" <?= $k['id'] == $kelas_id ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Filter Kategori</label>
            <select name="jenis_perangkat" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white shadow-sm font-bold text-slate-700">
                <option value="">-- Semua Kategori --</option>
                <?php mysqli_data_seek($categories, 0); while($cat = mysqli_fetch_assoc($categories)): ?>
                    <option value="<?= $cat['nama_kategori'] ?>" <?= $cat['nama_kategori'] == $jenis_perangkat ? 'selected' : '' ?>><?= htmlspecialchars($cat['nama_kategori']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="lg:col-span-1 flex items-end gap-3">
            <button type="submit" class="flex-1 px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100">Filter</button>
            <a href="perangkat.php" class="px-6 py-2.5 bg-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-300 transition-all text-center">Reset</a>
        </div>
    </form>
</div>

<div class="lux-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Guru</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Perangkat / Media</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Jenis</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Target Kelas</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php while($p = mysqli_fetch_assoc($perangkats)): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-bold text-slate-700 text-sm italic">
                        <?= htmlspecialchars($p['nama_guru']) ?>
                        <div class="text-[9px] text-slate-400 font-normal"><?= date('d M Y, H:i', strtotime($p['created_at'])) ?></div>
                    </td>
                    <td class="px-6 py-4 font-semibold text-slate-600 text-sm"><?= htmlspecialchars($p['nama_perangkat']) ?></td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-600 text-[9px] font-black uppercase border border-indigo-100"><?= $p['jenis_perangkat'] ?></span>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-tighter truncate max-w-[150px]" title="<?= htmlspecialchars($p['target_kelas'] ?? 'Semua Kelas') ?>">
                            <?= htmlspecialchars($p['target_kelas'] ?? 'Semua Kelas') ?>
                        </p>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex justify-center">
                            <?php
                            $btn_color = 'bg-emerald-50 text-emerald-600 hover:bg-emerald-600';
                            $icon = 'fa-file-download';
                            if (strpos($p['file_path'], '.mp4') !== false) {
                                $btn_color = 'bg-indigo-50 text-indigo-600 hover:bg-indigo-600';
                                $icon = 'fa-play-circle';
                            }
                            ?>
                            <a href="<?= BASE_URL . $p['file_path'] ?>" target="_blank" class="w-9 h-9 flex items-center justify-center rounded-lg <?= $btn_color ?> hover:text-white transition-all shadow-sm" title="Lihat/Download">
                                <i class="fa <?= $icon ?>"></i>
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

<?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
