# F03: Payment Plan legacy posting vs. v33 Maker

Tested application base: `59d5debd2edc167f07b9820111e1f026421655d8`. No application code change.
MariaDB 10.4.32, disposable loopback `:33391`, one synthetic MGI per schema,
`idr3` mapped to `111101`, initial available IDR `500.00`. Legacy is a direct
service (`610001`); Maker uses a synthetic AP bill so the rollback check
also exercises AP allocation. Workers use the normal `payment.post_journal`
and `payment-plan-v33.action` routes. The first worker wraps its request in
an outer transaction only to defer commit; the harness does not acquire the
financial fence for either worker.

| First commit | Holder / waiter / observer / final connection | Observed wait before release | Committed outcome |
|---|---|---|---|
| Maker `400.00` | `11 / 12 / 9 / 13` | `ppl_financial_contexts`, `PRIMARY`, company `1`; waiting trx `9264`, blocking trx `9263` | Legacy `100.01` rejected (`funds_insufficient`), payment unchanged at `PAID`, no legacy journal, one active hold and one AP allocation of `400.00`, available `100.00`. |
| Legacy `100.01` | `16 / 17 / 14 / 18` | `ppl_financial_contexts`, `PRIMARY`, company `1`; waiting trx `19232`, blocking trx `19230` | Legacy `POSTED`, exactly one legacy journal (Dr `610001`, Cr `111101`, `100.01` each). Maker rejected (`422 funds_insufficient`), request unchanged, no hold or AP allocation, available `399.99`. |

Focused PHPUnit run: **2 tests, 82 assertions**, exit `0`. Raw run artifacts
retained locally at `D:/ERP-Recovery/m1-f03-race.{log,jsonl,identity,exit,cleanup}`.
Snapshot JSONL SHA-256: `a1947c48116d72be7cc24822ca75e016aee52ff7e0baefc65b9e09d07ec0b584`.
Fixture schemas and the disposable MariaDB instance were cleaned up.
Targeted follow-up: **5 tests, 153 assertions**, exit `0` (both new orders,
both existing manual-journal/Maker orders, and the committed-hold legacy case).
No full suite was run.

F03 evidence is limited to this writer pair, these two orders and this
single-MGI IDR bank mapping. It does not verify other legacy writers,
operational acceptance, or a staging deployment.