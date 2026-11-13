// EduSphere - shared JavaScript helpers (no build step, plain JS)

(function () {
    'use strict';

    // Small wrapper: localStorage can throw in private mode, so never let it break the page
    const store = {
        get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* ignore */ } }
    };

    /* ---------- Sidebar collapse (desktop) ---------- */
    const COLLAPSE_KEY = 'es-sidebar-collapsed';
    if (store.get(COLLAPSE_KEY) === '1') document.body.classList.add('sidebar-collapsed');
    document.querySelectorAll('[data-sidebar-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const on = document.body.classList.toggle('sidebar-collapsed');
            store.set(COLLAPSE_KEY, on ? '1' : '0');
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    });

    /* ---------- Toasts (flash messages) ---------- */
    function closeToast(t) {
        t.classList.add('hide');
        setTimeout(function () { t.remove(); }, 260);
    }
    document.querySelectorAll('.es-toast').forEach(function (t) {
        t.querySelector('.btn-close').addEventListener('click', function () { closeToast(t); });
        if (t.dataset.autohide === '1') setTimeout(function () { closeToast(t); }, 4500);
    });

    /* ---------- Confirmation dialog for [data-confirm] ----------
       Works for links and for submit buttons (keeps the button's name/value). */
    let modalEl = null, pending = null;
    function getModal() {
        if (modalEl) return modalEl;
        modalEl = document.createElement('div');
        modalEl.className = 'modal fade';
        modalEl.tabIndex = -1;
        modalEl.innerHTML =
            '<div class="modal-dialog modal-dialog-centered" style="max-width:440px"><div class="modal-content">' +
            '<div class="modal-body d-flex gap-3 p-4"><div class="confirm-icon"><i class="bi bi-exclamation-triangle"></i></div>' +
            '<div><h2 class="h6 mb-1" data-title>Are you sure?</h2><p class="text-muted mb-0" data-msg></p></div></div>' +
            '<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>' +
            '<button type="button" class="btn btn-danger" data-ok>Confirm</button></div></div></div>';
        document.body.appendChild(modalEl);
        modalEl.querySelector('[data-ok]').addEventListener('click', function () {
            const el = pending;
            pending = null;
            bootstrap.Modal.getInstance(modalEl).hide();
            if (!el) return;
            el.dataset.confirmed = '1';
            if (el.form && (el.type === 'submit' || el.tagName === 'BUTTON')) {
                el.form.requestSubmit ? el.form.requestSubmit(el) : el.click();
            } else if (el.href) {
                window.location.href = el.href;
            } else {
                el.click();
            }
        });
        return modalEl;
    }
    document.addEventListener('click', function (e) {
        const el = e.target.closest('[data-confirm]');
        if (!el) return;
        if (el.dataset.confirmed === '1') { delete el.dataset.confirmed; return; }
        if (typeof bootstrap === 'undefined') {           // fallback if Bootstrap JS failed to load
            if (!confirm(el.dataset.confirm)) e.preventDefault();
            return;
        }
        e.preventDefault();
        pending = el;
        const m = getModal();
        m.querySelector('[data-msg]').textContent = el.dataset.confirm;
        m.querySelector('[data-title]').textContent = el.dataset.confirmTitle || 'Are you sure?';
        m.querySelector('[data-ok]').textContent = el.dataset.confirmOk || 'Delete';
        bootstrap.Modal.getOrCreateInstance(m).show();
    });

    /* ---------- Client-side validation using Bootstrap styles (server validates again) ---------- */
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                const first = form.querySelector(':invalid');
                if (first) first.focus();
            }
            form.classList.add('was-validated');
        });
    });

    /* ---------- Prevent double submits: disable the clicked button briefly ---------- */
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (e.defaultPrevented || form.method.toLowerCase() !== 'post') return;
        const btn = e.submitter;
        if (btn && !btn.hasAttribute('data-no-busy')) {
            setTimeout(function () { btn.disabled = true; }, 0);   // after the value is sent
            setTimeout(function () { btn.disabled = false; }, 8000);
        }
    });

    /* ---------- Show / hide password ---------- */
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = document.getElementById(btn.dataset.togglePassword);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.querySelector('i').className = 'bi bi-' + (show ? 'eye-slash' : 'eye');
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });

    /* ---------- Simple client-side table search: <input data-table-search="#tableId"> ---------- */
    document.querySelectorAll('[data-table-search]').forEach(function (input) {
        const table = document.querySelector(input.dataset.tableSearch);
        if (!table) return;
        input.addEventListener('input', function () {
            const q = input.value.toLowerCase();
            table.querySelectorAll('tbody tr').forEach(function (tr) {
                if (tr.querySelector('.empty-cell')) return;
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    });

    /* ---------- Export an HTML table to CSV: <button data-export-csv="#tableId" data-filename="x.csv"> ---------- */
    document.querySelectorAll('[data-export-csv]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const table = document.querySelector(btn.dataset.exportCsv);
            const rows = [];
            table.querySelectorAll('tr').forEach(function (tr) {
                if (tr.style.display === 'none' || tr.querySelector('.empty-cell')) return;
                const cells = [];
                tr.querySelectorAll('th, td').forEach(function (td) {
                    if (td.classList.contains('no-export')) return;
                    cells.push('"' + td.innerText.trim().replace(/\s+/g, ' ').replace(/"/g, '""') + '"');
                });
                rows.push(cells.join(','));
            });
            const blob = new Blob(['﻿' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = btn.dataset.filename || 'report.csv';
            a.click();
        });
    });

    /* ---------- Tooltips for collapsed sidebar & icon buttons ---------- */
    if (typeof bootstrap !== 'undefined') {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new bootstrap.Tooltip(el, { container: 'body' });
        });
    }
})();

/* Shared Chart.js defaults so every chart matches the design system */
window.esChartDefaults = function () {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#64748b';
    Chart.defaults.borderColor = '#eef0f5';
    Chart.defaults.plugins.legend.display = false;
    Chart.defaults.plugins.tooltip.backgroundColor = '#0f172a';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.titleFont = { weight: '600' };
    Chart.defaults.plugins.tooltip.displayColors = false;
    Chart.defaults.maintainAspectRatio = false;
};
