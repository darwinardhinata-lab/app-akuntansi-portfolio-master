<?php
/**
 * Check which welcome page keys already exist in lang files.
 */

$id = include 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\id\\erp.php';
$en = include 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\en\\erp.php';
$zh = include 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\zh_CN\\erp.php';

// Keys we need for welcome page
$needed = [
    'welcome_dashboard',
    'welcome_login',
    'welcome_register',
    'welcome_lets_get_started',
    'welcome_rich_ecosystem',
    'welcome_read_the',
    'welcome_documentation',
    'welcome_laravel_docs_title',
    'welcome_laravel_docs_desc',
    'welcome_laracasts_title',
    'welcome_laracasts_desc',
    'welcome_laravel_news_title',
    'welcome_laravel_news_desc',
    'welcome_vibrant_ecosystem',
    'welcome_vibrant_ecosystem_desc',
    'welcome_start_again',
];

echo "=== Key availability check ===\n\n";
printf("%-40s %-20s %-20s %-20s\n", "KEY", "ID", "EN", "ZH");
echo str_repeat("-", 100) . "\n";

foreach ($needed as $k) {
    $id_val = isset($id[$k]) ? substr($id[$k], 0, 18) : 'NO';
    $en_val = isset($en[$k]) ? substr($en[$k], 0, 18) : 'NO';
    $zh_val = isset($zh[$k]) ? substr($zh[$k], 0, 18) : 'NO';
    printf("%-40s %-20s %-20s %-20s\n", $k, $id_val, $en_val, $zh_val);
}

echo "\n=== All welcome keys in each file ===\n";
echo "\nID file welcome keys:\n";
foreach ($id as $k => $v) {
    if (str_starts_with($k, 'welcome_')) {
        echo "  $k => $v\n";
    }
}
echo "\nEN file welcome keys:\n";
foreach ($en as $k => $v) {
    if (str_starts_with($k, 'welcome_')) {
        echo "  $k => " . substr($v, 0, 50) . "\n";
    }
}
echo "\nZH file welcome keys:\n";
foreach ($zh as $k => $v) {
    if (str_starts_with($k, 'welcome_')) {
        echo "  $k => " . substr($v, 0, 50) . "\n";
    }
}
