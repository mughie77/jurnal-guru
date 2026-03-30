                </div> <!-- .container-fluid or main end -->
            </main>
        </div>
    </div>

    <?php if (isset($_SESSION['role']) && ($_SESSION['role'] == 'guru' || $_SESSION['role'] == 'siswa')): ?>
        <!-- Persistent Footer Menu -->
        <div class="fixed bottom-0 left-0 right-0 z-[100] lg:px-8 pb-4">
            <div class="max-w-xl mx-auto glass shadow-2xl rounded-2xl border border-slate-200/50 p-2 flex items-center justify-around translate-y-0 transition-transform duration-500">

                <?php if ($_SESSION['role'] == 'guru'): ?>
                    <a href="<?= BASE_URL ?>guru/index.php" class="flex flex-col items-center p-2 rounded-xl transition-all <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'text-indigo-600' : 'text-slate-400' ?>">
                        <i class="fa fa-home text-lg"></i>
                        <span class="text-[8px] font-black uppercase mt-1">Beranda</span>
                    </a>
                    <a href="<?= BASE_URL ?>guru/riwayat.php" class="flex flex-col items-center p-2 rounded-xl transition-all <?= basename($_SERVER['PHP_SELF']) == 'riwayat.php' ? 'text-indigo-600' : 'text-slate-400' ?>">
                        <i class="fa fa-history text-lg"></i>
                        <span class="text-[8px] font-black uppercase mt-1">Riwayat Jurnal</span>
                    </a>
                    <a href="<?= BASE_URL ?>guru/isi_absensi.php" class="flex flex-col items-center p-2 rounded-xl transition-all <?= in_array(basename($_SERVER['PHP_SELF']), ['isi_absensi.php', 'isi_jurnal.php']) ? 'text-indigo-600' : 'text-slate-400' ?>">
                        <div class="w-10 h-10 bg-indigo-600 rounded-full flex items-center justify-center text-white shadow-lg shadow-indigo-200 -mt-8 border-4 border-slate-50 transition-transform active:scale-90">
                            <i class="fa fa-plus"></i>
                        </div>
                        <span class="text-[8px] font-black uppercase mt-1 text-indigo-600">Isi Jurnal</span>
                    </a>
                    <a href="<?= BASE_URL ?>guru/perangkat.php" class="flex flex-col items-center p-2 rounded-xl transition-all <?= basename($_SERVER['PHP_SELF']) == 'perangkat.php' ? 'text-indigo-600' : 'text-slate-400' ?>">
                        <i class="fa fa-folder text-lg"></i>
                        <span class="text-[8px] font-black uppercase mt-1">Perangkat</span>
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>siswa/index.php" class="flex flex-col items-center p-2 rounded-xl transition-all <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'text-indigo-600' : 'text-slate-400' ?>">
                        <i class="fa fa-home text-lg"></i>
                        <span class="text-[8px] font-black uppercase mt-1">Beranda</span>
                    </a>
                    <a href="<?= BASE_URL ?>siswa/barcode.php" class="flex flex-col items-center p-2 rounded-xl transition-all <?= basename($_SERVER['PHP_SELF']) == 'barcode.php' ? 'text-indigo-600' : 'text-slate-400' ?>">
                        <i class="fa fa-qrcode text-lg"></i>
                        <span class="text-[8px] font-black uppercase mt-1">Barcode</span>
                    </a>
                    <a href="<?= BASE_URL ?>siswa/kartu.php" class="flex flex-col items-center p-2 rounded-xl transition-all <?= basename($_SERVER['PHP_SELF']) == 'kartu.php' ? 'text-indigo-600' : 'text-slate-400' ?>">
                        <div class="w-10 h-10 bg-indigo-600 rounded-full flex items-center justify-center text-white shadow-lg shadow-indigo-200 -mt-8 border-4 border-slate-50 transition-transform active:scale-90">
                            <i class="fa fa-id-card"></i>
                        </div>
                        <span class="text-[8px] font-black uppercase mt-1 text-indigo-600">Kartu Pelajar</span>
                    </a>
                    <div class="flex flex-col items-center p-2 text-slate-300 pointer-events-none opacity-20">
                        <i class="fa fa-chart-bar text-lg"></i>
                        <span class="text-[8px] font-black uppercase mt-1">Nilai</span>
                    </div>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>logout.php" class="flex flex-col items-center p-2 rounded-xl transition-all text-rose-400 hover:text-rose-600">
                    <i class="fa fa-sign-out-alt text-lg"></i>
                    <span class="text-[8px] font-black uppercase mt-1">Keluar</span>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- App Scripts -->
    <script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
