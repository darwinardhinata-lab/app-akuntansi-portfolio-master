<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SystemLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemLogAuthorizationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_read_log_endpoints(): void
    {
        foreach (['STAFF', 'FINANCE'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('logs.index'))->assertForbidden();
            $this->getJson(route('logs.entity', ['keyword' => '%']))->assertForbidden();
        }
    }

    public function test_admin_search_escapes_wildcards_and_caps_results(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        SystemLog::create(['action' => 'UPDATE', 'module' => 'Audit', 'description' => 'REF%_literal']);
        SystemLog::create(['action' => 'UPDATE', 'module' => 'Audit', 'description' => 'REF-other']);
        $this->getJson(route('logs.entity', ['keyword' => '%_']))->assertOk()
            ->assertJsonPath('count', 1)->assertJsonPath('has_more', false);
        for ($i = 0; $i < 101; $i++) {
            SystemLog::create(['action' => 'UPDATE', 'module' => 'Audit', 'description' => 'LIMIT-'.$i]);
        }
        $this->getJson(route('logs.entity', ['keyword' => 'LIMIT-']))->assertOk()
            ->assertJsonPath('count', 100)->assertJsonPath('has_more', true);
        $this->getJson(route('logs.entity', ['keyword' => ' ']))->assertUnprocessable();
        $this->getJson(route('logs.entity', ['keyword' => str_repeat('x', 201)]))->assertUnprocessable();
        $this->get(route('logs.index'))->assertOk();
    }
}