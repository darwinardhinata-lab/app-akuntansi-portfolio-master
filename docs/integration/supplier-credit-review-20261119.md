# Supplier price credit — source-validation review package

Status: **candidate for code review only; human UAT DEFERRED; production NO-GO**.

Base candidate: `08daa3f50f140d9cd94a3db5fe81885a9c183994`.
Branch: `feat/supplier-price-credit-20261119` (not integrated into the ERP branch).
The final publication hash is recorded after commit in
`D:\ERP-Recovery\supplier-credit-source-20261119\publication.json`.

## Scope and defects

The approved transaction class remains supplier price discount without inventory/tax effects:
`AP_SUPPLIER_PRICE_DISCOUNT_CREDIT_NOTE → 510003 — Diskon Pembelian`.
No operational mapping or authorization grants are installed by this increment.

MariaDB RED proof is retained in `maria-red.log/.exit`: 4 tests, 57 assertions,
2 failures. A `GRN_V1` PO with its receipt removed was accepted at posting; an
identical retry with a changed sealed intent `source_id` was also accepted.
Both are corrected. A GRN-mode PO now requires its coherent persisted receipt.
Binding validation now verifies intent source ID/key/type/actor, seal/reversal
state, deterministic source identity, header invoice/currency, exact AP line,
two positive balanced IDR lines with the approved counterpart, and the original
business payload hash. Persisted datetime is normalized to its business date.

## Rejection evidence

Evidence root: `D:\ERP-Recovery\supplier-credit-source-20261119`.
Final `maria-release.log/.exit`: **9 tests / 339 assertions, exit 0**.
Final `full-release.log/.exit`: **798 tests / 16,239 assertions, exit 0**.
All four changed/new PHP files pass syntax checks; `git diff --check` passes.
`rejections.jsonl` contains exact before/after snapshot pairs, not only counts.
Each pair includes journals, posting intents, bindings, reconciliations and
decision audit, requests/events, bank/AP holds, payment evidence, PO/GRN/Bill,
inventory, and residual/events/exceptions. Snapshot is taken **after** injecting
the invalid source, immediately before the normal operation: it proves that
rejection does not repair, approve, reserve, or partially post anything.

| Group | Posting/retry | Approval after valid proposal |
| --- | --- | --- |
| Foreign bill/PO or binding company | HTTP 409 under sole-MGI guard | HTTP 403 under AP authorization guard; no decision appended |
| Wrong supplier in binding, with sole MGI restored | — | `supplier_credit_binding_invalid` |
| GRN wrong PO, supplier, journal, missing Bill link, VOID, missing receipt | HTTP 409 | HTTP 409 after re-reading current GRN chain |
| Intent source ID/key/type/seal, binding journal/AP line, header Bill, AP account, payload hash | Exact retry HTTP 409 | Validation rejection, proposal remains proposed |
| PPL payment event or consumed AP allocation | HTTP 409 | HTTP 409 after evidence is inserted after proposal |

Posting tests use the authenticated registered POST route. Approval tests invoke
the existing authenticated `ApReconciliation::decide` application entry point,
the same use case used by the Filament page; they do not bypass authorization or
call private validators. Synthetic corruption is raw fixture setup only. This
is not a browser/Livewire end-to-end approval acceptance claim.

Foreign company tests intentionally retain the existing single-operational-MGI
restriction. They prove early fail-closed rejection, not supported multi-tenant
posting or attribution. Payment evidence is seeded independently while the Bill
remains UNPAID, so rejection is not merely a payment-status check. A consumed
allocation may already reduce the pre-attempt residual; rejection must preserve
that actual residual, rather than force it back to 200.50.

## Overlap evidence reuse

Retain the `08daa3f` evidence in
`D:\ERP-Recovery\supplier-credit-20261118`: `approve-race.json`,
`maker-race.json`, `A-states.json`, `B-states.json`, `matrix-final.log/.exit`.
That evidence proves actual distinct-connection InnoDB fence waits and third-
connection final-state inspection for both orderings. Approval-first leaves
180.50 and rejects 190.00; maker-first leaves a 190.00 hold / 10.50 residual
and rejects credit approval. It is not a fresh race execution for this increment.

Compatibility rationale: `ApReconciliation`, `WorkflowStore`,
`PurchaseBillAllocation`, `BankPaymentControl`, `LegacyFinancialFence`, and the
projection algorithm are unchanged from `08daa3f`; no lock acquisition order is
changed. The shared seal predicate is stricter and read-only. The positive
posting/retry/independent-attribution regression passes on both SQLite and
MariaDB with the new predicate. This supports reusing the ordering proof but
does not constitute a universal concurrency guarantee for arbitrary writers.

## Finance acceptance steps — not yet executed by a human

Use a reviewed disposable/UAT environment only; do not use production fixtures.

1. Establish sole active MGI, an explicit active company mapping for the approved
   key to 510003, and separately authorized Finance posting / AP proposer /
   independent approver actors. Permission catalog seeding is not a grant.
2. Select an unpaid IDR Bill with proven active supplier on its owned PO and,
   for GRN mode, a POSTED coherent receipt with matching Bill/PO/Party/company/
   recognition journal. Verify no payments or active/consumed holds and residual
   200.50. Record initial journal, inventory, audit and hold snapshots.
3. As Finance, submit authenticated POST `/purchase-bills/{id}/supplier-credit`
   with JSON `{"supplier_reference":"CN-UAT-001","posting_date":"2026-01-02",
   "amount":"20.00","reason":"Supplier approved price discount"}`; use a date
   in an open test accounting period. Include the normal session and CSRF token.
4. Verify exactly one sealed two-line journal: debit the Bill's proven AP account
   20.00; credit 510003 20.00, IDR; inspect exact persisted binding identities.
   Inventory/tax must be unchanged. Residual must still be 200.50.
5. Repeat the identical request: same binding/journal, no extra posting. Change
   amount/reference payload on the same identity: conflict, no financial writes.
6. Open `/erp-panel/ap-reconciliation` as the authorized proposer. Select the Bill
   and bound journal, amount 20.00, mandatory reason/evidence. Submit proposal;
   inspect immutable source snapshot and proposal audit. Residual stays 200.50.
7. Attempt self-approval: rejected. As independent authorized approver, approve
   current proposal/version with reason. Verify residual 180.50, one approved
   attribution, one approval audit, no extra journal, inventory or bank hold.
8. Repeat the exact approval: no extra contribution/audit. A changed retry must
   fail. Verify payable capacity is 180.50 and a 190.00 maker reservation fails.
9. In separate owned test cases, apply each invalid-source group above before
   posting/retry or after proposal; verify the rejection and exact unchanged
   financial snapshots. Do not tamper sealed operational journals to run UAT.
10. Record reviewer/Finance findings and acceptance evidence. Do not treat these
    instructions or automated tests as human sign-off or deployment approval.

Limitations: matrix is representative and source-bound, not exhaustive for all
legacy payment statuses/writers, arbitrary corruption, tenancy, or UI journeys.
Credit-note void remains unsupported. Review and human acceptance precede ERP
integration; production activation requires separate explicit authorization.