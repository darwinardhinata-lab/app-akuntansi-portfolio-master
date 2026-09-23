<?php
$locales = ['id', 'en', 'zh_CN'];
$langData = [];
foreach ($locales as $loc) {
    $langData[$loc] = include "lang/{$loc}/erp.php";
}

$patterns = [
    // Titles & Alt
    'title="Reset Filter"' => 'title="{{ __(\'erp.reset_filter\') }}"',
    'title="Edit"' => 'title="{{ __(\'erp.edit_btn\') }}"',
    'title="Hapus"' => 'title="{{ __(\'erp.delete_btn\') }}"',
    'title="Klik untuk toggle status"' => 'title="{{ __(\'erp.toggle_status\') }}"',
    'title="Jejak Log Aktivitas"' => 'title="{{ __(\'erp.activity_log\') }}"',
    'title="Duplikat/Duplicate"' => 'title="{{ __(\'erp.duplicate\') }}"',
    'title="Hapus Aset"' => 'title="{{ __(\'erp.delete_asset\') }}"',
    'title="Klik untuk menelusuri dokumen asal"' => 'title="{{ __(\'erp.trace_origin_doc\') }}"',
    'title="Klik untuk menelusuri dokumen operasional asli"' => 'title="{{ __(\'erp.trace_origin_doc\') }}"',
    'title="Lihat Pasangan Jurnal"' => 'title="{{ __(\'erp.view_journal_pair\') }}"',
    'title="Void Cutting Order"' => 'title="{{ __(\'erp.void_cutting_order\') }}"',
    'title="Void Stitching Order"' => 'title="{{ __(\'erp.void_stitching_order\') }}"',
    'title="Lihat rincian item"' => 'title="{{ __(\'erp.view_item_details\') }}"',
    'title="Lihat Bukti Nota/Upload"' => 'title="{{ __(\'erp.view_receipt_proof\') }}"',
    'title="Edit Data"' => 'title="{{ __(\'erp.edit_data\') }}"',
    'title="Hapus Data"' => 'title="{{ __(\'erp.delete_data\') }}"',
    'title="Lihat bukti item ini"' => 'title="{{ __(\'erp.view_item_proof\') }}"',
    'title="Sinkronkan data produk dari Dashboard"' => 'title="{{ __(\'erp.sync_products_dashboard\') }}"',
    'title="Terkoneksi Payment Plan"' => 'title="{{ __(\'erp.connected_payment_plan\') }}"',
    'title="Edit PO"' => 'title="{{ __(\'erp.edit_po\') }}"',
    'title="Hapus PO"' => 'title="{{ __(\'erp.delete_po\') }}"',
    'title="Tanggal Tagihan"' => 'title="{{ __(\'erp.bill_date\') }}"',
    'title="Jatuh Tempo (Opsional)"' => 'title="{{ __(\'erp.due_date_optional\') }}"',
    'title="Cetak"' => 'title="{{ __(\'erp.print\') }}"',
    'title="Export PDF"' => 'title="{{ __(\'erp.export_pdf\') }}"',
    'title="Export Excel"' => 'title="{{ __(\'erp.export_excel\') }}"',
    'title="Klik untuk lihat detail mutasi jurnal"' => 'title="{{ __(\'erp.click_view_mutation_detail\') }}"',
    'title="Lihat Rincian Faktur"' => 'title="{{ __(\'erp.view_invoice_details\') }}"',
    'title="Batal / Void Faktur"' => 'title="{{ __(\'erp.void_invoice_btn\') }}"',
    
    'alt="Logo Perusahaan"' => 'alt="{{ __(\'erp.company_logo\') }}"',
    'alt="Logo"' => 'alt="{{ __(\'erp.logo_alt\') }}"',
    'aria-label="Close"' => 'aria-label="{{ __(\'erp.close_btn\') }}"',

    // Placeholders
    'placeholder="Ketik Kode atau Nama Akun..."' => 'placeholder="{{ __(\'erp.search_account\') }}"',
    'placeholder="Cari Kode Aset / Nama Aset..."' => 'placeholder="{{ __(\'erp.search_asset\') }}"',
    'placeholder="Masukkan PIN numerik untuk otorisasi karyawan"' => 'placeholder="{{ __(\'erp.enter_numeric_pin\') }}"',
    'placeholder="Ketik nama divisi..."' => 'placeholder="{{ __(\'erp.search_divisi\') }}"',
    'placeholder="Ketik Kode atau Nama Entitas / Kategori..."' => 'placeholder="{{ __(\'erp.search_payment_category\') }}"',
    'placeholder="Cari No. Bukti / Keterangan..."' => 'placeholder="{{ __(\'erp.search_evidence\') }}"',
    'placeholder="Cari Kode/Jenis Fabric"' => 'placeholder="{{ __(\'erp.search_fabric\') }}"',
    'placeholder="Cari Kode/Nama Supplier"' => 'placeholder="{{ __(\'erp.search_supplier\') }}"',
    'placeholder="Cari Kode/Jenis Yarn"' => 'placeholder="{{ __(\'erp.search_yarn\') }}"',
    'placeholder="Cari No. MRN"' => 'placeholder="{{ __(\'erp.search_mrn\') }}"',
    'placeholder="Cari No. SPK / Nama Garmen / Style SKU"' => 'placeholder="{{ __(\'erp.search_spk\') }}"',
    'placeholder="Keterangan kategori..."' => 'placeholder="{{ __(\'erp.category_description_ph\') }}"',
    'placeholder="Pilih tanggal transaksi"' => 'placeholder="{{ __(\'erp.select_transaction_date\') }}"',
    'placeholder="Ringkasan pengajuan, misal: Belanja ATK & Operasional Bulan Ini"' => 'placeholder="{{ __(\'erp.submission_summary_ph\') }}"',
    'placeholder="Nama barang/jasa"' => 'placeholder="{{ __(\'erp.item_service_name\') }}"',
    'placeholder="Uraian item"' => 'placeholder="{{ __(\'erp.item_description_ph\') }}"',
    'placeholder="Kosongkan jika = Nominal"' => 'placeholder="{{ __(\'erp.leave_blank_if_nominal\') }}"',
    'placeholder="Ketik No / Vendor..."' => 'placeholder="{{ __(\'erp.search_vendor\') }}"',
    'placeholder="Cari Detil Akun (COA)..."' => 'placeholder="{{ __(\'erp.search_coa\') }}"',
    'placeholder="Nama Lengkap Karyawan"' => 'placeholder="{{ __(\'erp.employee_full_name\') }}"',
    'placeholder="Nama Toko atau Supplier"' => 'placeholder="{{ __(\'erp.store_supplier_name\') }}"',
    'placeholder="Nama Bank - Nomor - Atas Nama"' => 'placeholder="{{ __(\'erp.bank_number_holder_ph\') }}"',
    'placeholder="Jelaskan secara rinci detail item dan tujuan penggunaan dana..."' => 'placeholder="{{ __(\'erp.explain_item_purpose_ph\') }}"',
    'placeholder="Masukkan PIN Perusahaan"' => 'placeholder="{{ __(\'erp.enter_company_pin\') }}"',
    'placeholder="Ketik SKU, Nama Barang, atau Kategori..."' => 'placeholder="{{ __(\'erp.search_product\') }}"',
    'placeholder="Masukkan nama vendor..."' => 'placeholder="{{ __(\'erp.enter_vendor_name\') }}"',
    'placeholder="Ketik kata kunci..."' => 'placeholder="{{ __(\'erp.search_placeholder\') }}"',
    'placeholder="Ketik nama tag / proyek..."' => 'placeholder="{{ __(\'erp.search_tag\') }}"',
    'placeholder="Nama Customer"' => 'placeholder="{{ __(\'erp.customer_name\') }}"',
    'placeholder="Cari No. Faktur / Pelanggan..."' => 'placeholder="{{ __(\'erp.search_invoice\') }}"',
    'placeholder="Nama Pelanggan / Customer"' => 'placeholder="{{ __(\'erp.customer_name\') }}"',
    'placeholder="Referensi Eksternal"' => 'placeholder="{{ __(\'erp.external_reference\') }}"',
    'placeholder="Nama Toko Cabang"' => 'placeholder="{{ __(\'erp.branch_store_name\') }}"',
    'placeholder="Masukkan Alamat Tujuan"' => 'placeholder="{{ __(\'erp.enter_dest_address\') }}"',
    'placeholder="Ketik No Retur..."' => 'placeholder="{{ __(\'erp.search_retur\') }}"',
    'placeholder="Ketik nama pajak..."' => 'placeholder="{{ __(\'erp.search_tax\') }}"',
    'placeholder="Ketik nama atau email..."' => 'placeholder="{{ __(\'erp.search_user\') }}"',
    'placeholder="Contoh: Kas Besar"' => 'placeholder="{{ __(\'erp.eg_kas_besar\') }}"',
    'placeholder="Contoh: Divisi Keuangan"' => 'placeholder="{{ __(\'erp.eg_finance_division\') }}"',
    'placeholder="Contoh: Pembayaran listrik bulan ini"' => 'placeholder="{{ __(\'erp.eg_electricity_payment\') }}"',
    'placeholder="Contoh: Beli ATK untuk bulan ini"' => 'placeholder="{{ __(\'erp.eg_buy_atk\') }}"',
    'placeholder="Contoh: Shopee, Tokopedia, atau link toko"' => 'placeholder="{{ __(\'erp.eg_store_link\') }}"',
];

// Check which keys are missing in lang files
$newKeys = [
    'id' => [
        'trace_origin_doc' => 'Klik untuk menelusuri dokumen asal',
        'view_journal_pair' => 'Lihat Pasangan Jurnal',
        'void_cutting_order' => 'Void Cutting Order',
        'void_stitching_order' => 'Void Stitching Order',
        'view_item_details' => 'Lihat rincian item',
        'view_receipt_proof' => 'Lihat Bukti Nota/Upload',
        'edit_data' => 'Edit Data',
        'delete_data' => 'Hapus Data',
        'view_item_proof' => 'Lihat bukti item ini',
        'sync_products_dashboard' => 'Sinkronkan data produk dari Dashboard',
        'connected_payment_plan' => 'Terkoneksi Payment Plan',
        'edit_po' => 'Edit PO',
        'delete_po' => 'Hapus PO',
        'bill_date' => 'Tanggal Tagihan',
        'due_date_optional' => 'Jatuh Tempo (Opsional)',
        'print' => 'Cetak',
        'export_pdf' => 'Export PDF',
        'export_excel' => 'Export Excel',
        'click_view_mutation_detail' => 'Klik untuk lihat detail mutasi jurnal',
        'view_invoice_details' => 'Lihat Rincian Faktur',
        'void_invoice_btn' => 'Batal / Void Faktur',
        'company_logo' => 'Logo Perusahaan',
        'enter_numeric_pin' => 'Masukkan PIN numerik untuk otorisasi karyawan',
        'category_description_ph' => 'Keterangan kategori...',
        'select_transaction_date' => 'Pilih tanggal transaksi',
        'submission_summary_ph' => 'Ringkasan pengajuan, misal: Belanja ATK & Operasional Bulan Ini',
        'item_service_name' => 'Nama barang/jasa',
        'item_description_ph' => 'Uraian item',
        'leave_blank_if_nominal' => 'Kosongkan jika = Nominal',
        'employee_full_name' => 'Nama Lengkap Karyawan',
        'store_supplier_name' => 'Nama Toko atau Supplier',
        'bank_number_holder_ph' => 'Nama Bank - Nomor - Atas Nama',
        'explain_item_purpose_ph' => 'Jelaskan secara rinci detail item dan tujuan penggunaan dana...',
        'enter_company_pin' => 'Masukkan PIN Perusahaan',
        'enter_vendor_name' => 'Masukkan nama vendor...',
        'customer_name' => 'Nama Customer',
        'external_reference' => 'Referensi Eksternal',
        'branch_store_name' => 'Nama Toko Cabang',
        'enter_dest_address' => 'Masukkan Alamat Tujuan',
        'eg_kas_besar' => 'Contoh: Kas Besar',
        'eg_finance_division' => 'Contoh: Divisi Keuangan',
        'eg_electricity_payment' => 'Contoh: Pembayaran listrik bulan ini',
        'eg_buy_atk' => 'Contoh: Beli ATK untuk bulan ini',
        'eg_store_link' => 'Contoh: Shopee, Tokopedia, atau link toko',
    ],
    'en' => [
        'trace_origin_doc' => 'Click to trace source document',
        'view_journal_pair' => 'View Journal Pair',
        'void_cutting_order' => 'Void Cutting Order',
        'void_stitching_order' => 'Void Stitching Order',
        'view_item_details' => 'View item details',
        'view_receipt_proof' => 'View Receipt/Upload Proof',
        'edit_data' => 'Edit Data',
        'delete_data' => 'Delete Data',
        'view_item_proof' => 'View proof for this item',
        'sync_products_dashboard' => 'Sync product data from Dashboard',
        'connected_payment_plan' => 'Connected to Payment Plan',
        'edit_po' => 'Edit PO',
        'delete_po' => 'Delete PO',
        'bill_date' => 'Bill Date',
        'due_date_optional' => 'Due Date (Optional)',
        'print' => 'Print',
        'export_pdf' => 'Export PDF',
        'export_excel' => 'Export Excel',
        'click_view_mutation_detail' => 'Click to view journal mutation detail',
        'view_invoice_details' => 'View Invoice Details',
        'void_invoice_btn' => 'Cancel / Void Invoice',
        'company_logo' => 'Company Logo',
        'enter_numeric_pin' => 'Enter numeric PIN for employee authorization',
        'category_description_ph' => 'Category description...',
        'select_transaction_date' => 'Select transaction date',
        'submission_summary_ph' => 'Submission summary, e.g.: Monthly Office Stationery & Operations',
        'item_service_name' => 'Item/service name',
        'item_description_ph' => 'Item description',
        'leave_blank_if_nominal' => 'Leave blank if = Nominal',
        'employee_full_name' => 'Employee Full Name',
        'store_supplier_name' => 'Store or Supplier Name',
        'bank_number_holder_ph' => 'Bank Name - Account No - Account Holder',
        'explain_item_purpose_ph' => 'Explain in detail item details and fund usage purpose...',
        'enter_company_pin' => 'Enter Company PIN',
        'enter_vendor_name' => 'Enter vendor name...',
        'customer_name' => 'Customer Name',
        'external_reference' => 'External Reference',
        'branch_store_name' => 'Branch Store Name',
        'enter_dest_address' => 'Enter Destination Address',
        'eg_kas_besar' => 'Example: Petty Cash',
        'eg_finance_division' => 'Example: Finance Division',
        'eg_electricity_payment' => 'Example: Electricity payment this month',
        'eg_buy_atk' => 'Example: Buy stationery for this month',
        'eg_store_link' => 'Example: Shopee, Amazon, or store link',
    ],
    'zh_CN' => [
        'trace_origin_doc' => '点击追踪原始凭证',
        'view_journal_pair' => '查看关联日记账分录',
        'void_cutting_order' => '作废裁剪工单',
        'void_stitching_order' => '作废缝制工单',
        'view_item_details' => '查看项目明细',
        'view_receipt_proof' => '查看发票凭证/上传',
        'edit_data' => '编辑数据',
        'delete_data' => '删除数据',
        'view_item_proof' => '查看此项目的凭证',
        'sync_products_dashboard' => '从仪表板同步产品数据',
        'connected_payment_plan' => '已关联付款计划',
        'edit_po' => '编辑采购单',
        'delete_po' => '删除采购单',
        'bill_date' => '账单日期',
        'due_date_optional' => '到期日（可选）',
        'print' => '打印',
        'export_pdf' => '导出PDF',
        'export_excel' => '导出Excel',
        'click_view_mutation_detail' => '点击查看日记账变动明细',
        'view_invoice_details' => '查看发票详情',
        'void_invoice_btn' => '作废/取消发票',
        'company_logo' => '公司标志',
        'enter_numeric_pin' => '输入员工授权数字PIN码',
        'category_description_ph' => '类别说明...',
        'select_transaction_date' => '选择交易日期',
        'submission_summary_ph' => '申请摘要，例如：本月办公用品和运营费用',
        'item_service_name' => '物品/服务名称',
        'item_description_ph' => '项目描述',
        'leave_blank_if_nominal' => '如果等于名义金额请留空',
        'employee_full_name' => '员工全名',
        'store_supplier_name' => '商店或供应商名称',
        'bank_number_holder_ph' => '银行名称 - 账号 - 开户人',
        'explain_item_purpose_ph' => '详细说明物品细节和资金用途...',
        'enter_company_pin' => '输入公司PIN码',
        'enter_vendor_name' => '输入供应商名称...',
        'customer_name' => '客户姓名',
        'external_reference' => '外部参考号',
        'branch_store_name' => '分店名称',
        'enter_dest_address' => '输入目的地地址',
        'eg_kas_besar' => '例如：总出纳现金',
        'eg_finance_division' => '例如：财务部',
        'eg_electricity_payment' => '例如：本月电费支出',
        'eg_buy_atk' => '例如：购买本月办公用品',
        'eg_store_link' => '例如：淘宝、京东或店铺链接',
    ]
];

// Step 1: Append missing keys to lang files
foreach ($locales as $loc) {
    $filePath = "lang/{$loc}/erp.php";
    $content = file_get_contents($filePath);
    $existing = include $filePath;
    
    $toAdd = [];
    foreach ($newKeys[$loc] as $k => $val) {
        if (!isset($existing[$k])) {
            $toAdd[$k] = $val;
        }
    }
    
    if (count($toAdd) > 0) {
        $lastBracketPos = strrpos($content, '];');
        if ($lastBracketPos !== false) {
            $addText = "\n    // HTML Attribute Keys (100% Pass)\n";
            foreach ($toAdd as $k => $val) {
                $escaped = addcslashes($val, "'\\");
                $addText .= "    '{$k}' => '{$escaped}',\n";
            }
            $content = substr($content, 0, $lastBracketPos) . $addText . substr($content, $lastBracketPos);
            file_put_contents($filePath, $content);
            echo "Added " . count($toAdd) . " keys to {$filePath}\n";
        }
    }
}

// Step 2: Replace patterns in blade files
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/views'));
$modifiedFiles = 0;
$replacementsCount = 0;

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $orig = file_get_contents($file->getPathname());
        $updated = $orig;
        
        foreach ($patterns as $find => $replace) {
            if (strpos($updated, $find) !== false) {
                $count = substr_count($updated, $find);
                $updated = str_replace($find, $replace, $updated);
                $replacementsCount += $count;
            }
        }
        
        if ($updated !== $orig) {
            file_put_contents($file->getPathname(), $updated);
            $modifiedFiles++;
            echo "Updated: " . $file->getPathname() . "\n";
        }
    }
}

echo "\nDone: Modified {$modifiedFiles} files with {$replacementsCount} replacements.\n";
