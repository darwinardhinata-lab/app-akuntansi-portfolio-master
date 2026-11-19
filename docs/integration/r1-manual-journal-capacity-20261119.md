# R1 active manual journal writer — 2026-11-19

Independent candidate from integration `4182523c56311cf89cb553c74110996e8e0073d0`, not
bundled into supplier-credit PR #5. Evidence root:
`D:\ERP-Recovery\r1-manual-journal-capacity-20261119`.

## Reproduction and minimal correction

Authenticated POST `jurnal.store` reaches active `JournalController::store`. It took the
financial fence but omitted assertBankCapacity. With idr3 actual 500.00, reservation 400.00
and available 100.00, a balanced manual journal crediting bank 200.00 succeeded, leaving
actual 300.00 and available **-100.00**. SQLite `red.log` and disposable MariaDB
`maria-red-ready.log` / `writer-maria-red-ready.jsonl` reproduce this. The regression test
fails because the normal route reports success rather than an error.

Patch reuses assertBankCapacity immediately after the financial fence and before posting.
No new policy, route, mapping or permissions. Existing UI rejection is 302 + session error.

Final disposable MariaDB `maria-publish.log/.exit/.xml`: **1 test / 9 assertions, exit 0**.
`writer-maria-publish.jsonl` proves rejected 200.00 leaves identical captured journals,
intents, holds, payment events, assets and balance. Affordable 100.00 succeeds once with
actual 400.00, reserved 400.00, available 0.00. Affected SQLite
`focused-publish.log/.exit/.xml`: **111 tests / 1,449 assertions, exit 0**.
PHP syntax and diff checks pass. Initial autoload/readiness failures are retained, not
behavioral evidence; final runs use a separate vendor copy and initialized owned database.

## Final overlapping transaction closure (supersedes earlier sequential-only gate)

Base reconfirmed against remote integration: `4182523c56311cf89cb553c74110996e8e0073d0`.
Two standalone PHP workers use the normal Laravel HTTP test client through route middleware,
authentication, request validation and controller dispatch. Maker uses persisted company/role
membership and an AP invoice request prepared and approved through normal routes (including
settlement attachment upload). Neither worker substitutes a direct hold/GL insertion for its
action. The AP invoice is 600.00 so bank capacity, not AP residual, decides the 400.00 maker.
These are in-process HTTP dispatches, not a live Apache/browser test.

The first worker holds an outer transaction after its actual mutation; the second starts while
that transaction is open. A third independent admin connection must observe the real wait
before releasing the first worker to commit. This deliberately orchestrates commit order;
it is not a sleep-only race claim. A fourth fresh connection reads final committed state.

Authoritative evidence: `race-final.jsonl`, `maria-final-races.log/.exit/.xml`.

| Schedule | Holder / waiter / observer / final reader | Observed wait | Committed result |
|---|---|---|---|
| Maker first | 12 / 13 / 10 / 14 | PRIMARY company 1 on ppl_financial_contexts | Maker 200, manual 302 + funds_insufficient session error; actual 500.00, reserved 400.00, available 100.00 |
| Manual first | 17 / 18 / 15 / 19 | PRIMARY company 1 on ppl_financial_contexts | Manual 302 + success, maker 422 funds_insufficient; actual 300.00, reserved 0.00, available 300.00 |

Assertions check baseline + expected journal headers/details, unchanged intent count, zero
payment events, reservation/AP allocation counts, maker status/version, and event increments.
Manual-first rejection leaves the exact maker row unchanged and no AP allocation, despite the
AP reserve check occurring before the bank capacity rejection inside the transaction. Maker-first
has one active 400.00 bank hold and one active 400.00 AP allocation, with no manual journal.
Manual-first has exactly one RACE-MANUAL journal (two balanced 200.00 lines), no hold/allocation.
The manual route has no explicit posting-intent key; intent counts remain zero, **not** an
invented sealed/idempotent manual-submit contract. Exactly once here means one successful call
produced one journal, not a guarantee against repeated manual form submissions.

No production fix beyond the guard in `1978e37` was required. Added concurrency regressions and
worker only. Initial readiness, standalone PHPUnit teardown, expected-intent-count and short
observer-window harness failures remain retained; only the named final runs are authoritative.
Worker cleanup closes connections; tests stop unfinished workers, remove release marker, purge
fresh reader and drop only their random `audit_manual_writer_<16 hex>` database.

Final verification on the publication tree:
- MariaDB disposable: **3 tests / 76 assertions**, exit 0 (both schedules + sequential regression).
- Related SQLite: **113 tests / 1,496 assertions**, exit 0.
- Full default SQLite suite: **799 tests / 16,247 assertions**, exit 0. This candidate does not
  include separate supplier-credit reversal PR #5, explaining different totals from that branch.
- PHP syntax and git diff checks pass; local manifests bind source and final evidence hashes.

**SIAP MERGE for this scoped R1 blocker**, pending human code review and explicit merge instruction.
Publish only as a separate draft PR to integration; no integration merge, PR #5 change, operational
DB mutation, grant, migration or deployment. Update/delete/import/opening/FX writers, crash/kill
recovery and arbitrary multi-company schedules remain outside this evidence. Human UAT
**DEFERRED**; production **NO-GO**. No claim that all R1 acceptance gates are closed.