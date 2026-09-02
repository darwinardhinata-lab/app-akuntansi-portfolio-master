<?php

namespace App\Http\Controllers;

use App\Services\BudgetingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BudgetingController extends Controller
{
    protected BudgetingService $budgetingService;

    public function __construct(BudgetingService $budgetingService)
    {
        $this->budgetingService = $budgetingService;
    }

    /**
     * Menampilkan dashboard budgeting dan proyeksi berdasarkan data historis.
     * Menggunakan pendekatan modern dengan metode perhitungan yang lebih tepat.
     */
    public function index(Request $request)
    {
        // Validasi input tanggal
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'method' => 'sometimes|in:simple_avg,weighted_avg,exponential_smoothing,linear_regression,median,holt_winters',
        ]);

        // Set default dates: 2 bulan terakhir sampai akhir bulan ini
        $defaultStart = Carbon::now()->subMonths(2)->startOfMonth()->format('Y-m-d');
        $defaultEnd = Carbon::now()->endOfMonth()->format('Y-m-d');

        $startDate = $request->get('start_date', $defaultStart);
        $endDate = $request->get('end_date', $defaultEnd);
        $method = $request->get('method', 'simple_avg');

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // Validasi logis: start_date tidak boleh lebih besar dari end_date
        if ($start->greaterThan($end)) {
            return back()->withErrors(['start_date' => 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.'])->withInput();
        }

        // Gunakan service untuk menghasilkan laporan budgeting
        $data = $this->budgetingService->generateBudgetingReport($startDate, $endDate, $method);
        
        // Tambahkan informasi metode yang digunakan
        $data['methodUsed'] = $method;

        // Hitung trend info berdasarkan metode yang dipilih
        if ($method === 'linear_regression') {
            $data['trendInfo'] = $this->budgetingService->calculateLinearRegression([
                'pendapatan' => $data['summary']['pendapatan'],
                'hpp' => $data['summary']['hpp'],
                'opex' => $data['summary']['opex']
            ]);
        } elseif ($method === 'weighted_avg' || $method === 'exponential_smoothing') {
            $data['trendInfo'] = [
                'trend_direction' => 'mixed',
                'confidence_r2' => 0
            ];
        }

        return view('budgeting.index', $data);
    }

    /**
     * API endpoint untuk mendapatkan proyeksi menggunakan metode tertentu.
     */
    public function projection(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'method' => 'required|in:simple_avg,weighted_avg,exponential_smoothing,linear_regression,median,holt_winters',
        ]);

        $data = $this->budgetingService->generateBudgetingReport(
            $request->start_date,
            $request->end_date,
            $request->method
        );

        return response()->json([
            'status' => 'success',
            'method' => $request->method,
            'data' => $data
        ]);
    }
}