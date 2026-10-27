# Filament / Livewire adoption — installed, acceptance incomplete

## Latest superseding verification

Security advisory block below describes historical installation state. Targeted patches now yield **Composer audit 0**, Laravel **12.70.1**, Filament/Livewire unchanged. Existing frontend build and npm audit pass; tooling lock added. Actual Livewire HTTP regression validates signed snapshot/update with permission revoke, inactive company, logout, flag/upload rejection and CSRF 419. Full suite **714 tests / 14,553 assertions**, oracle **66/66**. See dependency-security-triage.md for evidence and remaining gaps. Browser acceptance and complete ERP backend remain open; no status promoted to verified.

## Versions and installation evidence

- Working branch: `feat/marvel-dard-integration`; previous user/B01 work retained. No commit/push/merge.
- PHP CLI 8.2.12 with BCMath, intl, fileinfo, GD, mbstring, XML and ZIP. Apache configuration points to the same XAMPP PHP directory; actual web SAPI/extension response has NOT been inspected.
- Laravel remains **12.61.1**. Composer **2.10.3** executed using existing root composer.phar (not modified); composer executable absent on PATH.
- Targeted require dry-run then install: `php composer.phar require "filament/filament:^5.0" "livewire/livewire:^4.0" --no-scripts --no-interaction --no-progress` (dry run adds `--dry-run`). Resolved **Filament 5.11.0 / Livewire 4.4.8**.
- 35 packages added; **zero existing package versions changed**, independently compared HEAD lockfile to new lockfile. Large lock diff also contains refreshed metadata; do not mistake it for package upgrades. No global composer update/setup.
- `composer validate --no-check-publish` and `check-platform-reqs` pass. Autoload dump and package discovery run explicitly in isolated SQLite testing environment. No migration/setup script run against user database.
- `filament:assets --no-interaction` published versioned vendor JS/CSS/fonts successfully. Existing Bootstrap layouts/Vite entry untouched. NPM/Vite build NOT executed: node_modules and npm lock absent; panel uses published package assets, no custom theme. Browser asset-loading/CSS isolation remains acceptance work.

## Security advisory blocker

Composer audit reports **34 advisories across 9 existing packages**: guzzlehttp/guzzle (9), guzzlehttp/psr7 (2), laravel/framework (3), league/commonmark (12), league/flysystem (1), maatwebsite/excel (1), phpoffice/phpspreadsheet (4), symfony/http-foundation (1), symfony/yaml (1). None of these package versions changed during this install. JSON audit evidence saved outside webroot at `D:\xampp\tmp\erp-integration-reference\composer-audit-filament.json`. This is a release blocker requiring targeted security triage and compatible patches; no blanket upgrade or ignore-platform/security bypass performed.

## Panel / identity / authorization

- Panel id `erp`, path `/erp-panel`, guard `web`, existing users/sessions. No separate admin login/user/tenant database.
- `PLATFORM_FILAMENT_ENABLED=false` in config and .env.example; actual .env untouched. HTTP panel off returns 404; guest on returns 401. Existing ERP login remains the entry point.
- `User::canAccessPanel()` and persistent EnsureErpPanelAccess use ErpPanelAccess: re-query user, selected active membership, exact MGI company and sole-company condition, explicit Platform permission `erp.panel.access`. No ADMIN title bypass and no payment-role grant.
- Separate `division.view/create/update` catalog entries; seeder creates permission definitions only, no automatic assignment. DivisionPolicy covers model/action authorization. Persistent middleware re-runs on Livewire requests; service and Resource checks repeat at mutation time.
- Existing users schema has no confirmed active/disabled field. Deleted user, revoked company/permission and inactive company fail closed. A new universal user-disable policy must be designed against existing authentication; do not claim nonexistent active-field enforcement.
- `config/livewire.php` disables global asset injection, preventing automatic Alpine on legacy layouts. Global temporary uploads deliberately reject (404 off / 403 on) until a private attachment use case is accepted. No FileUpload, exports, bulk delete, inline edit or relation manager enabled.
- Persistent reactive HTTP replay, upload signing/preview and real browser session-expiry tests remain outstanding; component tests alone do not prove these.

## First complete master slice

Existing `master_divisi` / MasterDivisi reused; no primary key or schema change, no duplicate master. Filament Resource routes:

- `/erp-panel/divisions` — searchable/sortable paginated table.
- `/erp-panel/divisions/create` — create through DivisionService.
- `/erp-panel/divisions/{record}` — view.
- `/erp-panel/divisions/{record}/edit` — update through DivisionService.

DivisionService normalizes uppercase as legacy, validates code/name/active and code uniqueness, locks existing record, rechecks permission and records audit inside the same transaction. Delete is denied by Resource and model policy. No editable table columns/bulk mutations. ID/EN/ZH labels added.

This is a shared single-MGI master, not newly multi-tenant data. Legacy DivisiController remains unchanged and must be converged later; panel policy is NOT a claim that legacy routes now share service protection. Panel rejects multi-company configuration rather than leaking shared division data.

Dashboard widget reports actual master count only and links to same scoped list; it does NOT invent financial balances or payment tasks. Full payment/operation dashboard still pending authoritative query adapters.

## Evidence

- Early permission test fixture failed missing required permission resource/action fields; corrected fixture, not runtime guard.
- Early Livewire test mounted without session because test harness disables middleware, causing 403/null instance. Test harness attaches existing test session; runtime membership requirement unchanged.
- Panel/Livewire group **2 tests / 21 assertions**: flag/guest/access, revoke permission/membership, create/audit, view, successful update, revoke update permission after mount, reject mutation when flag disabled.
- Latest full suite: **709 tests / 14,522 assertions, exit 0**, SQLite :memory:. Node legacy oracle **66/66** after dependency changes. Syntax/diff checks and Composer validation pass.
- Menu ID 18: `implemented-pending-acceptance`, presentation_driver=filament, Resource/service/permission/test paths attached. No verified promotion; other 150 entries retain prior evidence/state.

## Remaining implementation and acceptance

Material/party/warehouse/unit Resources; payment dashboard/inbox adapter; backend reservation/allocation/attachment/stable-event posting; dedicated v33 Blade and full URL adaptation; operations and legacy convergence remain open. Browser 360/390/768, keyboard/print/three-language visual, MySQL concurrency, restore/control totals and release package acceptance NOT done. No new financial capability or panel activated.

## References actually consulted

- https://filamentphp.com/docs/5.x/introduction/installation
- https://filamentphp.com/docs/5.x/users/overview
- https://livewire.laravel.com/docs/4.x/installation
- Installed source: Filament Panel HasMiddleware persistence, Resource authorization, Create/EditRecord hooks; Livewire InitialRender test harness and FileUploadConfiguration.

## Safe rollback

Turn flag off while retaining existing ledger/payment protections. No schema change in this UI increment. Before removing packages, remove only this panel provider, User contract/method and panel-specific code via reviewed patch; do not reset user edits or revert earlier accounting changes. Keep composer.json/lock paired. Published vendor assets may be regenerated from the lock; do not delete unrelated public files.