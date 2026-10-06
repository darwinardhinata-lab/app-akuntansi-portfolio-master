<?php

namespace App\Modules\CustomsReports\Services;

use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;

class CustomsSettingsService
{
    public function autoSyncInternal(): bool
    {
        return (bool) (DB::table('cbr_settings')->where('id', 1)->value('auto_sync_internal') ?? true);
    }

    public function save(bool $autoSync): void
    {
        DB::transaction(function () use ($autoSync) {
            DB::table('cbr_settings')->updateOrInsert(['id' => 1], [
                'auto_sync_internal' => $autoSync,
                'updated_at' => now(),
            ]);
            SystemLog::record('UPDATE', 'Pengaturan Bea Cukai', 'Sinkronisasi otomatis internal: '.($autoSync ? 'aktif' : 'nonaktif').'; H2H tetap terkunci.');
        });
    }
}