/* Orders list: single-assign modal and bulk-assign bar. Behaviour only; the server enforces every rule. */
(function () {
    'use strict';
    var modal = document.getElementById('ordAssignModal');
    if (modal) {
        var form = document.getElementById('ordAssignForm');
        var select = document.getElementById('ordAssignSelect');
        var label = document.getElementById('ordAssignNumber');
        var last = null;
        var close = function () { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); if (last && last.isConnected) last.focus(); };
        document.addEventListener('click', function (e) {
            var open = e.target.closest('[data-ord-assign-open]');
            if (open) {
                last = open.closest('.a-menu') ? open.closest('.a-menu').querySelector('[data-a-menu-toggle]') : open;
                form.setAttribute('action', open.dataset.action);
                label.textContent = '#' + open.dataset.number;
                var ids = (open.dataset.ids || '').split(',').filter(Boolean);
                Array.prototype.forEach.call(select.options, function (o) { o.selected = ids.indexOf(o.value) !== -1; });
                select.dispatchEvent(new Event('change', { bubbles: true }));
                modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); var f = modal.querySelector('.a-msel__search') || select; f.focus();
                return;
            }
            if (e.target.closest('[data-ord-assign-close]') || e.target === modal) close();
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('show')) close(); });
    }

    var bar = document.getElementById('bulkAssignForm');
    if (bar) {
        var boxes = function () { return Array.prototype.slice.call(document.querySelectorAll('input[name="order_ids[]"]')); };
        var count = bar.querySelector('[data-bulk-count]');
        var sync = function () {
            var n = boxes().filter(function (b) { return b.checked; }).length;
            count.textContent = n;
            bar.hidden = n === 0;
        };
        // Approve / Reject buttons post the same selection to their own route after a confirmation.
        bar.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-bulk-confirm]');
            if (btn && !window.confirm(btn.dataset.bulkConfirm)) e.preventDefault();
        });
        document.addEventListener('change', function (e) {
            if (e.target.matches('[data-bulk-all]')) { boxes().forEach(function (b) { b.checked = e.target.checked; }); }
            if (e.target.matches('input[name="order_ids[]"], [data-bulk-all]')) sync();
        });
    }
})();
