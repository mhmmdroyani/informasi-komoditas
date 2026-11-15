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
            <a href="index.php" class="admin-nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">
                <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="5" rx="1.5"/><rect x="13" y="10" width="8" height="11" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/></svg>
                <span>Dashboard</span>
            </a>

            <div class="admin-nav-dropdown <?= in_array($currentPage, ['upload_hewan.php', 'upload_ikan.php', 'upload_tumbuhan.php'], true) ? 'is-open' : '' ?>">
                <button class="admin-nav-link admin-nav-dropdown-toggle <?= in_array($currentPage, ['upload_hewan.php', 'upload_ikan.php', 'upload_tumbuhan.php'], true) ? 'active' : '' ?>" type="button" aria-expanded="<?= in_array($currentPage, ['upload_hewan.php', 'upload_ikan.php', 'upload_tumbuhan.php'], true) ? 'true' : 'false' ?>">
                    <span class="admin-nav-label">
                        <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4m0 0L7 9m5-5 5 5"/><path d="M5 14v5a2 2 0 002 2h10a2 2 0 002-2v-5"/></svg>
                        <span>Upload Excel</span>
                    </span>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                </button>
                <div class="admin-nav-dropdown-panel">
                    <a href="upload_hewan.php" class="admin-nav-dropdown-item <?= $currentPage === 'upload_hewan.php' ? 'active' : '' ?>">
                        <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 10a2 2 0 100-4 2 2 0 000 4zm10 0a2 2 0 100-4 2 2 0 000 4zM4 16a2 2 0 100-4 2 2 0 000 4zm16 0a2 2 0 100-4 2 2 0 000 4z"/><path d="M12 12c-3 0-5 2-5 4.5a2.5 2.5 0 002.5 2.5h5a2.5 2.5 0 002.5-2.5C17 14 15 12 12 12z"/></svg>
                        <span>Hewan (KH)</span>
                    </a>
                    <a href="upload_ikan.php" class="admin-nav-dropdown-item <?= $currentPage === 'upload_ikan.php' ? 'active' : '' ?>">
                        <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12s3.5-5 9-5 9 5 9 5-3.5 5-9 5-9-5-9-5z"/><circle cx="12" cy="12" r="2"/><path d="M3 12l-2-3v6l2-3z"/></svg>
                        <span>Ikan (KI)</span>
                    </a>
                    <a href="upload_tumbuhan.php" class="admin-nav-dropdown-item <?= $currentPage === 'upload_tumbuhan.php' ? 'active' : '' ?>">
                        <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 4c-8 0-14 3-14 9a5 5 0 005 5c6 0 9-6 9-14z"/><path d="M3 21c3-6 7-9 13-13"/></svg>
                        <span>Tumbuhan (KT)</span>
                    </a>
                </div>
            </div>
            <a href="kelola_data.php" class="admin-nav-link <?= $currentPage === 'kelola_data.php' ? 'active' : '' ?>">
                <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 4h11a2 2 0 012 2v13a2 2 0 01-2 2H8a2 2 0 01-2-2V6a2 2 0 012-2z"/><path d="M6 8H4a2 2 0 00-2 2v10a2 2 0 002 2h13M10 9h7m-7 4h7m-7 4h4"/></svg>
                <span>Kelola Data</span>
            </a>
            <?php if (is_admin_role()): ?>
                <a href="users.php" class="admin-nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>">
                    <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0113 0M16 5a3.5 3.5 0 010 7m2 2a5 5 0 013.5 5"/></svg>
                    <span>Kelola Pengguna</span>
                </a>
                <a href="activity_log.php" class="admin-nav-link <?= $currentPage === 'activity_log.php' ? 'active' : '' ?>">
                    <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    <span>Log Aktivitas</span>
                </a>
            <?php endif; ?>
            <a href="../index.php" target="_blank" rel="noopener noreferrer" class="admin-nav-link accent">
                <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3h7v7m0-7L10 14"/><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/></svg>
                <span>Lihat Board Publik</span>
            </a>
        </div>

        <div class="admin-nav-user">
            <span>Masuk sebagai <strong><?= htmlspecialchars($admin['nama']) ?></strong></span>
            <form method="POST" action="logout.php" data-confirm="Yakin ingin logout?" style="display: inline;">
                <?= csrf_field() ?>
                <button type="submit" class="admin-logout">
                    <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3"/><path d="M12 3h6a2 2 0 012 2v14a2 2 0 01-2 2h-6"/></svg>
                    <span>Logout</span>
                </button>
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

<div class="admin-confirm-backdrop" id="adminSessionTimeoutModal" hidden>
    <div class="admin-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="adminSessionTimeoutTitle" aria-describedby="adminSessionTimeoutMessage">
        <div class="admin-confirm-icon" aria-hidden="true">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9L2.8 17a2 2 0 001.7 3h15a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>
        </div>
        <div class="admin-confirm-content">
            <h2 id="adminSessionTimeoutTitle">Sesi akan berakhir</h2>
            <p id="adminSessionTimeoutMessage">Sesi Anda akan segera berakhir.</p>
        </div>
        <div class="admin-confirm-actions">
            <button type="button" class="btn btn-primary" id="adminSessionExtend">Perpanjang sesi</button>
        </div>
    </div>
</div>

<script>
(function() {
    const modal = document.getElementById('adminSessionTimeoutModal');
    const message = document.getElementById('adminSessionTimeoutMessage');
    const extendButton = document.getElementById('adminSessionExtend');
    const csrfToken = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const idleTimeoutMs = <?= ADMIN_IDLE_TIMEOUT * 1000 ?>;
    let idleExpiresAt = Date.now() + <?= max(0, ADMIN_IDLE_TIMEOUT - (time() - (int)($_SESSION['admin_last_activity'] ?? time()))) ?> * 1000;
    let maxExpiresAt = Date.now() + <?= max(0, ADMIN_MAX_SESSION_DURATION - (time() - (int)($_SESSION['admin_authenticated_at'] ?? time()))) ?> * 1000;
    let timer = null;
    let lastSyncAt = 0;
    let lastActivityEventAt = 0;
    let syncPromise = null;
    let warningVisible = false;

    function expireSession() {
        window.location.replace('login.php?expired=1');
    }

    function updateWarning() {
        const remaining = Math.min(idleExpiresAt, maxExpiresAt) - Date.now();
        if (remaining <= 0) {
            expireSession();
            return;
        }

        if (remaining <= 120000) {
            warningVisible = true;
            const seconds = Math.ceil(remaining / 1000);
            const minutes = Math.floor(seconds / 60);
            const leftoverSeconds = seconds % 60;
            const countdown = (minutes > 0 ? minutes + ' menit ' : '') + leftoverSeconds + ' detik';
            const maximumWillExpireFirst = maxExpiresAt <= idleExpiresAt;
            message.textContent = maximumWillExpireFirst
                ? 'Batas maksimum sesi tercapai dalam ' + countdown + '. Anda akan diminta masuk kembali.'
                : 'Sesi akan berakhir dalam ' + countdown + ' karena tidak ada aktivitas.';
            extendButton.hidden = maximumWillExpireFirst;
            modal.hidden = false;
        }

        clearTimeout(timer);
        timer = window.setTimeout(updateWarning, remaining <= 120000 ? 1000 : remaining - 120000);
    }

    function syncSession() {
        if (syncPromise) return syncPromise;

        syncPromise = fetch('session_activity.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ csrf_token: csrfToken })
        }).then(async function(response) {
            if (response.status === 401) {
                expireSession();
                return false;
            }
            if (!response.ok) {
                throw new Error('Gagal memperbarui sesi admin (HTTP ' + response.status + ').');
            }

            const result = await response.json();
            const now = Date.now();
            idleExpiresAt = now + result.idle_remaining_seconds * 1000;
            maxExpiresAt = now + result.absolute_remaining_seconds * 1000;
            lastSyncAt = now;
            warningVisible = false;
            modal.hidden = true;
            updateWarning();
            return true;
        }).catch(function(error) {
            console.error(error);
            if (warningVisible) {
                message.textContent = 'Sesi belum berhasil diperpanjang. Periksa koneksi, lalu coba lagi.';
            }
            return false;
        }).finally(function() {
            syncPromise = null;
        });

        return syncPromise;
    }

    function recordActivity() {
        const now = Date.now();
        if (now - lastActivityEventAt < 1000) return;
        lastActivityEventAt = now;
        idleExpiresAt = now + idleTimeoutMs;
        updateWarning();

        if (warningVisible || now - lastSyncAt >= 60000) {
            syncSession();
        }
    }

    ['keydown', 'mousedown', 'mousemove', 'scroll', 'touchstart'].forEach(function(eventName) {
        document.addEventListener(eventName, recordActivity, { passive: true });
    });

    extendButton.addEventListener('click', function() {
        syncSession();
    });

    updateWarning();
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
