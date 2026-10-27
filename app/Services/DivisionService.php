<?php

namespace App\Services;

use App\Models\MasterDivisi;
use App\Models\SystemLog;
use App\Support\ErpPanelAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class DivisionService
{
    public function save(array $data, ?int $id = null): MasterDivisi
    {
        return DB::transaction(function () use ($data, $id) {
            abort_unless(app(ErpPanelAccess::class)->allows(request(), auth()->user(), 'division.'.($id ? 'update' : 'create')), 403);
            $record = $id ? MasterDivisi::whereKey($id)->lockForUpdate()->firstOrFail() : new MasterDivisi();
            $data = [
                'kode_divisi' => mb_strtoupper(trim((string) ($data['kode_divisi'] ?? ''))),
                'nama_divisi' => mb_strtoupper(trim((string) ($data['nama_divisi'] ?? ''))),
                'status_aktif' => $data['status_aktif'] ?? false,
            ];
            $valid = Validator::make($data, [
                'kode_divisi' => ['required', 'string', 'max:10', Rule::unique('master_divisi', 'kode_divisi')->ignore($id, 'id_divisi')],
                'nama_divisi' => ['required', 'string', 'max:50'], 'status_aktif' => ['required', 'boolean'],
            ])->validate();
            $record->fill($valid)->save();
            SystemLog::record($id ? 'UPDATE' : 'CREATE', 'Divisi', 'Menyimpan divisi melalui panel ERP: '.$record->kode_divisi);
            return $record;
        });
    }
}