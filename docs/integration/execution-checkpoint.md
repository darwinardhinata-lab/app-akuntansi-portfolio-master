# F04 reconciliation panel first operational slice — 2026-10-27

- Added Filament `/erp-panel/ap-reconciliation`: invoice projections/exceptions, proposal inputs, approve/cancel commands; same ApReconciliation use case authorizes every mutation with fresh company membership and explicit `ap.reconcile.propose/approve/cancel` permissions. View requires `ap.reconcile.view` plus existing panel gate. Catalog only; no role grants.
- Additive migration `2026_10_27_180000_create_ap_reconciliations.php` stores source-invoice partial journal allocations, source evidence snapshot, mandatory reason/evidence reference, optimistic version and proposed/approved/cancelled status; append-only decisions retain actor/reason. Rollback refuses used evidence. Evidence is currently a required internal document reference, NOT a verified uploaded attachment.
- Scope deliberately restricted to balanced positive IDR AP reductions in unattributed journals (no bill_id/purch_ret_id). Independent approval checks source snapshot, source-wide approved capacity, invoice residual, financial context fence and row locks. PPL payment journals/intents rejected to prevent duplicate attribution. No journal is created. Stale retries reject rather than duplicate contributions.
- Approved allocations feed ApResidualProjection once; cancelling restores the original exception. Changed source evidence blocks payment and suppresses its stale contribution. Cancellation refused while active payment allocation exists. Revisions use cancellation plus a new proposal; no explicit revision-parent field yet.
- Regression includes GET rendering of actual panel, partial allocation, self-approval rejection, capacity rejection, stale retry, audit, cancel, revoked permission and changed source detection. Targeted initial group 29 tests / 316 assertions; final full suite **720 tests / 14,676 assertions, exit 0**, SQLite in-memory. Syntax and diff checks passed.
- NOT completed: PO return quantity/value allocation and resolution, attachment-backed verified evidence, multiple-invoice source test, browser/Livewire mutation HTTP acceptance, localized polished UI, all legacy/import/manual writer fencing, MySQL concurrency, v33 UI/print. Current queue limited to first 200 open invoices/proposals, not a complete paginated operational ledger. F04/F11 remain IN_PROGRESS; no verified menu promotion or activation.

# F04/F11 explainable residual increment — 2026-10-27

- Added ApResidualProjection: source-keyed contributions from active/consumed Payment Plan allocations and balanced IDR journals with explicit bill_id; signed AP credits restore residual (reversal), AP debits reduce it. Payment-event journal/intent identities are excluded when already represented by an allocation, including missing ACK recovery.
- Document-number-only journals are reconciliation candidates, not auto-attributed payments. PO-linked non-void returns become `return_invoice_allocation_required` exceptions, without reducing a specific invoice. Out-of-bounds residuals and unsupported currency block new reservations/approval.
- API source results include events/exceptions/blocked; development workbench displays raw explainable breakdown with textContent, and blocked selector options remain disabled. This is NOT the final reference v33 UI or print acceptance.
- Actual schema discovery: PurchaseBill declared purchase_order_id in model but no migration created it. Added nullable restrictive FK migration `2026_10_27_170000_add_explicit_purchase_bill_order_link.php`, with no inferred historical backfill and evidence-retaining rollback. Unknown legacy PO links remain unreconciled; not claimed comprehensive.
- Purchase-return store and purchase-bill delete acquire existing financial context rows in company order before PO/bill locks when bank workflow enabled. Other manual journal, legacy PaymentPlan, import and source writers are NOT yet coordinated; no universal race-safety claim.
- Regression covers PPL journal deduplication, explicit manual payment, signed reversal, ambiguous reference, and PO-only return blocking/no subtraction. Initial runs found stdClass validator conversion, missing declared schema column, and protected journal-query architecture test; fixed without bypassing tests.
- Final full suite: **719 tests / 14,662 assertions, exit 0**, SQLite in-memory. Diff check passed. No MySQL concurrency/browser/restore proof, no user DB migration. F04/F11 remain IN_PROGRESS.
- Next: explicit reconciled source-allocation records/API and legacy payment reference inventory (not merely bill_id), return reconciliation resolution, coordinate all remaining writers, implement v33 presentation, then isolated MySQL multi-process acceptance. Historical missing links and standalone credit-note lifecycle are not resolved by this projection.

# Settlement approval accounting basis — 2026-10-27

- WorkflowStore now captures server-owned accountingApproval after the Director settlement transition, in the same transaction/event payload: currency, settlement amounts, invoice/source journal/payable identity, source-bank identity, financial settings version, mapping rows and posting lines. Mapping has no explicit revision field; its persisted row/content/timestamp is the version fingerprint, not an invented business revision.
- Maker validates the current source/mappings against the approved basis; any mismatch or missing basis prevents reservation and requires reapproval. Preparing a new settlement clears previous evidence. Reservation source snapshots from `1782c829` remain distinct from approval evidence.
- Confirmed payment events retain approval basis; posting uses approved lines even if active bank mapping changes afterwards. Context fence remains held; posting skips mutable balance reprojection, which initially blocked AP recovery despite frozen lines. No new balance check is appropriate after an actual debit solely to acknowledge its journal.
- HTTP regression covers both direct and AP flows: mapping invalidation before maker rejects without reservation; mapping invalidation after payment still posts frozen counterparts. Existing parity domain is unchanged. Legacy events lacking approval basis retain the explicitly limited prior reconciliation path; not backfilled automatically.
- Initial targeted run exposed AP posting's balance-projection dependency (1 failure); fixed the cause. Final full suite **719 tests / 14,651 assertions, exit 0**, SQLite in-memory. Syntax/diff checks passed. No MySQL concurrency/browser acceptance or user DB mutation performed.
- F04/F11 remain incomplete: comprehensive residual source reconciliation, ambiguous PO-only return queue/allocation, coordinated old writers, and MySQL verification. UI v33/print still open. Approval snapshot does not claim universal database immutability or external mapping-writer fencing.

# F04 source counterpart recovery increment — 2026-10-27

- Allocation reservation now persists the validated AP source identity/account/journal as `source_snapshot`, via additive migration `2026_10_27_160000_add_ap_allocation_source_snapshot.php`. Existing rows remain null rather than manufacturing historical evidence.
- AP posting uses this frozen allocation source, company-scoped and locked, not a fresh resolution of mutable invoice payment status/credit account. Regression changes the source to PAID and a different account after reservation and confirms posting still debits the original payable.
- Missing historical snapshot returns `ap_source_snapshot_reconciliation_required` without a new journal; reconciliation is required, not an automatic backfill. Rollback refuses to discard used snapshots.
- Purchase-bill destroy explicitly refuses bills with any Payment Plan allocation evidence before reversing inventory or deleting journals. This is a scoped business guard, not universal raw-SQL protection or full writer coordination.
- Targeted initial run: 28 tests / 293 assertions passed. Final full suite after additional regression assertions: **719 tests / 14,650 assertions, exit 0**, SQLite in-memory. PHP syntax and diff checks passed.
- Remaining F04/F11: manual/legacy payment source identities, returns/credit notes (current return links to PO, not a particular AP bill), shared writer locking, approval-time source freeze and full posting-account snapshot remain open. Bank/fee mapping still resolves at posting; do not claim all counterparts immutable. MySQL/browser acceptance not run. No production/user database migration.

# Git publication checkpoint — 2026-10-27

- User authorized focused commits and non-force pushes on `feat/marvel-dard-integration`; no merge to main or production deployment.
- Reviewed local changes and committed accounting, panel/security, payment/AP development flow, sales schema compatibility, legacy-sync retirement and integration evidence separately.
- Fetched origin and merged remote master-plan commit `ca6142d95faf893f4637330bd99e515eca378216` without conflicts. Local and remote v1.1 master-plan text matched; retained unchanged.
- Fresh pre-publication regression: **719 tests / 14,646 assertions, exit 0**, SQLite in-memory. Composer validation passed, Composer audit reported no advisories, Vite build passed.
- User deletion `template_saldo_awal.csv` remains unstaged/uncommitted. Generated public build/Filament assets remain local; regenerate with `npm ci`, `npm run build` and `php artisan filament:assets` in the appropriate isolated environment.
- No migration against the user database, financial activation, production secret change, main merge or deployment. Publication does not promote any acceptance or menu status.
- Next implementation priority remains master-plan section 18: reconcile all AP residual sources, coordinate legacy/manual writers, then v33 UI/print and isolated MySQL/browser acceptance. Existing documented gaps remain open.

# Marvel–Dard integration execution checkpoint

## Working state

- Work branch: `feat/marvel-dard-integration`, based on `a8f9fe5`.
- Preserve all B01 edits and the pre-existing deletion of `template_saldo_awal.csv`.
- No production migration, reset, deployment, bank transfer or CEISA submission performed.
- New period runtime flag `PLATFORM_PERIOD_LOCK_ENABLED` defaults to false. No `.env` or secrets changed.
- Checkpoints are not acceptance gates or claims that all B02–B12 are complete.

## Implemented and verified in this increment

1. Existing `PostingService::post()` base-currency persistence now wraps header and detail insert in one database transaction; the existing FX path remains transactional.
2. Manual journal creation uses that same service. The UI order and input normalization remain unchanged. Asset creation remains inside the controller's outer transaction and uses persisted detail identifiers.
3. Period lifecycle and write guard support explicit runtime opt-in independently of the testing-only preview. Existing FINANCE role and separate close/reopen allowlists remain in force. Staging-environment regression proves closed-period insert rejection.
4. Opening balance and January/February/year ledger export regression proves carry-forward, credit-normal behavior and no duplicated opening movement.
5. Addonan PDF section 2 extracted into exactly 151 source rows. Repeated navigable destinations identify alias candidates, not approved runtime redirects. Headings are not aliases. Existing route matches are reuse candidates only.

## Actual verification

- PHP 8.2.12 / PHPUnit 11.5.55, SQLite `:memory:`; configuration cache absent.
- Full configured Laravel suite after the runtime and regression changes: **630 tests / 13,989 assertions, exit 0**.
- Initial fixed-asset regression failed because model dates serialize as datetime. Assertion now compares the model-cast calendar date; no production date behavior changed.
- Reference v33 source extracted outside the web application to `D:\xampp\tmp\erp-integration-reference\MGI_Payment_Plan_Source_v33`.
- Node v26.7.0 reference-only command: `node --test tests/domain.test.mjs tests/journal.test.mjs tests/historical-payments.test.mjs tests/comments.test.mjs tests/notifications.test.mjs tests/service.test.mjs`.
- Reference-only result: **21 tests passed, 0 failed, exit 0**. These are NOT Laravel integration/parity tests.
- No isolated MySQL race test, browser/mobile/print acceptance, staging restore or operational reconciliation performed in this increment.

## Stage checklist — incomplete items must stay open

| Stage | Actual status / next dependency |
|---|---|
| B01 | B01.1 green; opening/multi-period regression added. Full report/browser coverage and staging DB+attachment restore/control totals still open. |
| B02 | 151 source entries inventoried. Functional disposition, route/label correction, approved master crosswalk and precision validation still open. Reuse existing `legacy_map`, not a parallel master. |
| B03 | Atomic base posting and first writer consolidation implemented; runtime period opt-in tested. Linked reversal, common idempotency, immutable posted records, remaining writers and MySQL concurrency still open. |
| B04 | v33 source inspected and selected reference tests passed. Eleven-menu ERP integration, auth/storage adaptation and three-language parity NOT implemented. |
| B05 | Reservation, AP/DP/progress/final allocation and ERP acknowledgement integration open. No second ledger or bank balance introduced. |
| B06 | Existing GRN A3 remains canonical. Decimal/DP/tax/QC/matching expansion open. |
| B07 | Inventory/lot/opname/valuation extensions and acceptance open. |
| B08 | BOM/MRP/routing/WIP/FG/closing integration and acceptance open. |
| B09 | Sales/AR/subcontract/mechanic/scrap integration and acceptance open. |
| B10 | EXIM/import staging/reconciliation/report integration and acceptance open. |
| B11 | End-to-end role/retry/concurrency/export/browser acceptance and restore rehearsal open. |
| B12 | Release/cutover/rollback package not release-ready; no activation authorized by test success alone. |

## v33 security/parity observations

- `app/chatgpt-auth.ts` trusts Sites-authenticated headers in its original host context. Do NOT expose that trust model to public ERP requests. Use ERP authenticated identity and server-side role mapping for the integration.
- `db/schema.ts` includes versioned requests/settings/reports, immutable historical source hashes, events, comments and notification read state. Preserve these contracts when designing the adapter; do not substitute deprecated Laravel PaymentPlanService.
- Reference tests prove two Director approvals for bank, independent maker/GM, actual debit evidence, petty separation, immutable settlements, import atomicity, CSRF and concurrency protections in the reference only.
- IDR3/USD1 mandate remains unchanged; no operational account configuration modified.

## Next execution order

1. Inspect existing legacy-map services/import commands before adding master validation; preserve single-MGI ownership and quarantine conflicts instead of last-write-wins.
2. Complete financial report regression and isolated local restore tooling; actual operational restore stays an external activation blocker until authorized artifacts exist.
3. Introduce additive, flag-gated accounting source/idempotency/reversal contracts with transaction and retry tests, then migrate writers incrementally.
4. Build v33 ERP identity/storage/ledger adapter against golden reference fixtures, retaining all approval/separation protections. Never report source test success as ERP parity.
5. Continue operation modules in dependency order, maintaining explicit per-menu acceptance evidence and off-by-default flags.

Do not reset the worktree or restart B01 when resuming. Inspect current Git diff and this checkpoint first.

## ERP GL bank authority + partial AP ID extension (latest)

Read erp-bank-ap-extension.md. Added optional ERP bank projection flag (default off), cutoff/GL authority, stable paid-event subtraction only before represented journal, sealed intent detection when ACK null; no manual receipt double count. AP direct-IDR partial flow now binds purchaseBillId to actual posted bill/journal, reserves residual via company fence + bill lock, consumes/releases only proper lifecycle, posts existing payable without repeating expense/VAT. Added allocation table migration 150000, flag off. Data API exposes finance-only AP candidates and ERP projections; workbench selector/totals added (not original UI v33). Real HTTP AP path fixture: opening 500.00, bill 200.50, debit 100.25, bank 399.75, AP residual 100.25.

Target **5 tests / 88 assertions**, final full suite **719 tests / 14,646 assertions**, exit 0, oracle **66/66**. Full-run source guard initially caught direct GL reads; changed to ProtectedJournalQuery, guard unchanged. Actual source schema fixture correction documented. No MySQL/browser/restore/physical storage acceptance claimed. DP/contract stages/USD/petty/reference UI remain unfinished. AP legacy/manual payments/returns/credit reconciliation and cross-writer locks still block activation, as does verified cutoff/mapping. No production ops, no flag activation. Continue from these edits; don't declare full AP/DP delivery.

## Functional bank slice — use this as latest bank checkpoint

Read bank-flow-development.md. Implemented restricted **direct IDR services bank flow** with actual HTTP request→both Director approvals→private settlement upload→Finance settlement→maker reservation→GM release→private actual-debit evidence→paid event UUID→real existing PostingService journal+ACK. Dedicated `/payment-plan/bank` Blade development workbench linked from Filament; it is NOT the full reference v33 UI. API request/data/action/attachment/download implemented, default off. Additive migration 140000 adds company financial fence/settings, reservation/payment-event/private attachment metadata. Same existing domain/WorkflowStore/identity/ledger retained.

Stable event-key posting, mapping failure leaves paid and zero journal, retry one journal; reference-only post_external no longer sufficient in enabled bank adapter. Coarse single-company fence; hold/pending/failed preserve reservation and active instruction cannot be returned. AP/DP/asset/topup/non-direct/USD explicitly rejected pending proper adapters, not posted as expenses. Financial context settings currently fixture/provisioned only: no UI setup and no verified ERP opening/receipt reconciliation. No user DB migration/mandate/mapping changed.

Service and real HTTP tests prove synthetic 100.25 IDR path to one balanced journal, stage uploads/download, failed mapping paid preservation, overspend/return guards and retry. Target **4 tests / 73 assertions**; final full **716 tests / 14,597 assertions**, exit 0, isolated SQLite, oracle **66/66**. Blob disk faked in HTTP test; MySQL/browser/restore not claimed. Workbench parse failure diagnosed (complex inline @json), fixed with endpoint variable. Continue AP/DP resources, private attachment lifecycle/retries, reference UI/inbox and petty, not more discovery restarts. Full integration remains incomplete.

## Security triage + frontend build + reactive HTTP continuation

Latest source of truth: `dependency-security-triage.md`, saved Composer audit before/after JSON and 34-row triage CSV. 34 unique findings: 1 critical/15 high/14 medium/2 low/2 unknown; 33 prod and 1 dev (symfony/yaml), not Filament-origin assumptions. Targeted dry-run and actual patch upgraded only 9 affected packages plus guzzle promises; **Laravel now 12.70.1**, Filament/Livewire unchanged. **Composer audit 0**, validate/platform pass. No bypass/global update. Earlier 34-advisory release blocker is superseded by patched audit, not erased from history.

Installed frontend tooling using ignore-scripts, generated package-lock.json, explicitly ran existing Vite build successfully (58 modules, Vite 7.3.7), **npm audit 0**. No layout/CSS entry rewrite. New ErpPanelReactiveHttpTest exercises actual signed snapshot + update endpoint with middleware, revoked permission/company inactive/logout/flag/upload rejection and real CSRF 419. Handles process request boundaries with Livewire flushState in test only. **714 tests / 14,553 assertions**, full suite exit 0; **66/66 oracle**. Master division remains implemented-pending-acceptance: browser/ID tamper/export/MySQL/restore incomplete. Backend reservation/allocation/attachment/posting and module expansion are unfinished, not claimed delivered. No deployment or user DB writes.

## User-directed retirement — legacy company Sync to Temp

Removed obsolete sync buttons/forms and JS handlers from journal, sales_order, sales_invoice, purchase_order, purchase_bill and product index views (including differently labelled Sync Dashboard). Removed six corresponding HTTP dispatch routes; named routes absent and direct POST cannot dispatch even with legacy flag true. Kept temp tables/data, archived job classes/controller methods and existing job/console flag guards intact; no data deletion or environment changes. Manual entry/import/export and current-company processes retained. Regression compiles all six changed Blade templates and rejects old POSTs (404/405 depending existing GET record routes), asserting zero queued dispatches. Target group **8 tests / 92 assertions**, full suite **713 tests / 14,543 assertions**, exit 0, isolated SQLite. Initial test expected only 404; existing GET wildcard yields 405 on some URLs, contract updated to explicit non-dispatchable 404/405 with route absence and Bus assertions. No production route cache cleared; deployment with cached routes must rebuild it through normal release procedure.

## Priority incident fix — sales invoice index on legacy schema

User reported MySQL unknown si.cancelled_at. Reproduced HTTP 500 in isolated SQLite with cancellation-audit columns absent. Patched only SalesInvoiceController pivot predicate to check Schema::hasColumn before filtering; existing modern-schema exclusion remains tested, cancellation writer unchanged. Added two SalesInvoiceIndexTest regressions (legacy HTTP read and cancelled exclusion). Target group **12 tests / 57 assertions**, full suite **711 tests / 14,530 assertions**, exit 0. No operational schema/data changes, session values logged or user-cookie replay. This fixes reader compatibility, not full database migration readiness or ERP acceptance. Existing integration work preserved.

## Filament / Livewire official adoption continuation

Read `filament-livewire-adoption.md` before resuming. Actual targeted dependency install completed: Filament **5.11.0**, Livewire **4.4.8**, Laravel remains 12.61.1 / PHP 8.2.12. 35 added packages, zero existing versions changed. Composer validate/platform requirements passed. **34 security advisories on 9 existing package versions remain release blockers**, not dismissed. Published Filament JS/CSS/fonts; no NPM build/custom theme, no global Bootstrap/Alpine modification.

Panel `/erp-panel` id erp uses ERP session, flag default off, explicit MGI membership + `erp.panel.access` (no ADMIN fallback), persistent middleware and per-action service/Resource/model policy. Initial master slice reuses MasterDivisi with list/view/create/update, exact legacy uppercase convention, atomic service audit and denied delete/bulk/inline actions. Dashboard only actual master count, not invented financial summaries. Temporary uploads deliberately blocked pending private attachment acceptance. Three-language labels added; menu 18 marked implemented-pending-acceptance with presentation_driver and evidence; no verified claim.

Panel/Livewire tests passed **2 tests / 21 assertions**. Latest full suite **709 tests / 14,522 assertions, exit 0**, SQLite in-memory; Node legacy checks **66/66**. Permission fixture/harness issues diagnosed and fixed without weakening guards. Livewire test harness session explicitly attached because middleware is disabled by its unit-test route. Browser/reactive HTTP/upload/session-expiry acceptance remains open. No universal user-active field was found; deleted user, inactive company and revoked membership/permission are covered by guards, active-user policy still requires explicit model decision.

Next: targeted security advisory triage; real reactive HTTP authorization tests; dashboard/payment Resource use-case adapters; legacy division writer convergence; continue safe Payment Plan storage/attachment/reservation/posting and operational integration. Unfinished coding remains unfinished, not an external-access blocker. No production operations, auto commit or module activation.

## Continuation — all golden transitions and initial ERP workflow endpoint

- Extended same V33ApprovalDomain to all 17 reference actions, including prepare settlement, petty approval, submit, maker, release, payment confirmation and return. PaymentBalances supplies exact-decimal account/cash projections, legacy payee+invoice/PO residual checks and settlement balance snapshot. This is legacy parity, not reservation or ID-based allocation hardening.
- Explicit derived fixture now includes 60 transition cases, 3 weekly cases and 3 financial cases. G042 synthetic competing journal and G050 synthetic unconfirmed instruction have annotated provenance. Reference oracle reproduces **66/66**. PHP checks all 60 transition outcomes/errors and full successful result projections (monetary number/string representation canonicalized), three weekly and three financial cases. Full-projection fixtures are generated from the actual frozen-clock reference source, not hand-authored expected approvals.
- Pure v33 group after full projections: **64 tests / 408 assertions**, exit 0. Grouping weekly/financial examples into tests explains why PHPUnit test count is not 66.
- New default-off config `payment_plan_v33.php`, no production config/mandate changes. ErpActor reads authenticated ERP user, current MGI membership and explicit company-scoped PPL_* Platform roles; legacy ADMIN title or Sites headers confer no payment authority. Tests prove guest spoofing rejection, missing role denial, membership revocation and disabled flag.
- Additive development migration `2026_10_27_130000_create_ppl_workflow_storage.php` creates ppl_requests and ppl_events with company/user FK, document uniqueness, optimistic version and mutation evidence. Tested only via isolated SQLite migrations. Used evidence cannot be dropped by migration down().
- WorkflowStore currently persists only nonfinancial actions on nonallocated requests under request lock/transaction with three deadlock attempts. It checks visibility/version, returns identical mutation retry result and appends event in same transaction. Financial actions and asset/AP/DP sources explicitly fail closed until shared-invariant locks/reservation adapters exist. No posting or balance side effects.
- Real web route `POST /payment-plan/api/action`, auth/session/CSRF web stack, ErpActor and validation. HTTP regression proves submit/retry/stale/conflict, requester cannot approve, assigned Director can approve, spoofed header cannot authenticate, fake external journal reference cannot mark posted, flag-off 404. This is a working restricted endpoint, not the complete v33 API/UI.
- First full run found validated empty `input` omitted after nested rules (500). Fixed by normalizing validated empty action input to []; repeated HTTP test passed **1 test / 22 assertions**; final full suite **707 tests / 14,489 assertions**, exit 0. Initial failed run retained in test-evidence, not claimed green.
- Documentation sources for transaction/locking: https://laravel.com/docs/12.x/database#database-transactions and https://laravel.com/docs/12.x/queries#pessimistic-locking .
- STILL OPEN: request creation/edit validation and normalized lines; private attachments; complete API/data/settings/comments/notifications/history/reports; aggregate locks/reservations/AP/DP/event-ID posting; UI adapter; legacy convergence; operation modules; MySQL/browser/restore/release acceptance. Do not activate partial module. These are unfinished coding, not external blockers. Production artifacts/mandates/UAT remain separate activation blockers.

## Resumption — expanded decision parity and weekly projection

- Actual branch reconfirmed `feat/marvel-dard-integration`; all prior edits and user deletion preserved.
- Extended existing V33ApprovalDomain rather than adding a parallel state machine. It now handles initial approval, both petty acknowledgements, settlement approval, hold, restart-after-no-debit, external-reference legacy posting, rejection, bank attempts and correction. Still incomplete: submit/prepare_settlement/approve_petty/mark_maker/release_gm/confirm_paid/return and supporting balance/allocation operations.
- Golden subset expanded to 22 original case IDs. All requests collections are explicit. G042 now supplies an annotated synthetic competitor referencing J1. Source fixture SHA-256 retained; original expectations unchanged. Frozen clock moved to noon UTC on the fixture date, after recorded decision timestamps. G050 remains outside current coverage; no concurrency/reservation guarantee inferred.
- PHP: **22 parity cases / 122 assertions passed**. Actual reference Node domain with same derived context: **22/22 passed**. Success cases now compare requested settlement fields as well as state/actor/time; error code/status and unchanged input remain covered.
- Added pure WeeklyCashSnapshot, exact decimal strings and UTC calendar validation. Three weekly golden contexts explicitly use empty requests and first-case settings; this context selection is documented as synthetic. Additional test proves previous-period carry-forward and exclusion of pending/bank payments, retaining payment attachments only. These are read-only calculations, not frozen persisted report acceptance.
- Pure v33 group: **26 tests / 161 assertions passed**. Full isolated SQLite suite: **665 tests / 14,199 assertions, exit 0**. PHP/Node syntax and whitespace checks passed.
- No new migration, endpoint, storage adapter, actual payment, live journal, UI activation, MySQL race, restore or full 66-case parity claimed. Continue remaining transitions and balance/allocation helpers before persistence/API adapter. Keep legacy external-reference behavior distinct from actual ERP posting acknowledgement.

## Combined Claude/v33 method continuation

See `merged-v33-method.md`. Actual three-artifact ZIP located and read; no design CSV reuse claims promoted. Added pure PHP `App\Modules\PaymentPlan\Domain\V33ApprovalDomain` with injected immutable clock, RuleViolation and exact SettlementAmounts. Eight derived approval cases reproduce 8/8 on reference Node and PHP; three financial examples covered separately from hardened decimal-input tests. Pure-domain group: 10 tests / 60 assertions passed. Full suite after approval slice: 647 tests / 14,070 assertions passed; final full run after financial slice recorded separately. G042/G050 context not fabricated, full 66-case parity not claimed. Added UI transport audit (49 references, all pending adaptation). No endpoint/UI exposes the incomplete domain. Stable payment-event identity, retained hold reservations and aggregate invariant locking are required contracts, not yet implemented guarantees. Continue full domain and explicit golden contexts, then persistence/API/Blade reuse and legacy convergence; do not stop unrelated work on unsigned COA mappings.

Final full suite after financial slice: **649 tests / 14,097 assertions, exit 0**, SQLite in-memory. Syntax/diff checks passed. No additional migration, live API, production operation or module activation in this continuation.

## Shared accounting controls increment (continuation)

- Added development migration `2026_10_27_120000_create_accounting_posting_intents.php`; it stores a unique idempotency key, SHA-256 payload hash, source type/id, actor, sealed journal FK and unique original-journal reversal FK. This table is evidence, not a second ledger. Rollback refuses to discard used evidence.
- Existing PostingService accepts an optional fifth `$intent` argument. Default `PLATFORM_POSTING_INTENTS_ENABLED=false`; legacy callers remain unchanged. Keys are compared against canonical associative payloads; line order remains significant; persistence timestamps are excluded. Numerically equivalent but differently serialized values are conservatively conflicts, not silently normalized.
- Retry arbitration uses unique insert plus current locking read. Same payload returns the original journal; conflicting payload throws; failed posting rolls back the reservation. Actual simultaneous MySQL execution remains unverified.
- Sealing guards model/protected-query journal updates, deletions and appended/reparented details. Sealed protection remains active even if the creation flag is later off. Raw `DB::table` or direct SQL is NOT intercepted; remaining raw writers and database-level enforcement still require work. Do not claim universal immutability.
- `PostingService::reverse()` supports sealed MANUAL originals only, requires explicit FINANCE actor allowlist `JOURNAL_REVERSAL_USER_IDS`, reason and open destination period. It writes a new balanced journal and linked evidence without changing original lines. FX amounts and original rate snapshots are retained. Changed retry payload conflicts.
- Generic reversal rejects opening/source journals, reversal-of-reversal and journals with assets. Asset and operational source reversal must use domain-specific behavior; generic GL reversal must not corrupt subledgers. No HTTP reversal endpoint or UI has been activated yet.
- Source type/id and reversal FK are database traceability only; source-document link UI is not yet integrated with the new evidence.
- Menu generator now uses the six requested statuses and separate implementation/permission/test-evidence columns. It never promotes route existence to implemented/verified. See `menu-status-contract.md`.
- Full suite at intermediate verification: **637 tests / 14,029 assertions, exit 0**, including 7 new intent tests. Two additional rollback/conflicting-reversal tests and actor evidence were added after that run; final validation follows in test evidence.

Remaining priority: complete concurrency/lock-order verification, permissioned journal read/reversal UI and source-link integration, consolidate further writers, then ERP v33 adapter. B04–B12 remain incomplete, not blocked merely by lack of production backup. No claim of Laravel v33 parity, complete 151-menu functionality or release readiness.

Final verification for this continuation: **639 tests / 14,034 assertions, exit 0** (PHPUnit 11.5.55, PHP 8.2.12, SQLite in-memory). Nine new accounting-intent tests cover base retry/conflict, sealing, failure rollback, off-by-default creation, manual linked reversal/open destination month, actor allowlist, FX snapshots, evidence-retaining migration rollback and changed reversal payload. Migration was exercised only by isolated tests. Syntax and diff checks passed. Menu schema validation passed: 151 ordered IDs; 133 unmapped, 9 reuse candidates, 6 mapped-not-implemented, 3 headings; zero verified claims. See `accounting-intent-contract.md` for explicit limitations.