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

## Remaining gates

**Sequential writer closure only**: no overlapping maker/manual race claim. Next actionable
R1 prerequisite: two normal writer workers in both commit orders with third-observer InnoDB
wait and fresh final projection evidence. Update/delete/import/opening/FX writers are not
covered. No full-suite claim. Candidate saved separately, **TAHAN MERGE** pending overlap
evidence/review; no PR or integration merge. This is actionable R1 work, not an invented
blocker requiring diversion to M2/M3. Human UAT **DEFERRED**; production **NO-GO**.