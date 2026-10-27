# ERP bank authority and partial AP extension — development only

## Implemented

- Optional `PAYMENT_PLAN_V33_ERP_BANK_BALANCE=false` projects mapped IDR bank account directly from existing ERP journal headers/details. Explicit `erp_cutoff` partitions opening (before date) and movement (on/after); receipts already in GL are not added again from settings.
- Subtract actual stable payment events only while not represented by a journal. A sealed PostingService intent with null application ACK is recognized, so journal-committed/ACK-missing projection does not subtract the debit twice. Linked journal date/net bank credit must match event or projection fails closed. Pre-cutoff events require reconciliation instead of silently overlapping opening.
- Active reservations separately reduce available funds. Legacy domain receives an adapted seed so its paid-request subtraction does not double count ERP events; reference domain unchanged. No new balance ledger introduced.
- Optional `PAYMENT_PLAN_V33_AP_ALLOCATIONS=false`, additive migration `2026_10_27_150000_create_ppl_ap_allocations.php`, request FK to existing purchase bill and amount/status. Supports direct IDR partial AP payments by ID. Maker reserves residual under the existing company financial fence and source-bill row lock. Actual paid consumes allocation; verified no-debit restart releases it.
- Source validation uses actual PurchaseBill schema (`bill_date`, `vendor_name`, `credit_account`), existing posted balanced journal, single credit counterpart matching credit_account and KREDIT-normal account, and journal AP total matching bill total. Complex/ambiguous recognition is rejected rather than guessed.
- AP payment journal debits the source payable and credits bank/withholding, with separate fee; it does NOT rebook invoice expense/VAT. Nonzero source mappings still come from CompanyCoaMapping. Does not create/update business master/mandate.
- Request API accepts purchaseBillId and binds source vendor/invoice/total server-side. Finance data API supplies first 200 open invoice candidates with explicit blocked reason for incompatible sources and ERP balances. Workbench selector and GL/pending/reserved/available figures added. Requester-only users are not granted AP source list access. This is not the final reference UI.

## Actual evidence

- Balance regression: opening 1000.00 + ERP receipt 50.25 - actual debit 100.10 = 950.15 both before posting, journal committed with ACK null, and after ACK. Not a real process crash test; synthetic persisted-state recovery scenario only.
- Partial AP service fixture: invoice 100.00, allocated 40.00, residual 60.00; overpayment rejected, journal debits existing AP rather than expense.
- Actual HTTP bank flow runs twice: direct expense and partial AP. AP uses ERP opening 500.00 and invoice 200.50; confirms 100.25 debit and one payment journal, residual 100.25, actual bank 399.75, pending debit 0.00. Private uploads/downloads and mapping-failure/retry path retained.
- Target group **5 tests / 88 assertions** passed. First full suite found source-convention failure from direct journal DB reads; replaced with existing ProtectedJournalQuery rather than weakening the guard test. Final full suite **719 tests / 14,646 assertions**, exit 0; Node **66/66**.
- Initial AP fixture used SalesInvoice-like transaction_date/contact_name absent from purchase_bills; corrected to actual bill_date/vendor_name and validated payable account direction.
- All mutations/tests SQLite in-memory; no user database schema/data/config changes.

## Important incompleteness / activation no-go

1. DP/progress/final/retention, USD and petty remain explicitly unsupported; AP extension only direct IDR. No DP feature claimed.
2. Existing legacy/manual AP payments, credits/returns and external allocation sources are NOT yet comprehensively reconciled into this AP residual. Bill payment_status is not updated by this extension; allocation residual is authoritative only within current flow. Need cross-writer ownership/control totals before activation. Complex source journals and settled statuses fail closed.
3. ERP GL balance assumes single-MGI shared ledger and approved bank mapping. Existing writers that do not acquire financial fence can race. No real MySQL concurrency or process-crash/ACK test run yet; SQLite results do not establish this acceptance.
4. ERP authority adapter does not approve cutoff/mapping; production data reconciliation and opening attestation still required. Settings provisioning remains development fixture/manual controlled setup, not user-facing admin acceptance.
5. AP source listing capped at 200 candidates, not complete import/export selector pagination; amounts still revalidated by server on mutation.
6. Original v33 UI/forms/print not adapted in this increment. Workbench remains development-only; browser mobile/print and physical private-storage staging not run.
7. Payment original GL/account policy immutability and reversal/credit residual interactions need explicit acceptance; do not claim universal AP lifecycle.

Continue DP canonical PO/contract binding, cross-writer AP residual reconciliation, frozen source account snapshots, UI v33 adaptation and isolated MySQL races. Do not enable flags from unit test success.