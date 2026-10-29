# R1 Finance selectors and print — 2026-10-29

## Delivered slice

Continues local bank selector/print work present at session start (base `3542163`), without dependency changes. Locked versions inspected: Laravel 12.70.1, Filament 5.11.0, Livewire 4.4.8.

- Existing Filament `/erp-panel/ap-reconciliation` now offers searchable, separately paginated invoice, journal and return-detail selectors (20 rows/page), and paginated decisions. Invoice options show supplier/date/status/residual/block; returns show document/item/date/status/remaining quantity and value; journals show document/date/remaining capacity or service blocking reason. No typed database primary keys are required.
- Journal advisory capacity reuses the reconciliation source validator and approved allocations. Changing invoice/return resets dependent selections. Existing application services retain final permission, amount, source snapshot, independent approval and financial-fence validation. No journals or balances are created by lookups. Single-company MGI/panel permission scope remains mandatory.
- Existing bank screen `/payment-plan/bank` gains paginated AP lookup `/payment-plan/api/purchase-bills`, document/supplier/date/status/residual/block labels and a print link. This remains a development workspace, **not the completed final Filament/v33 payment UI**.
- `/payment-plan/bank/{request-uuid}/print` enforces company scope and domain visibility, includes approved lines/journal and decision metadata, and sets private/no-store. Fixed local print query referencing nonexistent `ppl_workflow_events`: actual audit source is `ppl_events`. It prints attachment names, **not embedded multi-photo evidence**.

## Actual validation

Commands run from `D:\xampp\htdocs\app-akuntansi-portfolio-master`:

| Command/check | Actual result / evidence boundary |
|---|---|
| `php vendor/phpunit/phpunit/phpunit --no-progress` | PASS: 721 tests / 14,754 assertions; configured Unit + Feature, SQLite in-memory. Log `%TEMP%\r1-full-suite.log`. |
| `$env:AP_MYSQL_TESTS='1'; php vendor/phpunit/phpunit/phpunit tests/Integration/ApReconciliationMysqlTest.php --no-progress` | PASS: 3 tests / 85 assertions; random disposable MySQL schema, including independent competing approval worker. Log `%TEMP%\r1-mysql.log`. Not competing bank makers or all AP writers. |
| `npm run build` | PASS: Vite 7.3.7, 58 modules. Log `%TEMP%\r1-build.log`. Generated assets excluded from commit. |
| `php -l` changed PHP controller/page/service/tests; `git diff --check` | PASS. Views exercised by full HTTP/Livewire suite. |

Initial bank HTTP execution exposed missing audit table, then the fixture expected a free-text payee although AP correctly uses authoritative supplier. Fixed table query and corrected AP-specific assertion, not authorization/domain guards. Full suite above is after fixes.

Regression adds signed page-derived Livewire HTTP search/selection updates, invoice second-page visibility, advisory capacity 50.00 to 0.00 across invoices, revoked lookup permission, AP lookup pagination/search, print visibility/flag checks, and full direct/AP HTTP posting paths. Attachments use `Storage::fake('local')` in bank HTTP tests, not staging objects.

## Demonstration (isolated development/UAT only)

1. Use existing configured MGI membership/permissions and financial context; do not enable flags or migrate a user database merely to demonstrate this change.
2. Open `/erp-panel/ap-reconciliation` as a user with `erp.panel.access` and `ap.reconcile.view`; choose invoice, optional PO return detail, then eligible journal. Search and page independently. Supply amount/quantity/reason/internal evidence reference. A distinct permitted approver approves the proposal. Inspect residual/exceptions; ambiguous source remains blocked.
3. Open `/payment-plan/bank` with the appropriate PPL role. Finance can search AP by number/supplier and choose a nonblocked invoice, or create direct service. Follow existing request evidence → submit → Director request approval → Finance settlement/evidence → Director settlement approval → maker → GM release → debit evidence/paid → accountant ERP acknowledgment. These stages are covered via HTTP, not a newly certified browser flow.
4. Select request and use Print, or open its `/payment-plan/bank/{uuid}/print` route. Inspect authoritative AP payee, approved accounting lines and ERP journal ID. Browser print/mobile layout still needs visual acceptance.

## R1 acceptance matrix

| Acceptance | Status |
|---|---|
| AC01 direct service / AC02 partial AP | PASS at HTTP/domain layer only; final Finance browser acceptance NOT RUN. |
| AC03 attribution | PASS scoped SQLite/MySQL regressions; comprehensive writer/credit lifecycle acceptance remains open. |
| AC04 bank/AP races | NOT RUN for competing makers/cross-account invoice writers; reconciliation approval race PASS only. |
| AC05 termination/restart | NOT RUN; state/idempotency tests are not process-crash evidence. |
| AC06 permissions | PASS covered HTTP/Livewire lookup/print/revocation cases; full browser/security matrix not certified. |
| AC07 private storage staging | NOT RUN; bank tests use fake disk. |
| AC08 desktop/mobile/print/photos/three languages | NOT RUN in browser; localized selector labels and print HTTP rendering only. |
| AC09 restore/reconciliation | NOT RUN on staging DB+objects. |
| AC10 Finance sign-off/release pack | NOT RUN. |

## Remaining R1 blockers / next actions

No authenticated isolated browser/UAT fixture or staging storage/restore target was established in this session (local Chrome binary exists; this is not browser evidence). No real database migration, production activation or transfer was performed.

Next: replace the development bank workspace with the final existing-panel/v33-adapted screen; connect reconciliation evidence through an authorized private attachment context (currently a required internal reference only); complete preview/audit and multi-item/photo printing; execute dedicated maker/AP writer MySQL races and true process-termination recovery; then authenticated desktop/mobile/print and staging physical-storage/restore acceptance. Selector pagination currently replaces visible options; retain/review selected document across navigation during browser polishing. Technical source blocking reasons are still rule codes rather than translated descriptions. R1 is not complete or production-ready.