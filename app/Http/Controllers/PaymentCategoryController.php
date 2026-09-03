<?php

namespace App\Http\Controllers;

use App\Models\PaymentCategory;
use App\Models\PaymentPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentCategoryController extends Controller
{
    public function index()
    {
        $categories = PaymentCategory::orderBy('name', 'asc')->get();
        return view('payment_category.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:payment_categories,name',
            'slug' => 'required|string|max:255|unique:payment_categories,slug',
            'description' => 'nullable|string',
        ]);

        PaymentCategory::create([
            'name' => strtoupper($request->name),
            'slug' => strtolower($request->slug),
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->back()->with('success', 'Kategori Payment Plan berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $category = PaymentCategory::findOrFail($id);
        
        $request->validate([
            'name' => 'required|string|max:255|unique:payment_categories,name,' . $category->id,
            'slug' => 'required|string|max:255|unique:payment_categories,slug,' . $category->id,
            'description' => 'nullable|string',
        ]);

        $oldName = $category->name;
        $newName = strtoupper($request->name);

        $category->update([
            'name' => $newName,
            'slug' => strtolower($request->slug),
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
        ]);

        if ($oldName !== $newName) {
            PaymentPlan::where('kategori_payment', $oldName)->update(['kategori_payment' => $newName]);
        }

        return redirect()->back()->with('success', 'Kategori Payment Plan berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $category = PaymentCategory::findOrFail($id);
        $category->delete();
        
        return redirect()->back()->with('success', 'Kategori Payment Plan berhasil dihapus!');
    }

    // API endpoint untuk mendapatkan kategori aktif (dipakai di PaymentPlan)
    public function apiIndex()
    {
        $categories = PaymentCategory::active()->orderBy('name', 'asc')->get(['name', 'slug']);
        return response()->json($categories);
    }
}