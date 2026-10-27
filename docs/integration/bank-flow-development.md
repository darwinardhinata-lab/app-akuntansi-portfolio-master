# Bank payment development slice — real HTTP to ERP journal, restricted scope

## Delivered path

`GET /payment-plan/bank` renders a dedicated Bootstrap Blade workbench, linked from Filament navigation when flags are on. This is a restricted development workbench, NOT the accepted reference v33 11-menu UI, print parity or complete replacement. It shares ERP identity/domain/WorkflowStore; no second ledger/state machine.

Actual HTTP routes: GET data, POST request, POST action, POST attachment, GET attachment/{id} under `/payment-plan/api`. Existing sessions/auth/CSRF and ErpActor apply. Default flags remain off. Three-language workbench labels; server role/state guards are authoritative. UI retry retains mutation key for identical pending action; create uses caller UUID but repeated create currently returns request_exists rather than a successful replay.

Implemented business path is **direct IDR service expense** only:

1. Requester creates draft with payee, three-language subject/purpose, amount, beneficiary and due date.
2. Submit; Director approval 1.
3. Finance uploads settlement evidence privately and prepares base/VAT/withholding/fees.
4. Director approval 2, explicit increased ceiling as required.
5. Maker reserves source funds; GM releases independently.
6. Finance uploads actual payment evidence and confirms debit/date. Reservation consumed, immutable payment-event UUID persisted; request becomes paid, no journal yet.
7. Accountant invokes posting. Existing CompanyCoaMapping resolves `ppl.expense`, `ppl.vat`, `ppl.fees`, `ppl.withholding`, `ppl.bank.idr3`. Only nonzero lines need mapping. Expense mapping must match approved settlement COA snapshot. Existing PostingService seals one balanced journal keyed **ppl:paid:<persisted event UUID>**, not request version. Request becomes posted only with real journal ID.

No new company/COA/bank masters or production mappings created. Synthetic mappings/settings in tests are NOT operational approval.

## Persistence and controls

Additive development migration `2026_10_27_140000_create_ppl_bank_controls.php`: financial-context mutex/settings, reservations, payment events and private attachment metadata, foreign keys and evidence-retaining rollback.

All enabled workflow mutations acquire company financial context before request lock. This coarse single-company fence deliberately serializes bank activity; not a scalable per-resource implementation. Within current slice it serializes balance/reservation/paid calculations. Real MySQL races and interaction with remaining legacy/raw writers still require acceptance.

Available = configured opening + receipts − confirmed paid events − active reservations, no second balance journal. Opening/settings provisioning currently has NO application setup screen/authorized mutation endpoint; supplied only in isolated fixture and must be bound to ERP opening/receipt authority before real staging acceptance. No browser-provided settings accepted. No claim that legacy GL/manual receipts are reconciled to these projections.

Hold/pending/failed retain active reservation. Return with an active reservation is rejected; no-debit restart requires Finance Manager + evidence/reference/reason according to domain. Reservation amount must match settlement before release/hold/debit/no-debit restart. No funds transfer is executed.

Private uploads: local disk rooted storage/app/private; current MIME allowlist PDF/PNG/JPEG/WEBP, 10 MB, max 40, hash+kind duplicate rejection, stage/actor/version checks, DB metadata/event atomicity with compensating blob deletion on failure. Download rechecks company/visibility, archive flag and SHA-256, forces attachment and nosniff/sandbox headers. XLSX/DOCX/CSV attachments, archive/remove API, malware scan, orphan reconciliation and interrupted-upload/idempotent retry acceptance remain open. Historical evidence is never deleted by this implementation.

Posting failure rolls back attempted ACK/journal and leaves previously committed paid state and event intact. Failed mapping can be repaired through approved provisioning and retried. Current implementation commits journal+ACK in one DB transaction; external outbox/crash-boundary recovery is NOT implemented. Stable PostingService key independently prevents duplicate event journal.

## Evidence actually run

- Service flow regression exercises all eight bank actions and one journal, idempotent ACK retry, reserved overspend rejection and unsafe-return rejection.
- **Actual HTTP end-to-end regression** creates draft, both approvals with distinct ERP role assignments, private settlement/payment uploads, authorized download, maker/GM/debit and ERP journal. Synthetic bank opening 500.00; actual fixture debit **100.25 IDR**, balanced 2 journal details, one payment event/journal. Mapping-disabled posting returns 409 and preserves paid/version with zero journal; restored fixture mapping retries successfully.
- HTTP Blade first failed inline `@json` complex expression; endpoints moved to controller variable, repeated test passed. No error suppressed.
- Targeted bank/workflow group **4 tests / 73 assertions**, pass.
- Final full suite **716 tests / 14,597 assertions**, exit 0, SQLite :memory:. Actual Node legacy oracle **66/66**. Syntax/diff checks passed.
- NO real bank operation, operational migration, browser interaction or MySQL/staging deployment performed. HTTP-kernel tests and Storage::fake are not physical staging blob/restore acceptance.

## Run on an approved isolated development database only

Apply additive migrations via normal dev release procedure on a verified disposable DB, never the user's current operational database. Provision explicit ERP memberships/PPL roles, approved semantic mappings and verified bank opening/receipts in the development financial context. Feature opt-ins needed: PAYMENT_PLAN_V33_ENABLED, PAYMENT_PLAN_V33_BANK_WORKFLOW, PAYMENT_PLAN_V33_RESERVATIONS, PAYMENT_PLAN_V33_POSTING and PLATFORM_POSTING_INTENTS_ENABLED. This document is NOT authorization to enable production or use unapproved bank data. No setup command was run against user DB.

## Still not delivered

AP/DP ID allocation and all contract stages, petty/top-up, USD/FX adapter, original UI full URL/print parity, comments/notifications/settings/reports/history/import/export, migration ownership/legacy convergence, full Filament inbox/stats and remaining operations. These return explicit allocation_adapter_required or bank_idr_adapter_required rather than expense-posting unsupported transactions. Workbench is not acceptance of those requirements.

Activation blockers: verified ERP authority for opening/receipts and effective-dated policy; approved COA mappings; MySQL concurrency and shared writers; browser/mobile/print/permissions/attachment failure acceptance; restore/control totals and Finance UAT. Many remaining modules are unfinished implementation, not external blockers.