/* TKT House — one-page express checkout.
   Submits the form with fetch so nothing the customer typed is ever lost: field errors are shown in place,
   an existing e-mail opens a small code modal (then the same submit is repeated), success goes to the gateway. */
(function () {
    'use strict';

    var form = document.getElementById('expressForm');
    if (!form) return;

    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
    var alertBox = document.getElementById('formAlert');
    var alertText = alertBox ? alertBox.querySelector('[data-form-alert-text]') : null;
    var submitting = false;

    function setLoading(on) {
        $$('[data-loading-target]').forEach(function (btn) {
            btn.classList.toggle('is-loading', on);
            btn.disabled = on;
        });
    }

    function showAlert(text) {
        if (!alertBox) return;
        alertText.textContent = text;
        alertBox.hidden = !text;
        if (text) alertBox.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }

    function clearErrors() {
        $$('.tkt-field__error[data-js]', form).forEach(function (el) { el.remove(); });
        $$('.is-invalid', form).forEach(function (el) { el.classList.remove('is-invalid'); });
        if (window.TktWizard) window.TktWizard.clearErrors();
        // server-rendered errors from a previous full page load
        $$('.tkt-field__error:not([data-js])', form).forEach(function (el) { el.remove(); });
        showAlert('');
    }

    function fieldFor(key) {
        if (key === 'first_name' || key === 'last_name') key = 'full_name';
        var m = key.match(/^attendees\.(\d+)\.(\w+)$/);
        var name = m ? 'attendees[' + m[1] + '][' + m[2] + ']' : key;
        return form.querySelector('[name="' + name + '"]');
    }

    function showFieldErrors(errors) {
        var first = null;
        var general = [];
        var cardInputs = [];
        Object.keys(errors).forEach(function (key) {
            var msg = [].concat(errors[key])[0];
            var input = fieldFor(key);
            if (!input) { general.push(msg); return; }

            if (input.closest('[data-attendee]')) cardInputs.push(input);
            var host = input.closest('.tkt-field') || input.closest('#methodGroup') || input.parentElement;
            if (input.type === 'radio' && input.name === 'payment_method') host = $('#methodGroup');
            var gender = input.closest('.tkt-gender');
            if (gender) gender.classList.add('is-invalid'); else input.classList.add('is-invalid');

            if (host && !host.querySelector('.tkt-field__error[data-js="' + key + '"]')) {
                var div = document.createElement('div');
                div.className = 'tkt-field__error';
                div.setAttribute('data-js', key);
                div.textContent = msg;
                host.appendChild(div);
            }
            if (!first && !input.closest('[data-attendee]')) first = gender || input;
            else if (!first && !window.TktWizard) first = gender || input;
        });

        // Wizard: open the ticket that has the error (and mark every affected ticket) before scrolling to it.
        if (window.TktWizard && cardInputs.length) {
            var inCard = window.TktWizard.revealErrors(cardInputs);
            if (!first && inCard) first = inCard.closest('.tkt-gender') || inCard;
        }

        if (general.length) showAlert(general[0]);
        else showAlert('Please check the highlighted fields and try again.');

        if (first) {
            first.scrollIntoView({ block: 'center', behavior: 'smooth' });
            if (first.focus && first.type !== 'radio') { try { first.focus({ preventScroll: true }); } catch (e) {} }
        }
    }

    function setCsrf(token) {
        if (!token) return;
        $$('input[name="_token"]').forEach(function (i) { i.value = token; });
        var meta = $('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', token);
    }

    var modal = window.jQuery ? window.jQuery('#otpModal') : null;
    var panel = $('#otpModal [data-otp-panel]');

    function openVerification(email, masked) {
        if (!panel || !modal) { showAlert('Please confirm your email to continue.'); return; }
        var otp = window.TktOtp.init(panel);
        otp.setEmail(email, masked || email);
        modal.modal('show');
        otp.send();
        setTimeout(function () { var c = panel.querySelector('[data-otp-code]'); if (c) c.focus(); }, 400);
    }

    // "Wrong email?": back to the form with everything kept, focus on the email field.
    var editBtn = $('[data-otp-edit-email]');
    if (editBtn) {
        editBtn.addEventListener('click', function () {
            if (modal) modal.modal('hide');
            var f = $('#buyer_email');
            if (f) { setTimeout(function () { f.focus(); f.select(); }, 350); }
        });
    }

    if (panel) {
        panel.addEventListener('tkt:otp-verified', function (e) {
            setCsrf(e.detail && e.detail.csrf);
            modal.modal('hide');
            submit(); // same details, now as the verified account owner
        });
    }

    function submit() {
        if (submitting) return;
        submitting = true;
        setLoading(true);
        clearErrors();

        fetch(form.getAttribute('action'), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': ($('meta[name="csrf-token"]') || {}).content || '' },
            body: new FormData(form),
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (data) { return { status: r.status, ok: r.ok, data: data }; });
        }).then(function (res) {
            var d = res.data || {};

            if (res.ok && d.redirect) {
                window.location.href = d.redirect; // keep the loading state until the next page appears
                return;
            }

            submitting = false;
            setLoading(false);

            if (res.status === 409 && d.needs_verification) { openVerification(((form.querySelector('#buyer_email') || {}).value || '').trim(), d.masked); return; }
            if (res.status === 409 && d.redirect) { window.location.href = d.redirect; return; }
            if (res.status === 409 && d.price_changed) { showAlert(d.message); setTimeout(function () { window.location.reload(); }, 1800); return; }
            if (res.status === 422 && d.errors) { showFieldErrors(d.errors); return; }
            if (res.status === 419) { showAlert('Your session expired. Please reload the page — your selection is still saved.'); return; }
            if (res.status === 429) { showAlert(d.message || 'Please wait a moment and try again.'); return; }
            showAlert(d.message || 'Something went wrong. Please try again.');
        }).catch(function () {
            submitting = false;
            setLoading(false);
            showAlert('Could not reach the server. Check your connection and try again.');
        });
    }

    // Wizard mode turns native validation off (hidden tickets would block it silently), so check here, in page order:
    // buyer fields, then every ticket, then the payment method.
    function wizardGate() {
        var wiz = window.TktWizard;
        var attendees = $('[data-attendee-wizard]');
        var fields = $$('input, select, textarea', form).filter(function (el) { return el.type !== 'hidden' && !el.disabled && !el.closest('[data-attendee]'); });
        var before = fields.filter(function (el) { return !(attendees.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING); });
        var after = fields.filter(function (el) { return attendees.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING; });
        var bad = function (list) {
            for (var i = 0; i < list.length; i++) { if (!list[i].checkValidity()) { list[i].reportValidity(); return true; } }
            return false;
        };
        if (bad(before)) return false;
        var r = wiz.ensureComplete();
        if (!r.ok) { showAlert('Please complete the details for ticket ' + r.ticket + ' before paying.'); return false; }
        if (bad(after)) return false;
        return true;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (window.TktWizard && !wizardGate()) return;
        submit();
    });

    // A page restored from the back/forward cache must not stay "busy".
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { submitting = false; setLoading(false); }
    });

    // Non-JS fallback path asked for verification: open the modal straight away.
    var pending = form.getAttribute('data-verify-email');
    if (pending) { openVerification(pending); }
})();
