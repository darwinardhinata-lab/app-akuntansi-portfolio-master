<?php

namespace App\Modules\CustomsReports\Services;

use App\Models\SystemLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ActivityReportService
{
    public function query(?string $dateFrom, ?string $dateTo, ?string $keyword = null): LengthAwarePaginator
    {
        return SystemLog::with('user')
            ->when($dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->when($keyword, fn ($query) => $query->where('description', 'LIKE', "%{$keyword}%"))
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    /**
     * Ekstrak nomor transaksi dari description secara best-effort, karena
     * system_logs tidak memiliki kolom nomor transaksi terstruktur.
     *
     * TODO: Pola ini didasarkan pada format yang teramati (PO/AFI/26/...,
     * WO/SPK-..., MRN-...) dan mungkin belum menangkap seluruh variasi.
     * Tampilkan kosong daripada salah menebak apabila tidak ada kecocokan.
     */
    public function extractTransactionNumber(string $description): ?string
    {
        if (preg_match('/\b([A-Z]{2,4}[\/\-][A-Za-z0-9\/\-]+)\b/', $description, $matches)) {
            return $matches[1];
        }

        return null;
    }
}