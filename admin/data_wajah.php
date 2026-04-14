<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pagination.php';

authorize_role(['admin']);

$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Handle Delete Face Data
if (isset($_POST['delete_face'])) {
    $id = (int)$_POST['id'];
    // Get filename first to delete file
    $res = mysqli_query($conn, "SELECT face_image FROM siswa WHERE id = $id");
    if ($row = mysqli_fetch_assoc($res)) {
        $file = __DIR__ . '/../uploads/siswa/face/' . $row['face_image'];
        if (file_exists($file)) unlink($file);

        mysqli_query($conn, "UPDATE siswa SET face_image = NULL WHERE id = $id");
        header("Location: data_wajah.php?status=success");
        exit();
    }
}

$where = " WHERE face_image IS NOT NULL";
if ($search) {
    $where .= " AND (nama_siswa LIKE '%$search%' OR nis LIKE '%$search%')";
}

$query_base = "siswa";
$pagin = get_pagination_data($conn, $query_base, 15, $where);

$query = "SELECT id, nis, nama_siswa, face_image FROM siswa $where ORDER BY nama_siswa ASC LIMIT {$pagin['limit']} OFFSET {$pagin['offset']}";
$list = mysqli_query($conn, $query);

$page_title = "Manajemen Data Wajah";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 lg:p-8">
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black italic text-slate-800 uppercase tracking-widest">Data Wajah Terdaftar</h1>
            <p class="text-slate-400 font-bold text-[10px] uppercase tracking-[0.3em] mt-1">Verifikasi Face Recognition</p>
        </div>

        <form action="" method="GET" class="relative group max-w-xs w-full">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari siswa atau NIS..."
                class="w-full pl-10 pr-4 py-3 bg-white rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 transition-all text-xs font-bold shadow-sm">
            <i class="fa fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-indigo-500 transition-colors"></i>
        </form>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6 mb-8">
        <?php if (mysqli_num_rows($list) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($list)): ?>
                <div class="lux-card p-5 bg-white border-none shadow-xl flex flex-col items-center text-center group hover:scale-[1.02] transition-all">
                    <div class="w-full aspect-square rounded-[32px] overflow-hidden bg-slate-50 border-4 border-white shadow-lg mb-4">
                        <img src="<?= BASE_URL ?>uploads/siswa/face/<?= $row['face_image'] ?>" class="w-full h-full object-cover">
                    </div>
                    <h3 class="font-black text-slate-800 text-sm italic uppercase tracking-tighter truncate w-full"><?= htmlspecialchars($row['nama_siswa']) ?></h3>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1"><?= $row['nis'] ?></p>

                    <div class="mt-5 w-full pt-4 border-t border-slate-50 flex gap-2">
                        <form action="" method="POST" class="w-full" onsubmit="return confirm('Hapus data wajah ini?')">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="submit" name="delete_face" class="w-full py-2 bg-rose-50 text-rose-500 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-500 hover:text-white transition-all">Hapus</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-span-full py-20 text-center lux-card bg-white/50 border-dashed border-2 border-slate-200 shadow-none rounded-[40px]">
                <i class="fa fa-face-smile text-4xl text-slate-200 mb-4"></i>
                <h3 class="text-slate-800 font-black italic text-lg uppercase tracking-widest">Tidak Ada Data</h3>
                <p class="text-slate-400 font-bold italic tracking-widest text-[10px] uppercase mt-2">Belum ada siswa yang mendaftarkan wajah</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="no-print">
        <?= render_pagination($pagin['page'], $pagin['total_pages'], $_GET) ?>
    </div>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: 'Data wajah telah dihapus.',
        timer: 1500,
        showConfirmButton: false
    });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
