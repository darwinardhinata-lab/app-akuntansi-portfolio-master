<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompanyContextController extends Controller
{
    public function edit(Request $request)
    {
        return view('platform.company', [
            'companies' => $request->user()->companies()->where('companies.active', true)->orderBy('code')->get(),
            'selected' => app(CompanyContext::class)->selected($request),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['company_id' => ['required', 'integer']]);
        $company = $request->user()->companies()->where('companies.active', true)
            ->whereKey($data['company_id'])->first();
        if (! $company) {
            throw ValidationException::withMessages(['company_id' => 'Perusahaan tidak dapat diakses.']);
        }

        $request->session()->put(CompanyContext::SESSION_KEY, $company->id);

        return redirect()->route('platform.company.edit')->with('success', 'Perusahaan aktif telah dipilih.');
    }
}
