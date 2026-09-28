<?php

namespace App\Modules\Platform\Support;

/**
 * Semantic posting keys are stable; each company explicitly maps them to its
 * own COA. Do not replace these with legacy config/coa.php fallback codes.
 */
class CompanyCoaRegistry
{
    /** @return array<string, array{normal_balance: string, label: string, mgi_account: string}> */
    public static function manufacturingMgi(): array
    {
        return [
            'raw_material_inventory' => ['normal_balance' => 'DEBET', 'label' => 'Persediaan bahan baku', 'mgi_account' => '114003'],
            'auxiliary_material_inventory' => ['normal_balance' => 'DEBET', 'label' => 'Bahan penolong dan suku cadang', 'mgi_account' => '114004'],
            'semi_finished_inventory' => ['normal_balance' => 'DEBET', 'label' => 'Barang setengah jadi', 'mgi_account' => '114008'],
            'wip_inventory' => ['normal_balance' => 'DEBET', 'label' => 'Barang dalam proses', 'mgi_account' => '114002'],
            'finished_goods_inventory' => ['normal_balance' => 'DEBET', 'label' => 'Barang jadi', 'mgi_account' => '114001'],
            'scrap_inventory' => ['normal_balance' => 'DEBET', 'label' => 'Scrap atau afal', 'mgi_account' => '114005'],
            'subcon_inventory' => ['normal_balance' => 'DEBET', 'label' => 'Barang di subcontractor', 'mgi_account' => '114007'],
            'supplier_advance' => ['normal_balance' => 'DEBET', 'label' => 'Uang muka pemasok', 'mgi_account' => '116002'],
            'input_vat' => ['normal_balance' => 'DEBET', 'label' => 'PPN masukan', 'mgi_account' => '117008'],
            'accounts_payable' => ['normal_balance' => 'KREDIT', 'label' => 'Utang usaha', 'mgi_account' => '211001'],
            'subcontract_accrual' => ['normal_balance' => 'KREDIT', 'label' => 'Akrual subcontractor', 'mgi_account' => '212001'],
            'grni_clearing' => ['normal_balance' => 'KREDIT', 'label' => 'Accrual inventory atau GRNI', 'mgi_account' => '212002'],
            'labor_accrual' => ['normal_balance' => 'KREDIT', 'label' => 'Akrual BTKL', 'mgi_account' => '212004'],
            'overhead_accrual' => ['normal_balance' => 'KREDIT', 'label' => 'Akrual overhead produksi', 'mgi_account' => '212005'],
            'output_vat' => ['normal_balance' => 'KREDIT', 'label' => 'PPN keluaran', 'mgi_account' => '213108'],
            'sales_local' => ['normal_balance' => 'KREDIT', 'label' => 'Penjualan lokal', 'mgi_account' => '411001'],
            'sales_export' => ['normal_balance' => 'KREDIT', 'label' => 'Penjualan ekspor', 'mgi_account' => '411002'],
            'cogs' => ['normal_balance' => 'DEBET', 'label' => 'Beban pokok penjualan', 'mgi_account' => '510001'],
            'inventory_adjustment' => ['normal_balance' => 'DEBET', 'label' => 'Penyesuaian persediaan', 'mgi_account' => '510004'],
        ];
    }
}