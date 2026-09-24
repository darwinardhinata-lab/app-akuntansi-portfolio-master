<?php

namespace Tests\Feature;

use App\Modules\Platform\Models\Company;
use Database\Seeders\PlatformFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MgiFoundationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_rerunning_foundation_does_not_recreate_bbw_after_mgi_cutover(): void
    {
        $company = Company::create(['code' => 'MGI', 'name' => 'PT. Magicase Group Indonesia']);
        $this->seed(PlatformFoundationSeeder::class);
        $this->seed(PlatformFoundationSeeder::class);
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseMissing('companies', ['code' => 'BBW']);
        $this->assertSame('MGI', $company->fresh()->code);
    }

    public function test_existing_bbw_is_not_renamed_by_merely_installing_or_seeding(): void
    {
        $company = Company::create(['code' => 'BBW', 'name' => 'Legacy']);
        $this->seed(PlatformFoundationSeeder::class);
        $this->assertDatabaseCount('companies', 1);
        $this->assertSame('BBW', $company->fresh()->code);
        $this->assertSame('Legacy', $company->fresh()->name);
    }
}
