        <script>
            (function () {
                var isAuthenticated = document.body && document.body.dataset.authenticated === '1';
                if (isAuthenticated) {
                    return;
                }

                function setPanelMessage(type, kind, message) {
                    var box = document.querySelector('[data-auth-message="' + type + '"]');
                    if (!box) {
                        return;
                    }

                    box.classList.remove('is-success', 'is-error');
                    if (!message) {
                        box.style.display = 'none';
                        box.textContent = '';
                        box.style.background = '';
                        box.style.border = '';
                        box.style.color = '';
                        return;
                    }

                    box.style.display = 'block';
                    box.textContent = message;
                    box.classList.add(kind === 'success' ? 'is-success' : 'is-error');
                    if (kind === 'success') {
                        box.style.background = 'rgba(34,197,94,.08)';
                        box.style.border = '1px solid rgba(34,197,94,.25)';
                        box.style.color = '#86efac';
                    } else {
                        box.style.background = 'rgba(232,68,90,0.08)';
                        box.style.border = '1px solid rgba(232,68,90,0.3)';
                        box.style.color = '#f0849a';
                    }
                }

                function clearInlineErrors(form) {
                    form.querySelectorAll('[data-auth-inline-error]').forEach(function (el) {
                        el.remove();
                    });
                }

                function showInlineError(form, field, message) {
                    var input = form.querySelector('[name="' + field + '"]');
                    if (!input) {
                        return;
                    }

                    var div = document.createElement('div');
                    div.setAttribute('data-auth-inline-error', '1');
                    div.className = 'tkt-field__error';
                    div.style.marginTop = '6px';
                    div.style.color = '#f0849a';
                    div.style.fontSize = '12px';
                    div.textContent = message;
                    var fieldWrap = input.closest('.auth-field');
                    if (fieldWrap) {
                        fieldWrap.appendChild(div);
                    }
                }

                var hasErrors = {{ $errors->any() ? 'true' : 'false' }};
                if (hasErrors && window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
                    window.jQuery('#login-register1').modal('show');

                    var shouldOpenRegister = {{ ($errors->has('name') || $errors->has('password') || old('name')) ? 'true' : 'false' }};
                    var shouldOpenForgot = {{ ($errors->has('email') && !old('name')) ? 'true' : 'false' }};
                    if (shouldOpenRegister) {
                        var registerTab = document.querySelector('[data-auth-tab="register"]');
                        if (registerTab) {
                            registerTab.click();
                        }
                    } else if (shouldOpenForgot) {
                        var forgotSwitch = document.querySelector('[data-auth-switch="forgot"]');
                        if (forgotSwitch) {
                            forgotSwitch.click();
                        }
                    }
                }

                document.querySelectorAll('form[data-auth-ajax-form]').forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        event.preventDefault();

                        var type = form.getAttribute('data-auth-type') || 'login';
                        var submitButton = form.querySelector('button[type="submit"]');
                        var originalButtonText = submitButton ? submitButton.textContent : '';

                        setPanelMessage(type, null, null);
                        clearInlineErrors(form);

                        if (submitButton) {
                            submitButton.disabled = true;
                            submitButton.textContent = 'Please wait...';
                        }

                        var endpoint = form.action;
                        if (type === 'register') {
                            endpoint = form.dataset.otpStage === 'verify'
                                ? (form.dataset.otpVerifyUrl || form.action)
                                : (form.dataset.otpRequestUrl || form.action);
                        }

                        fetch(endpoint, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: new FormData(form),
                            credentials: 'same-origin',
                        })
                            .then(function (response) {
                                return response.json().catch(function () { return {}; }).then(function (data) {
                                    return { status: response.status, ok: response.ok, data: data };
                                });
                            })
                            .then(function (result) {
                                if (result.ok) {
                                    if (type === 'forgot') {
                                        setPanelMessage(type, 'success', result.data.message || 'Password reset link sent successfully.');
                                        form.reset();
                                        return;
                                    }

                                    if (type === 'register' && result.data.otp_required) {
                                        form.dataset.otpStage = 'verify';
                                        var otpWrap = form.querySelector('[data-otp-field-wrap]');
                                        if (otpWrap) {
                                            otpWrap.style.display = 'block';
                                        }
                                        var otpInput = form.querySelector('[name="otp"]');
                                        if (otpInput) {
                                            otpInput.required = true;
                                            otpInput.focus();
                                        }
                                        setPanelMessage(type, 'success', result.data.message || 'OTP sent successfully.');
                                        return;
                                    }

                                    setPanelMessage(type, 'success', result.data.message || 'Success. Redirecting...');
                                    var redirectTo = result.data.redirect_to || '{{ route('front.account.dashboard') }}';
                                    window.location.href = redirectTo;
                                    return;
                                }

                                var errors = result.data.errors || {};
                                var firstError = null;
                                Object.keys(errors).forEach(function (key) {
                                    var message = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
                                    if (!firstError && message) {
                                        firstError = message;
                                    }
                                    showInlineError(form, key, message || 'Invalid input.');
                                });

                                setPanelMessage(type, 'error', firstError || result.data.message || 'Something went wrong.');

                                if (type === 'register') {
                                    var registerTab = document.querySelector('[data-auth-tab="register"]');
                                    if (registerTab) {
                                        registerTab.click();
                                    }
                                } else if (type === 'forgot') {
                                    var forgotSwitch = document.querySelector('[data-auth-switch="forgot"]');
                                    if (forgotSwitch) {
                                        forgotSwitch.click();
                                    }
                                }
                            })
                            .catch(function () {
                                setPanelMessage(type, 'error', 'Unable to submit now. Please try again.');
                            })
                            .finally(function () {
                                if (submitButton) {
                                    submitButton.disabled = false;
                                    submitButton.textContent = originalButtonText;
                                }
                            });
                    });
                });

                document.addEventListener('click', function (event) {
                    var link = event.target.closest('a.tkt-checkout-btn');
                    // Express checkout needs no login: only legacy links are routed through the sign-in modal.
                    if (!link || link.hasAttribute('data-express-checkout')) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();
                    if (typeof event.stopImmediatePropagation === 'function') {
                        event.stopImmediatePropagation();
                    }

                    var checkoutUrl = link.getAttribute('href') || '';
                    document.querySelectorAll('[data-redirect-target]').forEach(function (input) {
                        input.value = checkoutUrl;
                    });

                    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
                        window.jQuery('#login-register1').modal('show');
                    }
                }, true);
            })();
        </script>
        <script>
        (function () {
            var ITI_UTILS = 'https://cdn.jsdelivr.net/npm/intl-tel-input@22.0.2/build/js/utils.js';

            function initPhoneInput(input) {
                if (input._itiInstance || !window.intlTelInput) return;
                var iti = window.intlTelInput(input, {
                    separateDialCode: true,
                    initialCountry: 'eg',
                    preferredCountries: ['eg', 'sa', 'ae', 'us', 'gb'],
                    utilsScript: ITI_UTILS
                });
                input._itiInstance = iti;

                // Remove name from the visible input and create a hidden sibling
                // that always holds the full E.164 number. This way the value is
                // correct for both regular form submit, form.submit(), and AJAX
                // handlers that call new FormData(form) — all of which bypass the
                // submit event listener approach.
                var fieldName = input.getAttribute('name');
                if (fieldName) {
                    input.removeAttribute('name');
                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = fieldName;
                    input.insertAdjacentElement('afterend', hidden);

                    function sync() {
                        var num = '';
                        var country = iti.getSelectedCountryData();
                        if (window.intlTelInputUtils) {
                            // Explicitly request E.164 to avoid spaces/dashes
                            num = iti.getNumber(window.intlTelInputUtils.numberFormat.E164) || '';
                        } else if (country && country.dialCode) {
                            // Fallback before utils.js loads: strip leading zeros
                            var raw = input.value.trim().replace(/^0+/, '').replace(/\D/g, '');
                            num = raw ? ('+' + country.dialCode + raw) : '';
                        } else {
                            num = input.value;
                        }
                        // Always strip formatting characters — keep only leading + and digits
                        hidden.value = num.replace(/(?!^\+)\D/g, '');
                    }

                    input.addEventListener('input', sync);
                    input.addEventListener('countrychange', sync);
                    sync(); // initial sync on page load
                    // Re-sync with properly formatted E.164 once utils.js loads
                    if (iti.promise && typeof iti.promise.then === 'function') {
                        iti.promise.then(sync);
                    }
                }
            }

            function initAll(root) {
                (root || document).querySelectorAll('[data-phone-intl]:not([data-iti-ready])').forEach(function (el) {
                    el.setAttribute('data-iti-ready', '1');
                    initPhoneInput(el);
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () { initAll(); });
            } else {
                initAll();
            }

            var obs = new MutationObserver(function (muts) {
                muts.forEach(function (m) {
                    m.addedNodes.forEach(function (n) {
                        if (n.nodeType !== 1) return;
                        if (n.matches && n.matches('[data-phone-intl]')) {
                            n.setAttribute('data-iti-ready', '1');
                            initPhoneInput(n);
                        } else {
                            initAll(n);
                        }
                    });
                });
            });
            obs.observe(document.body, { childList: true, subtree: true });

            window.initPhoneInputs = initAll;
        })();
        </script>
