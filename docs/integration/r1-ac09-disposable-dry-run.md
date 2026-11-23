# AC09 — disposable attachment migration dry-run

Use an empty schema named `r1dry` followed by 16 lowercase hexadecimal characters, on an independently provisioned loopback MariaDB instance on a nondefault port. Use a dedicated account with the same name and `ALL PRIVILEGES` on that schema only, without grant option, global privileges, or roles. Never reuse a server or account used by an operational database. Set `R1_DRY_RUN_DB_URL` in the calling process via your approved secret source; do not put the URL in command history or logs. The URL format is `mysql://USER:PERCENT_ENCODED_PASSWORD@127.0.0.1:PORT/SCHEMA`.

Invoke `scripts/r1-migration-dry-run.ps1` with `-ExpectedSchema` and `-ExpectedServer` (the independently observed MariaDB `@@hostname`). Only `r1_disposable` is accepted as the alias. The runner pins Laravel's default and migrator alias, checks effective schema, port, server, account, grants and empty objects before invoking migrations. It applies prerequisites and then the named attachment migration; it does not use `migrate:fresh` or drop existing data. The operator owns schema/user cleanup even on a failed run; MariaDB DDL is not rolled back as one transaction.

| Exit | Meaning |
|---|---|
| 0 | Migration applied; actual attachment columns, unique indexes and context CHECK verified; journal-detail count and debit/credit totals unchanged |
| 1 | Launcher failed |
| 2 | Target rejected before migration |
| 3 | Migration or control-total capture failed |
| 4 | Post-migration verification failed |

Disposable verification: correct target `0` (116 tables); wrong alias, hostname and excess grants `2` (zero tables); injected migration exception `3`; wrong CHECK name `4`. All six temporary schemas and accounts were removed. An unrelated inherited `DB_URL` pointed at an unusable endpoint; the success case verified the parameter-supplied endpoint instead. Injected source variants were restored and were not committed. The former script returned `0` with an injected subprocess migration exit `23` and selected `sqlite_testing` regardless of its target parameter.

This is a technical subgate only. The ledger check is a disposable control-total check, not operational ledger reconciliation or recovery. Overall AC09 remains **NOT RUN** on approved staging DB and object storage. Ops provisions and verifies the target; Finance/DBA retain reconciliation and restore sign-off. Human UAT **DEFERRED**; production **NO-GO**.