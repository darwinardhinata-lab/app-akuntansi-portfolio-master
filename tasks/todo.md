# Allocation evidence reconciliation

- [x] Add read-only command with JSON-lines output and nonzero exit on findings.
- [x] Test clean/missing/mismatched evidence and read-only behavior.
- [x] Run focused/full regressions and update audit notes.

Acceptance: no writes, no automatic historical matching, bounded processing (cursor), AR reversals independently checked. Requires existing allocation migrations. Dependencies: previously implemented AR/AP allocations.

## Document reconciliation increment
- [x] Optional document scan: active payments, approved AR credits, negative balance and status mismatch.
- [x] Check source AR/AP journal against document total; never repair data automatically.
- [x] Test reversal exclusion, partial/full/overpayment, cancelled documents and read-only behavior.
- [x] Run regression tests and document exclusions (AP return credits, historical matching and global GL).

## Final closure work (requested; not yet implemented)
- [x] Approve maker/checker matrix: maker differs from approver; payer/poster may equal approver under their own allowlists. Legacy unknown provenance remains blocked.
- [ ] Implement trusted actor provenance, stale-approval invalidation and self-approval tests.
- [x] Approve global database calendar-month policy: each journal balanced; separate FINANCE close/reopen allowlists, reason and audit events, no ADMIN bypass.
- [ ] Implement period lifecycle and all mutation guards; verify concurrency.
  - [x] Preview journal guard: old/new dates, detail parents, protected bulk mutations and shared GLOBAL mutex.
  - [x] Preview source guards: stock sync/reversal, invoice cancellation, MRN void.
  - [ ] Complete warehouse/material/remaining source/import mutation paths and lock-order/connection review.
    - [x] Warehouse zero-cost paths, MRN receive/import, journal CSV/Excel entry guards; main material helper/caller lock-order increment.
    - [ ] Remaining manufacturing QC/finishing/stitching/reversal dates, core/GRN/payment/command-import ordering and connection audit.
  - [ ] Actual MySQL close/post concurrency tests before operational enablement.
    - [x] Close-first/header-post actual local MySQL test on random isolated fixture; stale snapshot bug reproduced and corrected with locking read.
    - [ ] Post-first, source/detail/import races and complete coverage before enablement.
- [ ] Inventory historical ambiguity; implement explicit AP credit allocation and GL tie-out.
- [ ] Approve refund VAT/discount/rounding and paid-invoice cash versus credit policy.
- [ ] Implement cumulative refund quotas and audited idempotent disbursement.
- [ ] Obtain official versioned CEISA contract and authorized sandbox access; implement contract tests.
- [ ] Configure isolated MySQL and browser fixtures; run actual concurrency/UAT.
- [ ] Perform physical printer/scan acceptance with operator and record results.
- [ ] Final verification, review deploy prerequisites and commit/push only with accurate completion scope.

Maker/checker first slice tested: trusted maker/editor creation, approval under existing row lock, same maker/latest editor rejection, unknown maker rejection and approval metadata cleared on controller edits/COA/rekening changes. Full SQLite: 475 tests, 12,338 assertions passed. Still open: provenance verification workflow for legacy/public/bulk-import records, downstream PAID/POSTED approval-proof enforcement, UI alignment and direct/bulk mutation coverage. Do not call full maker/checker complete yet.

Follow-up: approval fingerprint enforcement is now implemented in PaymentPlanWorkflow::realized, used for PAID and preflight/locked posting checks. Remaining limitations: legacy actor verification/reapproval, controlled corrections after PAID, UI, file-content provenance and non-workflow direct database writes. Never treat legacy status APPROVED/PAID alone as approval proof.

PAID correction now invalidates approval, and independent reapproval endpoint/form preserves PAID with realization validation and audit. Tested rollback and no journal creation. Period lifecycle schema/service/endpoint foundation is implemented but FAIL-CLOSED outside testing; all mutation guards and shared locking with writers must be completed before enabling. Latest full SQLite: 480 tests, 12,389 assertions passed. Neither full maker/checker nor closed-period enforcement is declared complete.