# AP return reconciliation — development checkpoint 2026-10-28

## Implemented scope

`/erp-panel/ap-reconciliation` now accepts an explicit return detail ID and quantity alongside source journal, invoice, amount, reason and internal evidence reference. The existing `ApReconciliation` use case handles both journal attribution and return attribution; no replacement return journal or ledger is created.

Approval requires an independent user with `ap.reconcile.approve`, an unchanged source snapshot, completed return, matching invoice/return PO, a unique journal with the return document number, positive IDR AP reduction, balanced journal, quantity/value consistency, available source capacity and invoice residual. Journal capacity is shared by allocations to the same payable account. Return-detail quantity is shared across invoices and journals. Decimal aggregation uses BCMath. Proposal and approval require a populated financial context and take its fence before invoice/source locks.

Partial allocations contribute only their approved amount. The PO-wide unresolved-return exception remains until every detail's quantity and value, and the return total, are fully allocated with unchanged supporting journals. Cancellation restores residual and the unresolved exception. Cancellation of a return attribution is blocked while any invoice on the PO has an active Payment Plan AP reservation. Mutated evidence produces a blocking exception rather than a guessed historical relation.

The screen includes ID/EN/ZH_CN labels. Evidence is still an internal document reference, not a verified uploaded attachment. Selection is explicit by ID; this is not the final v33 UI or a complete operational lookup experience.

## Writer coverage

The existing journal controller (base/FX create, update, delete and bulk delete), spreadsheet journal import, CSV journal import and deprecated PaymentPlanService now take the financial fence inside their outer transaction before document/journal mutation. Existing purchase bill deletion and return writer fence coverage is retained. This does not establish universal coordination for every raw SQL or operational writer; remaining writer inventory and bank/AP race scenarios are still acceptance work.

## Executed evidence

- `tests/Feature/ApReconciliationTest.php`: **2 tests / 35 assertions**, SQLite in-memory.
- Full configured suite: **721 tests / 14,699 assertions**, SQLite in-memory.
- `AP_MYSQL_TESTS=1` with `tests/Integration/ApReconciliationMysqlTest.php`: **3 tests / 68 assertions**. Each test creates a random `audit_ap_test_<hex>` schema, applies migrations there, and drops only that test-owned schema at teardown. No application/user database is selected for mutation.
- The MySQL test uses an independent PHP worker. One approval holds the financial fence while the competing worker waits; after commit, the competing approval rechecks capacity and rejects over-allocation. This proves that particular competing-return-approval scenario, not all banking or AP writer races.
- Real signed snapshots obtained from the Filament page submit proposal/approval through the Livewire update HTTP endpoint. Initial isolated `Livewire::test` failed with invalid snapshot structure; the regression now uses page-derived snapshots and normal session middleware rather than weakening authorization.
- Test source-return quantity is mutated to demonstrate blocking evidence drift; it is synthetic test data only.

## Compatibility observation

Strict MySQL fixture execution requires the attachment `created_at` migration column to accept its current-time default when omitted. The original create migration now declares `useCurrent()`; this only affects newly migrated isolated databases, not previously migrated installations. Existing storage service explicitly supplies timestamps; no production schema alteration is claimed.

## Still open

Browser/mobile/print acceptance; final v33 forms; operational pagination/search; verified evidence uploads and revision linkage; credit-note lifecycle; universal writer coordination; races between AP reservation and return/manual/import operations; restore rehearsal; DP/USD/petty and remaining ERP modules. Flags stay off by default, and this checkpoint does not mark F04/F11 or any menu verified.