<?php
/**
 * Check which attribute-related keys already exist in lang files.
 */

$id = include 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\id\\erp.php';

// Common UI keys that might be used in attributes
$keys = [
    'edit', 'delete', 'reset', 'reset_filter', 'search', 'close', 'logo',
    'filter', 'hapus', 'edit_btn', 'delete_btn', 'reset_btn',
    'activity_log', 'duplicate', 'toggle_status', 'company_logo',
    'browse_origin', 'product_status', 'warehouse_center'
];

echo "=== Key availability in id/erp.php ===\n\n";
foreach ($keys as $k) {
    $exists = isset($id[$k]) ? 'YES' : 'NO';
    $val = isset($id[$k]) ? " => '{$id[$k]}'" : '';
    printf("%-25s %-5s%s\n", $k, $exists, $val);
}
