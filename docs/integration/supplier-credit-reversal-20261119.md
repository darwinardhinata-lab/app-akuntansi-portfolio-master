# AC03/F11 — supplier price credit cancellation candidate

Base: integration merge `4182523c56311cf89cb553c74110996e8e0073d0` (PR #4).
Branch: `feat/supplier-credit-void-20261119`.
Status: **draft review candidate; human UAT DEFERRED; production NO-GO**.

## Contract

One MGI-owned unpaid IDR Bill and one bound supplier price allowance, using the approved
`AP_SUPPLIER_PRICE_DISCOUNT_CREDIT_NOTE -> 510003` policy; no inventory or tax movements.
Authenticated POST `/purchase-bills/{bill}/supplier-credit/{binding}/reverse` accepts
`{"posting_date":"2026-02-02","reason":"Supplier withdrew the price allowance"}`.
Requires current FINANCE role, current company membership and explicit company-scoped
`supplier_credit.reverse`. Permission seeding only adds the catalog entry; it grants nothing.
Existing feature flags and sole-active-MGI limitations remain in force.

Financial fence is acquired before period, invoice, supplier, binding and journal locks.
The Bill is loaded after the fence; active/consumed AP or bank holds, PPL payment events,
APPROVED/PAID/POSTED legacy payments and unresolved legacy allocations block new reversal.
Supplier/PO/GRN identity, original sealed intent and exact approved two-line source are
rechecked. Current residual provenance must be resolved. Existing accounting-period mutex
and destination-date rules apply; the original month may be closed if destination is open.

One outer transaction creates the opposite IDR lines through PostingService, seals its
deterministic `supplier-credit-reversal:{binding}` intent, links `reversal_of` to the original,
and inserts supplier_credit_reversals plus attribution decision audit where applicable.
The immutable reversal row contains status, actor, date, reason, payload hash, source/approval
snapshot and correction amount. Unique binding, reversal journal and reconciliation keys
prevent duplicate evidence. Rollback refuses to remove used reversal evidence.

Original journal, binding and reconciliation/approval history are not rewritten. A proposed
row remains historical proposed; an approved row remains historical approved. The linked
reversal marks the credit cancelled and fully compensates its effective attribution.
It is not an active economic reduction merely because the historical status says approved.
Both first approval and approval retry are rejected after reversal. New proposal is rejected
because the original seal is reversed. Generic AP cancel remains unsupported for this source.

Projection verifies binding, snapshot, original seal, reversal intent/header and exact opposite
lines before accepting compensation; invalid evidence yields a blocking exception. Valid
approved history contributes +20.00 and its correction -20.00 (net zero); pending correction
is zero. Neither original nor reversal is counted again as a generic journal adjustment.
Thus pending 200.50 stays 200.50; approved 180.50 returns to 200.50, once, with only one new
reversal journal. No separate journal is posted for attribution correction.

Identical date/reason retry returns the persisted reversal after current authorization,
ownership and evidence checks; changed payload conflicts. A completed retry is a read of
prior evidence, not a new posting, so later period closure/payment does not create a new
reversal. No reverse-of-reversal or partial cancellation is provided.

## Verification and evidence

Evidence root: `D:\ERP-Recovery\supplier-credit-void-20261119`.

- `focused-final.log/.exit/.xml`: **17 tests / 496 assertions**, exit 0, SQLite.
- `full-publish.log/.exit/.xml`: **807 tests / 16,481 assertions**, exit 0, SQLite.
- `maria-publish.log/.exit/.xml`: **13 tests / 303 assertions**, exit 0, MariaDB 10.4.32,
  fresh private instance port 13352. Each test creates/drops its owned random schema.
- `race-publish.jsonl`: freshly executed reversal-first, approval-first and payment-first
  schedules. Each requires actual INNODB_LOCK_WAITS proof on ppl_financial_contexts, distinct
  holder/worker IDs and a third observer connection inspecting committed final state.
  Reversal-first rejects waiting approval; approval-first succeeds then is fully compensated;
  payment-first commits PAID and rejects waiting reversal after its fence wait. Residual is
  200.50 with no active credit reduction in all final states; payment-first has no reversal.
- Normal authenticated HTTP route covers pending/approved/no-proposal reversal and retries,
  closed destination, unauthorized permission/role, source ownership/seal, PAID invoice,
  active hold, payment event, legacy evidence, tampered reversal and preservation checks.
  Approval uses the existing authenticated ApReconciliation application entry point.
- Failure injection uses a test-only DB listener, throwing after reversal and audit SQL but
  before commit; exact financial snapshots (including projection) match the pre-attempt
  state. Retry after injection succeeds once. No production failure-injection switch exists.
- Migration test removes only the unused new table, recreates the pre-increment schema shape
  with real binding/approved fixtures, upgrades via normal migrate, checks exact preservation,
  then proves duplicate reversal rejection (MariaDB 1062). Not a production restore rehearsal.
- PHP syntax and diff whitespace checks pass. Final publication/hash manifest is stored outside
  git at the evidence root. Original Windows worktree and staging are not edited.

The first MariaDB run found an audit action longer than existing VARCHAR(20); shortened to
`credit_reversed`. SQLite did not detect that length error. Initial fixture/helper mistakes,
one transient missing observed lock wait, and a disposable instance readiness failure are
retained in earlier logs, not counted as successful proof. The final complete MariaDB run
proves all three schedules. No full browser or human Finance acceptance is claimed.

## Remaining AC03/F11 / release boundaries

PR #4 positive posting/source tests remain in the full suite, but old approval-maker race
evidence is not a fresh proof for the changed projection. This increment freshly proves
reversal/approval/payment-fence schedules only. Broader maker/reservation schedules, arbitrary
raw writers/corruption and exhaustive legacy statuses remain outside the matrix.
No multi-company support, foreign currency, inventory/tax credit, partial reversal, browser
cancellation UI, production data restore, operational grant/mapping or deployment is included.
AC03/F11 end-to-end Finance/UAT acceptance remains OPEN. Merge requires explicit Darwin
approval; activation requires separate release checks and authorization.