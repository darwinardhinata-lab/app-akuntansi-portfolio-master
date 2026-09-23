<?php

namespace App\Modules\CustomsReports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomsReports\Services\ActivityReportService;
use Illuminate\Http\Request;

class ActivityReportController extends Controller
{
    public function __construct(
        protected ActivityReportService $service,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'keyword' => ['nullable', 'string', 'max:255'],
        ]);

        $activities = $this->service->query(
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $filters['keyword'] ?? null,
        )->appends($request->query());

        return view('customs-reports.riwayat-aktivitas', [
            'activities' => $activities,
            'filters' => $filters,
            'activityReportService' => $this->service,
        ]);
    }
}