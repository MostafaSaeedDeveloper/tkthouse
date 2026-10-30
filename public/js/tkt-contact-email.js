/* Checkout: "Change email" for a signed-in buyer. The contact e-mail is the account's own until another address is
   confirmed with a code; the server refuses an unconfirmed one. */
(function () {
    'use strict';
    var box = document.querySelector('[data-contact-email]');
    var input = document.querySelector('[data-buyer-email]');
    if (!box || !input) return;

    var $ = function (sel) { return box.querySelector(sel); };
    var panel = $('[data-contact-box]'), codeRow = $('[data-contact-code-row]'), code = $('[data-contact-code]');
    var info = $('[data-contact-info]'), err = $('[data-contact-error]');
    var account = box.getAttribute('data-account-email');
    var hint = document.querySelector('[data-signed-in-note]');

    // Called right after the e-mail code signed the buyer in (ajax): the page now behaves as for a signed-in customer.
    window.TktContactEmail = {
        activate: function () {
            account = input.value.trim();
            box.setAttribute('data-account-email', account);
            box.hidden = false; input.readOnly = true;
            Array.prototype.forEach.call(document.querySelectorAll('[data-guest-password]'), function (el) { el.remove(); });
            if (hint) hint.textContent = 'Signed in as ' + account + '. Your email is your account email unless you change and confirm another; other details apply to this order only.';
        }
    };

    function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return (m && m.content) || ''; }
    function say(el, text) { el.textContent = text || ''; el.hidden = !text; }
    function post(url, body) {
        return fetch(url, { method: 'POST', credentials: 'same-origin', body: JSON.stringify(body),
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() } })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); })
            .catch(function () { return { ok: false, d: { message: 'Could not reach the server. Please try again.' } }; });
    }
    function close(restore) {
        panel.hidden = true; codeRow.hidden = true; code.value = ''; say(info, ''); say(err, '');
        if (restore) input.value = account;
        input.readOnly = true;
        $('[data-contact-change]').hidden = false;
    }

    $('[data-contact-change]').addEventListener('click', function () {
        input.readOnly = false; input.focus(); panel.hidden = false; this.hidden = true;
    });
    $('[data-contact-cancel]').addEventListener('click', function () { close(true); });
    input.addEventListener('input', function () { codeRow.hidden = true; say(info, ''); });

    $('[data-contact-send]').addEventListener('click', function () {
        say(err, ''); var btn = this; btn.disabled = true;
        post(box.getAttribute('data-send-url'), { email: input.value.trim() }).then(function (res) {
            if (res.ok && res.d.ok) { say(info, res.d.message); codeRow.hidden = false; code.focus(); }
            else { say(err, (res.d.errors && res.d.errors.email && res.d.errors.email[0]) || res.d.message || 'Could not send a code.'); }
            setTimeout(function () { btn.disabled = false; }, 30000);
        });
    });
    $('[data-contact-verify]').addEventListener('click', function () {
        say(err, '');
        post(box.getAttribute('data-verify-url'), { email: input.value.trim(), code: (code.value || '').replace(/\D/g, '') }).then(function (res) {
            if (res.ok && res.d.ok) { close(false); say(info, ''); panel.hidden = false; say(info, 'Email confirmed for this order.'); }
            else { say(err, res.d.message || 'That code is not valid.'); }
        });
    });
})();
