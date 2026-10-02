# Maklun receipt/reversal V1 — full completion only

## Implemented

Receive knitting/processing delegates to MaklunReceiptService. Source credits use
issue source_account_code snapshots, never current source master. Destination COA,
accrual COA, consumed issue IDs/values, total capitalized value, pre-receipt stock/MAC
and receipt ledger ID are saved as JSON on receipt with journal_id/status.

Full completion acknowledgment is mandatory. All outstanding issue costs consumed;
positive received qty, no returned_qty, reject or shrinkage. Result must be GREY for
knitting or FINISHED distinct SKU for processing. This is NOT partial allocation;
acknowledgment must reflect real completion, not bypass a partial transaction.
Liability limited to 212001 accrual: AP invoice/vendor matching not implemented.
Future Bill must clear accrual rather than debit inventory again; not automated here.

Reverse flips original journal lines into a deterministic new journal, appends OUT
ledger, restores stock/MAC from snapshot and marks original REVERSED. Original
journal/receipt/ledger remain. Order becomes CANCELED and cannot re-receive.
Reversal requires receipt to be latest fabric ledger and current stock/MAC/code to
match: later consumption, incoming stock, or master changes reject automatic reversal.
Original issue remains physical goods with subcontractor; source stock is NOT returned
by receipt reversal. Issue return/reversal is a separate pending lifecycle.

## Deployment

Migration 2026_10_02_070000 adds nullable coa_snapshot/journal_id/reversal_journal_id/
posting_status to both receipts. Not run on production, no historical backfill.
PLATFORM_MAKLUN_RECEIPT_ENABLED and issue flag remain false. Operator must review
both snapshot migrations, historical issues, Finance policy, UAT and concurrency
MySQL before any activation. No config cache/.env updated automatically.

## Remaining limitations

Company-specific ownership/role permissions, approval reasons, immutable journal
guards, issue returns, partial/reject/shrinkage allocation and accrued-to-AP settlement
remain pending. Evidence JSON stored but old/source journals can still be edited
through other existing paths; do not treat this as immutable ledger enforcement.
Stock cost calculation uses existing moving-average helper floats/DECIMAL storage;
BCMath aggregates journal amounts. No journal at physical issue (existing policy),
so GL inventory remains until completion; inventory-at-subcontractor reclass needs
Finance decision. Does not claim stock-vs-GL full reconciliation while goods in transit.

Legacy receipt without metadata cannot auto-reverse. Legacy issue void remains old
path for unsnapshotted documents; not a production permission to delete history.
New snapshotted issue void is blocked. No destructive receipt legacy code is reachable
after service delegation; old receipt implementations removed.

Regression covers knitting and processing issue snapshot → full receipt → reversal,
source master change after issue, original preservation, repeated reversal rejection,
reject quantity refusal. Does not prove production readiness for partial/FX/AP.