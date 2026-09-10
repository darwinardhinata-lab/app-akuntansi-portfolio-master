<?php
$locales = ['id', 'en', 'zh_CN'];

$patterns = [
    'placeholder="Contoh: 11101"' => 'placeholder="{{ __(\'erp.eg_account_code\') }}"',
    'placeholder="Contoh: 36"' => 'placeholder="{{ __(\'erp.eg_useful_life\') }}"',
    'placeholder="Contoh: FIN"' => 'placeholder="{{ __(\'erp.eg_divisi_code\') }}"',
    'placeholder="Contoh: CUST-001"' => 'placeholder="{{ __(\'erp.eg_customer_code\') }}"',
    'placeholder="Misal: BKM-01"' => 'placeholder="{{ __(\'erp.eg_evidence_no\') }}"',
    'placeholder="Kaos Polo Lengan Pendek"' => 'placeholder="{{ __(\'erp.eg_garment_name\') }}"',
    'placeholder="PEMBELIAN PERSEDIAAN (PEMBAYARAN HUTANG)"' => 'placeholder="{{ __(\'erp.eg_payment_cat_name\') }}"',
    'placeholder="CONTOH: BCA - 123456 - NAMA"' => 'placeholder="{{ __(\'erp.eg_bank_account_format\') }}"',
    'placeholder="SKU..."' => 'placeholder="{{ __(\'erp.sku_ph\') }}"',
    'placeholder="Nama Barang..."' => 'placeholder="{{ __(\'erp.item_name_ph\') }}"',
    'placeholder="Ketik SKU..."' => 'placeholder="{{ __(\'erp.type_sku_ph\') }}"',
    'placeholder="PO-XXXX"' => 'placeholder="{{ __(\'erp.po_number_ph\') }}"',
    'placeholder="Misal: INV-SUP-01"' => 'placeholder="{{ __(\'erp.eg_supplier_inv\') }}"',
    'placeholder="JNE / J&T / Sicepat"' => 'placeholder="{{ __(\'erp.courier_ph\') }}"',
    'placeholder="Contoh: PPN 11%"' => 'placeholder="{{ __(\'erp.eg_tax_name\') }}"',
    'placeholder="Misal: SJ-001 / INV-001"' => 'placeholder="{{ __(\'erp.eg_delivery_note_inv\') }}"',
];

$newKeys = [
    'id' => [
        'eg_account_code' => 'Contoh: 11101',
        'eg_useful_life' => 'Contoh: 36',
        'eg_divisi_code' => 'Contoh: FIN',
        'eg_customer_code' => 'Contoh: CUST-001',
        'eg_evidence_no' => 'Misal: BKM-01',
        'eg_garment_name' => 'Contoh: Kaos Polo Lengan Pendek',
        'eg_payment_cat_name' => 'Contoh: PEMBELIAN PERSEDIAAN (PEMBAYARAN HUTANG)',
        'eg_bank_account_format' => 'CONTOH: BCA - 123456 - NAMA',
        'sku_ph' => 'SKU...',
        'item_name_ph' => 'Nama Barang...',
        'type_sku_ph' => 'Ketik SKU...',
        'po_number_ph' => 'PO-XXXX',
        'eg_supplier_inv' => 'Misal: INV-SUP-01',
        'courier_ph' => 'JNE / J&T / Sicepat',
        'eg_tax_name' => 'Contoh: PPN 11%',
        'eg_delivery_note_inv' => 'Misal: SJ-001 / INV-001',
    ],
    'en' => [
        'eg_account_code' => 'e.g. 11101',
        'eg_useful_life' => 'e.g. 36',
        'eg_divisi_code' => 'e.g. FIN',
        'eg_customer_code' => 'e.g. CUST-001',
        'eg_evidence_no' => 'e.g. BKM-01',
        'eg_garment_name' => 'e.g. Short Sleeve Polo Shirt',
        'eg_payment_cat_name' => 'e.g. INVENTORY PURCHASE (ACCOUNTS PAYABLE)',
        'eg_bank_account_format' => 'EXAMPLE: BANK - 123456 - NAME',
        'sku_ph' => 'SKU...',
        'item_name_ph' => 'Item Name...',
        'type_sku_ph' => 'Type SKU...',
        'po_number_ph' => 'PO-XXXX',
        'eg_supplier_inv' => 'e.g. INV-SUP-01',
        'courier_ph' => 'Courier / Shipping Express',
        'eg_tax_name' => 'e.g. VAT 11%',
        'eg_delivery_note_inv' => 'e.g. DN-001 / INV-001',
    ],
    'zh_CN' => [
        'eg_account_code' => '例如：11101',
        'eg_useful_life' => '例如：36',
        'eg_divisi_code' => '例如：FIN',
        'eg_customer_code' => '例如：CUST-001',
        'eg_evidence_no' => '例如：BKM-01',
        'eg_garment_name' => '例如：短袖Polo衫',
        'eg_payment_cat_name' => '例如：库存采购（应付账款）',
        'eg_bank_account_format' => '例如：银行 - 123456 - 姓名',
        'sku_ph' => 'SKU...',
        'item_name_ph' => '商品名称...',
        'type_sku_ph' => '输入SKU...',
        'po_number_ph' => 'PO-XXXX',
        'eg_supplier_inv' => '例如：INV-SUP-01',
        'courier_ph' => '顺丰 / 中通 / 圆通',
        'eg_tax_name' => '例如：增值税 11%',
        'eg_delivery_note_inv' => '例如：送货单-001 / 发票-001',
    ],
];

// 1. Add keys
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
            $addText = "\n    // Additional example & input placeholders\n";
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

// 2. Replace
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/views'));
$count = 0;
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $orig = file_get_contents($file->getPathname());
        $updated = $orig;
        foreach ($patterns as $find => $replace) {
            if (strpos($updated, $find) !== false) {
                $c = substr_count($updated, $find);
                $updated = str_replace($find, $replace, $updated);
                $count += $c;
            }
        }
        if ($updated !== $orig) {
            file_put_contents($file->getPathname(), $updated);
            echo "Updated: " . $file->getPathname() . "\n";
        }
    }
}
echo "Total replaced: {$count}\n";
