/* Show / hide ticket QR codes. Per ticket ([data-qr-toggle]) or for a whole order ([data-qr-all="show|hide"]).
   The QR image is only loaded (src set) while it is shown. */
(function () {
    function boxOf(scope) { return scope.querySelector('[data-qr-box]'); }

    function setShown(box, shown) {
        var img = box.querySelector('[data-qr-img]');
        if (shown) { img.setAttribute('src', box.getAttribute('data-qr-src')); } else { img.removeAttribute('src'); }
        box.classList.toggle('is-hidden', !shown);
        var scope = box.closest('[data-qr-scope]');
        var btn = scope && scope.querySelector('[data-qr-toggle]');
        if (btn) { btn.textContent = shown ? 'Hide QR' : 'Show QR'; btn.setAttribute('aria-pressed', shown ? 'true' : 'false'); }
    }

    function isShown(box) { return !box.classList.contains('is-hidden'); }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-qr-toggle]');
        if (toggle) {
            var box = boxOf(toggle.closest('[data-qr-scope]'));
            if (box) { setShown(box, !isShown(box)); }
            return;
        }
        var all = e.target.closest('[data-qr-all]');
        if (all) {
            var root = all.closest('[data-qr-group]') || document;
            var show = all.getAttribute('data-qr-all') === 'show';
            root.querySelectorAll('[data-qr-box]').forEach(function (b) { setShown(b, show); });
        }
    });

    // Sync button labels with the server-rendered state (some QRs start visible when the event is close).
    document.querySelectorAll('[data-qr-box]').forEach(function (b) { setShown(b, isShown(b)); });
})();
