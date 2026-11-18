# U08/UI-06 create-only source review and independent M3 proof — 17 November 2026

## PCS v1 decision — 18 November 2026

The continuation conversation explicitly records Darwin's delegation of R1 design and sets literal `PCS` (pieces) → `PCS` at factor 1, with no conversion and no new signature prerequisite. This is the application contract for the first create-only increment; other units are unsupported. It supersedes the unresolved unit decision and closed-Simpan statements in the historical section below. Runtime implementation and evidence: `D:\xampp\htdocs\app-akuntansi-portfolio-master\docs\integration\u08-pcs-save-20261118.md`.

## Outcome

**IN_PROGRESS; import Simpan remains unavailable.** Create-only/reject-existing is now explicitly requested; merge and blank-field update decisions no longer gate this slice. Missing proved source/unit mapping remains the blocker. No import writer, main migration, browser commit claim, concurrency claim, or live grants. Operational NO-GO; Finance8912/8913 untouched; human UAT DEFERRED.

Prior `D:\ERP-Recovery\u08-preview-budget-20261117\final-manifest.json` verified before editing:128 entries/zero mismatches. Preserve all historical browser failures, including upload422; 33 browser cases remain preview-only historical evidence. New raw evidence: `D:\ERP-Recovery\u08-create-contract-20261117`.

## Source-established contract

All source refs below are relative to absolute root `D:\xampp\htdocs\app-akuntansi-portfolio-master`.

| Concern | Source and consequence |
| --- | --- |
| Create validation | `app\Modules\Manufacturing\Http\Controllers\AuxiliaryMaterialController.php:44-49`: required code string max50/unique, name max255, unit max20; optional category/color max100, hs_code max50, description/english_name max255, specification text; nullable nonnegative numeric meters_per_roll. No dedicated master-create service/canonical-unit validator found in reviewed paths. |
| Identity/scope | `database\migrations\2026_09_30_140001_create_mfg_auxiliary_materials_and_issue_tables.php:13-29`: global unique material_code, no company_id. `app\Filament\Resources\AuxiliaryMaterialResource.php:34-48`: single-MGI gate plus material.view; create denied. No per-company duplicate namespaces. DB collation/equality and unique index, not preview exact-string candidates, must control commit conflicts. Migration does not pin column collation; verify actual master collation before implementing commit. |
| Precision | Same schema DECIMAL(20,2); `app\Modules\Manufacturing\Models\AuxiliaryMaterial.php:11-13` decimal:2. Reject non-representable values rather than silently round/float-cast. Leading-zero codes stay strings. Preview preserves values wider than master precision. |
| Unit/category | Schema/controller accept free strings, category optional, unit required in controller. No proved source namespace-to-master unit/alias/conversion crosswalk located in reviewed app/migration/doc searches. Free-text acceptance does not prove canonical identity or conversion equivalence. |
| Account/state | Schema/controller explicitly specify account114004, active true, stock/cost zero: existing contract, not an invented importer default. Save-time COA validity still needs checking. No import stock/cost/journal updates authorized. |
| Permission | `database\seeders\PlatformPermissionSeeder.php`: added catalog-only material.create using firstOrCreate; no role grants. material.view still read-only. Future Simpan must freshly check material.create plus current single-MGI scope. Catalog presence alone enables no write. |
| Excluded legacy | `app\Modules\Manufacturing\Imports\AuxiliaryMaterialImport.php:17-33`: trim, updateOrCreate, float cast, default unit/account. Unmodified; not reused for create-only. |

## Concrete remaining decision

Approve **exact source unit literals as canonical with zero conversion**, or supply authoritative versioned source namespace + unit crosswalk. Provide approved source identifiers/unit samples including ambiguous aliases. No fuzzy matching or guessed conversion.

Synthetic decision examples (not observed customer source rows):

- Row7 `000017`, PCS, meters12.75: representable; need proof that this source PCS denotes master PCS, or approval of exact-literal/no-conversion scope.
- Row8 `000018`, ROLL, meters12.75: meters per roll cannot infer PCS quantity; block invalid dimensional conversion.
- Row9 `000019`, MTR versus METER: alias equivalence not proved by free-text schema; map explicitly or reject alias.
- Row10 `000020`, meters12.345: reject at master scale2; preserving value requires separate approved precision/schema change, not rounding.
- Row11 AUX-01 versus latest master aux-01: use actual DB collation/constraint, not SQLite preview identity. Never numerically collapse0001 to1.

No immutable upload-bound batch, save-time reparse, per-row save conflicts, transaction writer, idempotency or simultaneous import has been implemented/proven. Browser upload-to-save, material.create revocation, post-preview conflicts, failed-import-row rollback and concurrent imports remain pending. M3 evidence below is NOT import atomicity evidence.

## Ready fallback: manufacturing supplier → PO via existing service

Extended `tests\Integration\MaterialProcurementMysqlTest.php` using MaterialProcurementService createRequest/submitRequest/approveRequest/createOrderFromRequest, synthetic creator/approver allowlists and fresh disposable migrations. Manufacturing Supplier ID is not platform Party ID: no crosswalk inferred from matching names/codes.

- Same-name KNITTER and inactive supplier rejected; every table's row snapshot unchanged. Service requires active RAW_MATERIAL.
- Revoked PO-create allowlist rejected by service, no mutation. Existing M3 authorization evidence, not material.create authorization proof.
- Two identical PO lines exhaust one approved PR: first writes, second fails; transaction restores all table rows including PR qty_ordered, histories and document-sequence rows. Snapshots exclude auto-increment counters; gaps are possible.
- Success preserves supplier/material/source-PR-detail IDs, PCS, amount25.50 and qty_ordered2.00. Retry rejected because source PR exhausted; not general idempotency/parallel-write proof.
- Master code000017, meters12.75, stock5.25 and cost1.50 unchanged. Journal headers/details and inventory/manufacturing ledgers empty. Existing M3 service uses float arithmetic internally; no import arithmetic introduced.
- Each test creates/drops only its own random mgi_fresh_m2test_[16hex] database. MariaDB10.4.32. Final SHOW DATABASES LIKE mgi_fresh_m2test_% found none. No Finance process/server changes.

## Fresh raw verification

- Focused preview + PartyLinkage **13 tests/508 assertions PASS exit0**, focused.log/.exit; includes catalog existence/no-granted-role assertions.
- Disposable MariaDB **2 tests/33 assertions PASS exit0**, m3-mariadb.log/.exit,24.915s/62MiB. Initial fixture enum KNITTING failed exit2; corrected to schema KNITTER. Initial raw log/exit retained as m3-mariadb-initial-failed.*.
- Fresh default full suite **809 tests/16,687 assertions PASS exit0**, full-suite.log/.exit,2m35.834s/198MiB. Integration excluded by default, reported separately. Earlier809/16685 remains historical.
- Three changed PHP files lint PASS; scoped tracked diff-check0. New final manifest binds changed sources/reports/raw evidence without rewriting prior evidence.

**One next action:** obtain exact-literal/no-conversion or authoritative source-unit mapping approval, then implement server-reparsed create-only transaction against actual MariaDB identity with failed-row rollback/retry/concurrent duplicate tests. U08/UI-06 remain IN_PROGRESS.