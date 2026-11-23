# AC07 storage verification and Ops handoff

## Local technical check

Run `node scripts/r1-storage-validation.mjs` with Composer dependencies and
PHP SQLite available. `R1_STORAGE_PHP` may specify the PHP executable;
`R1_STORAGE_REPORT` may specify a dedicated report path. The validator uses
SQLite in memory and a fresh temporary local private filesystem. It verifies
upload audit rollback and clean retry, DB/blob hash correlation, duplicate
rejection, missing/tampered evidence, authorized download, and forbidden
download/preview through application routes. The injected audit failure is
a handled failure, not an abrupt worker termination.

Exit 0 requires a passing required regression and persisted PASS report.
Missing PHP, a failed or absent regression, or unwritable reporting is
nonzero. The report must not be used as physical staging acceptance.

## Ops target and access (not yet approved)

Ops must record the staging application URL and deployed SHA, company/test
tenant, exact private disk backend and isolated bucket/prefix or filesystem
root, DB alias, and cleanup owner. Do not use production, shared Finance
records 8912/8913, or a public webroot. Grant the staging application only
the object operations it requires on that dedicated prefix; give the
evidence operator read/list and scoped DB read access for correlation, not
global storage or DB privileges. Use named staging accounts: an authorized
request owner/uploader, a reconciliation proposer/evidence owner with the
appropriate reconciliation permissions and company access, an authorized
archived-evidence reviewer where configured, and a same-company non-owner
without review permissions for negative checks. Provision secrets outside
the evidence package. No grants or flag changes are implied by this handoff.

## Evidence to collect on that target

- Upload controlled fixtures through normal request and reconciliation UI;
  verify stage, size/MIME limits, duplicate/retry behavior and actor/version
  audits. Match the served bytes, stored object and DB metadata SHA-256.
- Verify private access, authorized preview/download, denied non-owner
  access, and post-archive behavior for ordinary users and approved archive
  reviewers. Verify missing/tampered object handling on test objects only.
- Check object inventory against committed DB keys in both directions.
  Demonstrate handled rollback and cleanup; capture or reconcile a controlled
  interrupted upload if required by the acceptance contract. Do not delete
  unknown objects or use a prefix-wide production cleanup.
- Record SHA, roles (no secrets), UTC timestamps, status codes, redacted
  audit records, object/DB correlation, and exact fixture cleanup. Preserve
  existing evidence; Finance/Ops record acceptance separately.

AC07 overall status: **PARTIAL** until the approved staging run is complete.