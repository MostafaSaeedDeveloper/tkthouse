# Admin / public mobile checks

Headless-Chromium scripts (390px viewport) against a **local** app. They need a local admin user `auditadmin` / `Passw0rd!aud`
(role `super_admin`, all permissions) and the demo events/orders; never run them against production.

```bash
cd tests/browser/admin-mobile && npm install playwright-core@1.49.1
node mobile-audit.mjs shots [/path,/path]   # horizontal overflow, off-screen elements, small tap targets, tiny text + screenshots
node filters.mjs                            # collapsible filters on phones, unchanged on desktop
```
