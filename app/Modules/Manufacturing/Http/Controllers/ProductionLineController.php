<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Modules\Manufacturing\Models\ProductionLine;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductionLineController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $lines = ProductionLine::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($where) => $where
                ->where('line_code', 'like', '%'.$search.'%')
                ->orWhere('line_name', 'like', '%'.$search.'%')
                ->orWhere('area', 'like', '%'.$search.'%')))
            ->orderBy('line_code')
            ->paginate(30)
            ->withQueryString();

        return view('manufacturing.master.production_line_index', compact('lines', 'search'));
    }

    public function store(Request $request)
    {
        $line = ProductionLine::create($this->validated($request));
        SystemLog::record('CREATE', 'Manufacturing Production Line', 'Menambahkan Line Produksi: '.$line->line_code);

        return back()->with('success', 'Line Produksi berhasil ditambahkan.');
    }

    public function update(Request $request, int $id)
    {
        $line = ProductionLine::findOrFail($id);
        if ($line->workOrders()->exists() && $request->input('line_code') !== $line->line_code) {
            throw ValidationException::withMessages([
                'line_code' => 'Kode Line Produksi tidak dapat diubah karena sudah digunakan oleh SPK.',
            ]);
        }
        $line->update($this->validated($request, $line));
        SystemLog::record('UPDATE', 'Manufacturing Production Line', 'Memperbarui Line Produksi: '.$line->line_code);

        return back()->with('success', 'Line Produksi berhasil diperbarui.');
    }

    public function destroy(int $id)
    {
        $line = ProductionLine::findOrFail($id);
        $line->update(['is_active' => false]);
        SystemLog::record('DEACTIVATE', 'Manufacturing Production Line', 'Menonaktifkan Line Produksi: '.$line->line_code);

        return back()->with('success', 'Line Produksi dinonaktifkan. Riwayat SPK tetap terjaga.');
    }

    private function validated(Request $request, ?ProductionLine $line = null): array
    {
        return $request->validate([
            'line_code' => ['required', 'string', 'max:50', Rule::unique('mfg_production_lines', 'line_code')->ignore($line)],
            'line_name' => ['required', 'string', 'max:150'],
            'area' => ['nullable', 'string', 'max:100'],
            'daily_capacity' => ['nullable', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}