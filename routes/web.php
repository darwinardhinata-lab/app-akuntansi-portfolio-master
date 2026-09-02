<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\HelperCodeController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\ProfitLossController;
use App\Http\Controllers\BalanceSheetController;
use App\Http\Controllers\CashFlowController;
use App\Http\Controllers\DivisiController;
use App\Http\Controllers\PaymentPlanController;
use App\Http\Controllers\PaymentCategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BudgetingController;
use App\Http\Controllers\SalesInvoiceController;
use App\Http\Controllers\ReconciliationController;
// ── Guest (login) ─────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ── PORTAL KARYAWAN (BISA DIAKSES TANPA LOGIN) ────────────────
Route::get('/form-pengajuan', [App\Http\Controllers\PaymentPlanController::class, 'publicForm'])->name('payment.public_form');
Route::post('/form-pengajuan/kirim', [App\Http\Controllers\PaymentPlanController::class, 'publicStore'])
    ->middleware('throttle:5,1')
    ->name('payment.public_store');

// FIX #030: Webhook endpoint dipindahkan ke routes/api.php (lebih tepat untuk webhook eksternal)
// Lihat: Route::post('/api/jubelio/webhook/sales', ...) di routes/api.php

// ── Authenticated ERP routes ──────────────────────────────────
Route::middleware(['auth'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboard/chart-data', [DashboardController::class, 'getChartData'])->name('dashboard.chart-data');

    Route::get('/system-logs', [App\Http\Controllers\SystemLogController::class, 'index'])->name('logs.index');
    Route::get('/system-logs/entity-ajax', [App\Http\Controllers\SystemLogController::class, 'getEntityLogs'])->name('logs.entity');

    // === MANAJEMEN USER (DIAMANKAN OLEH CONTROLLER UNTUK ADMIN SAJA) ===
    Route::resource('users', App\Http\Controllers\UserController::class);

   // --- KODE AKUN (COA) ---
    Route::get('/akun', [App\Http\Controllers\AccountController::class, 'index'])->name('account.index');

    // Rute Create & Store
    Route::get('/akun/create', [App\Http\Controllers\AccountController::class, 'create'])->name('account.create');
    Route::post('/akun', [App\Http\Controllers\AccountController::class, 'store'])->name('account.store');

    // Rute Edit, Update, Delete
    Route::get('/akun/{id}/edit', [App\Http\Controllers\AccountController::class, 'edit'])->name('account.edit');
    Route::put('/akun/{id}', [App\Http\Controllers\AccountController::class, 'update'])->name('account.update');
    Route::delete('/akun/{id}', [App\Http\Controllers\AccountController::class, 'destroy'])->name('account.destroy');

    // Rute Ekstra
    Route::post('/akun/bulk-delete', [App\Http\Controllers\AccountController::class, 'bulkDelete'])->name('account.bulkDelete');
    Route::post('/akun/import', [App\Http\Controllers\AccountController::class, 'import'])->name('account.import');
    Route::get('/akun/template', [App\Http\Controllers\AccountController::class, 'downloadTemplate'])->name('account.template');
    Route::get('/akun/export', [App\Http\Controllers\AccountController::class, 'export'])->name('account.export');


    // 👇 TAMBAHKAN RUTE SALDO AWAL (Mencegah Error RouteNotFound) 👇
    Route::get('/akun/opening-balance', [App\Http\Controllers\AccountController::class, 'openingBalanceForm'])->name('account.opening_balance');
    Route::post('/akun/opening-balance', [App\Http\Controllers\AccountController::class, 'openingBalanceStore'])->name('account.store_opening_balance');
    //Route::post('/akun/import-opening-balance', [App\Http\Controllers\AccountController::class, 'importOpeningBalance'])->name('account.import_opening_balance');
    //Route::get('/akun/download-template-opening-balance', [App\Http\Controllers\AccountController::class, 'downloadTemplateOpeningBalance'])->name('account.download_template_opening_balance');

    Route::get('/kode-bantu', [HelperCodeController::class, 'index'])->name('helper.index');
    Route::delete('/kode-bantu/{id}', [HelperCodeController::class, 'destroy'])->name('helper.destroy');
    Route::post('/kode-bantu/bulk-delete', [HelperCodeController::class, 'bulkDelete'])->name('helper.bulk-delete');
    Route::post('/kode-bantu/import', [HelperCodeController::class, 'import'])->middleware('throttle:5,1')->name('helper.import');
    Route::get('/kode-bantu/download-template', [HelperCodeController::class, 'downloadTemplate'])->name('helper.download-template');

    Route::get('/buku-besar', [LedgerController::class, 'index'])->name('buku-besar.index');

    // --- SMART DOCUMENT TRACING ---
    Route::get('/trace-document/{evidence_number}', [\App\Http\Controllers\DocumentTraceController::class, 'trace'])->name('trace.document');

    Route::get('/jurnal', [JournalController::class, 'index'])->name('jurnal.index');
    Route::get('/jurnal/create', [JournalController::class, 'create'])->name('jurnal.create');
    Route::post('/jurnal', [JournalController::class, 'store'])->name('jurnal.store');
    Route::get('/jurnal/download-template', [JournalController::class, 'downloadTemplate'])->name('jurnal.download-template');
    Route::post('/jurnal/import', [JournalController::class, 'import'])->middleware('throttle:3,1')->name('jurnal.import');
    Route::post('/jurnal/sync-temp', [JournalController::class, 'dispatchSyncJob'])->name('jurnal.sync_temp');
    Route::get('/jurnal/detail/ajax', [JournalController::class, 'getJournalDetailsAjax'])->name('jurnal.detail.ajax');
    Route::get('/jurnal/export', [JournalController::class, 'export'])->name('jurnal.export');
    Route::get('/jurnal/{journal_id}/edit', [JournalController::class, 'edit'])->name('jurnal.edit');
    Route::put('/jurnal/{journal_id}', [JournalController::class, 'update'])->name('jurnal.update');
    Route::delete('/jurnal/{journal_id}', [JournalController::class, 'destroy'])->name('jurnal.destroy');
    Route::delete('/jurnal/mass-delete', [JournalController::class, 'massDestroy'])->name('jurnal.massDestroy');

    Route::get('/aset', [AssetController::class, 'index'])->name('aset.index');
    Route::get('/aset/create', [AssetController::class, 'create'])->name('aset.create');
    Route::post('/aset', [AssetController::class, 'store'])->name('aset.store');
    Route::post('/aset/update/{id}', [AssetController::class, 'update'])->name('aset.update');
    Route::delete('/aset/{id}', [AssetController::class, 'destroy'])->name('aset.destroy');
    Route::get('/aset/list-depresiasi', [AssetController::class, 'depreciationList'])->name('aset.list');
    Route::post('/aset/generate-depreciation', [AssetController::class, 'generateDepreciation'])->name('aset.generate_depreciation');
    Route::get('/aset/template', [AssetController::class, 'downloadTemplate'])->name('aset.template');
    Route::post('/aset/import', [AssetController::class, 'import'])->name('aset.import');
    Route::get('/aset/export', [AssetController::class, 'export'])->name('aset.export');
    Route::post('/aset/toggle-status/{id}', [AssetController::class, 'toggleStatus'])->name('aset.toggle_status');

    Route::get('/laba-rugi', [ProfitLossController::class, 'index'])->name('laba-rugi.index');
    Route::get('/neraca', [BalanceSheetController::class, 'index'])->name('neraca.index');
    Route::get('/laporan/arus-kas', [CashFlowController::class, 'index'])->name('arus-kas.index');

    // --- ADVANCED REPORTS ---
    Route::get('/reports/cogs-chronology', [App\Http\Controllers\AdvancedReportController::class, 'cogsChronology'])->name('reports.cogs');
    Route::get('/reports/tags', [App\Http\Controllers\AdvancedReportController::class, 'reportByTag'])->name('reports.tags');
    Route::get('/reports/ar-dp', [App\Http\Controllers\AdvancedReportController::class, 'reportArDp'])->name('reports.ar_dp');
    Route::get('/reports/ap-dp', [App\Http\Controllers\AdvancedReportController::class, 'reportApDp'])->name('reports.ap_dp');

    // 👇 PASTIKAN DUA BARIS INI ADA 👇
    Route::get('/reports/ar-management', [App\Http\Controllers\AdvancedReportController::class, 'arSubledger'])->name('reports.ar_subledger');
    Route::get('/reports/ap-management', [App\Http\Controllers\AdvancedReportController::class, 'apSubledger'])->name('reports.ap_subledger');

    Route::get('/reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
    Route::post('/reconciliation/compare', [ReconciliationController::class, 'compare'])->name('reconciliation.compare');
    Route::post('/reconciliation/export', [ReconciliationController::class, 'exportMismatch'])->name('reconciliation.export');

    Route::get('/divisi', [DivisiController::class, 'index'])->name('divisi.index');
    Route::get('/divisi/create', [DivisiController::class, 'create'])->name('divisi.create');
    Route::post('/divisi/store', [DivisiController::class, 'store'])->name('divisi.store');
    Route::get('/divisi/{id}/edit', [DivisiController::class, 'edit'])->name('divisi.edit');
    Route::put('/divisi/{id}/update', [DivisiController::class, 'update'])->name('divisi.update');
    Route::delete('/divisi/{id}/delete', [DivisiController::class, 'destroy'])->name('divisi.destroy');

    // --- PAYMENT PLAN ---
    Route::get('/payment-plan', [PaymentPlanController::class, 'index'])->name('payment.index');
    Route::get('/payment-plan/create', [PaymentPlanController::class, 'create'])->name('payment.create');
    Route::get('/payment-plan/template', [PaymentPlanController::class, 'downloadTemplate'])->name('payment.template');
    Route::get('/payment-plan/api/accounts', [PaymentPlanController::class, 'apiAccounts'])->name('payment.api_accounts');
    Route::get('/payment-plan/api/vendors', [PaymentPlanController::class, 'apiSearchVendors'])->name('payment.api.vendors');
    Route::get('/payment-plan/api/bills-by-vendor', [PaymentPlanController::class, 'apiBillsByVendor'])->name('payment.api.bills');
    Route::get('/payment-plan/api/pos-by-vendor', [PaymentPlanController::class, 'apiPOsByVendor'])->name('payment.api.pos');
    Route::post('/payment-plan', [PaymentPlanController::class, 'store'])->name('payment.store');
    Route::post('/payment-plan/import', [PaymentPlanController::class, 'importCsv'])->middleware('throttle:5,1')->name('payment.import');
    Route::post('/payment-plan/post-journal', [PaymentPlanController::class, 'postJournal'])->name('payment.post_journal');
    Route::get('/payment-plan/{id}/edit', [PaymentPlanController::class, 'edit'])->name('payment.edit');
    Route::put('/payment-plan/{id}/update', [PaymentPlanController::class, 'update'])->name('payment.update');
    Route::delete('/payment-plan/{id}/delete', [PaymentPlanController::class, 'destroy'])->name('payment.destroy');
    Route::post('/payment-plan/{id}/set-coa', [PaymentPlanController::class, 'setCoa'])->name('payment.set_coa');
    Route::post('/payment-plan/{id}/set-rekening', [PaymentPlanController::class, 'setRekening'])->name('payment.set_rekening');
    Route::post('/payment-plan/{id}/update-status', [PaymentPlanController::class, 'updateStatus'])->name('payment.update_status');
    Route::get('/payment-plan/export/kasbank', [PaymentPlanController::class, 'exportJubelioKasBank'])->name('payment.export.kasbank');
    Route::get('/payment-plan/export/worklist', [PaymentPlanController::class, 'exportManualWorklist'])->name('payment.export.worklist');
    
    // --- MASTER KATEGORI PAYMENT ---
    Route::get('/payment-category', [PaymentCategoryController::class, 'index'])->name('payment-category.index');
    Route::post('/payment-category', [PaymentCategoryController::class, 'store'])->name('payment-category.store');
    Route::put('/payment-category/{id}', [PaymentCategoryController::class, 'update'])->name('payment-category.update');
    Route::delete('/payment-category/{id}', [PaymentCategoryController::class, 'destroy'])->name('payment-category.destroy');
    Route::get('/payment-category/api', [PaymentCategoryController::class, 'apiIndex'])->name('payment-category.api');

    Route::post('/account/import-opening-balance', [App\Http\Controllers\AccountController::class, 'importOpeningBalance'])->name('account.import_opening_balance');
    Route::get('/account/download-template-opening-balance', [App\Http\Controllers\AccountController::class, 'downloadTemplateOpeningBalance'])->name('account.download_template_opening_balance');
    
    // 👇 RUTE BARU PROFIL PERUSAHAAN 👇
    Route::get('/profil-perusahaan', [App\Http\Controllers\CompanyProfileController::class, 'edit'])->name('company.edit');
    Route::put('/profil-perusahaan', [App\Http\Controllers\CompanyProfileController::class, 'update'])->name('company.update');

    // Rute Menu Proyeksi & Budgeting
    Route::get('/budgeting', [BudgetingController::class, 'index'])->name('budgeting.index');

    // --- MASTER BARANG ---
    Route::get('/master-barang', [App\Http\Controllers\ProductController::class, 'index'])->name('product.index');
    Route::post('/master-barang/import', [App\Http\Controllers\ProductController::class, 'import'])->name('product.import');
    Route::get('/master-barang/template', [App\Http\Controllers\ProductController::class, 'downloadTemplate'])->name('product.template');
    Route::get('/master-barang/create', [App\Http\Controllers\ProductController::class, 'create'])->name('product.create');
    Route::post('/master-barang', [App\Http\Controllers\ProductController::class, 'store'])->name('product.store');
    Route::post('/master-barang/sync-dashboard', [App\Http\Controllers\ProductController::class, 'dispatchSyncJob'])->name('product.sync_dashboard');
    Route::get('/master-barang/{id}/edit', [App\Http\Controllers\ProductController::class, 'edit'])->name('product.edit');
    Route::put('/master-barang/{id}', [App\Http\Controllers\ProductController::class, 'update'])->name('product.update');
    Route::delete('/master-barang/{id}', [App\Http\Controllers\ProductController::class, 'destroy'])->name('product.destroy');

    // --- INVENTORY LEDGER ---
    Route::get('/master-barang/kartu-stok', [App\Http\Controllers\InventoryLedgerController::class, 'index'])->name('inventory.ledger');

    // --- KODE BANTU (HELPER) ---
    Route::get('/kode-bantu/create', [App\Http\Controllers\HelperCodeController::class, 'create'])->name('helper.create');
    Route::post('/kode-bantu', [App\Http\Controllers\HelperCodeController::class, 'store'])->name('helper.store');

    // ==========================================
    // --- PURCHASE ORDER (PEMBELIAN) ---
    // ==========================================
    Route::prefix('purchase-order')->name('po.')->controller(\App\Http\Controllers\PurchaseOrderController::class)->group(function () {
        Route::get('/',              'index')->name('index');
        Route::get('/create',        'create')->name('create');
        Route::post('/',             'store')->name('store');
        Route::get('/template',      'downloadTemplate')->name('template');
        Route::post('/import',       'import')->name('import');
        Route::get('/{id}/edit',     'edit')->name('edit');
        Route::put('/{id}',          'update')->name('update');
        Route::delete('/{id}',       'destroy')->name('destroy');
        Route::post('/{id}/receive', 'receiveItems')->name('receive');
        Route::post('/{id}/void',    'voidReceipt')->name('void');
        Route::post('/sync-temp',    'dispatchSyncJob')->name('sync_temp');
    });

    // 👇 RUTE INBOUND GUDANG 👇
    Route::get('/penerimaan-barang', [\App\Http\Controllers\PurchaseOrderController::class, 'inboundIndex'])->name('inbound.index');

    // --- MASTER PAJAK ---
    Route::get('/tax/generate-default', [App\Http\Controllers\TaxController::class, 'generateDefault'])->name('tax.generate');
    Route::resource('tax', App\Http\Controllers\TaxController::class);
    
    // --- MANAJEMEN PENJUALAN (SALES ORDER) ---
    Route::get('/sales-order', [App\Http\Controllers\SalesOrderController::class, 'index'])->name('so.index');
    
    // 👇 DUA RUTE INI WAJIB DITAMBAHKAN UNTUK FITUR MANUAL 👇
    Route::get('/sales-order/create', [App\Http\Controllers\SalesOrderController::class, 'create'])->name('so.create');
    Route::post('/sales-order', [App\Http\Controllers\SalesOrderController::class, 'store'])->name('so.store');

    Route::get('/sales-order/{id}/edit', [App\Http\Controllers\SalesOrderController::class, 'edit'])->name('so.edit');
    Route::put('/sales-order/{id}', [App\Http\Controllers\SalesOrderController::class, 'update'])->name('so.update');
    Route::delete('/sales-order/{id}', [App\Http\Controllers\SalesOrderController::class, 'destroy'])->name('so.destroy');
    
    Route::post('/sales-order/import', [App\Http\Controllers\SalesOrderController::class, 'import'])->name('so.import');
    Route::get('/sales-order/template', [App\Http\Controllers\SalesOrderController::class, 'downloadTemplate'])->name('so.template');
    Route::post('/sales-order/{id}/ship', [App\Http\Controllers\SalesOrderController::class, 'processShipment'])->name('so.ship');
    Route::post('/sales-order/{id}/rollback', [App\Http\Controllers\SalesOrderController::class, 'rollbackShipment'])->name('so.rollback');
    Route::post('/sales-order/sync-temp', [App\Http\Controllers\SalesOrderController::class, 'dispatchSyncJob'])->name('so.sync_temp');

    // Route untuk Menu Faktur Penjualan & Analisa Pivot
    Route::get('/sales-invoices', [SalesInvoiceController::class, 'index'])->name('invoice.index');
    Route::get('/sales-invoices/create', [SalesInvoiceController::class, 'create'])->name('invoice.create');
    Route::post('/sales-invoices', [SalesInvoiceController::class, 'store'])->name('invoice.store');
    Route::get('/sales-invoices/{id}/show', [App\Http\Controllers\SalesInvoiceController::class, 'show'])->name('invoice.show');
    
    // 👇 TAMBAHKAN BARIS INI 👇
    Route::delete('/sales-invoices/{id}', [App\Http\Controllers\SalesInvoiceController::class, 'destroy'])->name('invoice.destroy');
    // 👆 ------------------ 👆

    Route::post('/sales-invoices/sync-temp', [SalesInvoiceController::class, 'dispatchSyncJob'])->name('invoice.sync_temp');

    // --- RETUR PENJUALAN (PEMERIKSAAN GUDANG) ---
    Route::get('/sales-returns', [App\Http\Controllers\SalesReturnController::class, 'index'])->name('sales-returns.index');
    Route::get('/sales-returns/create', [App\Http\Controllers\SalesReturnController::class, 'create'])->name('sales-returns.create');
    Route::post('/sales-returns', [App\Http\Controllers\SalesReturnController::class, 'store'])->name('sales-returns.store');
    Route::get('/sales-returns/get-invoice-items/{id}', [App\Http\Controllers\SalesReturnController::class, 'getInvoiceItems'])->name('sales-returns.get-invoice-items');
    Route::get('/sales-returns/{id}', [App\Http\Controllers\SalesReturnController::class, 'show'])->name('sales-returns.show');
    Route::post('/sales-returns/{id}/process', [App\Http\Controllers\SalesReturnController::class, 'process'])->name('sales-returns.process');

    // --- RETUR PEMBELIAN (PEMERIKSAAN GUDANG) ---
    Route::get('/purchase-returns', [App\Http\Controllers\PurchaseReturnController::class, 'index'])->name('purchase-returns.index');
    Route::get('/purchase-returns/create', [App\Http\Controllers\PurchaseReturnController::class, 'create'])->name('purchase-returns.create');
    Route::post('/purchase-returns', [App\Http\Controllers\PurchaseReturnController::class, 'store'])->name('purchase-returns.store');
    Route::get('/purchase-returns/get-po-items/{id}', [App\Http\Controllers\PurchaseReturnController::class, 'getPoItems'])->name('purchase-returns.get-po-items');
    Route::get('/purchase-returns/{id}', [App\Http\Controllers\PurchaseReturnController::class, 'show'])->name('purchase-returns.show');
    Route::post('/purchase-returns/{id}/process', [App\Http\Controllers\PurchaseReturnController::class, 'process'])->name('purchase-returns.process');

    // --- TAGIHAN PEMBELIAN (PURCHASE BILLS) ---
    Route::get('/purchase-bills', [App\Http\Controllers\PurchaseBillController::class, 'index'])->name('purchase-bills.index');
    Route::get('/purchase-bills/create', [App\Http\Controllers\PurchaseBillController::class, 'create'])->name('purchase-bills.create');
    Route::post('/purchase-bills', [App\Http\Controllers\PurchaseBillController::class, 'store'])->name('purchase-bills.store');
    Route::get('/purchase-bills/get-pos', [App\Http\Controllers\PurchaseBillController::class, 'getPosBySupplier'])->name('purchase-bills.get-pos');
    Route::get('/purchase-bills/{id}', [App\Http\Controllers\PurchaseBillController::class, 'show'])->name('purchase-bills.show');
    Route::delete('/purchase-bills/{id}', [App\Http\Controllers\PurchaseBillController::class, 'destroy'])->name('purchase-bills.destroy');
    Route::post('/purchase-bills/sync-temp', [App\Http\Controllers\PurchaseBillController::class, 'dispatchSyncJob'])->name('purchase-bills.sync_temp');

    // --- WAREHOUSE / GUDANG ---
    Route::prefix('warehouse')->name('warehouse.')->controller(\App\Http\Controllers\WarehouseController::class)->group(function () {
        Route::get('/process-orders', 'processOrders')->name('process-orders');
        Route::get('/inbound', 'inbound')->name('inbound');
        Route::get('/inbound/create', 'createInbound')->name('inbound.create');
        Route::post('/inbound', 'storeInbound')->name('inbound.store');
        Route::get('/inbound/get-po-by-supplier', 'getPoBySupplier')->name('inbound.get_po');
        Route::get('/inbound/get-po-details/{id}', 'getPoDetailsAjax')->name('inbound.get_po_details');
        Route::get('/outbound', 'outbound')->name('outbound');
        Route::get('/outbound/create', 'createOutbound')->name('outbound.create');
        Route::post('/outbound', 'storeOutbound')->name('outbound.store');
    });

    Route::get('/lang/{locale}', [App\Http\Controllers\LanguageController::class, 'switchLanguage'])->name('lang.switch');
});