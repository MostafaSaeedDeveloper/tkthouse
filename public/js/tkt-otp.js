/* TKT House — e-mail one-time-code boxes ([data-otp-panel]).
   Used inside checkout (modal), on the confirmation page and on emailed payment links.
   Fires `tkt:otp-verified` (bubbles) with {csrf, name, passwordless} once the server accepted the code. */
(function () {
    'use strict';

    var csrf = function () {
        var m = document.querySelector('meta[name="csrf-token"]');
        var i = document.querySelector('input[name="_token"]');
        return (m && m.content) || (i && i.value) || '';
    };

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(body),
            credentials: 'same-origin'
        }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (data) { return { ok: r.ok, status: r.status, data: data }; }); })
          .catch(function () { return { ok: false, status: 0, data: { message: 'Could not reach the server. Please try again.' } }; });
    }

    function init(panel) {
        if (panel._otp) return panel._otp;
        var code = panel.querySelector('[data-otp-code]');
        var err = panel.querySelector('[data-otp-error]');
        var info = panel.querySelector('[data-otp-info]');
        var verifyBtn = panel.querySelector('[data-otp-verify]');
        var resendBtn = panel.querySelector('[data-otp-resend]');
        var emailEl = panel.querySelector('[data-otp-email]');

        var show = function (el, text) { if (!el) return; el.textContent = text || ''; el.hidden = !text; };
        var payload = function (extra) {
            var p = { email: panel.getAttribute('data-email') };
            if (panel.getAttribute('data-order')) { p.order = panel.getAttribute('data-order'); p.token = panel.getAttribute('data-token'); }
            return Object.assign(p, extra || {});
        };

        var api = {
            setEmail: function (email, masked) {
                panel.setAttribute('data-email', email);
                if (emailEl) emailEl.textContent = masked || email;
            },
            send: function () {
                show(err, ''); resendBtn.disabled = true;
                return post(panel.getAttribute('data-send-url'), payload()).then(function (res) {
                    if (res.ok && res.data.ok) { show(info, res.data.message); }
                    else { show(err, res.data.message || 'Could not send a code. Please try again.'); }
                    setTimeout(function () { resendBtn.disabled = false; }, 30000);
                    return res;
                });
            },
            verify: function () {
                var value = (code.value || '').replace(/\D/g, '');
                if (value.length !== 6) { show(err, 'Enter the 6-digit code from your email.'); return Promise.resolve(); }
                show(err, ''); verifyBtn.classList.add('is-loading'); verifyBtn.disabled = true;
                return post(panel.getAttribute('data-verify-url'), payload({ code: value })).then(function (res) {
                    verifyBtn.classList.remove('is-loading'); verifyBtn.disabled = false;
                    if (res.ok && res.data.ok) {
                        panel.dispatchEvent(new CustomEvent('tkt:otp-verified', { bubbles: true, detail: res.data }));
                        var after = panel.getAttribute('data-after');
                        if (after) { window.location.href = after; }
                        else if (panel.hasAttribute('data-reload')) { window.location.reload(); }
                    } else {
                        show(err, res.data.message || 'That code is not valid.');
                        code.select();
                    }
                    return res;
                });
            }
        };

        verifyBtn.addEventListener('click', api.verify);
        resendBtn.addEventListener('click', api.send);
        code.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); api.verify(); } });
        code.addEventListener('input', function () { if ((code.value || '').replace(/\D/g, '').length === 6) { api.verify(); } });

        if (panel.hasAttribute('data-autosend')) {
            var k = 'tkt-otp-sent-' + panel.getAttribute('data-email');
            try { if (!sessionStorage.getItem(k)) { sessionStorage.setItem(k, '1'); api.send(); } } catch (e) { api.send(); }
        }

        panel._otp = api;
        return api;
    }

    window.TktOtp = { init: init };
    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.forEach.call(document.querySelectorAll('[data-otp-panel]'), init);
    });
})();

/* A code accepted via ajax signs the visitor in: swap the header's "Log in / Sign up" for the account menu, no reload. */
document.addEventListener('tkt:otp-verified', function (e) {
    var d = (e && e.detail) || {};
    var set = function (sel, fn) { Array.prototype.forEach.call(document.querySelectorAll(sel), fn); };
    set('[data-header-guest]', function (el) { el.hidden = true; el.style.display = 'none'; });
    set('[data-header-account]', function (el) { el.hidden = false; });
    set('[data-header-name]', function (el) { if (d.name) el.textContent = d.name; });
    set('[data-header-initial]', function (el) { if (d.name) el.textContent = d.name.trim().charAt(0).toUpperCase(); });
    if (d.csrf) set('input[name="_token"]', function (el) { el.value = d.csrf; }); // the session id was rotated
});
