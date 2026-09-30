<?php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use App\Models\Tax;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    // ... (kode Anda di bawahnya biarkan saja)
    public function index()
    {
        if (! Tax::query()->exists()) {
            $this->syncIndonesiaDefaults();
        }

        $taxes = Tax::orderBy('tax_type', 'asc')->orderBy('rate', 'desc')->get();

        return view('tax.index', compact('taxes'));
    }

    public function generateDefault()
    {
        $created = $this->syncIndonesiaDefaults();

        SystemLog::record('CREATE', 'Master Pajak', 'Sinkronisasi katalog awal pajak Indonesia: '.$created.' kode baru.');

        return redirect()->route('tax.index')->with('success', $created.' master pajak Indonesia baru berhasil ditambahkan. Data yang sudah ada tidak diubah.');
    }

    // Menampilkan halaman form tambah pajak
    public function create()
    {
        return view('tax.create');
    }

    // Menyimpan data pajak baru ke database
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tax_code' => 'required|unique:taxes,tax_code',
            'tax_name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0',
            'tax_type' => 'required|in:ADDITION,DEDUCTION',
            'account_code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $tax = Tax::create($validated);

        SystemLog::record('CREATE', 'Master Pajak', 'Menambahkan pajak baru: '.$tax->tax_code.' - '.$tax->tax_name);

        return redirect()->route('tax.index')->with('success', 'Pajak baru berhasil ditambahkan!');
    }

    // Menampilkan halaman form edit
    public function edit($id)
    {
        $tax = Tax::findOrFail($id);

        return view('tax.edit', compact('tax'));
    }

    // Menyimpan perubahan data pajak
    public function update(Request $request, $id)
    {
        $tax = Tax::findOrFail($id);

        $validated = $request->validate([
            'tax_code' => 'required|unique:taxes,tax_code,'.$id,
            'tax_name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0',
            'tax_type' => 'required|in:ADDITION,DEDUCTION',
            'account_code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $tax->update($validated);

        SystemLog::record('UPDATE', 'Master Pajak', 'Mengubah data pajak: '.$request->tax_code.' - '.$request->tax_name);

        return redirect()->route('tax.index')->with('success', 'Data pajak berhasil diperbarui!');
    }

    // Menghapus data pajak
    public function destroy($id)
    {
        $tax = Tax::findOrFail($id);
        $taxCode = $tax->tax_code;
        $taxName = $tax->tax_name;
        $tax->delete();

        SystemLog::record('DELETE', 'Master Pajak', 'Menghapus pajak: '.$taxCode.' - '.$taxName);

        return redirect()->route('tax.index')->with('success', 'Data pajak berhasil dihapus!');
    }

    private function syncIndonesiaDefaults(): int
    {
        $created = 0;

        foreach (config('taxes.indonesia_defaults', []) as $tax) {
            $record = Tax::firstOrCreate(
                ['tax_code' => $tax['tax_code']],
                $tax + ['is_active' => true],
            );

            if ($record->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }
}
