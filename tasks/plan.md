# Remaining audit work — six-batch roadmap

This increment adds read-only reconciliation of AR/AP allocation evidence. It does not automatically repair historical data or certify total GL balances.

1. Implement a streaming Artisan allocation audit; check missing documents/journals, references, journal balance, allocation amounts and AR reversal evidence.
2. Test clean evidence, inconsistent evidence, JSON output, failure exit code and absence of mutations.
3. Run focused and full SQLite regressions; document limitations.

Later increments: document-status/GL reconciliation, return-credit allocation, approved maker/checker rules, closed-period policy, verified CEISA specification and MySQL/browser UAT. These remain open; do not infer accounting policy from this command.

## Final closure work requested (six original batches, not six new batches)

### 1. Payment Plan maker/checker provenance
Add trusted creator, last financial editor, approver and payer provenance. Record from authenticated actors, never request IDs. Serialize approval under the payment lock; reject self-approval and unknown legacy provenance until explicitly reviewed. Define whether payer/poster must also be different people before enforcing that additional segregation. Approval must be invalidated on financial edits. Test self-approval, tampered request IDs, stale approvals and legacy records. Dependencies: approved segregation policy. Existing PaymentPlan store/workflow currently lacks this provenance.

### 2. Accounting period lifecycle
Approve company scope, allowed closers/reopeners, close prerequisites and reopen process. Add audited close/reopen records and shared transaction locking. Guard both original and replacement dates on journal header/details, bulk writes/imports, stock/source lifecycle and reversals, not only HTTP controllers. Test closed-date creates/edits/deletes, open-to-closed moves, rollback and close/post races. Dependencies: company provenance for operational journals and approved period rules. Do not claim a model hook alone protects all paths.

### 3. Historical allocation/GL reconciliation
Build read-only inventory of unallocated historical receipts/payments and ambiguous references. Validate linked source/receipt/reversal/return journals against balances by document, account and supported currency; explicitly report unclassified cash/advance transactions. Add explicit AP credit-to-bill mapping and cumulative quota before applying credits. Any approved correction must preserve original evidence and record actor/reason. Tests: duplicates, wrong party, multiple partial payments, AP credits and ambiguous history. Never infer matches only by description or PO.

### 4. Sales refund policy
Obtain approved treatment of line/header discounts, VAT, shipping, rounding, already-paid invoices and customer credits versus cash refunds. Record source line/value snapshots and cumulative value quota. Refund authorization and cash disbursement must be separately auditable/idempotent. Tests: partial/multiple returns, tax rounding, paid/partial invoice, over-refund and retry. Dependencies: accounting policy and reliable payment allocations; no invented VAT rate/account.

### 5. Verified CEISA integration
Obtain authoritative versioned API contract for document types, auth/signature, endpoints, payloads, response/status, retry/idempotency and callback security. Existing CeisaH2HClient checkStatus explicitly says its GET path is an assumption. Keep H2H settings disabled; no real submissions until contract and sandbox access are verified. Implement contract fixtures and mocked tests, then authorized sandbox acceptance. Dependencies: official spec, licensed entity context and credentials supplied securely (not git).

### 6. Isolated MySQL/browser/printer acceptance
Existing tests/Integration/GrnReceivingMysqlTest.php provides opt-in random fixture database creation/cleanup and parallel worker patterns. Extend for shared invoice counters, barcode quotas, approval and period-close races. Run only with isolated test database permission, never operational fixtures. Browser verification requires a test app URL/users; printer acceptance requires actual device/driver/media and scan results. Record actual versus skipped results. Dependencies: implemented policies, safe test environment and physical operator for printer output.

Acceptance gate: all focused/full tests pass, no unauthorized production changes, policy/spec decisions recorded, migration instructions reviewed, actual MySQL/UAT evidence and unresolved limitations documented before claiming audit closure or pushing main as complete.