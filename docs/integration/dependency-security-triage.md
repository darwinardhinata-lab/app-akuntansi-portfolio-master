# Dependency security triage — actual local verification

## Findings before patch

Saved native JSON: `composer-audit-before.json`; detailed per-advisory evidence: `composer-advisory-triage.csv` (ID, package/version, severity, production/dev scope, affected expression, selected fixed version, CVE/link and usage assessment).

**34 unique Composer advisory IDs**, not repeated source records: 1 critical, 15 high, 14 medium, 2 low, 2 unknown severity. 33 production-dependency findings and 1 development-only finding (symfony/yaml). Multiple advisory links/sources are NOT separately counted. Framework TokenGuard usage and direct untrusted Markdown/YAML reachability are not established; findings are confirmed version matches, not claims that every exploit path was demonstrated.

These are existing dependencies, NOT Filament advisories. Review before/after lockfiles confirmed Filament 5.11.0 and Livewire 4.4.8 unchanged.

## Targeted patch

Ran targeted dry-run then actual update with `--with-dependencies --minimal-changes --no-scripts --no-interaction --no-progress`, naming only the nine affected packages. No global update, ignore-advisory, platform bypass or root constraint relaxation. Composer allowed one supporting dependency change.

| Package | Before | Selected patched version |
|---|---|---|
| guzzlehttp/guzzle | 7.11.0 | 7.16.1 |
| guzzlehttp/psr7 | 2.11.0 | 2.13.2 |
| guzzlehttp/promises (supporting dependency) | 2.5.0 | 2.6.0 |
| laravel/framework | 12.61.1 | 12.70.1 |
| league/commonmark | 2.8.2 | 2.10.4 |
| league/flysystem | 3.34.0 | 3.36.0 |
| maatwebsite/excel | 3.1.69 | 3.1.70 |
| phpoffice/phpspreadsheet | 1.30.5 | 1.30.7 |
| symfony/http-foundation | 7.4.13 | 7.4.21 |
| symfony/yaml | 7.4.13 | 7.4.21 |

Selected versions are patched according to actual post-update audit, not asserted to be the minimum fixed release for every advisory. Affected ranges and advisory links retained in CSV permit individual review. Snapshot before security update saved outside webroot at `D:\xampp\tmp\erp-integration-reference\composer-lock-before-security.json`; it includes earlier Filament work and must not be replaced with HEAD when reviewing rollback.

## Actual checks

- `composer audit --format=json`: **0 advisories, exit 0**, saved as `composer-audit-after.json`.
- Composer validate / check-platform-reqs pass on PHP 8.2.12. Explicit package discovery under isolated testing SQLite passed. No user DB migration or .env changes.
- Full suite immediately after security update: **713 tests / 14,543 assertions**, exit 0.
- NPM tooling initially absent. Ran `npm.cmd install --ignore-scripts --no-audit --no-fund`, generated package-lock.json without changing package.json. Then explicit `npm.cmd run build`: **Vite 7.3.7, 58 modules, exit 0**. Existing CSS/JS entries unchanged. Build outputs generated locally under public/build.
- `npm audit --json`: **0 vulnerabilities, exit 0**; no dependency scripts automatically trusted.
- New real Livewire HTTP test gets signed snapshot from actual panel page, POSTs to actual update route, tests permission revocation, company inactive, logout, panel flag off and uploads (403 on/404 off). Real CSRF middleware enabled by switching app environment within test, missing token returns 419.
- Test development failures: component alias was generated rather than expected textual name; selected by signed memo path. Multiple HTTP requests within one PHPUnit process retained Livewire middleware request cache; flushState between simulated requests models actual request boundaries. Runtime guards unchanged.
- Latest full suite after reactive regression: **714 tests / 14,553 assertions**, exit 0; actual Node legacy oracle **66/66**; syntax and diff checks pass.

## Remaining risk / no-go

Clean audits address known catalog matches, not supply-chain/exploit proof or complete application security. Visual browser/mobile/print and real Apache runtime acceptance are not done. Real HTTP test uses Laravel kernel, not browser automation. User inactive field does not exist; only company inactive/membership/permission/session behavior established. ID tampering, export, lookups, session-timeout browser flows and MySQL concurrency still need dedicated coverage. Payment reservation/AP/DP/stable-event posting/private attachments and operation integration remain unfinished coding. No release/production activation.

Do not rollback by blindly restoring insecure dependencies. Prefer forward patch and preserve original user/B01 edits. If compatibility failure requires a temporary lock rollback, document reopened advisories and keep deployment blocked.