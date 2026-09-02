<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CompanyProfile;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Storage;

class CompanyProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (auth()->user()->role !== 'ADMIN') {
                abort(403, 'Akses ditolak. Hanya Administrator yang dapat mengubah data ini.');
            }
            return $next($request);
        });
    }

    public function edit()
    {
        $profile = CompanyProfile::first();
        return view('company_profile.edit', compact('profile'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // Maksimal 2MB
            'employee_pin' => 'nullable|string|max:255',
        ]);

        $profile = CompanyProfile::first();
        $dataUpdate = $request->except('logo');

        // Logika Upload Logo
        if ($request->hasFile('logo')) {
            // Hapus logo lama dari storage jika ada
            if ($profile->logo && Storage::disk('public')->exists($profile->logo)) {
                Storage::disk('public')->delete($profile->logo);
            }

            $file = $request->file('logo');
            $filename = 'logo_perusahaan_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('company_logos', $filename, 'public');
            $dataUpdate['logo'] = $path;
        }

        $profile->update($dataUpdate);

        SystemLog::record('UPDATE', 'Profil Perusahaan', 'Memperbarui informasi identitas, logo, dan PIN karyawan.');

        return redirect()->back()->with('success', 'Profil identitas perusahaan berhasil diperbarui!');
    }
}