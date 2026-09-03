<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;

class DivisiController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $divisi = DB::table('master_divisi')
            ->when($search, function ($query, $search) {
                return $query->where('nama_divisi', 'like', "%{$search}%")
                             ->orWhere('kode_divisi', 'like', "%{$search}%");
            })
            ->orderBy('id_divisi', 'asc')
            ->paginate(50);

        $divisi->appends(['search' => $search]);

        return view('divisi.index', compact('divisi', 'search'));
    }

    public function create()
    {
        return view('divisi.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode_divisi' => 'required|unique:master_divisi,kode_divisi|max:10',
            'nama_divisi' => 'required|max:50'
        ]);

        DB::table('master_divisi')->insert([
            'kode_divisi'  => strtoupper($request->kode_divisi),
            'nama_divisi'  => strtoupper($request->nama_divisi),
            'status_aktif' => $request->has('status_aktif') ? 1 : 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        SystemLog::record('CREATE', 'Divisi', 'Menambahkan divisi baru: ' . strtoupper($request->kode_divisi) . ' - ' . strtoupper($request->nama_divisi));

        return redirect()->route('divisi.index')->with('success', 'Divisi baru berhasil ditambahkan!');
    }

    public function edit($id)
    {
        $divisi = DB::table('master_divisi')->where('id_divisi', $id)->first();

        if (!$divisi) {
            return redirect()->route('divisi.index')->with('error', 'Data divisi tidak ditemukan!');
        }

        return view('divisi.edit', compact('divisi'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            // FIX: kode_divisi unik kecuali untuk baris yang sedang diedit (ignore ID sendiri)
            'kode_divisi' => 'required|max:10|unique:master_divisi,kode_divisi,' . $id . ',id_divisi',
            'nama_divisi' => 'required|max:50'
        ]);

        DB::table('master_divisi')->where('id_divisi', $id)->update([
            'kode_divisi'  => strtoupper($request->kode_divisi),
            'nama_divisi'  => strtoupper($request->nama_divisi),
            'status_aktif' => $request->has('status_aktif') ? 1 : 0,
            'updated_at'   => now(),
        ]);

        SystemLog::record('UPDATE', 'Divisi', 'Mengubah data divisi: ' . strtoupper($request->kode_divisi) . ' - ' . strtoupper($request->nama_divisi));

        return redirect()->route('divisi.index')->with('success', 'Data Divisi berhasil diperbarui!');
    }

    // FIX: Method destroy() sebelumnya tidak ada, padahal route divisi.destroy sudah terdaftar.
    // Ditambahkan dengan pengecekan apakah divisi masih digunakan di payment plan.
    public function destroy($id)
    {
        // Cek apakah divisi masih digunakan di transaksi payment plan
        $isUsed = DB::table('transaksi_payment_plan')
            ->where('id_divisi', $id)
            ->exists();

        if ($isUsed) {
            return redirect()->back()->with('error', 'Gagal: Divisi ini masih memiliki data transaksi Payment Plan dan tidak dapat dihapus.');
        }

        $deleted = DB::table('master_divisi')->where('id_divisi', $id)->delete();

        if (!$deleted) {
            return redirect()->back()->with('error', 'Data divisi tidak ditemukan!');
        }

        SystemLog::record('DELETE', 'Divisi', 'Menghapus divisi dengan ID: ' . $id);

        return redirect()->route('divisi.index')->with('success', 'Data Divisi berhasil dihapus!');
    }
}
