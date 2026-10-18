# Security notice — exposed Fawaterak API key (rotate it)

**Status: the key must be treated as compromised. Removing it from the latest commit does NOT make it safe.**

## What happened
`database/seeders/FawaterakPaymentMethodsSeeder.php` shipped a real Fawaterak API key as the default value of `config('services.fawaterak.api_key', …)`. The value is present in the Git history of this repository (9 commits found with `git log --all -S<value>`; use the command below to list them) and therefore in every clone, fork, CI cache and backup made since. This report deliberately does not print the key.

## What this branch changed
* The seeder no longer contains any credential; `FAWATERAK_API_KEY` is read from the environment only. When the environment has no key, methods are created **inactive** with an empty key.
* Both payment seeders (`Fawaterak…`, `Paymob…`) now **only insert missing methods**: they never overwrite a configured method, and the Paymob seeder no longer deletes the legacy `visa` / `wallet` / `card` codes.
* `tests/Feature/SecretHygieneTest.php` fails the build if a seeder contains a long secret-looking literal, and stores only a **SHA-256 fingerprint** of the leaked key so it cannot be re-introduced anywhere in `app/ config/ database/ routes/ tests/ docs/ resources/views/ bootstrap/ README .env.example`.
* A scan of the working tree and of the full history found no other real credential (only obvious test fixtures such as `sk_live_SUPERSECRET_…` inside tests, which are fake and used to prove masking). Nothing was printed to logs, this document, or the PR.

## Required actions (owner of the Fawaterak account) — before any production release
1. **Rotate / regenerate** the API key in the Fawaterak dashboard (Integrations → API Key) and **revoke the old one**. Do this even if you believe the repository was private: history, forks, CI logs and backups may hold it.
2. Put the new key only in the environment (`FAWATERAK_API_KEY`) or, preferably, in Settings → Payments → Providers & Merchant Accounts (stored encrypted, never shown again). Never in code, tests, docs, tickets or chat.
3. Review Fawaterak's request logs for the old key's use since the first exposure; check for payments/invoices you do not recognise.
4. If the old key was used on production, re-verify every Fawaterak method after rotation (Providers → *Test connection*).
5. (Optional hardening, does **not** replace rotation) rewrite history with `git filter-repo --replace-text` and force-push, then have every collaborator re-clone. This breaks open PR/branch references and must be coordinated; the key stays valid for anyone who already has a copy until it is revoked.

## Finding the commits without exposing the value
```
KEY=$(read -rs -p 'paste key: ' k; echo "$k")      # type it, it is not echoed or saved
git log --all --oneline -S"$KEY"                    # list commits that introduced/removed it
```
## Guard rails going forward
* No real secrets in seeders, tests, fixtures, docs, screenshots or PR text — use fake values like `TEST-ONLY-NOT-A-SECRET`.
* Enable GitHub secret scanning / push protection on the repository (Settings → Code security).
* Browser QA and runbook evidence must be redacted before being attached anywhere.
