<?php
// admin/navbar.php
$admin = current_admin();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="admin-mobile-header">
    <button class="admin-menu-toggle" id="adminMenuToggle" type="button" aria-controls="adminSidebar" aria-expanded="false" aria-label="Buka menu navigasi">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>
    </button>
    <strong>SIGMA Admin</strong>
</div>
<button class="admin-sidebar-backdrop" id="adminSidebarBackdrop" type="button" aria-label="Tutup menu navigasi" hidden></button>
<nav class="admin-nav" id="adminSidebar" aria-label="Navigasi admin">
    <div class="admin-nav-inner">
        <a href="index.php" class="admin-brand">
            <img src="../embed/logo-barantin.png" alt="Logo Barantin">
            <div class="admin-brand-text">
                <strong>Badan Karantina Indonesia</strong>
                <span>Balai Karantina Kalimantan Selatan</span>
            </div>
        </a>
        <button class="admin-sidebar-close" id="adminSidebarClose" type="button" aria-label="Tutup menu navigasi">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m18 6-12 12M6 6l12 12"></path></svg>
        </button>

        <div class="admin-nav-links">
            <a href="index.php" class="admin-nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">Dashboard</a>

            <div class="admin-nav-dropdown <?= in_array($currentPage, ['upload_hewan.php', 'upload_ikan.php', 'upload_tumbuhan.php'], true) ? 'is-open' : '' ?>">
                <button class="admin-nav-link admin-nav-dropdown-toggle <?= in_array($currentPage, ['upload_hewan.php', 'upload_ikan.php', 'upload_tumbuhan.php'], true) ? 'active' : '' ?>" type="button" aria-expanded="<?= in_array($currentPage, ['upload_hewan.php', 'upload_ikan.php', 'upload_tumbuhan.php'], true) ? 'true' : 'false' ?>">
                    <span>Upload Excel</span>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                </button>
                <div class="admin-nav-dropdown-panel">
                    <a href="upload_hewan.php" class="admin-nav-dropdown-item <?= $currentPage === 'upload_hewan.php' ? 'active' : '' ?>"><span class="dot dot-hewan"></span> Hewan (KH)</a>
                    <a href="upload_ikan.php" class="admin-nav-dropdown-item <?= $currentPage === 'upload_ikan.php' ? 'active' : '' ?>"><span class="dot dot-ikan"></span> Ikan (KI)</a>
                    <a href="upload_tumbuhan.php" class="admin-nav-dropdown-item <?= $currentPage === 'upload_tumbuhan.php' ? 'active' : '' ?>"><span class="dot dot-tumbuhan"></span> Tumbuhan (KT)</a>
                </div>
            </div>
            <a href="kelola_data.php" class="admin-nav-link <?= $currentPage === 'kelola_data.php' ? 'active' : '' ?>">Kelola & Publish Data</a>
            <?php if (is_admin_role()): ?>
                <a href="users.php" class="admin-nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>">Kelola Pengguna</a>
                <a href="activity_log.php" class="admin-nav-link <?= $currentPage === 'activity_log.php' ? 'active' : '' ?>">Log Aktivitas</a>
            <?php endif; ?>
            <a href="../index.php" target="_blank" rel="noopener noreferrer" class="admin-nav-link accent">Lihat Board Publik</a>
        </div>

        <div class="admin-nav-user">
            <span>Masuk sebagai <strong><?= htmlspecialchars($admin['nama']) ?></strong></span>
            <form method="POST" action="logout.php" data-confirm="Yakin ingin logout?" style="display: inline;">
                <?= csrf_field() ?>
                <button type="submit" class="admin-logout">Logout</button>
            </form>
        </div>
    </div>
</nav>

<script>
(function() {
    const sidebar = document.getElementById('adminSidebar');
    const toggle = document.getElementById('adminMenuToggle');
    const close = document.getElementById('adminSidebarClose');
    const backdrop = document.getElementById('adminSidebarBackdrop');
    const uploadGroup = document.querySelector('.admin-nav-dropdown');
    const uploadToggle = document.querySelector('.admin-nav-dropdown-toggle');
    const scrollKey = 'adminFilterScroll';

    window.navigateAdminFilter = function(url) {
        try {
            sessionStorage.setItem(scrollKey, JSON.stringify({
                path: window.location.pathname,
                y: window.scrollY,
                savedAt: Date.now()
            }));
        } catch (error) {
            console.warn('Posisi halaman tidak dapat disimpan.', error);
        }
        window.location.href = url;
    };

    window.addEventListener('pageshow', () => {
        try {
            const saved = JSON.parse(sessionStorage.getItem(scrollKey) || 'null');
            sessionStorage.removeItem(scrollKey);
            if (saved && saved.path === window.location.pathname && Date.now() - saved.savedAt < 15000) {
                requestAnimationFrame(() => window.scrollTo(0, saved.y));
            }
        } catch (error) {
            console.warn('Posisi halaman sebelumnya tidak dapat dipulihkan.', error);
        }
    });

    function setSidebarOpen(isOpen) {
        sidebar.classList.toggle('is-open', isOpen);
        document.body.classList.toggle('admin-sidebar-open', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi');
        backdrop.hidden = !isOpen;
        if (isOpen) close.focus();
        else toggle.focus();
    }

    toggle.addEventListener('click', () => setSidebarOpen(true));
    close.addEventListener('click', () => setSidebarOpen(false));
    backdrop.addEventListener('click', () => setSidebarOpen(false));
    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 900px)').matches) setSidebarOpen(false);
        });
    });
    uploadToggle.addEventListener('click', () => {
        const isOpen = uploadGroup.classList.toggle('is-open');
        uploadToggle.setAttribute('aria-expanded', String(isOpen));
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) setSidebarOpen(false);
    });
})();
</script>

<div class="admin-confirm-backdrop" id="adminConfirmModal" hidden>
    <div class="admin-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="adminConfirmTitle" aria-describedby="adminConfirmMessage">
        <div class="admin-confirm-icon" aria-hidden="true">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9L2.8 17a2 2 0 001.7 3h15a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>
        </div>
        <div class="admin-confirm-content">
            <h2 id="adminConfirmTitle">Konfirmasi</h2>
            <p id="adminConfirmMessage"></p>
        </div>
        <div class="admin-confirm-actions">
            <button type="button" class="btn btn-secondary" id="adminConfirmCancel">Batal</button>
            <button type="button" class="btn btn-primary" id="adminConfirmAccept">Lanjutkan</button>
        </div>
    </div>
</div>

<script>
(function() {
    const modal = document.getElementById('adminConfirmModal');
    const message = document.getElementById('adminConfirmMessage');
    const cancelButton = document.getElementById('adminConfirmCancel');
    const acceptButton = document.getElementById('adminConfirmAccept');
    let pendingAction = null;

    function closeModal() {
        modal.hidden = true;
        pendingAction = null;
    }

    function openModal(text, action, tone) {
        message.textContent = text;
        acceptButton.classList.toggle('btn-danger', tone === 'danger');
        acceptButton.classList.toggle('btn-primary', tone !== 'danger');
        pendingAction = action;
        modal.hidden = false;
        acceptButton.focus();
    }

    document.addEventListener('click', function(event) {
        const trigger = event.target.closest('[data-confirm]');
        if (!trigger) return;

        // Form confirmation is handled by the submit listener below.
        if (trigger.matches('form')) return;

        event.preventDefault();
        openModal(trigger.dataset.confirm, function() {
            if (trigger.matches('a')) {
                window.location.href = trigger.href;
            } else if (trigger.matches('form')) {
                trigger.dataset.confirmed = 'true';
                trigger.requestSubmit();
            } else if (trigger.form) {
                trigger.form.requestSubmit(trigger);
            }
        }, trigger.dataset.confirmTone || 'primary');
    });

    document.addEventListener('submit', function(event) {
        const form = event.target.closest('form[data-confirm]');
        if (!form) return;

        if (form.dataset.confirmed === 'true') {
            delete form.dataset.confirmed;
            return;
        }

        event.preventDefault();
        openModal(form.dataset.confirm, function() {
            form.dataset.confirmed = 'true';
            form.requestSubmit();
        }, form.dataset.confirmTone || 'primary');
    });

    cancelButton.addEventListener('click', closeModal);
    acceptButton.addEventListener('click', function() {
        const action = pendingAction;
        closeModal();
        if (action) action();
    });

    modal.addEventListener('click', function(event) {
        if (event.target === modal) closeModal();
    });

    document.addEventListener('keydown', function(event) {
        if (!modal.hidden && event.key === 'Escape') closeModal();
    });
})();
</script>
