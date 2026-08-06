<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka']);

$message = '';
$message_type = '';

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Reply Submission (Umpan Balik)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_reply'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Token CSRF tidak valid.";
        $message_type = "error";
    } else {
        $kritik_id = (int)$_POST['kritik_id'];
        $umpan_balik = mysqli_real_escape_string($conn, $_POST['umpan_balik']);

        $stmt = mysqli_prepare($conn, "UPDATE kritik_saran SET umpan_balik = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $umpan_balik, $kritik_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Tanggapan / Umpan Balik berhasil dikirim!";
            $message_type = "success";
        } else {
            $message = "Gagal mengirim tanggapan.";
            $message_type = "error";
        }
        mysqli_stmt_close($stmt);
    }
}

// Fetch all Kritik & Saran entries
$query = "SELECT * FROM kritik_saran ORDER BY tanggal DESC";
$result = mysqli_query($conn, $query);

$feedbacks = [];
while ($row = mysqli_fetch_assoc($result)) {
    $feedbacks[] = $row;
}

$page_title = "Rekap Kritik & Saran";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 lg:p-8 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-black italic text-slate-800 tracking-tight">REKAP KRITIK & SARAN</h1>
            <p class="text-slate-400 font-bold text-xs uppercase tracking-widest mt-1">Umpan Balik dari Siswa dan Guru</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="export_kritik.php" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl shadow-lg shadow-emerald-100 transition-all flex items-center gap-2 text-xs uppercase tracking-wider">
                <i class="fa fa-file-excel text-sm"></i> Export Excel
            </a>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="mb-8 relative max-w-md">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
            <i class="fa fa-search"></i>
        </div>
        <input type="text" id="searchKritik" onkeyup="filterKritik()" placeholder="Cari berdasarkan nama, subjek, atau isi..."
               class="w-full pl-12 pr-4 py-3 rounded-2xl border border-slate-200 bg-white focus:outline-none focus:ring-4 focus:ring-indigo-50 focus:border-indigo-500 font-bold text-slate-700 placeholder:text-slate-300 transition-all shadow-sm">
    </div>

    <!-- Table -->
    <div class="lux-card overflow-hidden border-none shadow-2xl bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Pengirim & Peran</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Waktu & Subjek</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Isi Kiriman</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Tanggapan (Umpan Balik)</th>
                        <th class="px-6 py-4 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50" id="kritikTableBody">
                    <?php if (empty($feedbacks)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-bold italic">Belum ada kritik & saran yang dikirim.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($feedbacks as $f): ?>
                            <tr class="kritik-row hover:bg-slate-50/50 transition-colors"
                                data-nama="<?= strtolower(htmlspecialchars($f['nama_pengirim'])) ?>"
                                data-subjek="<?= strtolower(htmlspecialchars($f['subjek'])) ?>"
                                data-isi="<?= strtolower(htmlspecialchars($f['isi'])) ?>">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($f['nama_pengirim']) ?></div>
                                    <div class="mt-1">
                                        <?php if ($f['role'] == 'siswa'): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-sky-50 text-sky-600 border border-sky-100">Siswa</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-600 border border-indigo-100">Guru</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs font-bold text-slate-600"><?= date('d M Y, H:i', strtotime($f['tanggal'])) ?></div>
                                    <div class="text-sm font-black text-indigo-600 mt-0.5 italic"><?= htmlspecialchars($f['subjek']) ?></div>
                                </td>
                                <td class="px-6 py-4 max-w-xs">
                                    <div class="text-xs text-slate-700 leading-relaxed italic line-clamp-3">"<?= htmlspecialchars($f['isi']) ?>"</div>
                                </td>
                                <td class="px-6 py-4 max-w-xs">
                                    <?php if (!empty($f['umpan_balik'])): ?>
                                        <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-100 text-emerald-800 text-xs font-semibold italic">
                                            "<?= htmlspecialchars($f['umpan_balik']) ?>"
                                        </div>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-xs italic">Belum ditanggapi</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <button onclick="openReplyModal(<?= $f['id'] ?>, '<?= addslashes(htmlspecialchars($f['nama_pengirim'])) ?>', '<?= addslashes(htmlspecialchars($f['umpan_balik'] ?? '')) ?>')"
                                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-md shadow-indigo-100 transition-all hover:scale-105 active:scale-95">
                                        <i class="fa fa-reply mr-1"></i> Tanggapi
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0" onclick="closeAllModals()"></div>

<!-- Reply Modal -->
<div id="replyModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] max-w-lg bg-white rounded-[32px] sm:rounded-[40px] shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0 overflow-hidden">
    <div class="bg-slate-900 px-6 py-5 sm:px-8 sm:py-6 text-white flex items-center justify-between">
        <h3 class="text-lg sm:text-xl font-black italic tracking-widest uppercase">Kirim Tanggapan</h3>
        <button onclick="closeModal('replyModal')" class="text-white/50 hover:text-white transition-colors"><i class="fa fa-times text-xl"></i></button>
    </div>
    <form action="" method="POST" class="p-6 sm:p-8 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="kritik_id" id="replyKritikId">

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Penerima</label>
            <div id="replySenderName" class="text-slate-800 font-bold text-sm bg-slate-50 px-4 py-2.5 rounded-xl border border-slate-100"></div>
        </div>

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Tanggapan / Umpan Balik</label>
            <textarea name="umpan_balik" id="replyUmpanBalik" rows="5" required placeholder="Tulis tanggapan atau solusi Anda di sini..."
                      class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 border-2 border-transparent focus:border-indigo-100 outline-none font-bold text-slate-700 transition-all italic text-sm"></textarea>
        </div>

        <div class="pt-4">
            <button type="submit" name="submit_reply" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black italic tracking-widest uppercase shadow-xl shadow-indigo-100 hover:bg-indigo-700 transition-all active:scale-95">Kirim Tanggapan</button>
        </div>
    </form>
</div>

<script>
function filterKritik() {
    const query = document.getElementById('searchKritik').value.toLowerCase();
    const rows = document.getElementsByClassName('kritik-row');

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const nama = row.getAttribute('data-nama');
        const subjek = row.getAttribute('data-subjek');
        const isi = row.getAttribute('data-isi');

        if (nama.includes(query) || subjek.includes(query) || isi.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}

function openReplyModal(id, sender, reply) {
    document.getElementById('replyKritikId').value = id;
    document.getElementById('replySenderName').innerText = sender;
    document.getElementById('replyUmpanBalik').value = reply;
    openModal('replyModal');
}

function openModal(id) {
    const modal = document.getElementById(id);
    const overlay = document.getElementById('modalOverlay');
    overlay.classList.remove('hidden');
    modal.classList.remove('hidden');
    setTimeout(() => {
        overlay.classList.add('opacity-100');
        modal.classList.remove('scale-95', 'opacity-0');
    }, 10);
}

function closeModal(id) {
    const modal = document.getElementById(id);
    const overlay = document.getElementById('modalOverlay');
    overlay.classList.remove('opacity-100');
    modal.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        overlay.classList.add('hidden');
        modal.classList.add('hidden');
    }, 300);
}

function closeAllModals() {
    document.querySelectorAll('.modal-content').forEach(m => {
        if (!m.classList.contains('hidden')) closeModal(m.id);
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php if (!empty($message)): ?>
    Swal.fire({
        icon: '<?= $message_type ?>',
        title: '<?= $message_type == "success" ? "Berhasil" : ($message_type == "error" ? "Gagal" : "Info") ?>',
        text: '<?= $message ?>',
        confirmButtonColor: '#4f46e5',
        customClass: { popup: 'rounded-3xl', title: 'font-black italic' }
    });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
