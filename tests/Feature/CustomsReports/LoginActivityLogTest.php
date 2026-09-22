<?php

namespace Tests\Feature\CustomsReports;

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class LoginActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        URL::forceRootUrl('http://localhost');
    }

    public function test_login_creates_system_log_entry(): void
    {
        $user = User::factory()->create([
            'name' => 'Andi Prasetyo',
            'password' => Hash::make('rahasia123'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'rahasia123',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('system_logs', [
            'user_id' => $user->id,
            'action' => 'LOGIN',
            'module' => 'Autentikasi',
            'description' => 'Login: Andi Prasetyo',
        ]);
        $this->assertSame(1, SystemLog::count());
    }
}