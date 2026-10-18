/* Admin bell: opens the panel, polls the unread counter and the latest items, marks everything read. Everything is
   server-rendered (the feed returns ready HTML), so this file only toggles, swaps and announces. */
(function () {
    'use strict';
    var root = document.querySelector('[data-a-notif]');
    if (!root) return;

    var btn = root.querySelector('.a-notif__btn');
    var panel = root.querySelector('.a-notif__panel');
    var badge = root.querySelector('[data-notif-badge]');
    var list = root.querySelector('[data-notif-list]');
    var live = root.querySelector('[data-notif-live]');
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var INTERVAL = 45000;
    var unread = parseInt((badge && badge.textContent) || '0', 10) || 0;
    var timer = null;

    function setBadge(n) {
        unread = n;
        if (!badge) return;
        badge.hidden = n < 1;
        badge.textContent = n > 99 ? '99+' : String(n);
        btn.setAttribute('aria-label', n ? 'Notifications, ' + n + ' unread' : 'Notifications');
    }

    function open(on) {
        panel.hidden = !on;
        btn.setAttribute('aria-expanded', on ? 'true' : 'false');
        root.classList.toggle('is-open', on);
        if (on) refresh(false);
    }

    function refresh(announce) {
        return fetch(root.dataset.feed, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error('feed ' + r.status); return r.json(); })
            .then(function (d) {
                var grew = d.unread > unread;
                setBadge(d.unread);
                list.innerHTML = d.html;
                if (announce && grew && live) live.textContent = d.unread + ' unread notification' + (d.unread === 1 ? '' : 's');
            })
            .catch(function () { /* offline or session ended: keep what is shown */ });
    }

    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(function () {
            if (!document.hidden) refresh(true);
            schedule();
        }, INTERVAL);
    }

    btn.addEventListener('click', function (e) { e.stopPropagation(); open(panel.hidden); });
    document.addEventListener('click', function (e) { if (!panel.hidden && !root.contains(e.target)) open(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { open(false); btn.focus(); } });
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(true); });

    root.querySelector('[data-notif-readall]').addEventListener('click', function () {
        fetch(root.dataset.readAll, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function () { setBadge(0); Array.prototype.forEach.call(list.querySelectorAll('.is-unread'), function (n) { n.classList.remove('is-unread'); var d = n.querySelector('.a-notif__dot'); if (d) d.remove(); }); })
            .catch(function () {});
    });

    schedule();
})();
