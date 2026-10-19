/* Admin date fields: every <input type="date"> becomes a styled calendar (flatpickr, bundled with the admin).
   The real input keeps its name and its Y-m-d value, so forms submit exactly what they did before; the visible field shows "19 Oct 2026".
   Opt out with data-native-date. Without flatpickr the browser's own date input is left alone. */
(function () {
    'use strict';

    function enhance(el) {
        if (!window.flatpickr || el.dataset.native !== undefined || el.hasAttribute('data-native-date') || el._flatpickr) return;

        var fp = window.flatpickr(el, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd M Y',
            altInputClass: ((el.className || 'form-control') + ' a-datepicker__alt').trim(),
            allowInput: false,
            disableMobile: true,
            locale: { firstDayOfWeek: 6 },
            minDate: el.getAttribute('min') || null,
            maxDate: el.getAttribute('max') || null,
            prevArrow: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>',
            nextArrow: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>',
            onReady: function (selected, str, inst) {
                var alt = inst.altInput;
                if (alt) {
                    alt.placeholder = el.getAttribute('placeholder') || 'Select date';
                    if (el.id) {
                        alt.id = el.id + '-display';
                        document.querySelectorAll('label[for="' + el.id + '"]').forEach(function (l) { l.setAttribute('for', alt.id); });
                    }
                }
                var foot = document.createElement('div');
                foot.className = 'a-fp-foot';
                foot.innerHTML = '<button type="button" class="a-fp-foot__btn" data-fp-clear>Clear</button><button type="button" class="a-fp-foot__btn a-fp-foot__btn--primary" data-fp-today>Today</button>';
                foot.addEventListener('click', function (e) {
                    if (e.target.closest('[data-fp-clear]')) { inst.clear(); inst.close(); }
                    if (e.target.closest('[data-fp-today]')) { inst.setDate(new Date(), true); inst.close(); }
                });
                inst.calendarContainer.appendChild(foot);
            },
        });

        return fp;
    }

    function init(root) {
        (root || document).querySelectorAll('input[type="date"]').forEach(enhance);
    }

    window.TKTDatePicker = { init: init };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(); });
    else init();
})();
