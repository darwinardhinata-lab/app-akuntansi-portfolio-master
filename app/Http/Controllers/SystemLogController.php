<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SystemLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $logs = \App\Models\SystemLog::with('user')->orderBy('created_at', 'desc')->paginate(50);
        return view('system_log.index', compact('logs'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Menarik data log secara dinamis berdasarkan No Transaksi / Keyword
     */
    public function getEntityLogs(Request $request)
    {
        $keyword = $request->get('keyword');
        
        if (!$keyword) {
            return response()->json(['status' => 'error', 'message' => 'Keyword / Nomor Referensi tidak valid.']);
        }

        $logs = \App\Models\SystemLog::with('user')
            ->where('description', 'LIKE', "%{$keyword}%")
            ->orderBy('created_at', 'desc')
            ->get();

        $html = view('system_log.partials.ajax_list', compact('logs', 'keyword'))->render();

        return response()->json(['status' => 'success', 'html' => $html]);
    }
}