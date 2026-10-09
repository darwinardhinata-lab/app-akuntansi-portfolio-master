<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthorizePaymentAction
{
    public function handle(Request $request, Closure $next, string $action)
    {
        if ($action === 'status') {
            $action = $request->input('status_payment') === 'PAID' ? 'pay' : 'approve';
        }
        $user = $request->user();
        abort_unless($user && $user->role === 'FINANCE'
            && in_array((string) $user->id, array_map('strval', config('platform.payment_'.$action.'_user_ids', [])), true), 403);
        return $next($request);
    }
}