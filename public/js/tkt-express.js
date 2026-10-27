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
        $$('.tkt-field__error:not([data-js])', form).forEach(function (el) { if (!el.closest('[data-otp-panel]')) el.remove(); }); // the code box keeps its own message element
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
        var allInputs = [];
        Object.keys(errors).forEach(function (key) {
            var msg = [].concat(errors[key])[0];
            var input = fieldFor(key);
            if (!input) { general.push(msg); return; }

            allInputs.push(input);
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
            if (!first) first = gender || input;
        });

        // Wizard: open the earliest step that has an error (and flag every affected step) before scrolling to it.
        if (window.TktWizard && allInputs.length) {
            var inStep = window.TktWizard.revealErrors(allInputs);
            if (inStep) first = inStep.closest('.tkt-gender') || inStep;
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
    var modalPanel = $('#otpModal [data-otp-panel]');
    var inlineBox = $('[data-wz-otp]');
    var inlinePanel = inlineBox ? $('[data-otp-panel]', inlineBox) : null;
    var verifyOrigin = 'submit';
    var lastMasked = '';

    // Classic page: the code box is a small modal. Wizard: it is shown inline in step 1 (see tkt-checkout-wizard.js).
    function openVerification(email, masked) {
        lastMasked = masked || '';
        if (window.TktWizard && inlinePanel) { window.TktExpress.lastMasked = lastMasked; window.TktWizard.needsCode(); return; }
        if (!modalPanel || !modal) { showAlert('Please confirm your email to continue.'); return; }
        var otp = window.TktOtp.init(modalPanel);
        otp.setEmail(email, masked || email);
        modal.modal('show');
        otp.send();
        setTimeout(function () { var c = modalPanel.querySelector('[data-otp-code]'); if (c) c.focus(); }, 400);
    }

    // "Wrong email?": back to the form with everything kept, focus on the email field.
    $$('[data-otp-edit-email]').forEach(function (editBtn) {
        editBtn.addEventListener('click', function () {
            if (window.TktWizard) { window.TktWizard.editEmail(); return; }
            if (modal) modal.modal('hide');
            var f = $('#buyer_email');
            if (f) { setTimeout(function () { f.focus(); f.select(); }, 350); }
        });
    });

    if (modalPanel) {
        modalPanel.addEventListener('tkt:otp-verified', function (e) {
            setCsrf(e.detail && e.detail.csrf);
            modal.modal('hide');
            if (window.TktContactEmail) window.TktContactEmail.activate();
            submit(); // same details, now as the verified account owner
        });
    }
    if (inlinePanel) {
        inlinePanel.addEventListener('tkt:otp-verified', function (e) {
            setCsrf(e.detail && e.detail.csrf);
            window.TktWizard.verified();
            if (window.TktContactEmail) window.TktContactEmail.activate();
            if (verifyOrigin === 'submit') submit(); else window.TktWizard.advanceFromDetails();
        });
    }

    // Wizard step 1: validate the buyer block and learn whether the code box is needed now. Creates nothing.
    function buyerData() {
        var fd = new FormData(form);
        Array.from(fd.keys()).forEach(function (k) { if (!/^(_token|full_name|email|phone|password)/.test(k)) fd.delete(k); });
        return fd;
    }

    function preflight() {
        verifyOrigin = 'preflight';
        clearErrors();
        return fetch((document.querySelector('[data-wz]') || form).getAttribute('data-preflight-url') || '/checkout/buyer', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': ($('meta[name="csrf-token"]') || {}).content || '' },
            body: buyerData(),
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (d) { return { status: r.status, ok: r.ok, d: d || {} }; });
        }).then(function (res) {
            var d = res.d;
            if (res.ok && d.ok) return 'ok';
            if (res.status === 409 && d.needs_verification) { window.TktExpress.lastMasked = d.masked || ''; return 'verify'; }
            if (res.status === 409 && d.redirect) { window.location.href = d.redirect; return 'error'; }
            if (res.status === 422 && d.errors) { showFieldErrors(d.errors); return 'error'; }
            if (res.status === 419) { showAlert('Your session expired. Please reload the page — your selection is still saved.'); return 'error'; }
            showAlert(d.message || 'Something went wrong. Please try again.');
            return 'error';
        }).catch(function () { showAlert('Could not reach the server. Check your connection and try again.'); return 'error'; });
    }

    // Wizard: keep what was typed (session only) so a refresh restores it. Nothing is validated or created here.
    function saveDraft() {
        var url = root0 && root0.getAttribute('data-draft-url');
        if (!url || submitting) return;
        var fd = new FormData(form);
        Array.from(fd.keys()).forEach(function (k) { if (!/^(_token|full_name|email|phone|attendees|payment_method)/.test(k)) fd.delete(k); });
        try {
            fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': ($('meta[name="csrf-token"]') || {}).content || '' }, body: fd, credentials: 'same-origin', keepalive: true }).catch(function () {});
        } catch (e) {}
    }
    var root0 = document.querySelector('[data-wz]');

    window.TktExpress = { preflight: preflight, saveDraft: saveDraft, lastMasked: '' };

    function submit() {
        if (submitting) return;
        submitting = true;
        verifyOrigin = 'submit';
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

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (window.TktWizard) {
            // Every step must be complete before anything is sent; the first incomplete one opens with its errors.
            var gate = window.TktWizard.ensureComplete();
            if (!gate.ok) { showAlert('Please complete ' + gate.label + ' before paying.'); return; }
        }
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
