# F03 — committed residual writer evidence, 27 November 2026

**IN_PROGRESS.** The characterization below predates the guard candidate. Probed source `b116887f9de96b9f599d51682471aa8c42e2ad78` is now the second parent of merge #36, `57ef9418ffcf52978fb031db592a7f6225676685`, with the same tree `80b720e014ff0751e29599f14396a062259ff58c`. The separate candidate is based on that verified merge.

MariaDB 10.4.32, dedicated loopback `:33397`, one fresh schema per case. D = 2026-11-27 and D+1 = 2026-11-28 in Asia/Jakarta. Each case used the actual Maker action and authenticated writer route. Four independent connections per case read committed state after Maker, projected at D and D+1 before the writer, then at D and D+1 after the writer. This is not a lock-wait race. Active IDR3 hold stayed 400.00 and Maker stayed `bank_prepared` in all snapshots.

| Writer | Committed evidence | Available before writer (D / D+1) | Available after writer (D / D+1) |
| --- | --- | --- | --- |
| `purchase-bills.destroy` | Route-created, unallocated bill contained a protected bank DEBET of 100.01; deletion removed the bill and GL. | 100.00 / 100.00 | **−0.01 / −0.01** |
| `invoice-payment-allocations.reverse` | Synthetic IDR receipt of 100.01 allocated via HTTP; reversal linked a compensating journal with protected bank KREDIT; invoice became UNPAID. | 100.00 / 100.00 | **−0.01 / −0.01** |
| `warehouse.outbound.store` | D+1 stock issue committed; stock 1 → 0, DEBET IDR3 `111003`, KREDIT inventory `11200`, each 100.01. No decrease under distinct mappings. Does not certify outbound cancellation or correction. | 100.00 / 100.00 | 100.00 / 200.01 |
| `account.store_opening_balance` | Replaced a D+1 bank DEBET of 100.01 with a non-bank DEBET while a synthetic already-posted D+1 bank outflow of 100.01 remained. This is an interaction with existing history, not a new future credit via the form. | 100.00 / 100.00 | 100.00 / **−0.01** |

4 tests / 142 assertions, exit 0. A pass confirms the observation, not protection. Raw snapshots: `D:/ERP-Recovery/m1-f03-residual-20261127-green.jsonl`, SHA-256 `34dfa11746651fb0e2774321078fd91069218867979b7d02509691e9ebafc0b6`; `.log` and `.exit` at the same stem. Identity and cleanup: `D:/ERP-Recovery/m1-f03-residual-20261127.identity` and `D:/ERP-Recovery/m1-f03-residual-20261127.cleanup`. No listener or data directory remained. No staging, main DB, real transaction or operational fixture used.

## Separate guard candidate

The transaction takes the financial fence before the period and source locks. Bill deletion checks the combined opposite GL lines of the journals it will remove from the current projection before reversing stock or deleting anything. AR reversal checks the final GL lines before creating the reversal; the original posting date rule, allocation checks, audit and authorization are unchanged.

MariaDB 10.4.32 on disposable loopback `:33397`: **6 tests / 213 assertions**, no skips. Each of the following outcomes was observed for both writers. In all six cases an observer confirmed the waiter was blocked on `ppl_financial_contexts.PRIMARY` for the company. Balances and source effects were then read from a new connection.

| First commit | Requested decrease | Committed result | Available | Active hold |
| --- | --- | --- | --- | --- |
| Maker | 100.01 | Writer rejected; original bill/detail/GL or receipt/GL/allocation/invoice unchanged; no reversal journal | 100.00 | 400.00 |
| Writer | 100.01 | Writer committed; Maker rejected without a reservation or AP allocation | 399.99 | 0.00 |
| Maker | 100.00 | Writer committed; bill and GL deleted or reversal linked and invoice reopened | 0.00 | 400.00 |

The red run on the unpatched tree failed four cases: the bill-delete overdraft and the three AR cases without a fence wait. Focused regressions: **64 tests / 428 assertions**. Raw candidate evidence is local at `D:/ERP-Recovery/m1-f03-residual-guard-20261127-green.jsonl` (SHA-256 `d31779b33912cc4f8ae795c96815c89cc6403cc72cf8190fe0e020816afb8e34`). Disposable listener and data directory were removed.

The opening replacement case requires a separate Accounting decision for future funding removed while a pre-existing outflow remains. Outbound evidence covers only the tested stock issue with distinct inventory and bank mappings; it does not cover corrections or cancellations. Purchase-return reversal reachability remains open. Do not correct history or deploy based on this draft.