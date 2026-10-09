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
        abort_unless(auth()->user()?->role === 'ADMIN', 403);
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
        abort_unless($request->user()?->role === 'ADMIN', 403);
        $data = $request->validate(['keyword' => 'required|string|max:200']);
        $keyword = trim($data['keyword']);
        if ($keyword === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['keyword' => __('erp.audit_log_keyword')]);
        }
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword);

        $logs = \App\Models\SystemLog::with('user')
            ->whereRaw("description LIKE ? ESCAPE '!'", ['%'.$escaped.'%'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(101)
            ->get();
        $hasMore = $logs->count() > 100;
        $logs = $logs->take(100);

        $html = view('system_log.partials.ajax_list', compact('logs', 'keyword'))->render();

        return response()->json(['status' => 'success', 'html' => $html, 'count' => $logs->count(), 'has_more' => $hasMore]);
    }
}