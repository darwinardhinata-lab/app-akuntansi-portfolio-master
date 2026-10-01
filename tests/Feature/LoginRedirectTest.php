<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://localhost/app-akuntansi-portfolio-master/public']);
    }

    public function test_login_discards_an_intended_url_outside_the_application_base_path(): void
    {
        $user = User::factory()->create([
            'email' => 'login-redirect@erp.local',
            'password' => Hash::make('correct-password'),
        ]);

        $this->withSession(['url.intended' => 'http://localhost/'])
            ->post('/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertRedirect(route('dashboard.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_keeps_an_intended_url_inside_the_application_base_path(): void
    {
        $user = User::factory()->create([
            'email' => 'login-redirect-internal@erp.local',
            'password' => Hash::make('correct-password'),
        ]);
        $intended = 'http://localhost/app-akuntansi-portfolio-master/public/akun';

        $this->withSession(['url.intended' => $intended])
            ->post('/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertRedirect($intended);

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_authenticated_user_opening_the_login_page_is_sent_to_the_explicit_dashboard_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect(route('dashboard.index'));
    }
}
