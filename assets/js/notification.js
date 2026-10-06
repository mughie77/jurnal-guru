// System Notification Handler for Teacher Schedule Alerts (Guru Only)
(function () {
    if (typeof window.USER_ROLE === 'undefined' || window.USER_ROLE !== 'guru') {
        return;
    }

    if (!('Notification' in window)) {
        return;
    }

    const baseUrl = window.BASE_URL || '/';

    // Register Service Worker for Guru if supported
    let swReg = null;
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(baseUrl + 'sw.js')
            .then((reg) => {
                swReg = reg;
            })
            .catch((err) => {
                console.log('SW registration error:', err);
            });
    }

    // Request notification permissions
    function requestPermission() {
        if (Notification.permission === 'default') {
            try {
                Notification.requestPermission().then((perm) => {
                    if (perm === 'granted') {
                        checkTeacherSchedules();
                    }
                }).catch(() => {
                    Notification.requestPermission();
                });
            } catch (e) {
                Notification.requestPermission();
            }
        }
    }

    // Request on load and on first click
    if (Notification.permission === 'default') {
        setTimeout(requestPermission, 1500);
        document.addEventListener('click', function askNotifOnce() {
            requestPermission();
            document.removeEventListener('click', askNotifOnce);
        }, { once: true });
    }

    // Fire notification helper
    function sendScheduleNotification(item, todayDate) {
        const title = `Waktunya Mengajar: ${item.nama_kelas}`;
        const targetUrl = baseUrl + 'guru/isi_absensi.php';
        const options = {
            body: `Mata Pelajaran: ${item.nama_mapel} (Jam Ke: ${item.jam_ke}). Klik untuk mengisi jurnal kelas.`,
            icon: baseUrl + 'assets/images/logo.png',
            badge: baseUrl + 'assets/images/logo.png',
            tag: `jadwal-${item.id}`,
            data: { url: targetUrl }
        };

        if (swReg && swReg.showNotification && swReg.active) {
            swReg.showNotification(title, options).catch(() => {
                fallbackNotification(title, options, targetUrl);
            });
        } else {
            fallbackNotification(title, options, targetUrl);
        }
    }

    function fallbackNotification(title, options, targetUrl) {
        try {
            const notif = new Notification(title, options);
            notif.onclick = function () {
                window.focus();
                window.location.href = targetUrl;
            };
        } catch (e) {
            console.log("Browser notification error:", e);
        }
    }

    // Check teacher schedules
    function checkTeacherSchedules() {
        if (Notification.permission !== 'granted') {
            return;
        }

        fetch(baseUrl + 'api/get_jadwal_guru_today.php')
            .then(res => res.json())
            .then(data => {
                if (!data.success || !data.schedules || data.schedules.length === 0) {
                    return;
                }

                const now = new Date();
                const curMinTotal = now.getHours() * 60 + now.getMinutes();
                const todayDate = data.today_date;

                data.schedules.forEach(item => {
                    if (item.is_filled) return;

                    const notifKey = `notif_jadwal_${todayDate}_${item.id}`;
                    if (localStorage.getItem(notifKey)) return;

                    let shouldNotify = false;

                    if (item.jam_mulai) {
                        const startTimeParts = item.jam_mulai.split(':');
                        const sH = parseInt(startTimeParts[0], 10) || 0;
                        const sM = parseInt(startTimeParts[1], 10) || 0;
                        const startMinTotal = sH * 60 + sM;

                        // Notify if current time has reached or passed start time
                        if (curMinTotal >= startMinTotal) {
                            shouldNotify = true;
                        }
                    } else {
                        // If no start time specified, notify once for today's unfilled schedule
                        shouldNotify = true;
                    }

                    if (shouldNotify) {
                        sendScheduleNotification(item, todayDate);
                        localStorage.setItem(notifKey, '1');
                    }
                });
            })
            .catch(err => console.log('Schedule check error:', err));
    }

    // Initial check and periodic check every 30 seconds
    setTimeout(checkTeacherSchedules, 2000);
    setInterval(checkTeacherSchedules, 30000);
})();
