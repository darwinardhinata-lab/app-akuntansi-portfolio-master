# Accounting intent contract — development, not activated

## Call contract

`PostingService::post($header, $lines, $message, $fx, $intent)` extends the existing posting service. The optional fifth argument requires `idempotency_key`, `source_type`, `source_id`; actor ID is optional for posting and mandatory through the authorized reversal method. Keys are global in the intentionally single-company ledger; callers must namespace by source and action.

Examples of distinct operations: `grn:<uuid>:post`, `payment:<uuid>:settlement`, `reversal:<journal-id>`. Do not invent new keys when retrying an operation. Do not reuse a key for a changed amount, source, date, currency or reason.

The SHA-256 comparison sorts object keys but retains line order and value types. It excludes `created_at`/`updated_at` persistence metadata. Callers must submit stable decimal serialization; `25.50` and `25.5` may conflict. No currency conversion, master creation or silent mismatch correction occurs in this layer.

## Transaction and sealing

The unique intent reservation, existing base/FX posting and seal commit atomically. On successful retry the same journal is returned. Invalid input or detail insert failure leaves no reservation/header/detail. Sealing is represented by `sealed_at`, not by replacing legacy journal status or rebuilding the ledger.

Model writes and ProtectedJournalQuery reject changes to sealed journals and their details, including append/reparent attempts. Protection remains on for existing seals even if the opt-in flag is subsequently off. These are application guards, NOT a SQL firewall; remaining raw SQL writers require consolidation and/or database controls before activation. Unique-key/current-read behavior still needs real MySQL race verification and lock-order review.

## Linked manual reversal

`PostingService::reverse($journalId, $postingDate, $reason, $actor)` requires the feature flag, FINANCE role and explicit user-ID allowlist. It supports only sealed MANUAL originals that pass SourceJournalProtection and have no linked assets. It does not change or delete original lines. It swaps positions, retains transaction/base amounts and exchange rates, and seals a new reversal with unique `reversal_of` reference and actor evidence.

The original may be in a closed month; destination date must be explicit, valid and open. Changed retry reason/date/actor is a payload conflict. Source-document reversal, assets, opening balances and reversal-of-reversal are deliberately rejected rather than falsely updating only GL.

No HTTP route or UI is supplied by this increment. Controllers/adapters must authorize source visibility and provide authenticated ERP actors, never client identity headers or arbitrary URLs. Stored source type/id is traceability, not an accepted clickable source-link implementation.

## Migration / rollback

Migration `2026_10_27_120000_create_accounting_posting_intents.php` is additive and exercised by SQLite RefreshDatabase only. It has NOT been applied to a user database. `down()` refuses when evidence exists. After activation, rollback means disable new creation and deploy compatible code while retaining evidence and seal enforcement; do not drop the table or delete posted transactions.

## Acceptance still required

- MySQL concurrent same-key retry, changed-payload race, reversal race, mutation-vs-seal race and deadlock retry behavior.
- Remaining raw writer consolidation, authorized read/reversal UI, source links and asset/domain-specific reversals.
- v33 financial adapter, AP/DP allocations/reservations, source-document acknowledgement and role parity.
- Staging restore/control totals and independent role/browser/print acceptance.

Passing unit/feature tests is not authorization to enable the flag.