# R1 Migration Runbook — Private Attachment Extension for Reconciliation

**Target commit:** `64a64b7` (feat/payment-plan: promote canonical Finance payment UI)  
**Branch:** `feat/marvel-dard-integration`  
**Migration file:** `database/migrations/2026_10_29_120000_extend_private_attachments_for_reconciliation.php`  
**Environment:** Staging / Production candidate — **do not run on user's local database**

---

## 1. Pre-Flight Checklist

| # | Item | Owner | Status |
|---|------|-------|--------|
| 1 | Full database backup (mysqldump / pg_dump) completed and verified | DBA | ☐ |
| 2 | Verify current schema matches expected baseline (`ppl_attachments`, `ppl_ap_reconciliations`, `users` tables exist) | DBA | ☐ |
| 3 | Verify disk space ≥ 2× database size for migration overhead | IT/Ops | ☐ |
| 4 | Verify `composer install` / `php artisan migrate:status` shows no pending migrations | Developer | ☐ |
| 5 | Maintenance mode scheduled (`php artisan down`) | IT/Ops | ☐ |
| 6 | Rollback procedure reviewed and approved (see Section 4) | DBA + Lead | ☐ |
| 7 | Feature flags remain OFF: `PAYMENT_PLAN_V33_AP_ALLOCATIONS=false`, `PAYMENT_PLAN_V33_BANK_WORKFLOW=false` | Developer | ☐ |
| 8 | Staging database is a disposable copy, not production | Developer | ☐ |

---

## 2. Command Sequence — Apply Migration

Run on staging database only:

```bash
# 1. Enable maintenance mode
php artisan down

# 2. Verify migration status before applying
php artisan migrate:status

# 3. Apply the specific migration
php artisan migrate --path=database/migrations/2026_10_29_120000_extend_private_attachments_for_reconciliation.php

# 4. Verify migration applied
php artisan migrate:status

# 5. Run post-migration validation queries (Section 3)
# 6. Disable maintenance mode
php artisan up
```

**What this migration does (additive, backward-compatible):**

1. `ppl_attachments.request_id` → nullable change (was required)
2. `ppl_attachments.reconciliation_id` → new FK to `ppl_ap_reconciliations.id`, nullable
3. Unique constraint `(reconciliation_id, sha256)` on `ppl_attachments`
4. MySQL CHECK constraint: exactly one of `request_id` / `reconciliation_id` must be non-null (one-context rule)
5. Creates `ppl_attachment_audits` table: id, attachment_id (FK), actor_id (FK users), action (varchar 30), created_at

**Down migration:** Throws `RuntimeException` — evidence must be retained, rollback refused. To reverse, restore from backup.

---

## 3. Post-Migration Validation Queries

Run against the migrated database:

```sql
-- 3.1 Verify ppl_attachments.request_id is nullable
SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ppl_attachments' AND COLUMN_NAME = 'request_id';

-- Expected: IS_NULLABLE = 'YES'

-- 3.2 Verify ppl_attachments.reconciliation_id exists
SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_TYPE, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.COLUMNS c
LEFT JOIN information_schema.KEY_COLUMN_USAGE k
  ON c.TABLE_NAME = k.TABLE_NAME AND c.COLUMN_NAME = k.COLUMN_NAME
WHERE c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME = 'ppl_attachments' AND c.COLUMN_NAME = 'reconciliation_id';

-- Expected: REFERENCED_TABLE_NAME = 'ppl_ap_reconciliations', REFERENCED_COLUMN_NAME = 'id'

-- 3.3 Verify unique constraint on (reconciliation_id, sha256)
SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ppl_attachments' AND INDEX_NAME = 'ppl_attachments_reconciliation_id_sha256_unique';

-- 3.4 Verify ppl_attachment_audits table exists
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ppl_attachment_audits';

-- Expected: 'ppl_attachment_audits'

-- 3.5 Verify MySQL one-context CHECK constraint
SELECT CHECK_CLAUSE
FROM information_schema.CHECK_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'ppl_attachments_one_context';
```

---

## 4. Rollback Procedure (Migration Failure)

The migration `down()` method throws `RuntimeException` intentionally — attachment evidence and audit records **must not be discarded**.

If migration fails mid-way:

1. **Do NOT attempt `php artisan migrate:rollback`** — it will fail by design.
2. Restore database from the pre-migration backup (Section 1, Item 1).
3. Investigate failure cause (disk space, lock timeout, foreign key conflict).
4. Re-run pre-flight checklist, then re-apply migration.

If migration partially applied but transaction rolled back (MySQL DDL is transactional for InnoDB), database remains consistent — re-apply migration.

---

## 5. Sign-Off Checklist (DBA / IT)

| # | Item | Sign-off | Date |
|---|------|----------|------|
| 1 | Pre-flight checklist complete | ☐ | |
| 2 | Migration applied successfully on staging | ☐ | |
| 3 | Post-migration validation queries all return expected results | ☐ | |
| 4 | Full test suite passes on migrated staging DB | ☐ | |
| 5 | Rollback procedure tested (backup restore verified) | ☐ | |
| 6 | Maintenance mode lifted | ☐ | |
| 7 | Production migration scheduled | ☐ | |

---

**Prepared by:** Cline (AI coding agent)  
**Reviewed by:** —  
**Approved by:** —  
**Date:** 2026-10-30