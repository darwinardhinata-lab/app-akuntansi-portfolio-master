# F03 IDR3 writer inventory — manual warehouse inbound, 27 November 2026

F03 remains **IN_PROGRESS**. This addendum updates the writer inventory for
`warehouse.inbound.store`. The tested source was local integration snapshot
`d9b3537eec4b964d025f01f9665a26cf88aea9f7`; this is not a claim about the
current remote base. PR #33 was not merged by this audit.

| Writer | Evidence | Disposition |
| --- | --- | --- |
| `warehouse.inbound.store` (`transfer_masuk`) | Baseline committed a `100.01` bank credit against `100.00` available. Separate candidate now guards mapped bank capacity and passes both Maker orders in disposable MariaDB. | Candidate verified; not merged or deployed. Accounting offset policy remains separate. |

## Committed baseline

The HTTP feature probe used an authenticated synthetic MGI user, took the
receipt reference from the rendered form and submitted quantity `1` at cost
`100.01`. The fixture seeded the committed Maker hold (`bank_prepared` with
an active `400.00` reservation); it did not run a Maker action or a race.
Local test flags: primary navigation ON, bank workflow and ERP projection ON,
v33 posting OFF. No operational environment or records were used.

| Observation | Result |
| --- | --- |
| Server and connections | MariaDB `10.4.32`, disposable `127.0.0.1:33393`; writer `10`, new reader `11`; writer transaction level `0` after POST |
| HTTP | Form `200` with bank option `111101`; POST `302` to inbound list with success flash |
| GL | Receipt `IN-20261127-DD39`, linked by `source_doc_no`; journal `JRN-20261127-000002` (`GRN-20260103-0001`): Dr inventory `11200` / Cr IDR3 `111101`, each `100.01` |
| Balance | Actual `500.00` → `399.99`; hold unchanged `400.00` active; available `100.00` → `-0.01` |
| Stock | Quantity `0` → `1`; MAC `0.00` → `100.01`; one `IN` row, qty and running qty `1`, unit cost, total cost, running value and MAC `100.01` |

Baseline probe: 1 test / 23 assertions, exit 0 (asserts observed unsafe
behavior, not a fix). Existing inventory and accounting period regression:
18 tests / 77 assertions, exit 0. Disposable schema and server were removed.
Raw committed snapshot: `D:/ERP-Recovery/m1-f03-inbound-20261127-committed.json`;
SHA-256 `a21770d4c99c40676e9c047022f498acf348504c98e6b57061b96af82caeec71`.

## Verified candidate

Verified application/test commit: `89b5cd4ee6a431a09ebe65e9a0de6c416123d6ca`
on `review/m1-f03-warehouse-inbound-20261127`. The existing financial fence
and mapped-account capacity guard run before period-lock initialization,
journal numbering and any stock, stock-ledger or GL write. The guard and GL
insert share the same final two-decimal lines; no picker or server-side COA
eligibility rule was changed. The candidate fixture maps `idr3` to `111003`,
not the baseline's `111101`.

| Case | Fresh connection after the request |
| --- | --- |
| `100.01` and `100.005` against `100.00` available | `funds_insufficient`; no write SQL recorded, no posting/stock change; active `400.00` hold and `100.00` available unchanged |
| Bank `100.00` | GL and `IN` ledger committed; qty `1`, MAC `100.00`; active hold `400.00`, available `0.00` |
| Non-bank `100.01` (`610001`) | GL and `IN` ledger committed; qty `1`, MAC `100.01`; available `100.00` and hold unchanged |
| Maker `400.00` first | Holder/waiter/observer/final `23/24/21/25`, waiting trx `48869` on trx `48868`; inbound rejected, no stock/MAC change, ledger or GL; actual `500.00`, active hold and AP allocation `400.00`, available `100.00` |
| Inbound `100.01` first | Holder/waiter/observer/final `28/29/26/30`, waiting trx `58829` on trx `58826`; Maker rejected, no hold or AP allocation; actual and available `399.99`, qty `1`, MAC `100.01`, one `IN` row and Dr `11200` / Cr `111003` for `100.01` |

Both observed waits were on `ppl_financial_contexts.PRIMARY`, row `1`. Race
workers use the real HTTP routes; the first worker holds its outer transaction
open only to control commit order. The single-writer boundary cases use a
seeded committed hold; races exercise the actual Maker action.

- RED on unpatched source: 4 tests / 72 assertions, 2 expected failures, exit `1`.
- MariaDB GREEN on disposable `127.0.0.1:33394`: 6 tests / 176 assertions, exit `0`.
- Inventory, accounting-period and purchase-order inbound route regression:
  22 tests / 115 assertions, exit `0`. No full suite run.
- Raw local artifacts: `D:/ERP-Recovery/m1-f03-inbound-patch-20261127-green.jsonl`
  (`86dfc66acb82490e9a35746ecd7b36db15290275f8c20bbac9ac4459bb9aaacd`) and
  `D:/ERP-Recovery/m1-f03-inbound-patch-20261127-green-race.jsonl`
  (`a1bcdbed8748a11ad54bad92ca6febde7166da9b558956dacc0eff007844c840`), SHA-256.

This evidence covers one synthetic MGI, one mapped IDR account, this HTTP
writer and these tested transaction orders. It does not cover other writers,
future-dated projection behavior, staging acceptance or deployments.

## Separate Accounting decision: `transfer_masuk` offset policy

The picker lists all accounts and server validation only requires an offset.
Form help gives opening equity and stock-adjustment income as examples, not
an exclusive list. No authoritative allow/deny contract for bank offsets was
found in the reviewed route, form, tests or documentation. Technical
acceptance is not Accounting authorization.

- **If allowed:** confirm the business cases and permitted MGI COA/semantic
  mappings. The capacity guard is not by itself a COA policy.
- **If prohibited:** confirm the permitted offset set or classification for
  manual receipts. Enforce it server-side before numbering or writes and
  prove a normal POST cannot create a journal or change stock or the hold.

No Accounting allow/deny rule is implied by this patch. Active-writer
coverage remains open.