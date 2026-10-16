/* Admin custom select: replaces every native single <select> with a styled dropdown.
   The native <select> stays in the DOM (visually hidden) so forms, validation, labels and
   existing `change` listeners keep working. Opt out with class "no-custom-select". */
(function () {
    'use strict';

    var SEARCH_MIN = 8;
    var openInst = null;
    var uid = 0;

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text != null) n.textContent = text;
        return n;
    }

    function eligible(select) {
        if (select.dataset.aSel === '1') return false;
        if (select.multiple || select.size > 1) return false;
        if (select.matches('.no-custom-select, .swal2-select')) return false;
        if (select.closest('.swal2-container, template')) return false;
        return true;
    }

    function CustomSelect(select) {
        var self = this;
        this.select = select;
        this.id = 'asel-' + (++uid);
        select.dataset.aSel = '1';

        this.root = el('div', 'a-sel');
        this.btn = el('button', 'a-sel__btn');
        this.btn.type = 'button';
        this.btn.setAttribute('role', 'combobox');
        this.btn.setAttribute('aria-haspopup', 'listbox');
        this.btn.setAttribute('aria-expanded', 'false');
        this.btn.setAttribute('aria-controls', this.id);
        this.label = el('span', 'a-sel__label');
        this.btn.appendChild(this.label);
        this.btn.insertAdjacentHTML('beforeend', '<svg class="a-sel__chev" width="14" height="14" viewBox="0 0 20 20" aria-hidden="true"><path d="M5 7.5l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>');

        this.panel = el('div', 'a-sel__panel');
        this.panel.hidden = true;
        this.search = el('input', 'a-sel__search');
        this.search.type = 'search';
        this.search.placeholder = 'Search…';
        this.search.autocomplete = 'off';
        this.search.setAttribute('aria-label', 'Search options');
        this.list = el('div', 'a-sel__list');
        this.list.id = this.id;
        this.list.setAttribute('role', 'listbox');
        this.panel.appendChild(this.search);
        this.panel.appendChild(this.list);

        var st = select.style;
        if (st.width === 'auto') this.root.classList.add('a-sel--auto');
        if (st.minWidth) this.root.style.minWidth = st.minWidth;
        if (st.maxWidth) this.root.style.maxWidth = st.maxWidth;
        if (st.width && st.width !== 'auto') this.root.style.width = st.width;
        if (st.minHeight) this.btn.style.minHeight = st.minHeight;
        select.classList.forEach(function (c) { if (/^(w-|col-|mb-|mt-|me-|ms-)/.test(c)) self.root.classList.add(c); });

        select.parentNode.insertBefore(this.root, select);
        this.root.appendChild(this.btn);
        this.root.appendChild(select);
        select.classList.add('a-sel__native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

        this.items = [];
        this.active = -1;
        this.typed = '';
        this.typedTimer = null;

        this.build();
        this.sync();
        this.bind();
    }

    CustomSelect.prototype.build = function () {
        var self = this, frag = document.createDocumentFragment();
        this.items = [];
        function addOption(o, group) {
            var li = el('div', 'a-sel__opt');
            li.setAttribute('role', 'option');
            li.dataset.value = o.value;
            li.dataset.index = String(o.index);
            li.id = self.id + '-' + o.index;
            if (group) li.classList.add('is-grouped');
            if (o.disabled || (group && group.disabled)) { li.classList.add('is-disabled'); li.setAttribute('aria-disabled', 'true'); }
            li.appendChild(el('span', 'a-sel__txt', o.textContent.trim() || ' '));
            li.insertAdjacentHTML('beforeend', '<svg class="a-sel__tick" width="14" height="14" viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10.5l4 4 8-9" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>');
            frag.appendChild(li);
            self.items.push({ el: li, option: o, text: li.textContent.toLowerCase() });
        }
        Array.prototype.forEach.call(this.select.children, function (c) {
            if (c.tagName === 'OPTGROUP') {
                var h = el('div', 'a-sel__group', c.label);
                h.setAttribute('role', 'presentation');
                frag.appendChild(h);
                Array.prototype.forEach.call(c.children, function (o) { addOption(o, c); });
            } else if (c.tagName === 'OPTION') {
                addOption(c, null);
            }
        });
        this.list.textContent = '';
        this.list.appendChild(frag);
        this.empty = el('div', 'a-sel__empty', 'No results');
        this.empty.hidden = true;
        this.list.appendChild(this.empty);
        this.hasSearch = this.items.length >= SEARCH_MIN;
        this.search.hidden = !this.hasSearch;
    };

    CustomSelect.prototype.sync = function () {
        var s = this.select, sel = s.options[s.selectedIndex];
        var empty = !sel || sel.value === '';
        this.label.textContent = sel ? sel.textContent.trim() : '';
        this.root.classList.toggle('is-placeholder', empty);
        this.root.classList.toggle('is-disabled', s.disabled);
        this.root.classList.toggle('is-invalid', s.classList.contains('is-invalid'));
        this.btn.disabled = s.disabled;
        this.items.forEach(function (it) {
            var on = it.option.selected;
            it.el.classList.toggle('is-selected', on);
            it.el.setAttribute('aria-selected', on ? 'true' : 'false');
        });
    };

    CustomSelect.prototype.bind = function () {
        var self = this, s = this.select;

        this.btn.addEventListener('click', function () { self.isOpen() ? self.close(true) : self.open(); });
        this.btn.addEventListener('keydown', function (e) { self.onBtnKey(e); });
        this.search.addEventListener('input', function () { self.filter(); });
        this.search.addEventListener('keydown', function (e) { self.onListKey(e); });
        this.list.addEventListener('click', function (e) {
            var o = e.target.closest('.a-sel__opt');
            if (o && !o.classList.contains('is-disabled')) self.choose(Number(o.dataset.index));
        });
        this.list.addEventListener('mousemove', function (e) {
            var o = e.target.closest('.a-sel__opt');
            if (o && !o.classList.contains('is-disabled')) self.setActive(self.items.findIndex(function (it) { return it.el === o; }), false);
        });
        this.panel.addEventListener('mousedown', function (e) { if (e.target !== self.search) e.preventDefault(); });

        s.addEventListener('change', function () { self.sync(); });
        s.addEventListener('focus', function () { self.btn.focus(); });
        if (s.form) s.form.addEventListener('reset', function () { setTimeout(function () { self.sync(); }, 0); });

        // options added/removed/relabelled by scripts (e.g. event lists loaded via ajax)
        this.mo = new MutationObserver(function () { self.build(); self.sync(); });
        this.mo.observe(s, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['disabled', 'label', 'class', 'selected'] });
        // `select.value = x` set programmatically doesn't fire events — poll cheaply on user-visible moments
        this.btn.addEventListener('focus', function () { self.sync(); });
    };

    CustomSelect.prototype.isOpen = function () { return !this.panel.hidden; };

    CustomSelect.prototype.visibleItems = function () {
        return this.items.filter(function (it) { return !it.el.hidden && !it.el.classList.contains('is-disabled'); });
    };

    CustomSelect.prototype.open = function () {
        if (this.select.disabled || this.isOpen()) return;
        if (openInst && openInst !== this) openInst.close(false);
        openInst = this;
        this.sync();
        this.host = this.root.closest('.modal') || document.body;
        this.host.appendChild(this.panel);
        this.panel.hidden = false;
        this.panel.style.minWidth = this.btn.offsetWidth + 'px';
        this.search.value = '';
        this.filter();
        this.root.classList.add('is-open');
        this.btn.setAttribute('aria-expanded', 'true');
        this.position();
        var idx = this.items.findIndex(function (it) { return it.option.selected && !it.el.classList.contains('is-disabled'); });
        if (idx < 0) { var v = this.visibleItems()[0]; idx = v ? this.items.indexOf(v) : -1; }
        this.setActive(idx, true);
        if (this.hasSearch) this.search.focus({ preventScroll: true });
    };

    CustomSelect.prototype.position = function () {
        var r = this.btn.getBoundingClientRect(), p = this.panel, gap = 6;
        p.style.maxHeight = '';
        var below = window.innerHeight - r.bottom - gap - 8, above = r.top - gap - 8;
        var h = Math.min(p.scrollHeight, 320);
        var up = h > below && above > below;
        var room = Math.max(160, Math.min(320, up ? above : below));
        p.style.maxHeight = room + 'px';
        p.style.left = Math.max(8, Math.min(r.left, window.innerWidth - p.offsetWidth - 8)) + 'px';
        var ph = Math.min(p.offsetHeight, room);
        p.style.top = (up ? r.top - ph - gap : r.bottom + gap) + 'px';
        p.classList.toggle('is-up', up);
    };

    CustomSelect.prototype.close = function (refocus) {
        if (!this.isOpen()) return;
        this.panel.hidden = true;
        this.root.classList.remove('is-open');
        this.btn.setAttribute('aria-expanded', 'false');
        this.btn.removeAttribute('aria-activedescendant');
        if (openInst === this) openInst = null;
        if (refocus) this.btn.focus({ preventScroll: true });
    };

    CustomSelect.prototype.filter = function () {
        var q = this.search.value.trim().toLowerCase(), shown = 0;
        this.items.forEach(function (it) {
            var hit = !q || it.text.indexOf(q) !== -1;
            it.el.hidden = !hit;
            if (hit) shown++;
        });
        Array.prototype.forEach.call(this.list.querySelectorAll('.a-sel__group'), function (g) {
            var n = g.nextElementSibling, any = false;
            while (n && n.classList.contains('a-sel__opt')) { if (!n.hidden) any = true; n = n.nextElementSibling; }
            g.hidden = !any;
        });
        this.empty.hidden = shown > 0;
        var v = this.visibleItems()[0];
        this.setActive(v ? this.items.indexOf(v) : -1, false);
        if (this.isOpen()) this.position();
    };

    CustomSelect.prototype.setActive = function (i, scroll) {
        if (this.items[this.active]) this.items[this.active].el.classList.remove('is-active');
        this.active = i;
        var it = this.items[i];
        if (!it) { this.btn.removeAttribute('aria-activedescendant'); return; }
        it.el.classList.add('is-active');
        this.btn.setAttribute('aria-activedescendant', it.el.id);
        if (scroll !== false) it.el.scrollIntoView({ block: 'nearest' });
    };

    CustomSelect.prototype.move = function (dir) {
        var vis = this.visibleItems();
        if (!vis.length) return;
        var cur = vis.indexOf(this.items[this.active]);
        var next = cur < 0 ? (dir > 0 ? 0 : vis.length - 1) : Math.max(0, Math.min(vis.length - 1, cur + dir));
        this.setActive(this.items.indexOf(vis[next]), true);
    };

    CustomSelect.prototype.choose = function (optionIndex) {
        var s = this.select, changed = s.selectedIndex !== optionIndex;
        s.selectedIndex = optionIndex;
        this.sync();
        this.close(true);
        if (changed) {
            s.dispatchEvent(new Event('input', { bubbles: true }));
            s.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    CustomSelect.prototype.onBtnKey = function (e) {
        var k = e.key;
        if (k === 'ArrowDown' || k === 'ArrowUp' || k === 'Enter' || k === ' ') {
            e.preventDefault();
            if (!this.isOpen()) this.open(); else if (k === 'Enter' || k === ' ') this.pick();
            else this.move(k === 'ArrowDown' ? 1 : -1);
        } else if (k === 'Escape') {
            this.close(true);
        } else if (k === 'Home' || k === 'End') {
            if (this.isOpen()) { e.preventDefault(); var v = this.visibleItems(); if (v.length) this.setActive(this.items.indexOf(k === 'Home' ? v[0] : v[v.length - 1]), true); }
        } else if (k === 'Tab') {
            this.close(false);
        } else if (k.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
            this.typeAhead(k);
        }
    };

    CustomSelect.prototype.onListKey = function (e) {
        var k = e.key;
        if (k === 'ArrowDown') { e.preventDefault(); this.move(1); }
        else if (k === 'ArrowUp') { e.preventDefault(); this.move(-1); }
        else if (k === 'Enter') { e.preventDefault(); this.pick(); }
        else if (k === 'Escape') { e.preventDefault(); e.stopPropagation(); this.close(true); }
        else if (k === 'Tab') { this.close(false); }
    };

    CustomSelect.prototype.pick = function () {
        var it = this.items[this.active];
        if (it && !it.el.hidden) this.choose(it.option.index);
    };

    CustomSelect.prototype.typeAhead = function (ch) {
        var self = this;
        this.typed += ch.toLowerCase();
        clearTimeout(this.typedTimer);
        this.typedTimer = setTimeout(function () { self.typed = ''; }, 600);
        var hit = this.items.find(function (it) { return !it.el.classList.contains('is-disabled') && it.text.indexOf(self.typed) === 0; });
        if (!hit) return;
        if (this.isOpen()) this.setActive(this.items.indexOf(hit), true);
        else this.choose(hit.option.index);
    };

    function enhance(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var list = scope.matches && scope.matches('select') ? [scope] : [];
        Array.prototype.push.apply(list, scope.querySelectorAll('select'));
        list.forEach(function (s) { if (eligible(s)) new CustomSelect(s); });
    }

    document.addEventListener('mousedown', function (e) {
        if (openInst && !openInst.root.contains(e.target) && !openInst.panel.contains(e.target)) openInst.close(false);
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && openInst) openInst.close(true); }, true);
    window.addEventListener('resize', function () { if (openInst) openInst.position(); });
    window.addEventListener('scroll', function (e) {
        if (openInst && !openInst.panel.contains(e.target)) openInst.position();
    }, true);

    new MutationObserver(function (muts) {
        muts.forEach(function (m) {
            m.addedNodes.forEach(function (n) { if (n.nodeType === 1 && !(openInst && openInst.panel === n)) enhance(n); });
        });
    }).observe(document.documentElement, { childList: true, subtree: true });

    window.AdminSelect = { enhance: enhance };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { enhance(); });
    else enhance();
})();
