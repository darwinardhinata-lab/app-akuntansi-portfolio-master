<?php

namespace App\Listeners;

use App\Models\SystemLog;
use Illuminate\Auth\Events\Login;

class LogUserLogin
{
    public function handle(Login $event): void
    {
        SystemLog::record('LOGIN', 'Autentikasi', 'Login: ' . $event->user->name);
    }
}