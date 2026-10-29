# R1 Finance payment UI and evidence — 2026-10-29

## Delivered

- Canonical payment page is now the Filament ERP page `/erp-panel/bank-payments` (`BankPayments`). The former `/payment-plan/bank` route redirects there and is not a second workbench. Existing workflow/application services, reservation, fence and ledger are reused.
- The page exposes role-scoped inbox filtering, transaction detail, status/action labels, loading/error/empty states, responsive mobile layout, direct-service and partial-AP request forms, multi-item lines, private attachment upload and authorized preview/download.
- Reconciliation evidence uses the existing private attachment service with context, SHA-256, uploader, timestamp, archive state and append-only upload/preview/download/archive audits. The free-text reconciliation evidence remains an internal document reference and is not treated as an uploaded-proof claim.
- Payment print has repeated table headers, item rows, accounting-basis page break, decisions/audit history and authorized image attachments with filename, uploader, timestamp and SHA-256.

## Routes and demonstration

1. In an isolated environment with the existing MGI context, flags and permissions enabled, open `/erp-panel/bank-payments`.
2. Use the role inbox. Requesters create direct IDR service requests; Finance selects a non-blocked AP invoice for a partial payment.
3. Follow the existing request → two Director decisions → settlement → maker → GM release → debit evidence → journal acknowledgement flow.
4. Upload and authorized-preview an attachment. For reconciliation, use `/erp-panel/ap-reconciliation`, upload evidence in proposal state, inspect hash/uploader/audit, then archive it as uploader.
5. Use the print link for multi-item lines, approved accounting basis, decisions and image evidence; print A4/PDF from the browser dialog.

## Actual validation and boundaries

| Check | Result | Evidence boundary |
|---|---|---|
| PHP lint and `git diff --check` | PASS | Syntax and diff integrity |
| Full configured PHPUnit suite | PASS: 721 tests / 14,795 assertions | SQLite + HTTP/Livewire; not browser or physical storage |
| Targeted bank/reconciliation suite | PASS: 4 tests / 178 assertions | HTTP/domain and fake private disk |
| Disposable MySQL maker/AP suite | PASS: 6 tests / 146 assertions, 1 skipped | Writer race/idempotency and crash-recovery fixture; one unavailable MySQL fixture was skipped |
| Edge visual capture | PASS: 1440px and 390px, no overflow, detail/inbox/item interaction, print evidence 5 items/1 photo | Replayed authenticated HTTP-test HTML/API, not live browser authentication |
| Vite build | PASS previously at `d950174`; no asset source changed in this slice | Blade-only changes |
| Physical staging storage/restore | NOT RUN | No staging facility available |
| Live authenticated desktop/mobile browser | NOT RUN | No safe UAT credentials/session supplied |
| Production migration/deployment | NOT RUN | Out of scope |

## Known blockers

- Live authenticated browser acceptance, physical private-storage restore and complete real-deployment crash evidence remain environment-dependent gaps.
- The attachment migration must be applied through controlled deployment before reconciliation uploads are enabled; no user database was migrated here.
- R1 remains default-off; no production deployment, bank transfer or main merge was performed.