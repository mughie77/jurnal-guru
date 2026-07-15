<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru']);

$user_id = $_SESSION['user_id'];

// Fetch all teachers in the school
$query = "SELECT u.nama_lengkap, g.nip, g.no_telp, g.foto, g.alamat
          FROM users u
          JOIN guru g ON u.id = g.user_id
          WHERE u.role = 'guru'
          ORDER BY u.nama_lengkap ASC";
$result = mysqli_query($conn, $query);

$rekan_guru = [];
while ($row = mysqli_fetch_assoc($result)) {
    $rekan_guru[] = $row;
}

// Helper to format WhatsApp phone number (must be numeric, convert leading 0 to 62)
function format_wa_number($num) {
    if (empty($num)) return '';
    $clean = preg_replace('/[^0-9]/', '', $num);
    if (strpos($clean, '0') === 0) {
        $clean = '62' . substr($clean, 1);
    }
    return $clean;
}

$page_title = "Rekan Guru";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    body { background-color: #F8FAFC; }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 lg:p-8 max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-black italic text-slate-800 tracking-tight">Rekan Guru</h1>
                <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mt-1">Daftar Kontak Teman Sejawat</p>
            </div>
            <a href="index.php" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-slate-600 transition-all">
                <i class="fa fa-arrow-left"></i>
            </a>
        </div>

        <!-- Search Bar -->
        <div class="mb-8 relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                <i class="fa fa-search"></i>
            </div>
            <input type="text" id="searchRekan" onkeyup="filterRekan()" placeholder="Cari berdasarkan nama, NIP atau nomor HP..."
                   class="w-full pl-12 pr-4 py-4 rounded-2xl border border-slate-200 bg-white focus:outline-none focus:ring-4 focus:ring-indigo-50 focus:border-indigo-500 font-bold text-slate-700 placeholder:text-slate-300 transition-all shadow-sm">
        </div>

        <!-- List Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="rekanGrid">
            <?php foreach ($rekan_guru as $r): ?>
                <?php
                $wa_number = format_wa_number($r['no_telp']);
                $has_wa = !empty($wa_number);
                ?>
                <div class="rekan-card lux-card p-6 bg-white border-none shadow-xl flex items-center justify-between gap-4 transition-all duration-300 hover:scale-[1.01] hover:shadow-2xl hover:shadow-slate-100"
                     data-nama="<?= strtolower(htmlspecialchars($r['nama_lengkap'])) ?>"
                     data-nip="<?= strtolower(htmlspecialchars($r['nip'] ?? '')) ?>"
                     data-telp="<?= strtolower(htmlspecialchars($r['no_telp'] ?? '')) ?>">

                    <div class="flex items-center gap-4 min-w-0">
                        <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-500 flex items-center justify-center text-xl shrink-0 overflow-hidden border border-indigo-100">
                            <?php if (!empty($r['foto'])): ?>
                                <img src="<?= BASE_URL ?>uploads/guru/<?= $r['foto'] ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fa fa-user-tie"></i>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-black text-slate-800 italic tracking-tight truncate leading-tight"><?= htmlspecialchars($r['nama_lengkap']) ?></h3>
                            <p class="text-slate-400 text-[9px] font-bold uppercase tracking-wider mt-0.5">NIP: <?= htmlspecialchars($r['nip'] ?? '-') ?></p>
                            <p class="text-slate-500 font-bold text-xs mt-1 truncate">
                                <i class="fa fa-phone text-indigo-400 mr-1.5 text-[10px]"></i><?= htmlspecialchars($r['no_telp'] ?? 'Belum diisi') ?>
                            </p>
                        </div>
                    </div>

                    <div>
                        <?php if ($has_wa): ?>
                            <a href="https://wa.me/<?= $wa_number ?>" target="_blank"
                               class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white flex items-center justify-center text-xl shadow-inner hover:shadow-lg hover:shadow-emerald-100 transition-all duration-300 transform hover:scale-105"
                               title="Kirim Pesan WhatsApp">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                        <?php else: ?>
                            <button disabled
                                    class="w-12 h-12 rounded-2xl bg-slate-50 text-slate-300 flex items-center justify-center text-xl cursor-not-allowed"
                                    title="No HP tidak valid / tidak ada">
                                <i class="fab fa-whatsapp opacity-50"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- No Results state -->
        <div id="noResults" class="hidden text-center py-20">
            <div class="w-20 h-20 bg-slate-100 text-slate-300 rounded-full flex items-center justify-center text-3xl mx-auto mb-4">
                <i class="fa fa-search-minus"></i>
            </div>
            <h3 class="text-lg font-black italic text-slate-700 tracking-tight">Rekan Guru Tidak Ditemukan</h3>
            <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">Coba gunakan kata kunci pencarian yang lain</p>
        </div>
    </div>
</div>

<script>
function filterRekan() {
    const query = document.getElementById('searchRekan').value.toLowerCase();
    const cards = document.getElementsByClassName('rekan-card');
    let visibleCount = 0;

    for (let i = 0; i < cards.length; i++) {
        const card = cards[i];
        const nama = card.getAttribute('data-nama');
        const nip = card.getAttribute('data-nip');
        const telp = card.getAttribute('data-telp');

        if (nama.includes(query) || nip.includes(query) || telp.includes(query)) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    }

    const noResults = document.getElementById('noResults');
    if (visibleCount === 0) {
        noResults.classList.remove('hidden');
    } else {
        noResults.classList.add('hidden');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
