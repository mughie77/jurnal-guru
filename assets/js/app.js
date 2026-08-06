document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
        });
    }

    // Close sidebar on mobile when clicking outside
    document.addEventListener('mousedown', (e) => {
        if (window.innerWidth < 1024 && sidebar && !sidebar.contains(e.target) && sidebarToggle && !sidebarToggle.contains(e.target)) {
            sidebar.classList.add('-translate-x-full');
        }
    });

    // Restore and save sidebar scroll position
    const sidebarNav = document.getElementById('sidebar-nav');
    if (sidebarNav) {
        const savedScrollTop = localStorage.getItem('sidebar-scroll-position');
        if (savedScrollTop !== null) {
            sidebarNav.scrollTop = parseInt(savedScrollTop, 10);
        }

        // Save scroll position when a link inside sidebar is clicked
        sidebarNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                localStorage.setItem('sidebar-scroll-position', sidebarNav.scrollTop);
            });
        });

        // Also save position on unload
        window.addEventListener('beforeunload', () => {
            localStorage.setItem('sidebar-scroll-position', sidebarNav.scrollTop);
        });
    }
});

// Shared Modal Logic
const overlay = document.getElementById('modalOverlay');

function openModal(id) {
    const m = document.getElementById(id);
    if (!m || !overlay) return;
    overlay.classList.remove('hidden');
    m.classList.remove('hidden');
    setTimeout(() => {
        overlay.classList.add('opacity-100');
        m.classList.add('opacity-100', 'scale-100');
    }, 10);
}

function closeModal(id) {
    const m = document.getElementById(id);
    if (!m || !overlay) return;
    overlay.classList.remove('opacity-100');
    m.classList.remove('opacity-100', 'scale-100');
    setTimeout(() => {
        overlay.classList.add('hidden');
        m.classList.add('hidden');
    }, 300);
}

function closeAllModals() {
    document.querySelectorAll('.modal-content').forEach(m => {
        if (!m.classList.contains('hidden')) closeModal(m.id);
    });
}
