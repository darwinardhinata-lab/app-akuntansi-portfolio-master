# AP allocation branch recovery — 18 November 2026

The isolated candidate reuses the local R2 inspection adapter and synthetic fixture (company, actor, bill, PO, posted GRN and recognition journal). It no longer depends on nonexistent `CompanySeeder`, `UserSeeder`, or `PurchaseBillFactory`, or on `User::company()`; the actor is attached through `companies()`.

| Prior scenarios | Coverage on the branch contract |
| --- | --- |
| Three-way match success and exception | Valid partial receipt, unposted/missing GRN, mismatched detail identity, quantity/totals and price tolerance, no match-state or journal mutation |
| Allocation success and outstanding | R1 reservation contributes to residual once, consumption does not double-count, release restores residual; direct R2 allocation is rejected |
| Reversal (two old scenarios) | Direct reversal is rejected; retry does not zero or create allocations or post an additional journal |
| Journal posting (two old fixtures) | Direct posting is rejected; retry does not add journals, allocations or events. The synthetic recognition fixture posts through the existing PostingService. |
| Match waiver | Rejected without events or financial writes; approved waiver lifecycle remains outside this adapter |

These are not approvals for direct R2 financial mutations. The legacy successful-write expectations are not carried forward: R1 remains the existing payment authority, and waiver/reversal successes require an authorized workflow and durable evidence. AP recognition currency queries go through `ProtectedJournalQuery` in the existing transaction and fail closed without explicit currency metadata and sole active MGI binding.

Isolated AP plus protected-journal regression: 13 tests, 488 assertions, exit 0 (10 AP cases, 3 journal boundary cases). No AP operational activation or production migration. Human UAT DEFERRED; production NO-GO.