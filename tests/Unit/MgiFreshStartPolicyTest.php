<?php

namespace Tests\Unit;

use App\Modules\Platform\Support\MgiFreshStartPolicy as Policy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MgiFreshStartPolicyTest extends TestCase
{
    public function test_target_must_be_new_prefixed_name(): void
    {
        Policy::validateTarget('bbw', 'mgi_fresh_20260924');
        $this->expectException(InvalidArgumentException::class);
        Policy::validateTarget('mgi_fresh_live', 'mgi_fresh_live');
    }

    public function test_unsafe_target_name_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Policy::validateTarget('bbw', 'mgi_fresh_a`; DROP DATABASE bbw;');
    }

    public function test_generic_database_name_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Policy::validateTarget('bbw', 'production');
    }

    public function test_only_coa_user_and_required_access_metadata_are_copied(): void
    {
        foreach (['accounts', 'account_translations', 'coa_type_translations', 'users', 'roles', 'permissions', 'role_permission', 'user_role', 'user_company', 'migrations'] as $table) {
            $this->assertSame('COPY_EXACT', Policy::mode($table));
        }
        $this->assertSame('USER_REFERENCES_ONLY', Policy::mode('master_divisi'));
    }

    public function test_all_operational_and_unknown_tables_start_empty(): void
    {
        foreach (['journal_headers', 'journal_details', 'products', 'inventory_ledgers', 'purchase_orders', 'sales_orders', 'purchase_bills', 'assets', 'parties', 'party_bank_accounts', 'payment_requests', 'transaksi_payment_plan', 'jobs', 'sessions', 'cache', 'personal_access_tokens', 'cst_customs_credentials', 'future_unknown_table'] as $table) {
            $this->assertSame('EMPTY', Policy::mode($table));
        }
    }

    public function test_company_changes_identity_without_breaking_access_ids(): void
    {
        $row = Policy::company(['id' => 9, 'code' => 'BBW', 'name' => 'Old', 'legacy_company_profile_id' => 7]);
        $this->assertSame(9, $row['id']);
        $this->assertSame(7, $row['legacy_company_profile_id']);
        $this->assertSame('MGI', $row['code']);
        $this->assertSame('PT. Magicase Group Indonesia', $row['name']);
    }

    public function test_old_profile_identity_and_pin_are_not_reused(): void
    {
        $row = Policy::profile(['id' => 7, 'company_name' => 'BBW', 'npwp' => 'old', 'address' => 'old', 'logo' => 'old.png', 'employee_pin' => 'old-pin']);
        $this->assertSame(7, $row['id']);
        $this->assertSame(Policy::NAME, $row['company_name']);
        foreach (['npwp', 'address', 'logo', 'employee_pin'] as $column) {
            $this->assertNull($row[$column]);
        }
    }

    public function test_unknown_company_profile_field_requires_review(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Policy::profile(['id' => 1, 'bank_account' => 'old bank']);
    }
}
