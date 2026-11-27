# F03 evidence — warehouse inbound dated D+1

Status: **IN_PROGRESS**. The future-date defect is confirmed for this
synthetic MGI scenario. The date guard is a separate review candidate; it
was not part of the approved three-PR merge package.

## Merge package reference

Integration `d9b3537eec4b964d025f01f9665a26cf88aea9f7`; #32
`bbb099ffd591467af9f47c1f70a7fa23de513381`; #33
`9b8497f2d73017e54618f0566e8d5a918abc4027`; #34 fast-forwarded to
`c12d160cb2d8e2b8f54c5d941898bab77158a570`. The new #34 has the old #34
as first parent, exact #33 as second parent, and does not include #32.
Merging in order #32 → #33 → #34 produces tree
`74da43b325e188c10d29e46891b07f9a59c0b22f`, identical to tested simulation
`b1e752bc24cc399a3720468359bfedf3bee7fac8` (9 tests/356 assertions, exits 0).
Darwin approved the exact heads. Integration advanced through merge commits
`c8c31deb1279d0c2f584a246c0685ab4e475ac67` (#32),
`af11b22ed935ecb4954a469af0ff9a48af6d11fa` (#33), and
`74e137bd88368c468e0757953b8e72a995b142c9` (#34). Each had the preceding
integration tip and approved head as parents, in that order. Final tree
matched exactly; no repeat of the nine-test package run was needed.

## Observed behavior

MariaDB 10.4.32 on isolated loopback `:33395`, fresh schema for each order.
Application clock fixed to 10:00 Asia/Jakarta on 27 November 2026 (D), then
advanced to 10:00 on 28 November 2026 (D+1) in the test process only. Both
routes ran on D using real HTTP test dispatch, authentication and a real
Maker reservation. Starting actual/available `500.00`, no hold; inbound
bank credit `100.01` dated D+1, Maker `400.00`. Sequential committed orders,
not a concurrency test. Each snapshot used a distinct physical connection.

| Order | End of D | D+1 before further writes |
| --- | --- | --- |
| Maker → inbound | Maker HTTP 200. Inbound redirects with `funds_insufficient`; no inbound GL or ledger, stock `0` / MAC `0.00`. Actual `500.00`, active hold `400.00`, available `100.00`. | Actual `500.00`, hold `400.00` active, available `100.00`. |
| Inbound → Maker | Inbound succeeds and commits D+1 journal (Dr `11200`, Cr mapped IDR3 `111003`, each `100.01`), stock `1` / MAC `100.01` and an `IN` ledger row. GL is not yet in D's balance. Maker HTTP 200; actual `500.00`, active hold `400.00`, available `100.00`. | Same GL and hold, now actual `399.99` and available **`-0.01`**. |

Probe passed **2 tests / 74 assertions**, exit `0`, by asserting the observed
behavior, not a fix. Raw evidence: `D:/ERP-Recovery/m1-f03-future-20261127-evidence.jsonl`,
SHA-256 `83311b32c30df1df70b51f391d35395a50288c6367ec7ed5b79ad32c70b4c3e8`.
Disposable schema and instance removed. No staging or operational writes.

## Approved inbound posting policy and verification

With bank workflow ON, manual inbound rejects a positive final GL credit
to a protected bank COA mapped under an active configured `ppl.bank.*` key
when its GL date is after the business date. The guard runs under the
existing fence, before period-lock initialization, numbering or writes.
It uses the same Jakarta date source as bank projection and is independent
of the ERP projection flag. COA selection is unchanged. No manual inbound
draft-save path was found; only the posting route is affected.

- RED on the merged base: both D+1 tests failed the new date-rule assertion
  (2 tests / 52 assertions, exit `1`); Maker-first hit capacity, inbound-first
  was accepted.
- GREEN on disposable `:33395`: **2 tests / 90 assertions, exit `0`**. The
  real inbound and Maker routes were exercised in both orders. The inbound
  POST returned `future_protected_bank_credit` without write SQL; fresh
  connections on D and D+1 found no inbound journal or ledger, qty `0`, MAC
  `0.00`, actual `500.00`, active hold `400.00`, and available `100.00`.
- Disposable `:33394`: **10 tests / 264 assertions, exit `0`**. At 00:30
  Jakarta, same-day `100.00` succeeded and left available `0.00`; future
  non-bank inbound succeeded without changing IDR3 capacity. Also covered
  workflow ON with ERP projection OFF, workflow OFF, existing rounded
  capacity boundaries and both existing Maker race scenarios.
- Inventory, accounting period and PO inbound route regression:
  **22 tests / 115 assertions, exit `0`**. No full suite run.
- Raw GREEN snapshots under `D:/ERP-Recovery/`:
  `m1-f03-date-guard-20261127-green.jsonl`,
  `m1-f03-date-boundaries-20261127-green.jsonl`, and
  `m1-f03-date-boundaries-20261127-green-race.jsonl`.

This closes the reproduced D+1 gap only for the candidate inbound writer;
other writers, the separate `transfer_masuk` COA-policy decision, and
operational acceptance remain open. Disposable instances were cleaned up.