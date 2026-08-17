<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['siswa']);

$siswa_id = (int)$_SESSION['user_id'];
$message = '';
$message_type = '';

// Secure URL parser & filter
function sanitize_and_format_urls($text) {
    // First, escape HTML to prevent XSS (CWE-79)
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // Regex to match URLs securely
    $pattern = '/\b(https?:\/\/[^\s<>]+|www\.[^\s<>]+)/i';

    // Replace URLs with safe clickable anchors
    $text = preg_replace_callback($pattern, function($matches) {
        $url = $matches[0];
        $href = $url;

        // Ensure protocol is present
        if (strpos(strtolower($href), 'http://') !== 0 && strpos(strtolower($href), 'https://') !== 0) {
            $href = 'https://' . $href;
        }

        // Prevent javascript: or data: pseudo-protocols
        $parsed = parse_url($href);
        $scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : '';
        if (!in_array($scheme, ['http', 'https'])) {
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        }

        // Return safe HTML anchor
        return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer nofollow" class="text-indigo-600 hover:underline font-bold break-all">' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a>';
    }, $text);

    return $text;
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 1. Fetch available BK Teachers for starting new consultation
$bk_teachers_query = "SELECT g.id, u.nama_lengkap, g.foto, g.no_telp
                      FROM guru g
                      JOIN users u ON g.user_id = u.id
                      JOIN guru_mapel gm ON g.id = gm.guru_id
                      JOIN mata_pelajaran mp ON gm.mapel_id = mp.id
                      WHERE mp.nama_mapel LIKE '%Bimbingan Konseling%' OR mp.nama_mapel LIKE '%BK%'
                      GROUP BY g.id
                      ORDER BY u.nama_lengkap ASC";
$bk_result = mysqli_query($conn, $bk_teachers_query);
$bk_teachers = [];
while ($row = mysqli_fetch_assoc($bk_result)) {
    $bk_teachers[] = $row;
}

// 2. Handle starting a new consultation thread
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_consultation'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Token CSRF tidak valid.";
        $message_type = "error";
    } else {
        $guru_id = (int)($_POST['guru_id'] ?? 0);
        $subjek = mysqli_real_escape_string($conn, trim($_POST['subjek'] ?? ''));
        $first_message = mysqli_real_escape_string($conn, trim($_POST['pesan'] ?? ''));

        if ($guru_id <= 0 || empty($subjek) || empty($first_message)) {
            $message = "Harap isi semua kolom formulir konsultasi baru.";
            $message_type = "error";
        } else {
            // Start transaction for atomicity
            mysqli_begin_transaction($conn);
            try {
                // Insert into konsultasi
                $q_ins_k = mysqli_query($conn, "INSERT INTO konsultasi (siswa_id, guru_id, subjek, status) VALUES ($siswa_id, $guru_id, '$subjek', 'open')");
                if (!$q_ins_k) {
                    throw new Exception(mysqli_error($conn));
                }
                $konsultasi_id = mysqli_insert_id($conn);

                // Insert first message
                $q_ins_m = mysqli_query($conn, "INSERT INTO konsultasi_pesan (konsultasi_id, pengirim_role, pesan) VALUES ($konsultasi_id, 'siswa', '$first_message')");
                if (!$q_ins_m) {
                    throw new Exception(mysqli_error($conn));
                }

                mysqli_commit($conn);
                $message = "Konsultasi baru berhasil dimulai!";
                $message_type = "success";
                // Redirect to active thread
                header("Location: konsultasi.php?id=" . $konsultasi_id);
                exit();
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $message = "Gagal memulai konsultasi: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// 3. Handle sending message in an existing consultation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Token CSRF tidak valid.";
        $message_type = "error";
    } else {
        $konsultasi_id = (int)($_POST['konsultasi_id'] ?? 0);
        $pesan = mysqli_real_escape_string($conn, trim($_POST['pesan'] ?? ''));

        if ($konsultasi_id <= 0 || empty($pesan)) {
            $message = "Pesan tidak boleh kosong.";
            $message_type = "error";
        } else {
            // Verify ownership of the consultation
            $check_owner = mysqli_query($conn, "SELECT id, status FROM konsultasi WHERE id = $konsultasi_id AND siswa_id = $siswa_id LIMIT 1");
            if (mysqli_num_rows($check_owner) > 0) {
                $c_data = mysqli_fetch_assoc($check_owner);
                if ($c_data['status'] === 'closed') {
                    $message = "Konsultasi ini sudah ditutup.";
                    $message_type = "error";
                } else {
                    $q_ins = mysqli_query($conn, "INSERT INTO konsultasi_pesan (konsultasi_id, pengirim_role, pesan) VALUES ($konsultasi_id, 'siswa', '$pesan')");
                    if ($q_ins) {
                        // Update updated_at column in konsultasi
                        mysqli_query($conn, "UPDATE konsultasi SET updated_at = CURRENT_TIMESTAMP WHERE id = $konsultasi_id");
                        header("Location: konsultasi.php?id=" . $konsultasi_id);
                        exit();
                    } else {
                        $message = "Gagal mengirim pesan: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
            } else {
                $message = "Akses ditolak.";
                $message_type = "error";
            }
        }
    }
}

// 4. Fetch student's consultation list
$threads_query = "SELECT k.*, u.nama_lengkap as nama_guru, g.foto as foto_guru,
                  (SELECT pesan FROM konsultasi_pesan kp WHERE kp.konsultasi_id = k.id ORDER BY kp.created_at DESC LIMIT 1) as last_msg,
                  (SELECT created_at FROM konsultasi_pesan kp WHERE kp.konsultasi_id = k.id ORDER BY kp.created_at DESC LIMIT 1) as last_msg_time
                  FROM konsultasi k
                  JOIN guru g ON k.guru_id = g.id
                  JOIN users u ON g.user_id = u.id
                  WHERE k.siswa_id = $siswa_id
                  ORDER BY k.updated_at DESC";
$threads_res = mysqli_query($conn, $threads_query);
if (!$threads_res) {
    die("Threads Query Error: " . mysqli_error($conn));
}
$threads = [];
while ($row = mysqli_fetch_assoc($threads_res)) {
    $threads[] = $row;
}

// 5. Active consultation thread details (if set)
$active_id = (int)($_GET['id'] ?? 0);
$active_thread = null;
$messages = [];

if ($active_id > 0) {
    $active_thread_query = "SELECT k.*, u.nama_lengkap as nama_guru, g.foto as foto_guru, g.no_telp as telp_guru
                            FROM konsultasi k
                            JOIN guru g ON k.guru_id = g.id
                            JOIN users u ON g.user_id = u.id
                            WHERE k.id = $active_id AND k.siswa_id = $siswa_id LIMIT 1";
    $active_thread_res = mysqli_query($conn, $active_thread_query);
    if (!$active_thread_res) {
        die("Active Thread Query Error: " . mysqli_error($conn));
    }
    if ($row = mysqli_fetch_assoc($active_thread_res)) {
        $active_thread = $row;

        // Fetch messages
        $messages_query = "SELECT * FROM konsultasi_pesan WHERE konsultasi_id = $active_id ORDER BY created_at ASC";
        $messages_res = mysqli_query($conn, $messages_query);
        if (!$messages_res) {
            die("Messages Query Error: " . mysqli_error($conn));
        }
        while ($msg = mysqli_fetch_assoc($messages_res)) {
            $messages[] = $msg;
        }
    }
}

$page_title = "Konsultasi BK Siswa";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    body { background-color: #F8FAFC; }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="p-4 lg:p-8 max-w-6xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-black italic text-slate-800 tracking-tight text-indigo-600">KONSULTASI BK</h1>
                <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mt-1">Layanan Bimbingan & Konsultasi Online CAKRA</p>
            </div>
            <a href="index.php" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-slate-600 transition-all">
                <i class="fa fa-times"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Sidebar: Threads List (4 cols) -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Action: Mulai Konsultasi -->
                <button onclick="openKonsultasiModal('newConsultationModal')"
                        class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-2xl shadow-lg shadow-indigo-100 transition-all flex items-center justify-center gap-2 text-sm uppercase tracking-wider italic">
                    <i class="fa fa-plus-circle text-lg"></i> Konsultasi Baru
                </button>

                <!-- Consultation History List -->
                <div class="lux-card bg-white p-6 border-none shadow-xl rounded-3xl space-y-4">
                    <h2 class="text-xs font-black text-slate-400 uppercase tracking-widest">Riwayat Konsultasi</h2>
                    <div class="space-y-3 max-h-[500px] overflow-y-auto pr-1">
                        <?php if (empty($threads)): ?>
                            <p class="text-xs text-slate-400 italic text-center py-8">Belum ada riwayat konsultasi.</p>
                        <?php else: ?>
                            <?php foreach ($threads as $t): ?>
                                <a href="konsultasi.php?id=<?= $t['id'] ?>"
                                   class="block p-4 rounded-2xl border transition-all flex items-center gap-4 <?= $t['id'] == $active_id ? 'border-indigo-500 bg-indigo-50/40 shadow-sm' : 'border-slate-100 hover:bg-slate-50' ?>">
                                    <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center shrink-0 overflow-hidden shadow-inner">
                                        <?php if (!empty($t['foto_guru'])): ?>
                                            <img src="<?= BASE_URL ?>uploads/guru/<?= $t['foto_guru'] ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <i class="fa fa-user-tie text-slate-400 text-lg"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2 mb-1">
                                            <h3 class="font-bold text-slate-800 text-xs truncate leading-none"><?= htmlspecialchars($t['nama_guru']) ?></h3>
                                            <span class="text-[8px] font-bold px-1.5 py-0.5 rounded uppercase tracking-wider <?= $t['status'] === 'open' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>">
                                                <?= $t['status'] ?>
                                            </span>
                                        </div>
                                        <p class="text-[10px] font-black text-indigo-500 truncate mb-1 uppercase tracking-tight"><?= htmlspecialchars($t['subjek']) ?></p>
                                        <p class="text-slate-400 text-[10px] truncate italic">"<?= htmlspecialchars($t['last_msg'] ?? 'Belum ada pesan') ?>"</p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Chat / Thread Area (8 cols) -->
            <div class="lg:col-span-8">
                <?php if ($active_thread === null): ?>
                    <!-- Empty State -->
                    <div class="lux-card bg-white p-12 text-center border-none shadow-xl rounded-3xl h-full flex flex-col items-center justify-center min-h-[400px]">
                        <div class="w-20 h-20 bg-indigo-50 text-indigo-500 rounded-full flex items-center justify-center text-4xl mb-6 shadow-inner animate-pulse">
                            <i class="fa fa-comments"></i>
                        </div>
                        <h2 class="text-xl font-black text-slate-800 italic">Pusat Konsultasi BK</h2>
                        <p class="text-slate-500 text-xs mt-3 leading-relaxed max-w-sm mx-auto">
                            Silakan pilih salah satu percakapan di sebelah kiri, atau mulai obrolan konsultasi baru dengan guru Bimbingan Konseling (BK) pilihan Anda.
                        </p>
                    </div>
                <?php else: ?>
                    <!-- Active Chat Area -->
                    <div class="lux-card bg-white border-none shadow-xl rounded-3xl overflow-hidden flex flex-col h-full min-h-[500px]">
                        <!-- Chat Header -->
                        <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center shrink-0 overflow-hidden shadow-inner">
                                    <?php if (!empty($active_thread['foto_guru'])): ?>
                                        <img src="<?= BASE_URL ?>uploads/guru/<?= $active_thread['foto_guru'] ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <i class="fa fa-user-tie text-slate-400 text-lg"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h3 class="font-black text-slate-800 italic text-sm"><?= htmlspecialchars($active_thread['nama_guru']) ?></h3>
                                    <p class="text-[9px] font-black text-indigo-500 uppercase tracking-widest mt-0.5">Topik: <?= htmlspecialchars($active_thread['subjek']) ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if (!empty($active_thread['telp_guru'])): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $active_thread['telp_guru']) ?>" target="_blank"
                                       class="w-10 h-10 flex items-center justify-center bg-emerald-50 text-emerald-600 border border-emerald-100 hover:bg-emerald-100 rounded-xl transition-all shadow-sm"
                                       title="Chat via WhatsApp">
                                        <i class="fab fa-whatsapp text-lg"></i>
                                    </a>
                                <?php endif; ?>
                                <span class="px-3 py-1 text-[10px] font-black uppercase tracking-wider rounded-full <?= $active_thread['status'] === 'open' ? 'bg-emerald-100 text-emerald-800 animate-pulse' : 'bg-slate-200 text-slate-600' ?>">
                                    <?= $active_thread['status'] ?>
                                </span>
                            </div>
                        </div>

                        <!-- Chat Messages Container -->
                        <div class="flex-1 p-6 overflow-y-auto max-h-[360px] min-h-[250px] space-y-4 bg-slate-50/30" id="chatContainer">
                            <?php if (empty($messages)): ?>
                                <p class="text-xs text-slate-400 italic text-center py-12">Belum ada pesan.</p>
                            <?php else: ?>
                                <?php foreach ($messages as $m): ?>
                                    <?php $is_me = ($m['pengirim_role'] === 'siswa'); ?>
                                    <div class="flex <?= $is_me ? 'justify-end' : 'justify-start' ?>">
                                        <div class="max-w-[75%] rounded-3xl px-5 py-3.5 shadow-sm text-sm <?= $is_me ? 'bg-indigo-600 text-white rounded-br-none' : 'bg-white border border-slate-100 text-slate-800 rounded-bl-none' ?>">
                                            <p class="leading-relaxed font-medium"><?= nl2br(sanitize_and_format_urls($m['pesan'])) ?></p>
                                            <div class="text-[9px] mt-2 flex items-center justify-between gap-4 <?= $is_me ? 'text-indigo-200' : 'text-slate-400' ?>">
                                                <span class="font-bold uppercase tracking-wider"><?= $is_me ? 'Anda' : 'Guru BK' ?></span>
                                                <span><?= date('H:i', strtotime($m['created_at'])) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Chat Input Box -->
                        <?php if ($active_thread['status'] === 'open'): ?>
                            <div class="p-6 border-t border-slate-100">
                                <!-- Emoticon Panel -->
                                <div class="flex flex-wrap gap-1.5 mb-3 p-2 bg-slate-50 border border-slate-100 rounded-2xl shadow-inner no-print" id="emoticonPanel">
                                    <?php
                                    $emojis = ['😊', '😂', '😍', '👍', '🙏', '😭', '😡', '😮', '👏', '🎉', '💔', '❤️', '🤔', '💡', '🌟', '🤝', '🧑‍🏫', '📝', '🏫', '📱'];
                                    foreach ($emojis as $emoji): ?>
                                        <button type="button" onclick="insertEmoji('<?= $emoji ?>')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white hover:bg-indigo-50 hover:text-indigo-600 text-base shadow-sm border border-slate-100 transition-all active:scale-90 select-none">
                                            <?= $emoji ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <form action="" method="POST" class="flex gap-4 items-center">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="konsultasi_id" value="<?= $active_id ?>">
                                    <input type="hidden" name="send_message" value="1">

                                    <textarea name="pesan" rows="1" required
                                              placeholder="Tulis pesan konsultasi Anda di sini..."
                                              class="flex-1 px-5 py-3.5 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 focus:border-indigo-500 font-medium text-slate-700 resize-none italic text-xs shadow-inner"
                                              onkeydown="if(event.keyCode == 13 && !event.shiftKey) { this.form.submit(); return false; }"></textarea>
                                    <button type="submit"
                                            class="w-12 h-12 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-2xl transition-all flex items-center justify-center shadow-lg shadow-indigo-100 hover:scale-105 active:scale-95">
                                        <i class="fa fa-paper-plane text-base"></i>
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="p-6 bg-slate-50 text-center text-slate-400 text-xs font-bold italic border-t border-slate-100">
                                <i class="fa fa-lock mr-1"></i> Konsultasi ini telah ditutup oleh Guru BK. Anda dapat memulai konsultasi baru jika diperlukan.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Start New Consultation -->
<div id="newConsultationModal" class="modal-content fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-lg max-h-[90vh] overflow-y-auto bg-white rounded-3xl shadow-2xl z-[70] hidden transition-all duration-300 scale-95 opacity-0">
    <div class="p-6 bg-gradient-to-br from-indigo-600 to-indigo-800 text-white relative">
        <h3 class="text-xl font-black italic uppercase tracking-tighter">Mulai Konsultasi Baru</h3>
        <p class="text-indigo-100 text-[10px] font-bold uppercase tracking-widest mt-1">Diskusikan keluhan Anda dengan guru BK secara aman & rahasia</p>
        <button onclick="closeKonsultasiModal('newConsultationModal')" class="absolute top-6 right-6 text-white/80 hover:text-white text-lg"><i class="fa fa-times"></i></button>
    </div>
    <form action="" method="POST" class="p-6 space-y-5">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="start_consultation" value="1">

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Pilih Guru BK</label>
            <select name="guru_id" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white font-bold text-slate-700 text-sm">
                <option value="">-- Pilih Guru BK --</option>
                <?php foreach ($bk_teachers as $g): ?>
                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_lengkap']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Topik / Subjek Masalah</label>
            <input type="text" name="subjek" required placeholder="Contoh: Hambatan Belajar, Masalah Pribadi, Bullying..."
                   class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-slate-700 text-sm">
        </div>

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Pesan Pembuka</label>
            <textarea name="pesan" rows="4" required placeholder="Jelaskan secara mendetail apa yang ingin Anda konsultasikan..."
                      class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-medium text-slate-700 text-sm italic"></textarea>
        </div>

        <div class="flex gap-4 pt-4">
            <button type="button" onclick="closeKonsultasiModal('newConsultationModal')" class="flex-1 px-4 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
            <button type="submit" class="flex-1 px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-xl shadow-lg shadow-indigo-100 text-sm transition-all">Mulai Percakapan</button>
        </div>
    </form>
</div>

<!-- Modal Backdrop -->
<div id="modalBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0"></div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Scroll chat to bottom
    const chatContainer = document.getElementById('chatContainer');
    if (chatContainer) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    // Emoji Insertion Helper
    function insertEmoji(emoji) {
        const textarea = document.querySelector('textarea[name="pesan"]');
        if (textarea) {
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            textarea.value = text.substring(0, start) + emoji + text.substring(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
        }
    }

    // Modal helpers
    function openKonsultasiModal(id) {
        const modal = document.getElementById(id);
        const backdrop = document.getElementById('modalBackdrop');
        if (modal && backdrop) {
            backdrop.classList.remove('hidden');
            modal.classList.remove('hidden');
            setTimeout(() => {
                backdrop.classList.remove('opacity-0');
                modal.classList.remove('opacity-0', 'scale-95');
                modal.classList.add('opacity-100', 'scale-100');
            }, 10);
        }
    }

    function closeKonsultasiModal(id) {
        const modal = document.getElementById(id);
        const backdrop = document.getElementById('modalBackdrop');
        if (modal && backdrop) {
            modal.classList.remove('opacity-100', 'scale-100');
            modal.classList.add('opacity-0', 'scale-95');
            backdrop.classList.remove('opacity-100');
            backdrop.classList.add('opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
                backdrop.classList.add('hidden');
            }, 300);
        }
    }

    // Automatically open modal and select teacher if query parameters are set
    window.addEventListener('DOMContentLoaded', (event) => {
        const urlParams = new URLSearchParams(window.location.search);
        const isNew = urlParams.get('id') === 'new';
        const guruId = urlParams.get('guru_id');

        if (isNew || guruId) {
            openKonsultasiModal('newConsultationModal');
            if (guruId) {
                const selectElement = document.querySelector('select[name="guru_id"]');
                if (selectElement) {
                    selectElement.value = guruId;
                }
            }
        }
    });

    <?php if (!empty($message)): ?>
    Swal.fire({
        icon: '<?= $message_type ?>',
        title: '<?= $message_type == "success" ? "Berhasil!" : "Gagal!" ?>',
        text: '<?= $message ?>',
        confirmButtonColor: '#4F46E5',
        customClass: { popup: 'rounded-[32px]', title: 'font-black italic text-indigo-600' }
    });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
