<?php

// FIX: multicurrency — controller memvalidasi transport; mutasi finansial ada di service.
namespace App\Modules\Currency\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Currency\Models\Currency;
use App\Modules\Currency\Models\ExchangeRate;
use App\Modules\Currency\Services\ExchangeRateService;
use App\Modules\Currency\Services\ExchangeRateNotFound;
use App\Modules\Platform\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExchangeRateController extends Controller
{
    private function companyId(Request $request): ?int
    {
        $company = app(CompanyContext::class)->selected($request);
        // FIX: multicurrency — pilihan perusahaan yang dicabut tidak boleh fallback ke scope global.
        abort_if(! $company && $request->hasSession() && $request->session()->has(CompanyContext::SESSION_KEY), 409, __('currency.invalid'));

        return $company?->id;
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'source' => ['nullable', Rule::in(['KMK', 'BI', 'MANUAL'])],
        ]);
        $from = Carbon::parse($filters['from'] ?? now()->startOfMonth()->toDateString());
        $to = Carbon::parse($filters['to'] ?? $from->copy()->endOfMonth()->toDateString());
        if ($to->lt($from) || $to->gt($from->copy()->addMonthsNoOverflow(3))) {
            throw ValidationException::withMessages(['to' => __('currency.range')]);
        }
        $source = $filters['source'] ?? 'KMK';
        $rates = ExchangeRate::with(['creator', 'updater'])->where('company_id', $this->companyId($request))
            ->where('source', $source)->whereBetween('rate_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('rate_date')->orderBy('currency_code')->paginate(25)->withQueryString();

        return view('currency.rates', [
            'rates' => $rates, 'source' => $source, 'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'currencies' => Currency::query()->where('active', true)->where('show', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, ExchangeRateService $service)
    {
        $service->save($request->all(), $this->companyId($request), $request->user()->id);
        return back()->with('success', __('currency.saved'));
    }

    public function lookup(Request $request, ExchangeRateService $service)
    {
        $data = $request->validate([
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/D'],
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['sometimes', Rule::in(['SPOT', 'TAX', 'AVERAGE'])],
        ]);
        $companyId = $this->companyId($request);
        try {
            return response()->json($service->lookup($data['currency'], Carbon::parse($data['date']), $data['type'] ?? 'SPOT', $companyId));
        } catch (ExchangeRateNotFound $exception) {
            return response()->json(['rate' => null, 'source' => null, 'found' => false]);
        }
    }

    public function update(Request $request, int $id, ExchangeRateService $service)
    {
        $service->save($request->all(), $this->companyId($request), $request->user()->id, $id);
        return back()->with('success', __('currency.saved'));
    }

    public function destroy(Request $request, int $id, ExchangeRateService $service)
    {
        $service->delete($id, $this->companyId($request));
        return back()->with('success', __('currency.saved'));
    }

    public function import(Request $request, ExchangeRateService $service)
    {
        $request->validate(['file' => ['required', 'file', 'max:2048']]);
        $stream = fopen($request->file('file')->getRealPath(), 'r');
        try {
            $header = fgetcsv($stream);
            if ($header) {
                $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
            }
            if (! $header || count(array_unique($header)) !== count($header)
                || array_diff(['currency_code', 'rate_date', 'rate', 'type'], $header)
                || array_diff($header, ['currency_code', 'rate_date', 'rate', 'type', 'source', 'valid_until', 'quoted_rate', 'quoted_unit'])) {
                throw ValidationException::withMessages(['file' => __('currency.csv_invalid')]);
            }
            $rows = [];
            while (($row = fgetcsv($stream)) !== false) {
                if (count($row) !== count($header) || count($rows) >= 1000) {
                    throw ValidationException::withMessages(['file' => __('currency.csv_invalid')]);
                }
                $data = array_combine($header, $row);
                $data['source'] = $data['source'] ?? ($data['type'] === 'TAX' ? 'KMK' : 'MANUAL');
                $rows[] = $data;
            }
            if (! $rows) {
                throw ValidationException::withMessages(['file' => __('currency.csv_invalid')]);
            }
            DB::transaction(function () use ($rows, $service, $request) {
                // FIX: multicurrency — urutan lock master konsisten untuk seluruh impor atomik.
                Currency::query()->whereIn('code', array_column($rows, 'currency_code'))->orderBy('code')->lockForUpdate()->get();
                foreach ($rows as $data) {
                    $service->save($data, $this->companyId($request), $request->user()->id);
                }
            });
        } finally {
            fclose($stream);
        }

        return back()->with('success', __('currency.saved'));
    }
}