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