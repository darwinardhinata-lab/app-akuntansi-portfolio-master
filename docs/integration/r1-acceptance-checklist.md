## 9 November 2026 — signature delta supersedes earlier OPEN signature row

Details: `D:\xampp\htdocs\app-akuntansi-portfolio-master\docs\integration\u04-signature-delta-20261109.md`; evidence `D:\ERP-Recovery\R1-apache-return-20261109-162024`. Existing v33 manual-signature contract recovered (not a new policy); six truthful blank spaces with separate workflow data/pending markers **PASS scoped**. HTTP 3 tests/292 assertions; initial browser 64 PASS, final replay 62 PASS; nine print responsive cases; print parity 79 PASS. ID final 18-page PDF signature groups pages 9–10 and photos 15–18 visually inspected; Mandarin/draft affected pages inspected. All 49 URL references reconciled into grouped review, **not** globally adapted. U04/R1-AC08 remains PARTIAL; broader all-form URL/responsive scope and separate human UAT remain. AC07 staging/archive decision unchanged.

# R1 Acceptance Checklist — Finance Payment UI

**Branch:** `feat/marvel-dard-integration`  
**Checkpoint:** `64a64b7` — `feat(payment-plan): promote canonical Finance payment UI`  
**Date:** 2026-10-30  
**Status:** Draft — pending staging sign-off

Format: Item | Status | Evidence | Sign-off | Date

| # | Item | Status | Evidence | Sign-off | Date |
|---|------|--------|----------|----------|------|
| AC01 | Canonical Filament payment page `/erp-panel/bank-payments` | PASS | `docs/integration/r1-finance-payment-ui-2026-10-29.md` | ☐ | |
| AC02 | Role-based inbox (awaiting_me / all_visible) | PASS | Full suite 721 tests / 14,795 assertions | ☐ | |
| AC03 | Multi-item form (direct service + AP invoice source) | PASS | Full suite 721 tests | ☐ | |
| AC04 | Private attachment with SHA-256 hash, uploader, archive, audit | PASS | `ppl_attachment_audits` table + migration | ☐ | |
| AC05 | Multi-item print + foto bukti + accounting basis | PASS | Edge replay 1440px + 390px | ☐ | |
| AC06 | MySQL concurrency: maker race, reservation, idempotency | PASS | 6 tests / 146 assertions / 1 skipped (MySQL) | ☐ | |
| AC07 | Attachment migration: one-context CHECK + unique indexes | PASS | `information_schema` assertion in MySQL tests | ☐ | |
| AC08 | Migration runbook (`r1-migration-runbook.md`) | NOT RUN | Staging not available | ☐ | |
| AC09 | Dry-run script (`r1-migration-dry-run.ps1`) | NOT RUN | Staging not available | ☐ | |
| AC10 | Browser acceptance harness (`r1-browser-acceptance.mjs`) | NOT RUN | Staging URL not configured | ☐ | |
| AC11 | Storage validation script (`r1-storage-validation.mjs`) | NOT RUN | Physical storage not available | ☐ | |
| AC12 | Acceptance checklist sign-off (this document) | PENDING | Awaiting DBA/IT sign-off | ☐ | |

### Action Required (NOT RUN items)

- **AC08–AC11:** Require staging environment with `R1_STAGING_URL`, `R1_UAT_USER`, `R1_UAT_PASS` environment variables configured.
- **AC12:** Requires DBA and IT sign-off after AC08–AC11 complete.

### Excluded from R1 Scope

- USD payment, DP/progress/retention, petty cash
- Non-payment ERP modules
- Production deploy / merge to main / real bank transfer
- Live browser acceptance on production

---

**Prepared by:** Cline  
**Reviewed by:** —  
**Approved by:** —