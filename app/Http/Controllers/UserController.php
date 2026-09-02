<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (Auth::user()->role !== 'ADMIN') {
                return abort(403, 'AKSES DITOLAK: Hanya Administrator yang diizinkan mengakses menu ini.');
            }
            return $next($request);
        });
    }

    public function index()
    {
        $users = DB::table('users')
            ->leftJoin('master_divisi', 'users.id_divisi', '=', 'master_divisi.id_divisi')
            ->select('users.*', 'master_divisi.nama_divisi')
            ->orderBy('users.created_at', 'desc')
            ->get();

        return view('user.index', compact('users'));
    }

    public function create()
    {
        $divisi = DB::table('master_divisi')->where('status_aktif', 1)->get();
        return view('user.create', compact('divisi'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:6',
            'role'      => 'required|in:ADMIN,FINANCE,STAFF',
            'id_divisi' => 'nullable|integer'
        ]);

        User::create([
            'name'      => strtoupper($request->name),
            'email'     => strtolower($request->email),
            'password'  => Hash::make($request->password),
            'role'      => $request->role,
            'id_divisi' => $request->id_divisi,
        ]);

        return redirect()->route('users.index')->with('success', 'Pengguna baru berhasil ditambahkan!');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $divisi = DB::table('master_divisi')->where('status_aktif', 1)->get();
        return view('user.edit', compact('user', 'divisi'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email,'.$id,
            'role'      => 'required|in:ADMIN,FINANCE,STAFF',
            'id_divisi' => 'nullable|integer'
        ]);

        $dataUpdate = [
            'name'      => strtoupper($request->name),
            'email'     => strtolower($request->email),
            'role'      => $request->role,
            'id_divisi' => $request->id_divisi,
        ];

        if ($request->filled('password')) {
            $dataUpdate['password'] = Hash::make($request->password);
        }

        $user->update($dataUpdate);

        return redirect()->route('users.index')->with('success', 'Data pengguna berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'Gagal: Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', 'Pengguna berhasil dihapus permanen.');
    }
}