# R1 AC05: posting process-loss regression

Run `vendor/bin/phpunit --filter PaymentPostCrashTest` in a test checkout.
The test runs the production workflow service in child processes against
two newly migrated SQLite files. No environment database or storage is used.

| Kill point | Durable state before retry | Result of identical mutation retry |
| --- | --- | --- |
| After event insert, before transaction commit | Request stays paid; payment event exists, but journal and posting ACK roll back | One journal, two lines and one stored ACK |
| After transaction commit, before response is emitted by worker | Request is posted; original journal and stored ACK are durable | Replay returns the original journal, without another event or journal |

The test checks the transaction level at each kill point and reads persisted
state from separate PHP processes. It does not change production code or
model an external posting outbox. Journal and stored ACK commit atomically
in this workflow: the after-commit case loses the caller's response, not the
stored ACK. Evidence is limited to the service worker, SQLite and a direct
IDR fixture. It is not proof for the HTTP transport, a partial AP invoice,
MariaDB/InnoDB, or Finance acceptance.

## MariaDB partial-AP HTTP matrix (Windows)

From an isolated checkout with Composer dependencies installed, run:

```powershell
node tests/Support/run-ac05-http-mariadb.mjs D:\ERP-Recovery\new-ac05-evidence D:\xampp\mysql\bin D:\xampp\php\php.exe
```

The runner creates a private loopback instance, separate schemas, and a
schema-scoped application account reduced to DML before HTTP runs. The PHP
server uses the application login, CSRF and accountant role and posts to
`/payment-plan/api/action`. The test router stops only the owned worker at
outer transaction levels 1 and 0, before HTTP headers are sent. Snapshots
are taken from fresh connections and the runner removes the private DB and
session/storage directories. The named evidence directory must not exist.

The fixture pays 100.25 against a 200.50 invoice. Reservation and allocation
are already consumed before posting. Available bank and residual remain
399.75 and 100.25, while bank ledger replaces pending debit on posting.
The posting adds one journal (AP debit and bank credit), one sealed intent
and one stored ACK, linking the existing confirmed event without changing
its creation timestamp. Same-key changed HTTP amount is rejected at the
mutation boundary; a separately probed changed amount under the same sealed
intent is rejected by `PostingService`. Neither changes committed state.

This does not verify Finance UAT, operational restore, or physical attachment
handling; prerequisite evidence metadata is synthetic.