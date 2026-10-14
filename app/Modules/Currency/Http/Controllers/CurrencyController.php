<?php

// FIX: multicurrency — master global, base dibaca dari perusahaan pilihan yang sah.
namespace App\Modules\Currency\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Currency\Models\Currency;
use App\Modules\Platform\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurrencyController extends Controller
{
    public function index(Request $request)
    {
        return view('currency.index', [
            'currencies' => Currency::query()->orderBy('code')->paginate(30),
            'base' => app(CompanyContext::class)->selected($request)?->base_currency ?? 'IDR',
        ]);
    }

    public function update(Request $request, string $code)
    {
        $data = $request->validate(['show' => ['required', 'boolean'], 'active' => ['required', 'boolean']]);
        DB::transaction(function () use ($code, $data) {
            $currency = Currency::query()->whereKey($code)->lockForUpdate()->firstOrFail();
            abort_if($data['active'] && $currency->decimal_places === null, 422, __('currency.invalid'));
            $currency->update($data);
        });

        return redirect()->route('currency.index')->with('success', __('currency.saved'));
    }
}