<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class RejectPanelUploads
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('platform.filament_enabled', false), 404);
        // No Resource currently owns an accepted attachment use case.
        abort(403, 'Panel uploads are not enabled.');
    }
}