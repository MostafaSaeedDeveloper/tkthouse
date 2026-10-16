        <!-- intl-tel-input -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@22.0.2/build/css/intlTelInput.css">
        <style>
        /* ── intl-tel-input dark theme ── */
        .iti { display: block !important; width: 100%; }

        /* Flag trigger button */
        .iti__selected-country {
            background: transparent !important;
            border-right: 1px solid rgba(255,255,255,0.09) !important;
            padding: 0 8px 0 8px !important;
            gap: 3px !important;
            display: flex !important;
            align-items: center !important;
        }
        /* reorder: flag → dial code → arrow */
        .iti__selected-country .iti__flag        { order: 1 !important; }
        .iti__selected-country .iti__selected-dial-code { order: 2 !important; }
        .iti__selected-country .iti__arrow       { order: 3 !important; margin-left: 3px !important; }

        .iti__selected-country:hover,
        .iti__selected-country[aria-expanded="true"] { background: rgba(215,166,0,0.08) !important; }
        .iti__selected-dial-code { color: #dbe4ff !important; font-size: 13px !important; font-weight: 500 !important; }
        .iti__arrow { border-top-color: #7e849b !important; }
        .iti--open .iti__arrow { border-bottom-color: #7e849b !important; }

        /* ── Nuke every possible white wrapper the library injects ── */
        .iti__flag-container   { background: transparent !important; }

        /* v22 wraps search+list in iti__dropdown-content */
        .iti__dropdown-content {
            background: #181821 !important;
            border: 1px solid rgba(255,255,255,0.35) !important;
            border-radius: 10px !important;
            overflow: hidden !important;
            box-shadow: 0 16px 48px rgba(0,0,0,0.75) !important;
            z-index: 999999 !important;
            padding: 0 !important;
        }

        /* The <ul> itself — no extra border, parent handles it */
        .iti__country-list {
            background: #181821 !important;
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            z-index: 999999 !important;
            max-height: 280px !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            padding: 4px 0 !important;
            margin: 0 !important;
            /* fallback border when iti__dropdown-content absent */
            outline: 1px solid rgba(255,255,255,0.35) !important;
            outline-offset: -1px !important;
        }
        .iti__country-list::-webkit-scrollbar { width: 4px; }
        .iti__country-list::-webkit-scrollbar-track { background: transparent; }
        .iti__country-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.14); border-radius: 99px; }

        /* Search input */
        .iti__search-input-wrapper { background: #181821 !important; padding: 8px 10px 6px !important; }
        .iti__search-input {
            display: block !important;
            width: 100% !important;
            height: 44px !important;
            background: #0f0f18 !important;
            border: 1px solid rgba(255,255,255,0.13) !important;
            border-radius: 10px !important;
            color: #dbe4ff !important;
            font-size: 13px !important;
            padding: 0 14px !important;
            outline: none !important;
            box-sizing: border-box !important;
            transition: border-color .18s !important;
            margin: 0 !important;
        }
        .iti__search-input:focus { border-color: rgba(215,166,0,0.5) !important; }
        .iti__search-input::placeholder { color: #7e849b !important; }

        /* Divider */
        .iti__divider { border-color: rgba(255,255,255,0.08) !important; margin: 2px 0 !important; }

        /* Country rows */
        .iti__country {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            padding: 0 14px !important;
            height: 46px !important;
            color: #dbe4ff !important;
            font-size: 13px !important;
            background: transparent !important;
            transition: background .1s !important;
        }
        .iti__country:hover    { background: rgba(214,166,0,0.18) !important; }
        .iti__country.iti__highlight { background: rgba(214,166,0,0.18) !important; }
        .iti__country.iti__active    { background: rgba(214,166,0,0.10) !important; }

        .iti__flag-box   { flex-shrink: 0 !important; }
        .iti__country-name {
            color: #dbe4ff !important;
            flex: 1 !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }
        .iti__dial-code { color: #7e849b !important; font-size: 12px !important; flex-shrink: 0 !important; }
        </style>
