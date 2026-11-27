# F03 IDR3 writer inventory — manual warehouse inbound, 27 November 2026

F03 remains **IN_PROGRESS**. This addendum updates the writer inventory for
`warehouse.inbound.store`. The tested source was local integration snapshot
`d9b3537eec4b964d025f01f9665a26cf88aea9f7`; this is not a claim about the
current remote base. PR #33 was not merged by this audit.

| Writer | Evidence | Disposition |
| --- | --- | --- |
| `warehouse.inbound.store` (`transfer_masuk`) | Normal GET offered the mapped bank COA; normal POST committed a `100.01` bank credit against `100.00` available. Independent MariaDB read confirmed `-0.01` available with the hold still active. | Active writer gap; not covered. Accounting must decide whether a bank COA is an authorized manual receipt offset. |

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

## Accounting decision required

The picker lists all accounts and server validation only requires an offset.
Form help gives opening equity and stock-adjustment income as examples, not
an exclusive list. No authoritative allow/deny contract for bank offsets was
found in the reviewed route, form, tests or documentation. Technical
acceptance is not Accounting authorization.

- **If allowed:** confirm the business case and permitted MGI COA/semantic
  mapping. Use the existing fence and capacity guard on the final rounded GL
  bank credit before journal numbering or writes. Prove `100.01` rejects
  atomically, `100.00` succeeds with hold intact, and a non-bank offset still
  works. Exercise both Maker orders and record the real InnoDB lock wait.
- **If prohibited:** confirm the permitted offset set or classification for
  manual receipts. Enforce it server-side before numbering or writes and
  prove a normal POST cannot create a journal or change stock or the hold.

No application patch, boundary acceptance, lock-wait evidence or draft PR
is claimed. Active-writer coverage remains open.