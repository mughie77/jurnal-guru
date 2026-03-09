                </div> <!-- .container-fluid or main end -->
            </main>
        </div>
    </div>

    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'guru'): ?>
        <!-- Persistent Footer Menu for Guru -->
        <div class="fixed bottom-0 left-0 right-0 z-[100] lg:px-8 pb-4">
            <div class="max-w-xl mx-auto glass shadow-2xl rounded-2xl border border-slate-200/50 p-2 flex items-center justify-around translate-y-0 transition-transform duration-500">
                <a href="<?= BASE_URL ?>guru/index.php" class="flex flex-col items-center p-2 rounded-xl transition-all <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'text-indigo-600' : 'text-slate-400' ?>">
                    <i class="fa fa-home text-lg"></i>
                    <span class="text-[8px] font-black uppercase mt-1">Beranda</span>
                </a>
                <a href="<?= BASE_URL ?>guru/isi_absensi.php" class="flex flex-col items-center p-2 rounded-xl transition-all <?= basename($_SERVER['PHP_SELF']) == 'isi_absensi.php' ? 'text-indigo-600' : 'text-slate-400' ?>">
                    <i class="fa fa-user-check text-lg"></i>
                    <span class="text-[8px] font-black uppercase mt-1">Absensi</span>
                </a>
                <a href="<?= BASE_URL ?>guru/isi_jurnal.php" class="flex flex-col items-center p-2 rounded-xl transition-all <?= basename($_SERVER['PHP_SELF']) == 'isi_jurnal.php' ? 'text-indigo-600' : 'text-slate-400' ?>">
                    <div class="w-10 h-10 bg-indigo-600 rounded-full flex items-center justify-center text-white shadow-lg shadow-indigo-200 -mt-8 border-4 border-slate-50">
                        <i class="fa fa-plus"></i>
                    </div>
                    <span class="text-[8px] font-black uppercase mt-1 text-indigo-600">Jurnal</span>
                </a>
                <a href="<?= BASE_URL ?>guru/riwayat.php" class="flex flex-col items-center p-2 rounded-xl transition-all <?= basename($_SERVER['PHP_SELF']) == 'riwayat.php' ? 'text-indigo-600' : 'text-slate-400' ?>">
                    <i class="fa fa-history text-lg"></i>
                    <span class="text-[8px] font-black uppercase mt-1">Riwayat</span>
                </a>
                <a href="<?= BASE_URL ?>guru/perangkat.php" class="flex flex-col items-center p-2 rounded-xl transition-all <?= basename($_SERVER['PHP_SELF']) == 'perangkat.php' ? 'text-indigo-600' : 'text-slate-400' ?>">
                    <i class="fa fa-folder text-lg"></i>
                    <span class="text-[8px] font-black uppercase mt-1">Perangkat</span>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
            });
        }

        // Close sidebar on mobile when clicking outside
        document.addEventListener('mousedown', (e) => {
            if (window.innerWidth < 1024 && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.add('-translate-x-full');
            }
        });
    </script>
</body>
</html>
