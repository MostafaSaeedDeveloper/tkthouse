/* TKT House V2 — page lifecycle for soft (SPA-like) navigation.
   A module registers an init(root) that returns an optional cleanup function:

     TktPage.register('name', function (root) { ...; return function cleanup() { ... }; });

   init runs on first load and again after every soft navigation, cleanup runs before the old content is
   removed. Nodes inside the swapped container are discarded with the content, so cleanup is only needed
   for anything attached to document, window, timers or media queries. */
(function () {
    'use strict';

    var modules = [];
    var cleanups = [];

    function mount(root) {
        modules.forEach(function (m) { run(m, root); });
    }

    function run(m, root) {
        try {
            var cleanup = m.init(root || document);
            if (typeof cleanup === 'function') cleanups.push(cleanup);
        } catch (err) {
            if (window.console) console.error('[TktPage] ' + m.name + ' failed to initialise', err);
        }
    }

    function unmount() {
        while (cleanups.length) {
            try { cleanups.pop()(); } catch (err) { if (window.console) console.error('[TktPage] cleanup failed', err); }
        }
    }

    window.TktPage = {
        register: function (name, init) {
            var m = { name: name, init: init };
            modules.push(m);
            // Registered after the page was mounted (a script loaded late): start it right away.
            if (mounted) run(m, document);
        },
        mount: function (root) { mounted = true; mount(root); },
        unmount: unmount
    };

    var mounted = false;
    var start = function () { if (!mounted) window.TktPage.mount(document); };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
