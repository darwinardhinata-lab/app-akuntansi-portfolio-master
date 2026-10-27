# Combined implementation method and payment invariants

## Source verification

Located `D:\Project ERP\ERP AddOn-ERP Marv-Dard Merged-Claude.zip`: three design artifacts only (`Prompt_Cline_ERP_Marvel_Dard.md`, `golden_fixtures_v33.json`, `menu-map.csv`). The similarly named `audit-claude-erp.zip` is a different customs patch and was not applied. Neither package overrides repository evidence, existing controls or user instructions.

Original golden JSON is retained outside the application. Derived approval/financial fixtures store its SHA-256 and explicit scope. Approval cases have explicit `allRequests=[]` because those actions do not read competing transactions. Clock is injected as `2026-10-27T00:00:00.000Z`. No fabricated competing context is silently attributed to the author.

G042 and G050 are excluded from current PHP parity coverage. G042 requires another posted request referencing J1. G050 requires an explicitly described competing instruction to demonstrate the reservation gap. Full 66-case parity has NOT been reproduced in this work. Original expected outcomes must not be adjusted to hide missing context.

## Adopted layering

1. Pure PHP domain with injected clock and no database/HTTP/storage dependencies; legacy parity separated from hardened policy.
2. Persistence/API adapter owns ERP authentication, CSRF, stable event identity, optimistic versions, aggregate locks and attachment authorization. Never trust Sites identity headers from the internet.
3. Reuse actual vanilla `public/workspace.js` and styles via Blade only after ALL transport/navigation endpoints are adapted. Do not expose a UI with missing actions.
4. Legacy convergence only after source/writer reconciliation. Paid-but-unposted legacy requests remain posting obligations, not historical-only rows. Posted duplicates require traceable reconciliation, not deletion.

## Implemented subset (not full payment workflow)

- `V33ApprovalDomain`: initial Director approval and manager/GM petty acknowledgements, visibility, independence, division boundaries, state checks, fixed decision clock. Pure PHP, no Laravel boot required. Unsupported actions reject; no endpoint exposes this partial slice.
- Eight approval fixtures match original reference behavior and the PHP port. Reference command: `node scripts/v33-reference-approval-parity.mjs <reference-domain.mjs> <approval-fixture.json>` — 8/8 passed.
- `SettlementAmounts`: exact BCMath financial calculation with canonical decimal strings. Three golden examples covered; rejecting float inputs/fractional cents is explicitly hardened policy, not asserted as v33 legacy parity.
- Combined pure-domain tests: 10 tests / 60 assertions passed.
- Full suite before financial class/tests: 647 tests / 14,070 assertions passed. The increase from prior baseline also reflects eight new approval cases and time-sensitive existing assertions; do not equate assertion deltas with delivered business functions.

## Required transaction invariants — implementation still pending

- Lock canonical resource keys in deterministic order: invoice residual, contract/stage allocation, source account, petty balance, top-up source/destination and report period as applicable. Account-only locks are insufficient.
- Hold/pending/failed does NOT release reservation. Release requires verified no-debit evidence; confirmed debit consumes reservation and records the actual payment atomically. Never equate bank response failure with no debit.
- Posting key uses immutable payment event identity, never request version. Attachments/comments/retries must not create new payment or GL events.
- One existing PostingService/ledger only. Business COA approval gates activation of affected flows, not unrelated implementation or resolver tests.
- Default permitted payment accounts remain IDR3/USD1. No production mandate altered.

## UI URL review

`scripts/v33-ui-url-audit.py` inventoried 49 transport/navigation references in workspace JS, original authentication and workspace wrapper. `v33-ui-url-audit.csv` is a review queue, NOT a completeness claim. It includes direct attachment upload/download, historical downloads, transfer exports, print/navigation and original sign-in/out routes. All remain `requires-review-not-adapted`. Inspect imported scripts, service routes, HTML attributes and generated links before acceptance; replacing api() alone is insufficient.

## Acceptance / activation still open

Entire remaining state machine, weekly snapshots, reservations, locking, storage/identity/API, historical access, notifications/comments, UI/cetak, ERP posting acknowledgement and Laravel-vs-reference parity require implementation. MySQL race tests, actual browser/permission acceptance and restore/control totals are still outstanding. No new module activated; no claim of complete Payment Plan or 151-menu delivery.