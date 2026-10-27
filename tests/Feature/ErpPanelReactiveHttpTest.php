<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpPanelReactiveHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_update_endpoint_rechecks_flag_and_revoked_permission(): void
    {
        config(['platform.filament_enabled' => true]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true]);
        $this->seed(\Database\Seeders\PlatformPermissionSeeder::class);
        $role = Role::create(['code' => 'REACTIVE_TEST', 'name' => 'Reactive test']);
        $role->permissions()->attach(Permission::whereIn('code', ['erp.panel.access', 'division.view', 'division.create'])->pluck('id'));
        $user->platformRoles()->attach($role->id, ['company_id' => $company->id]);
        $html = $this->actingAs($user)->get('/erp-panel/divisions/create')->assertOk()->getContent();
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = null;
        foreach ($matches[1] as $encoded) {
            $candidate = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
            if (($decoded['memo']['path'] ?? '') === 'erp-panel/divisions/create') $snapshot = $candidate;
        }
        $this->assertNotNull($snapshot, 'Actual panel page must expose a signed division component snapshot.');
        $body = ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]]];
        $url = route('default-livewire.update');
        $this->withHeader('X-Livewire', 'true')->postJson($url, $body)->assertOk();
        $role->permissions()->detach(Permission::where('code', 'erp.panel.access')->value('id'));
        app('livewire')->flushState();
        $this->postJson($url, $body)->assertForbidden();
        $role->permissions()->attach(Permission::where('code', 'erp.panel.access')->value('id'));
        app('livewire')->flushState();
        $company->update(['active' => false]);
        $this->postJson($url, $body)->assertForbidden();
        $company->update(['active' => true]);
        app('livewire')->flushState();
        $this->postJson(route('livewire.upload-file'), [])->assertForbidden();
        auth()->forgetGuards();
        $this->app['auth']->guard('web')->logout();
        app('livewire')->flushState();
        $this->postJson($url, $body)->assertUnauthorized();
        $this->actingAs($user);
        config(['platform.filament_enabled' => false]);
        app('livewire')->flushState();
        $this->postJson($url, $body)->assertNotFound();
        $this->postJson(route('livewire.upload-file'), [])->assertNotFound();
        $originalEnvironment = app()->environment();
        app()->instance('env', 'staging'); // Enable the real CSRF check normally skipped by PHPUnit.
        try {
            app('livewire')->flushState();
            $this->postJson($url, $body)->assertStatus(419);
        } finally {
            app()->instance('env', $originalEnvironment);
        }
    }
}