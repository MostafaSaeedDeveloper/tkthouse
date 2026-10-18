/* Admin multiselect: turns <div data-a-msel><select multiple></select></div> into a chips + searchable-list control.
   The native <select multiple> stays in the DOM (hidden once enhanced) and is the single source of truth, so the form
   submits exactly what it did before and the control degrades to the plain list without JavaScript. */
(function () {
    'use strict';

    var uid = 0;
    var openInst = null;

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text != null) n.textContent = text;
        return n;
    }

    function MultiSelect(root) {
        var self = this;
        this.root = root;
        this.select = root.querySelector('select');
        this.id = 'amsel-' + (++uid);
        this.placeholder = root.dataset.placeholder || 'Select…';
        this.emptyText = root.dataset.emptyText || '';
        this.items = Array.prototype.map.call(this.select.options, function (o) { return { value: o.value, label: o.textContent.trim(), option: o }; });
        this.active = -1;

        this.box = el('div', 'a-msel__box');
        this.chips = el('span', 'a-msel__chips');
        this.input = el('input', 'a-msel__search');
        this.input.type = 'text';
        this.input.setAttribute('role', 'combobox');
        this.input.setAttribute('aria-expanded', 'false');
        this.input.setAttribute('aria-controls', this.id + '-list');
        this.input.setAttribute('aria-autocomplete', 'list');
        this.input.autocomplete = 'off';
        this.chev = el('i', 'fa fa-chevron-down a-msel__chev');
        this.chev.setAttribute('aria-hidden', 'true');
        this.box.appendChild(this.chips);
        this.box.appendChild(this.input);
        this.box.appendChild(this.chev);

        this.panel = el('div', 'a-msel__panel');
        this.panel.hidden = true;
        var tools = el('div', 'a-msel__tools');
        this.count = el('span', 'a-msel__count');
        this.allBtn = el('button', 'a-msel__link', 'Select all'); this.allBtn.type = 'button';
        this.clearBtn = el('button', 'a-msel__link', 'Clear'); this.clearBtn.type = 'button';
        tools.appendChild(this.count); tools.appendChild(this.allBtn); tools.appendChild(this.clearBtn);
        this.list = el('ul', 'a-msel__list');
        this.list.id = this.id + '-list';
        this.list.setAttribute('role', 'listbox');
        this.list.setAttribute('aria-multiselectable', 'true');
        this.none = el('p', 'a-msel__none', 'No match');
        this.none.hidden = true;
        this.panel.appendChild(tools); this.panel.appendChild(this.list); this.panel.appendChild(this.none);

        root.appendChild(this.box);
        root.appendChild(this.panel);
        root.classList.add('is-enhanced');

        this.items.forEach(function (item, i) {
            var li = el('li', 'a-msel__opt');
            li.setAttribute('role', 'option');
            li.id = self.id + '-o' + i;
            var cb = el('span', 'a-msel__tick');
            cb.setAttribute('aria-hidden', 'true');
            li.appendChild(cb);
            li.appendChild(el('span', 'a-msel__lbl', item.label));
            li.addEventListener('mousedown', function (e) { e.preventDefault(); });
            li.addEventListener('click', function () { self.toggle(item); self.input.focus(); });
            item.li = li;
            self.list.appendChild(li);
        });

        this.box.addEventListener('mousedown', function (e) {
            if (e.target.closest('.a-msel__x')) return;
            if (e.target !== self.input) { e.preventDefault(); self.input.focus(); }
        });
        this.input.addEventListener('focus', function () { self.open(); });
        this.input.addEventListener('input', function () { self.open(); self.filter(); });
        this.input.addEventListener('keydown', function (e) { self.key(e); });
        this.allBtn.addEventListener('click', function () { self.setAll(true); self.input.focus(); });
        this.clearBtn.addEventListener('click', function () { self.setAll(false); self.input.focus(); });
        this.select.addEventListener('change', function () { self.render(); });
        this.panel.addEventListener('mousedown', function (e) { e.preventDefault(); });

        this.render();
    }

    MultiSelect.prototype.selected = function () { return this.items.filter(function (i) { return i.option.selected; }); };

    MultiSelect.prototype.toggle = function (item) {
        item.option.selected = !item.option.selected;
        this.changed();
    };

    MultiSelect.prototype.setAll = function (on) {
        var q = this.input.value.trim().toLowerCase();
        this.items.forEach(function (i) { if (!q || i.label.toLowerCase().indexOf(q) !== -1) i.option.selected = on; });
        this.changed();
    };

    MultiSelect.prototype.changed = function () {
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
        this.render();
    };

    MultiSelect.prototype.render = function () {
        var self = this;
        var sel = this.selected();
        this.chips.textContent = '';
        sel.forEach(function (item) {
            var chip = el('span', 'a-msel__chip');
            chip.appendChild(el('span', 'a-msel__chiptext', item.label));
            var x = el('button', 'a-msel__x', '×');
            x.type = 'button';
            x.setAttribute('aria-label', 'Remove ' + item.label);
            x.addEventListener('click', function () { self.toggle(item); self.input.focus(); });
            chip.appendChild(x);
            self.chips.appendChild(chip);
        });
        this.input.placeholder = sel.length ? '' : (this.emptyText || this.placeholder);
        this.items.forEach(function (item) {
            item.li.classList.toggle('is-selected', item.option.selected);
            item.li.setAttribute('aria-selected', item.option.selected ? 'true' : 'false');
        });
        this.count.textContent = sel.length + ' of ' + this.items.length + ' selected';
    };

    MultiSelect.prototype.visible = function () { return this.items.filter(function (i) { return !i.li.hidden; }); };

    MultiSelect.prototype.filter = function () {
        var q = this.input.value.trim().toLowerCase();
        this.items.forEach(function (i) { i.li.hidden = !!q && i.label.toLowerCase().indexOf(q) === -1; });
        var v = this.visible();
        this.none.hidden = v.length > 0;
        this.setActive(v.length ? 0 : -1);
    };

    MultiSelect.prototype.setActive = function (idx) {
        var v = this.visible();
        this.active = idx;
        this.items.forEach(function (i) { i.li.classList.remove('is-active'); });
        if (idx >= 0 && v[idx]) {
            v[idx].li.classList.add('is-active');
            this.input.setAttribute('aria-activedescendant', v[idx].li.id);
            if (v[idx].li.scrollIntoView) v[idx].li.scrollIntoView({ block: 'nearest' });
        } else {
            this.input.removeAttribute('aria-activedescendant');
        }
    };

    MultiSelect.prototype.open = function () {
        if (openInst && openInst !== this) openInst.close();
        openInst = this;
        if (this.panel.hidden) {
            this.panel.hidden = false;
            this.root.classList.add('is-open');
            this.input.setAttribute('aria-expanded', 'true');
            this.filter();
        }
    };

    MultiSelect.prototype.close = function () {
        this.panel.hidden = true;
        this.root.classList.remove('is-open');
        this.input.setAttribute('aria-expanded', 'false');
        this.input.value = '';
        this.items.forEach(function (i) { i.li.hidden = false; });
        if (openInst === this) openInst = null;
    };

    MultiSelect.prototype.key = function (e) {
        var v = this.visible();
        if (e.key === 'ArrowDown') { e.preventDefault(); this.open(); this.setActive(Math.min(v.length - 1, this.active + 1)); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); this.open(); this.setActive(Math.max(0, this.active - 1)); }
        else if (e.key === 'Enter') { e.preventDefault(); if (!this.panel.hidden && v[this.active]) this.toggle(v[this.active]); }
        else if (e.key === 'Escape') { if (!this.panel.hidden) { e.preventDefault(); this.close(); } }
        else if (e.key === 'Backspace' && !this.input.value) { var s = this.selected(); if (s.length) this.toggle(s[s.length - 1]); }
        else if (e.key === 'Tab') { this.close(); }
    };

    function init(scope) {
        Array.prototype.forEach.call((scope || document).querySelectorAll('[data-a-msel]:not(.is-enhanced)'), function (root) {
            if (root.querySelector('select')) new MultiSelect(root);
        });
    }

    document.addEventListener('mousedown', function (e) {
        if (openInst && !openInst.root.contains(e.target)) openInst.close();
    });

    window.AdminMultiSelect = { init: init };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(); });
    else init();
})();
