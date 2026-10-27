<?php

namespace App\Http\Middleware;

use App\Support\ErpPanelAccess;
use Closure;
use Illuminate\Http\Request;

final class EnsureErpPanelAccess
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('platform.filament_enabled', false), 404);
        abort_unless($request->user(), 401);
        abort_unless(app(ErpPanelAccess::class)->allows($request, $request->user()), 403);
        return $next($request);
    }
}