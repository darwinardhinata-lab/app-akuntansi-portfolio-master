<?php

namespace App\Modules\CustomsReports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomsReports\Services\CustomsSettingsService;
use Illuminate\Http\Request;

class CustomsSettingsController extends Controller
{
    public function edit(Request $request, CustomsSettingsService $settings)
    {
        abort_unless($request->user()->role === 'ADMIN', 403);

        return view('customs-reports.settings', ['autoSync' => $settings->autoSyncInternal()]);
    }

    public function update(Request $request, CustomsSettingsService $settings)
    {
        abort_unless($request->user()->role === 'ADMIN', 403);
        $validated = $request->validate([
            'h2h_enabled' => 'required|boolean',
            'auto_sync_internal' => 'required|boolean',
        ]);
        if ($request->boolean('h2h_enabled')) {
            return back()->withErrors(['h2h_enabled' => __('customs_settings.locked')]);
        }
        $settings->save((bool) $validated['auto_sync_internal']);

        return back()->with('success', __('customs_settings.saved'));
    }
}