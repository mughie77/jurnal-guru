// System Notification Handler for Teacher Schedule Alerts (Guru Only)
(function () {
    if (typeof window.USER_ROLE === 'undefined' || window.USER_ROLE !== 'guru') {
        return;
    }

    if (!('Notification' in window) || !('serviceWorker' in navigator)) {
        return;
    }

    const baseUrl = window.BASE_URL || '/';

    // Register Service Worker for Guru
    let swReg = null;
    navigator.serviceWorker.register(baseUrl + 'sw.js')
        .then((reg) => {
            swReg = reg;
        })
        .catch((err) => {
            console.log('SW registration skipped or failed:', err);
        });

    // Request notification permissions
    function requestPermission() {
        if (Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }

    // Trigger Permission Prompt on user interaction
    document.addEventListener('click', function askNotifOnce() {
        requestPermission();
        document.removeEventListener('click', askNotifOnce);
    }, { once: true });

    // Check teacher schedules
    function checkTeacherSchedules() {
        fetch(baseUrl + 'api/get_jadwal_guru_today.php')
            .then(res => res.json())
            .then(data => {
                if (!data.success || !data.schedules || data.schedules.length === 0) {
                    return;
                }

                const now = new Date();
                const curHours = String(now.getHours()).padStart(2, '0');
                const curMinutes = String(now.getMinutes()).padStart(2, '0');
                const curTimeStr = `${curHours}:${curMinutes}`;
                const todayDate = data.today_date;

                data.schedules.forEach(item => {
                    if (item.is_filled || !item.jam_mulai) return;

                    const startTimeParts = item.jam_mulai.split(':');
                    const startTimeStr = `${startTimeParts[0].padStart(2, '0')}:${startTimeParts[1].padStart(2, '0')}`;

                    const [sH, sM] = startTimeStr.split(':').map(Number);
                    const startMinTotal = sH * 60 + sM;
                    const curMinTotal = now.getHours() * 60 + now.getMinutes();
                    const diffMin = curMinTotal - startMinTotal;

                    const notifKey = `notif_jadwal_${todayDate}_${item.id}`;

                    if (diffMin >= 0 && diffMin <= 5 && !localStorage.getItem(notifKey)) {
                        if (Notification.permission === 'granted') {
                            const title = `Waktunya Mengajar: ${item.nama_kelas}`;
                            const options = {
                                body: `Mata Pelajaran: ${item.nama_mapel} (Jam Ke: ${item.jam_ke}). Klik untuk mengisi jurnal kelas.`,
                                icon: baseUrl + 'assets/images/logo.png',
                                badge: baseUrl + 'assets/images/logo.png',
                                tag: `jadwal-${item.id}`,
                                data: {
                                    url: baseUrl + 'guru/isi_absensi.php'
                                }
                            };

                            if (swReg && swReg.showNotification) {
                                swReg.showNotification(title, options);
                            } else {
                                new Notification(title, options);
                            }

                            localStorage.setItem(notifKey, '1');
                        }
                    }
                });
            })
            .catch(err => console.log('Schedule check error:', err));
    }

    setTimeout(checkTeacherSchedules, 2000);
    setInterval(checkTeacherSchedules, 45000);
})();
