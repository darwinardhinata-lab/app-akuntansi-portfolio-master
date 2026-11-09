# U04 signature delta — 9 November 2026

Repository `D:\xampp\htdocs\app-akuntansi-portfolio-master`, branch `feat/marvel-dard-integration`, source HEAD `ab39728`; no commit/push. Prior evidence `D:\ERP-Recovery\R1-apache-return-20261109-101934` retained unchanged. New disposable evidence: `D:\ERP-Recovery\R1-apache-return-20261109-162024` (pointer `D:\ERP-Recovery\r1-u04-signature-current.txt`). Not staging or human UAT.

## Existing contract recovered (not an owner proposal)

Read ZIP `D:\Project ERP\MGI_Payment_Plan_Source_v33.zip`, `public/workspace.js`, functions `referenceRequestSheet`, `referenceSettlementSheet`, `paperSignature`, and `public/reference-forms.css`. Workspace SHA256 `9741e0184813dcb7e064fc2c4b6047614a62acb63bdcf66ac59cfa122b8fac12` matches the 49-reference inventory. These functions already prescribe blank handwriting spaces; no new approval stage is authorized here. This implements their roles/order in the existing ERP combined print, not a pixel-identical Excel grid or the unrelated petty form.

| Form / order | Existing label | Persisted identity/date | Decision meaning |
|---|---|---|---|
| Request 1 | Applicant | `createdName`, `createdBy`, `createdAt` (history create fallback) | Creation recorded, NOT approval/signing |
| Request 2 | Head of Department | `managerAcknowledgement.actor/actorId/at` | Empty when absent; bank workflow does not manufacture an acknowledgement |
| Request 3 | Director request approval | `requestApproval.actor/actorId/at` | Approved only while current record exists |
| Settlement 1 | Reimbursement Department | `managerAcknowledgement.actor/actorId/at` (source mapping) | Empty when absent; no substitution of requester/director |
| Settlement 2 | Finance Department, prepared by | `settlement.preparedName/preparedBy/preparedAt` | Preparation recorded, NOT Director approval |
| Settlement 3 | GM bank release | `bankRelease.actor/actorId/at` | Release recorded only after normal server release |

`settlementApproval.actor/actorId/at` is separate Director settlement workflow metadata; `accountingApproval.actor_id/approved_at/basis` remains separate frozen accounting metadata. Raw persisted timestamps retain their timezone suffix; no manual-signing date is inferred. Each of six manual spaces is empty with separate blank date. ID/EN/zh_CN note explicitly disclaims digital signatures and proof of handwriting. No signature image, invented actor, new permission or new financial calculation.

## Product and verification decisions

- `bank_signatures.blade.php` renders two keep-together three-slot groups and separate settlement metadata; `bank_print.blade.php` includes it after the existing single item total. Screen-only stacking at <=600px; print remains three columns. Existing numeric and photo rules retained.
- Targeted HTTP direct-service + partial AP + locale regression: **3 tests / 292 assertions PASS**, `targeted-final.log`. Draft, approved request/settlement and released GM states, all three locale labels and missing Head of Department are asserted. These are SQLite HTTP tests, not multi-page AP live evidence.
- New restricted Apache/MariaDB fixture provisioned from scratch. Bootstrap checks schema/account/grants before provisioning writes; `prewrite-identity.json` verifies runtime root/account/schema before financial seed. Browser rechecks schema/account/private storage before draft writes. No existing 101934 DB restored or operational environment used.
- Reused normal API fixture generator: 21 long multilingual lines / four images. `u04-print-browser.json`: **79 PASS**, reused full item, amount, frozen basis, private image bytes/hash checks. Final document status is bank_released; approved accounting basis remains unchanged.
- `u04-signature-browser-initial-pass.json`: **64 PASS**, explicitly GM missing before normal maker/release and present afterwards. Final replay `u04-signature-browser.json`: **62 PASS**, skips already completed financial mutations; checks draft, actual locale, six empty spaces, no signature images, correct Director identity/timestamp, missing manager, Finance recorded, GM persisted state, print widths **390/768/1440 for ID/EN/zh_CN**, guest redirect. Not additive coverage totals.
- PDFs saved for draft, ID/EN/zh_CN approved/released captures. Final `u04-payment-print.pdf`: **18 pages**. Visually inspected final pages 9–10 (total/request group then settlement group), 11–12 (basis/continuation), 14 (history tail), 15–18 (all four full photos). Group headings, identity/status/date, manual lines remain on one page per group; amounts readable, total once in DOM/page 9. Delta inspection, not a claim every final page was re-inspected; previous 101934 all-page review is retained for unchanged content.
- Mandarin PDF `signature-approved-zh_CN.pdf` 17 pages; rendered page 9 inspected (both complete groups plus Director metadata), page 10 accounting basis inspected. Draft PDF 3 pages; pages 1–2 inspected (page 2 both groups missing approval, no fabricated actors). Rendered screenshots retained. PDF object-count checks alone are NOT visual acceptance.
- Attempts 1–4 retained: missing amount/201 expectation, locale race, wrong version source were harness issues. Locale switch now waits for workspace `aria-busy=false` before normal language fetch to avoid concurrent session writes. Query cache-busting alone did not solve it. No speculative product locale middleware change.

## URL / responsive / UAT reconciliation

`D:\xampp\htdocs\app-akuntansi-portfolio-master\docs\integration\u04-url-review-20261109.csv` accounts for **all 49 inventory entries**, grouped by feature with original excerpt/hash/status preserved. It is NOT 49 successful runtime checks. Duplicate matches and original Sites/React wrappers are not 49 distinct Laravel URLs.

| Reference group | Existing ERP/evidence disposition | Concrete remaining gap |
|---|---|---|
| labels/assets/identity shell | Blade locale labels, ERP login/session/logout rather than Sites identity; original workspace JS not imported | Full shell/assets/identity URL acceptance matrix beyond supported bank UI |
| API wrapper/data/request/actions | `/payment-plan/api/data`, `/request`, `/action`; current direct/AP HTTP tests + 101934/new live fixture | Unsupported v33 forms/actions remain broader U02/F dependencies; not exposed by this patch |
| attachments | Authorized `/payment-plan/api/attachment/{id}`; 101934 + current hashes/photos, prior scoped U03 reports | Actual staging/archive AC07 prerequisites unchanged; remove/archive reference is not closed by print |
| printing | ERP `/payment-plan/bank/{id}/print` + browser print; guest redirect and authorized live print checked | Exact all-form v33 layout/petty/reference design acceptance not established |
| comments/notifications, history, translator, settings, reports, transfer | Separate U05/U08/F features; no legacy URL transplantation or invented replacement | Owner/master-plan scope and implementation evidence required per feature; do not mark adapted |
| responsive | Existing 101934 workspace actual zh390 + ID/EN390 + zh1440 retained; new nine print locale/width cases PASS | Broader workspace form/action/state/device matrix and human visual/UAT decision remain |

**Closed scoped U04 criterion:** existing bank manual signature role contract, truthful data mapping/pending markers, multilingual print rendering and delta pagination. **U04/R1-AC08 remains IN_PROGRESS/PARTIAL**, because all-form/49-reference adaptation and broader workflow/UI/UAT are not established. UAT is a separate human decision, not a tool PASS. AC07 staging identity + owner scope/retention/audited archive access remains PARTIAL.

**One next action:** resolve the first unsupported inventory group against U02/F readiness (comments/notifications depends on U05); implement only supported API-backed UI, while owner supplies staging/archive prerequisites and human UAT disposition. No need to wait for a message saying “lanjut”; the inventory review was the independent ready increment completed in this run.

## Final checks / cleanup

PHP lint (both Blade files, three locale files, HTTP test), Node syntax (signature/PDF harness), scoped `git diff --check` PASS; only existing CRLF normalization warnings in two documentation files. Process 20816 and its httpd child were matched to the new disposable config and 127.0.0.1:8898 listener before shutdown; operational Apache processes untouched. Restricted schema/account removed. `cleanup-verification.json` confirms schema/account absent and port free after independent checks. Synthetic files retained for evidence. No commit/push.

Technical references verified against stack Laravel 12: official Blade subviews/escaped output documentation (`laravel.com/framework/docs/12.x/blade`), CSS Fragmentation Level 3 (`www.w3.org/TR/css-break-3/`, `break-inside`). Pagination acceptance is grounded in the actual rendered PDFs, not the CSS declaration alone.