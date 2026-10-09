<?php

namespace App\Http\Controllers;

use App\Services\AccountingPeriodService;
use Illuminate\Http\Request;

class AccountingPeriodController extends Controller
{
    public function update(Request $request, AccountingPeriodService $service)
    {
        $data = $request->validate(['month' => 'required|date_format:Y-m', 'action' => 'required|in:close,reopen', 'reason' => 'required|string|min:10|max:1000']);
        $service->change($data['month'], $data['action'] === 'close', $data['reason'], $request->user());
        return response()->json(['month' => $data['month'], 'action' => $data['action']]);
    }
}