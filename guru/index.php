<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
$guru_id = mysqli_fetch_assoc($guru_res)['id'];

// Stats for Dashboard
$total_jurnal = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM jurnal WHERE guru_id = $guru_id"))['total'];
$hadir_avg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(jml_hadir) as avg FROM jurnal WHERE guru_id = $guru_id"))['avg'];
$recent_jurnals = mysqli_query($conn, "SELECT j.*, k.nama_kelas, mp.nama_mapel FROM jurnal j JOIN kelas k ON j.kelas_id = k.id JOIN mata_pelajaran mp ON j.mapel_id = mp.id WHERE j.guru_id = $guru_id ORDER BY j.tanggal DESC LIMIT 5");

$page_title = "Dashboard Guru";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    body { background-color: #F3F4F6; }
    .dashboard-grid { display: grid; grid-template-columns: 80px 1fr 300px; min-height: 100vh; }
    @media (max-width: 1024px) { .dashboard-grid { grid-template-columns: 1fr; } .sidebar-mini, .right-panel { display: none; } }
</style>

<div class="bg-slate-50 min-h-screen">
    <!-- Main Content -->
    <div class="p-8 lg:p-12 max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-12">
            <div>
                <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Dashboard Guru <span class="text-indigo-600">(<?= htmlspecialchars($_SESSION['nama_lengkap']) ?>)</span></h1>
                <p class="text-slate-400 font-medium tracking-wide"><?= date('l, d F Y') ?></p>
            </div>
            <div class="flex items-center gap-4">
                <button class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-all"><i class="fa fa-search"></i></button>
                <button class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-all relative">
                    <i class="fa fa-bell"></i>
                    <span class="absolute top-0 right-0 w-2.5 h-2.5 bg-rose-500 border-2 border-white rounded-full"></span>
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            <div class="lux-card p-8 bg-indigo-600 text-white shadow-indigo-200 border-none relative overflow-hidden group">
                <div class="relative z-10">
                    <p class="text-indigo-100 text-xs font-black uppercase tracking-widest mb-4">Total Jurnal</p>
                    <h2 class="text-5xl font-black italic"><?= $total_jurnal ?></h2>
                    <p class="mt-4 text-xs font-bold bg-white/20 inline-block px-3 py-1 rounded-full">+12% dari bulan lalu</p>
                </div>
                <i class="fa fa-book-open absolute -bottom-4 -right-4 text-8xl opacity-10 group-hover:scale-110 transition-transform"></i>
            </div>
            <div class="lux-card p-8 bg-white border-slate-100">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-4">Rata-rata Kehadiran</p>
                <h2 class="text-5xl font-black italic text-slate-800"><?= round($hadir_avg, 1) ?></h2>
                <div class="mt-6 w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                    <div class="bg-emerald-500 h-full w-[85%] rounded-full"></div>
                </div>
            </div>
            <div class="lux-card p-8 bg-white border-slate-100 flex flex-col justify-center items-center text-center group cursor-pointer hover:border-indigo-500 transition-all">
                <a href="isi_jurnal.php" class="contents">
                    <div class="w-16 h-16 rounded-2xl bg-slate-50 text-indigo-600 flex items-center justify-center mb-4 group-hover:bg-indigo-600 group-hover:text-white transition-all duration-500 shadow-inner">
                        <i class="fa fa-plus text-2xl"></i>
                    </div>
                    <p class="font-black text-slate-800 italic uppercase tracking-tighter">Isi Jurnal Baru</p>
                </a>
            </div>
        </div>

        <!-- Activity Table -->
        <div class="lux-card overflow-hidden border-none shadow-2xl">
            <div class="p-6 border-b border-slate-50 flex items-center justify-between">
                <h3 class="font-black text-slate-800 italic uppercase tracking-widest text-sm">Aktivitas Mengajar Terbaru</h3>
                <a href="riwayat.php" class="text-xs font-bold text-indigo-600 hover:underline">Lihat Semua</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50">
                            <th class="px-6 py-4">Tanggal</th>
                            <th class="px-6 py-4">Mata Pelajaran</th>
                            <th class="px-6 py-4 text-center">Kelas</th>
                            <th class="px-6 py-4 text-center">Hadir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php while($j = mysqli_fetch_assoc($recent_jurnals)): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 text-xs font-bold text-slate-700"><?= date('d M', strtotime($j['tanggal'])) ?></td>
                            <td class="px-6 py-4 font-bold text-slate-800 text-sm italic"><?= htmlspecialchars($j['nama_mapel']) ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2 py-1 rounded bg-indigo-50 text-indigo-600 text-[10px] font-black border border-indigo-100 uppercase"><?= $j['nama_kelas'] ?></span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="font-black text-emerald-500"><?= $j['jml_hadir'] ?></span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
