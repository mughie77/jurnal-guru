<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Authorize role BK
authorize_role(['guru_bk', 'admin']);

$user_id = (int)$_SESSION['user_id'];
$message = '';
$message_type = '';

// Helper to compress and save uploaded chat images
function compress_and_save_upload($file_post, $upload_dir) {
    if (!isset($file_post) || $file_post['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file_tmp = $file_post['tmp_name'];
    $info = @getimagesize($file_tmp);
    if ($info === false) {
        return null;
    }

    $mime = $info['mime'];
    if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'])) {
        return null;
    }

    if ($mime == 'image/jpeg' || $mime == 'image/jpg') {
        $image = @imagecreatefromjpeg($file_tmp);
    } elseif ($mime == 'image/png') {
        $image = @imagecreatefrompng($file_tmp);
    } elseif ($mime == 'image/gif') {
        $image = @imagecreatefromgif($file_tmp);
    } else {
        return null;
    }

    if (!$image) {
        return null;
    }

    $new_filename = uniqid('chat_', true) . '.jpg';
    $target_path = rtrim($upload_dir, '/') . '/' . $new_filename;

    // Save as compressed jpeg with 50% quality (compact size)
    $success = @imagejpeg($image, $target_path, 50);
    @imagedestroy($image);

    return $success ? $new_filename : null;
}

// Find teacher (guru) id
$q_guru = mysqli_query($conn, "SELECT id, foto FROM guru WHERE user_id = $user_id");
$g_data = mysqli_fetch_assoc($q_guru);
$guru_id = (int)($g_data['id'] ?? 0);

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle closing a consultation thread
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_consultation'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Token CSRF tidak valid.";
        $message_type = "error";
    } else {
        $konsultasi_id = (int)($_POST['konsultasi_id'] ?? 0);
        if ($konsultasi_id > 0) {
            // Verify ownership
            $check_owner = mysqli_query($conn, "SELECT id FROM konsultasi WHERE id = $konsultasi_id AND guru_id = $guru_id LIMIT 1");
            if (mysqli_num_rows($check_owner) > 0) {
                if (mysqli_query($conn, "UPDATE konsultasi SET status = 'closed' WHERE id = $konsultasi_id")) {
                    $message = "Konsultasi berhasil ditutup.";
                    $message_type = "success";
                    header("Location: konsultasi.php?id=" . $konsultasi_id);
                    exit();
                } else {
                    $message = "Gagal menutup konsultasi: " . mysqli_error($conn);
                    $message_type = "error";
                }
            } else {
                $message = "Akses ditolak.";
                $message_type = "error";
            }
        }
    }
}

// Handle sending reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Token CSRF tidak valid.";
        $message_type = "error";
    } else {
        $konsultasi_id = (int)($_POST['konsultasi_id'] ?? 0);
        $pesan = mysqli_real_escape_string($conn, trim($_POST['pesan'] ?? ''));

        if ($konsultasi_id <= 0 || (empty($pesan) && empty($_FILES['lampiran_foto']['name']))) {
            $message = "Pesan atau foto tidak boleh kosong.";
            $message_type = "error";
        } else {
            // Verify ownership
            $check_owner = mysqli_query($conn, "SELECT id, status FROM konsultasi WHERE id = $konsultasi_id AND guru_id = $guru_id LIMIT 1");
            if (mysqli_num_rows($check_owner) > 0) {
                $c_data = mysqli_fetch_assoc($check_owner);
                if ($c_data['status'] === 'closed') {
                    $message = "Konsultasi ini sudah ditutup.";
                    $message_type = "error";
                } else {
                    // Handle image upload and compression
                    $lampiran = compress_and_save_upload($_FILES['lampiran_foto'] ?? null, __DIR__ . '/../uploads/konsultasi');

                    $stmt_msg = mysqli_prepare($conn, "INSERT INTO konsultasi_pesan (konsultasi_id, pengirim_role, pesan, lampiran_foto) VALUES (?, 'guru', ?, ?)");
                    mysqli_stmt_bind_param($stmt_msg, "iss", $konsultasi_id, $pesan, $lampiran);
                    if (mysqli_stmt_execute($stmt_msg)) {
                        // Update updated_at in konsultasi
                        mysqli_query($conn, "UPDATE konsultasi SET updated_at = CURRENT_TIMESTAMP WHERE id = $konsultasi_id");
                        header("Location: konsultasi.php?id=" . $konsultasi_id);
                        exit();
                    } else {
                        $message = "Gagal mengirim balasan: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                    mysqli_stmt_close($stmt_msg);
                }
            } else {
                $message = "Akses ditolak.";
                $message_type = "error";
            }
        }
    }
}

// Fetch active threads for this BK teacher
$threads_query = "SELECT k.*, s.nama_siswa, s.foto as foto_siswa, kelas.nama_kelas,
                  (SELECT pesan FROM konsultasi_pesan kp WHERE kp.konsultasi_id = k.id ORDER BY kp.created_at DESC LIMIT 1) as last_msg,
                  (SELECT created_at FROM konsultasi_pesan kp WHERE kp.konsultasi_id = k.id ORDER BY kp.created_at DESC LIMIT 1) as last_msg_time
                  FROM konsultasi k
                  JOIN siswa s ON k.siswa_id = s.id
                  LEFT JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = $active_tahun_id
                  LEFT JOIN kelas ON sk.kelas_id = kelas.id
                  WHERE k.guru_id = $guru_id
                  ORDER BY k.updated_at DESC";
$threads_res = mysqli_query($conn, $threads_query);
$threads = [];
while ($row = mysqli_fetch_assoc($threads_res)) {
    $threads[] = $row;
}

// Fetch active thread details
$active_id = (int)($_GET['id'] ?? 0);
$active_thread = null;
$messages = [];

if ($active_id > 0) {
    $active_thread_query = "SELECT k.*, s.nama_siswa, s.foto as foto_siswa, s.no_telp as telp_siswa, kelas.nama_kelas
                            FROM konsultasi k
                            JOIN siswa s ON k.siswa_id = s.id
                            LEFT JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = $active_tahun_id
                            LEFT JOIN kelas ON sk.kelas_id = kelas.id
                            WHERE k.id = $active_id AND k.guru_id = $guru_id LIMIT 1";
    $active_thread_res = mysqli_query($conn, $active_thread_query);
    if ($row = mysqli_fetch_assoc($active_thread_res)) {
        $active_thread = $row;

        // Fetch messages
        $messages_query = "SELECT * FROM konsultasi_pesan WHERE konsultasi_id = $active_id ORDER BY created_at ASC";
        $messages_res = mysqli_query($conn, $messages_query);
        while ($msg = mysqli_fetch_assoc($messages_res)) {
            $messages[] = $msg;
        }
    }
}

$page_title = "Konsultasi BK Guru";
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
                <h1 class="text-2xl font-black italic text-slate-800 tracking-tight text-indigo-600">CHAT KONSULTASI SISWA</h1>
                <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mt-1">Konsol Bimbingan & Konsultasi (BK) Guru</p>
            </div>
            <a href="index.php" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-slate-600 transition-all">
                <i class="fa fa-times"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Sidebar: Threads List (4 cols) -->
            <div class="lg:col-span-4 space-y-4">
                <div class="lux-card bg-white p-6 border-none shadow-xl rounded-3xl space-y-4">
                    <h2 class="text-xs font-black text-slate-400 uppercase tracking-widest">Daftar Konsultasi Siswa</h2>
                    <div class="space-y-3 max-h-[550px] overflow-y-auto pr-1">
                        <?php if (empty($threads)): ?>
                            <p class="text-xs text-slate-400 italic text-center py-8">Belum ada konsultasi masuk.</p>
                        <?php else: ?>
                            <?php foreach ($threads as $t): ?>
                                <a href="konsultasi.php?id=<?= $t['id'] ?>"
                                   class="block p-4 rounded-2xl border transition-all flex items-center gap-4 <?= $t['id'] == $active_id ? 'border-indigo-500 bg-indigo-50/40 shadow-sm' : 'border-slate-100 hover:bg-slate-50' ?>">
                                    <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center shrink-0 overflow-hidden shadow-inner">
                                        <?php if (!empty($t['foto_siswa'])): ?>
                                            <img src="<?= BASE_URL ?>uploads/siswa/<?= $t['foto_siswa'] ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <i class="fa fa-user text-slate-400 text-lg"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2 mb-1">
                                            <h3 class="font-bold text-slate-800 text-xs truncate leading-none"><?= htmlspecialchars($t['nama_siswa']) ?></h3>
                                            <span class="text-[8px] font-bold px-1.5 py-0.5 rounded uppercase tracking-wider <?= $t['status'] === 'open' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>">
                                                <?= $t['status'] ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-500 border border-slate-200 text-[8px] font-black uppercase tracking-wider"><?= htmlspecialchars($t['nama_kelas'] ?? 'Tanpa Kelas') ?></span>
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
                    <div class="lux-card bg-white p-12 text-center border-none shadow-xl rounded-3xl h-full flex flex-col items-center justify-center min-h-[450px]">
                        <div class="w-20 h-20 bg-indigo-50 text-indigo-500 rounded-full flex items-center justify-center text-4xl mb-6 shadow-inner animate-pulse">
                            <i class="fa fa-comments"></i>
                        </div>
                        <h2 class="text-xl font-black text-slate-800 italic">Pusat Konsultasi Guru BK</h2>
                        <p class="text-slate-500 text-xs mt-3 leading-relaxed max-w-sm mx-auto">
                            Silakan pilih obrolan siswa dari daftar di sebelah kiri untuk melihat pesan dan mulai memberikan bimbingan konseling secara terarah.
                        </p>
                    </div>
                <?php else: ?>
                    <!-- Active Chat Area -->
                    <div class="lux-card bg-white border-none shadow-xl rounded-3xl overflow-hidden flex flex-col h-full min-h-[500px]">
                        <!-- Chat Header -->
                        <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center shrink-0 overflow-hidden shadow-inner">
                                    <?php if (!empty($active_thread['foto_siswa'])): ?>
                                        <img src="<?= BASE_URL ?>uploads/siswa/<?= $active_thread['foto_siswa'] ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <i class="fa fa-user text-slate-400 text-lg"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h3 class="font-black text-slate-800 italic text-sm"><?= htmlspecialchars($active_thread['nama_siswa']) ?> <span class="text-xs font-bold text-slate-400">(<?= htmlspecialchars($active_thread['nama_kelas'] ?? 'Tanpa Kelas') ?>)</span></h3>
                                    <p class="text-[9px] font-black text-indigo-500 uppercase tracking-widest mt-0.5">Topik: <?= htmlspecialchars($active_thread['subjek']) ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if (!empty($active_thread['telp_siswa'])): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $active_thread['telp_siswa']) ?>" target="_blank"
                                       class="w-10 h-10 flex items-center justify-center bg-emerald-50 text-emerald-600 border border-emerald-100 hover:bg-emerald-100 rounded-xl transition-all shadow-sm"
                                       title="Chat via WhatsApp">
                                        <i class="fab fa-whatsapp text-lg"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if ($active_thread['status'] === 'open'): ?>
                                    <form action="" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menyelesaikan dan menutup konsultasi ini?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="konsultasi_id" value="<?= $active_id ?>">
                                        <button type="submit" name="close_consultation"
                                                class="px-3.5 py-2 bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white rounded-xl text-xs font-bold transition-all border border-rose-100 shadow-sm">
                                            Selesaikan Sesi
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="px-3 py-1 text-[10px] font-black uppercase tracking-wider rounded-full bg-slate-200 text-slate-600">
                                        SELESAI (CLOSED)
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Chat Messages Container -->
                        <div class="flex-1 p-6 overflow-y-auto max-h-[360px] min-h-[250px] space-y-4 bg-slate-50/30" id="chatContainer">
                            <?php if (empty($messages)): ?>
                                <p class="text-xs text-slate-400 italic text-center py-12">Belum ada pesan.</p>
                            <?php else: ?>
                                <?php foreach ($messages as $m): ?>
                                    <?php $is_me = ($m['pengirim_role'] === 'guru'); ?>
                                    <div class="flex <?= $is_me ? 'justify-end' : 'justify-start' ?>">
                                        <div class="max-w-[75%] rounded-3xl px-5 py-3.5 shadow-sm text-sm <?= $is_me ? 'bg-indigo-600 text-white rounded-br-none' : 'bg-white border border-slate-100 text-slate-800 rounded-bl-none' ?>">
                                            <?php if (!empty($m['pesan'])): ?>
                                                <p class="leading-relaxed font-medium"><?= nl2br(htmlspecialchars($m['pesan'])) ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($m['lampiran_foto'])): ?>
                                                <div class="mt-2">
                                                    <img src="<?= BASE_URL ?>uploads/konsultasi/<?= $m['lampiran_foto'] ?>" class="max-w-full sm:max-w-xs rounded-2xl shadow-sm border border-slate-100 cursor-pointer hover:opacity-95 transition-opacity" onclick="window.open(this.src)">
                                                </div>
                                            <?php endif; ?>
                                            <div class="text-[9px] mt-2 flex items-center justify-between gap-4 <?= $is_me ? 'text-indigo-200' : 'text-slate-400' ?>">
                                                <span class="font-bold uppercase tracking-wider"><?= $is_me ? 'Anda' : 'Siswa' ?></span>
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
                                <div id="fileNameIndicator" class="hidden text-[10px] font-bold text-indigo-600 bg-indigo-50 border border-indigo-100 rounded-full px-3 py-1 inline-block mb-3 animate-pulse"></div>
                                <form action="" method="POST" enctype="multipart/form-data" class="flex gap-4 items-center">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="konsultasi_id" value="<?= $active_id ?>">
                                    <input type="hidden" name="send_reply" value="1">

                                    <!-- Photo Upload Input -->
                                    <input type="file" name="lampiran_foto" accept="image/*" class="hidden" id="photoUploadInput" onchange="document.getElementById('fileNameIndicator').innerText = 'Foto terpilih: ' + this.files[0].name; document.getElementById('fileNameIndicator').classList.remove('hidden');">
                                    <label for="photoUploadInput" class="w-12 h-12 rounded-2xl bg-slate-50 border border-slate-200 text-slate-400 hover:text-indigo-600 flex items-center justify-center text-lg cursor-pointer transition-all active:scale-95" title="Kirim Foto">
                                        <i class="fa fa-camera"></i>
                                    </label>

                                    <textarea name="pesan" rows="1"
                                              placeholder="Tulis balasan bimbingan Anda di sini..."
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
                                <i class="fa fa-lock mr-1"></i> Sesi konsultasi ini telah ditutup/diselesaikan.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Scroll chat to bottom
    const chatContainer = document.getElementById('chatContainer');
    if (chatContainer) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

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
