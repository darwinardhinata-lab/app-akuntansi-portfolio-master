<?php

namespace App\Modules\Customs\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CustomsReportController extends Controller
{
    public function inbound(Request $request)
    {
        return view('customs.reports.template', [
            'title' => __('customs.reports.inbound'),
            'reportType' => 'inbound',
        ]);
    }

    public function outbound(Request $request)
    {
        return view('customs.reports.template', [
            'title' => __('customs.reports.outbound'),
            'reportType' => 'outbound',
        ]);
    }

    public function mutationRaw(Request $request)
    {
        return view('customs.reports.template', [
            'title' => __('customs.reports.mutation_raw'),
            'reportType' => 'mutation_raw',
        ]);
    }

    public function wip(Request $request)
    {
        return view('customs.reports.template', [
            'title' => __('customs.reports.wip'),
            'reportType' => 'wip',
        ]);
    }

    public function mutationFinished(Request $request)
    {
        return view('customs.reports.template', [
            'title' => __('customs.reports.mutation_finished'),
            'reportType' => 'mutation_finished',
        ]);
    }

    public function mutationCapital(Request $request)
    {
        return view('customs.reports.template', [
            'title' => __('customs.reports.mutation_capital'),
            'reportType' => 'mutation_capital',
        ]);
    }

    public function mutationReject(Request $request)
    {
        return view('customs.reports.template', [
            'title' => __('customs.reports.mutation_reject'),
            'reportType' => 'mutation_reject',
        ]);
    }

    public function activityLog(Request $request)
    {
        return view('customs.reports.template', [
            'title' => __('customs.reports.activity_log'),
            'reportType' => 'activity_log',
        ]);
    }
}
