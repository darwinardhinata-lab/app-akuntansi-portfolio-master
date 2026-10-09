<?php

namespace App\Modules\CustomsReports\Support;

use App\Modules\CustomsReports\Models\ReportPeriod;
use Illuminate\Support\Facades\DB;

class ManualReportImport
{
    public static function protect(ReportPeriod $period): void
    {
        DB::transaction(function () use ($period) {
            $locked = ReportPeriod::lockForUpdate()->findOrFail($period->id);
            if (!$locked->isDraft()) {
                throw new \RuntimeException('Hanya periode DRAFT yang dapat di-import.');
            }
            if ($locked->source_mode !== 'MANUAL') {
                $locked->update(['source_mode' => 'MANUAL']);
            }
        });
    }
}