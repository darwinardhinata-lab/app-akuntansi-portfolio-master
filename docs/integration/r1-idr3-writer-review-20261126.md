# F03 — IDR3 writer review, 26 November 2026

Scope: source at `5da595ee9b684acaf380314f372742d227f9bdbe`; no operational DB
or scheduler inspection. Pilot is single MGI with active `idr3` bank mapping.

27 November follow-up: see the [GL and business-date matrix](r1-f03-writer-dates-20261127.md).

| Entry point | Control and evidence | Disposition |
| --- | --- | --- |
| `payment.post_journal` | Posting authorization, shared fence before capacity/write; committed-hold and both race orders in `r1-legacy-payment-post-f03-race-20261126.md`. | PR #31 merged at `d9b3537eec4b964d025f01f9665a26cf88aea9f7`; not deployed. |
| `jurnal.store/update/destroy/massDestroy`, `jurnal.import` (CSV/XLSX), opening balance form/import, manual FX | `JournalController`, `JournalCsvImportService`, `AccountController`, `OpeningBalanceService`, `FxPostingService`; existing `ManualJournalBankCapacityTest` and MariaDB overlap variants exercise credits, removal/replacement of bank receipts, opening and currency cases. | Reused evidence; not new runs. |
| Legacy `PaymentPlanService::postToJournal` | No caller found in app/routes; separate AR semantics from normal posting route. | Not proven reachable; do not treat as covered by #31 if wired later. |
| `jurnal:fast` / `jurnal:sync` | CLI only, no sync flag/capacity fence; arbitrary journal import and old-journal replacement. RED on migrated disposable: new journal insert hits missing `description`; matching existing receipt hits `GrnProtection::journals(array)` with Collection before deletion. Neither proves a committed loss on this schema. | Explicit early refusal when `payment_plan_v33.bank_workflow` is on; use guarded `jurnal.import`. Off-mode legacy errors are unchanged, not repaired. |
| `SyncDashboardToTempJob` → `ProcessPendingTempJob` and parallel dashboard sync families | `routes/console.php` only registers these schedules when `platform.legacy_sync_enabled`; jobs short-circuit on the same flag, including queued work. Flag defaults OFF and staging config is OFF. `MgiLegacySyncGuardTest` passed (2 tests/15 assertions); no DB read/write when disabled. | Restricted in pilot config, not safe to enable on this evidence. Operational flag/supervisor settings still unconfirmed. |
| Other posting/import families | `import:bil`/`import:inv`/`import:sr` emit fixed inventory/AP/AR/revenue/COGS accounts, not free-form bank input; `import:pr` and purchase-return reversal use the financial fence. | Mapping or reachability changes need review. No blanket certification of other accounting writers or external scripts. |

The CLI follow-up is separate from PR #31. Its MariaDB tests hold `400.00`
against `500.00`, persist a synthetic existing receipt, attempt each CLI
entry point, then reconnect to confirm the commands return nonzero with the
expected message and leave both journals, all lines and the hold unchanged.
GREEN: 2 tests / 24 assertions (exit 0). CLI contract, web journal and sync
guard regression: 14 tests / 112 assertions. The CLI contract tests trap
file stat/open and DB access with workflow ON, even if posting and ERP bank
projection are OFF. With workflow OFF, the original missing-file output and
exit status are retained; successful legacy writes are not certified.
No call sites were found in tracked scheduler, jobs, workflow or scripts;
untracked/external operating schedules are outside this review.
Disposable logs live at
`D:/ERP-Recovery/m1-f03-cli-{red,green}.log`. No production configuration
changes or deployment are included.