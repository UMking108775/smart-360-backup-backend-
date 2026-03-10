/**
 * Smart 360 Backup WP — Admin Panel JavaScript
 * SSA Technologies
 */

document.addEventListener('DOMContentLoaded', function () {
    initMobileMenu();
    initCopyButtons();
    initModalSystem();
    initToastSystem();
    initAutoFade();
});

/* ─── Mobile Menu ───────────────────────────────────────────────────── */
function initMobileMenu() {
    const toggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    if (!toggle || !sidebar) return;

    toggle.addEventListener('click', function () {
        sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('active');
    });

    if (overlay) {
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }
}

/* ─── Copy to Clipboard ─────────────────────────────────────────────── */
function initCopyButtons() {
    document.querySelectorAll('.copy-text').forEach(function (el) {
        el.addEventListener('click', function () {
            const text = this.getAttribute('data-copy') || this.textContent.trim();
            copyToClipboard(text);
            showToast('Copied to clipboard!', 'success');
        });
    });
}

function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text);
    } else {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    }
}

/* ─── Modal System ──────────────────────────────────────────────────── */
function initModalSystem() {
    document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.getElementById(this.getAttribute('data-modal-open'));
            if (target) openModal(target);
        });
    });

    document.querySelectorAll('.modal-close, [data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const overlay = this.closest('.modal-overlay');
            if (overlay) closeModal(overlay);
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) closeModal(this);
        });
    });
}

function openModal(overlay) {
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(overlay) {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}

/* ─── Toast Notifications ───────────────────────────────────────────── */
function initToastSystem() {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
}

function showToast(message, type) {
    type = type || 'success';
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.innerHTML = '<span>' + (type === 'success' ? '✓' : '✕') + '</span> ' + message;
    container.appendChild(toast);

    setTimeout(function () {
        toast.classList.add('toast-out');
        setTimeout(function () { toast.remove(); }, 300);
    }, 3000);
}

/* ─── Auto fade alerts ──────────────────────────────────────────────── */
function initAutoFade() {
    document.querySelectorAll('.alert[data-auto-fade]').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.5s ease';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 500);
        }, 4000);
    });
}

/* ─── Confirm action (delete, revoke, etc.) ─────────────────────────── */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/* ─── AJAX helper ───────────────────────────────────────────────────── */
function ajaxPost(url, data, callback) {
    const form = new FormData();
    for (var key in data) {
        if (data.hasOwnProperty(key)) {
            form.append(key, data[key]);
        }
    }

    fetch(url, {
        method: 'POST',
        body: form
    })
    .then(function (res) { return res.json(); })
    .then(function (json) { callback(null, json); })
    .catch(function (err) { callback(err, null); });
}
