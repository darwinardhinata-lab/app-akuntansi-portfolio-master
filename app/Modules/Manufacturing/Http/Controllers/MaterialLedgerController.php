<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\MaterialLedger;
use App\Modules\Manufacturing\Models\Yarn;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaterialLedgerController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('item_type', 'FABRIC');
        abort_unless(in_array($type, ['YARN', 'FABRIC', 'AUXILIARY'], true), 404);

        $materials = $this->materials($type);
        $itemId = $request->integer('item_id') ?: $materials->first()?->id;
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $selectedMaterial = $materials->firstWhere('id', $itemId);
        $ledgers = collect();

        if ($selectedMaterial) {
            $ledgers = MaterialLedger::where('item_type', $type)->where('item_id', $selectedMaterial->id)
                ->whereDate('transaction_date', '>=', $startDate)->whereDate('transaction_date', '<=', $endDate)
                ->orderBy('transaction_date')->orderBy('id')->get();
        }

        return view('manufacturing.material_ledger.index', compact('type', 'materials', 'itemId', 'selectedMaterial', 'startDate', 'endDate', 'ledgers'));
    }

    private function materials(string $type)
    {
        return match ($type) {
            'YARN' => Yarn::orderBy('yarn_code')->get(),
            'FABRIC' => Fabric::orderBy('fabric_code')->get(),
            'AUXILIARY' => AuxiliaryMaterial::orderBy('material_code')->get(),
        };
    }
}