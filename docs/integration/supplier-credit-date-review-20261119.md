# PR #5 targeted date / AP capacity review — 2026-11-19

Reviewed head: `e154fdd58161287d37c6c3c20b99f4144aefa402`.
Evidence: `D:\ERP-Recovery\supplier-credit-date-review-20261119`.

## Reproduction and policy

Fresh disposable MariaDB normal authenticated reversal HTTP calls reproduced both defects:
with business date 2026-11-19 and original credit journal 2026-01-02, requests for 2026-01-01
and 2026-11-20 returned 200. Both immediately changed approved residual 180.50 to 200.50;
the actual PurchaseBillAllocation maker source and reserve path accepted 190.00. The current
MGI date request also succeeded as expected. `maria-red.log/.exit`, `date-red.jsonl`: 3 tests,
15 assertions, 2 failures. Reserve probes are rolled back and do not contaminate snapshots.

AccountingPeriodGuard checks explicit valid dates and closed months, not chronology or future
effectiveness. PurchaseReturnReversal defaults to company timezone (fallback Asia/Jakarta) and
rejects dates before its original return; it supplies no scheduled AP reversal contract.
MGI fresh-start policy sets Asia/Jakarta; ErpBankBalance uses that business date and excludes
future GL movements. No scheduled cancellation activation contract was found.

Per the user's explicit conservative boundary, new supplier-credit cancellation now requires:
**original journal business date <= reversal date <= current MGI business date**, with the
existing destination period open. Dates are compared as validated ISO dates, original persisted
datetime is reduced to YYYY-MM-DD, and current date is computed in company timezone with
Asia/Jakarta fallback after the financial fence. No schema or projection algorithm change.
Prior completed idempotent retries remain reads of existing evidence, not new scheduled writes.
Historical bad rows are not rewritten/backfilled; no operational database was inspected.

## Green evidence

- `maria-green.log/.exit/.xml`: **12 tests / 273 assertions**, exit 0, disposable MariaDB 10.4.32.
  Includes inherited pending/approved, source guards, closed period, rollback and integrity tests.
- `date-green.jsonl`: before-original and future return 409 with exact unchanged financial
  snapshots, no reversal journal, residual/maker capacity 180.50 and 190.00 maker rejected.
  Current MGI date returns 200, journal dated 2026-11-19, residual/capacity 200.50 and maker
  190.00 accepted. These tests freeze UTC at 2026-11-18 18:30, already November 19 in Jakarta,
  explicitly guarding against mistakenly using UTC/app date.
- `focused.log/.exit/.xml`: **29 tests / 757 assertions**, exit 0, affected SQLite regression.
- PHP syntax / diff checks pass. Final publication and source/evidence hashes saved outside git.

Only affected regressions rerun; no new full-suite or concurrent schedule run claimed. Previous
PR #5 overlap evidence is retained for unchanged fencing/projection paths; the new chronology
predicate is read-only under that fence. No universal race or writer coverage implied.

Verdict for this finding: **SIAP MERGE** after this patch, subject to explicit Darwin merge
instruction and review of other findings. PR remains draft. Human UAT DEFERRED; production NO-GO.