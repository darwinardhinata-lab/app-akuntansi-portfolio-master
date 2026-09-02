<?php

namespace App\Http\Controllers;

use App\Models\Tax;
use App\Models\SystemLog;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    // ... (kode Anda di bawahnya biarkan saja)
    public function index()
    {
        $taxes = Tax::orderBy('tax_type', 'asc')->orderBy('rate', 'desc')->get();
        return view('tax.index', compact('taxes'));
    }

    // Fitur Rahasia: Generate Pajak Standar Indonesia 2026
    public function generateDefault()
    {
        $defaultTaxes = [
            ['tax_code' => 'PPN-12', 'tax_name' => 'PPN (Sesuai UU HPP)', 'rate' => 12.00, 'tax_type' => 'ADDITION', 'account_code' => '21101', 'description' => 'Pajak Pertambahan Nilai 12%'],
            ['tax_code' => 'PPH-23', 'tax_name' => 'PPh 23 (Jasa/Sewa)', 'rate' => 2.00, 'tax_type' => 'DEDUCTION', 'account_code' => '21203', 'description' => 'Pemotongan atas jasa atau sewa selain tanah/bangunan'],
            ['tax_code' => 'PPH-42', 'tax_name' => 'PPh 4 ayat 2 (Sewa Bangunan)', 'rate' => 10.00, 'tax_type' => 'DEDUCTION', 'account_code' => '21204', 'description' => 'Pajak Final Sewa Tanah & Bangunan'],
            ['tax_code' => 'PPH-42-UMKM', 'tax_name' => 'PPh 4 ayat 2 (UMKM Final)', 'rate' => 0.50, 'tax_type' => 'DEDUCTION', 'account_code' => '21204', 'description' => 'Pajak Final UMKM (Omzet < 4.8M)'],
            ['tax_code' => 'PPH-21', 'tax_name' => 'PPh 21 (Tenaga Ahli/Bukan Pegawai)', 'rate' => 2.50, 'tax_type' => 'DEDUCTION', 'account_code' => '21201', 'description' => 'Pemotongan 50% x 5% untuk non-pegawai ber-NPWP'],
            ['tax_code' => 'PPH-22', 'tax_name' => 'PPh 22 (Impor/Pembelian Instansi)', 'rate' => 1.50, 'tax_type' => 'ADDITION', 'account_code' => '11401', 'description' => 'Pemungutan pajak impor atau bendaharawan'],
        ];

        foreach ($defaultTaxes as $tax) {
            Tax::updateOrCreate(['tax_code' => $tax['tax_code']], $tax);
        }

        SystemLog::record('CREATE', 'Master Pajak', 'Generate default master pajak standar Indonesia 2026.');

        return redirect()->route('tax.index')->with('success', 'Master Pajak Standar Indonesia 2026 Berhasil Dibuat!');
    }

    // Menampilkan halaman form tambah pajak
    public function create()
    {
        return view('tax.create');
    }

    // Menyimpan data pajak baru ke database
    public function store(Request $request)
    {
        $request->validate([
            'tax_code'     => 'required|unique:taxes,tax_code',
            'tax_name'     => 'required|string|max:255',
            'rate'         => 'required|numeric|min:0',
            'tax_type'     => 'required|in:ADDITION,DEDUCTION',
            'account_code' => 'nullable|string|max:50',
        ]);

        Tax::create($request->all());

        SystemLog::record('CREATE', 'Master Pajak', 'Menambahkan pajak baru: ' . $request->tax_code . ' - ' . $request->tax_name);

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

        $request->validate([
            'tax_code'     => 'required|unique:taxes,tax_code,' . $id,
            'tax_name'     => 'required|string|max:255',
            'rate'         => 'required|numeric|min:0',
            'tax_type'     => 'required|in:ADDITION,DEDUCTION',
            'account_code' => 'nullable|string|max:50',
        ]);

        $tax->update($request->all());

        SystemLog::record('UPDATE', 'Master Pajak', 'Mengubah data pajak: ' . $request->tax_code . ' - ' . $request->tax_name);

        return redirect()->route('tax.index')->with('success', 'Data pajak berhasil diperbarui!');
    }

    // Menghapus data pajak
    public function destroy($id)
    {
        $tax = Tax::findOrFail($id);
        $taxCode = $tax->tax_code;
        $taxName = $tax->tax_name;
        $tax->delete();

        SystemLog::record('DELETE', 'Master Pajak', 'Menghapus pajak: ' . $taxCode . ' - ' . $taxName);

        return redirect()->route('tax.index')->with('success', 'Data pajak berhasil dihapus!');
    }
}