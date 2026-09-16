$base = 'D:\xampp\htdocs\app-akuntansi-portfolio-master\lang'
$files = @('id\erp.php', 'en\erp.php', 'zh_CN\erp.php')
foreach ($f in $files) {
    $path = Join-Path $base $f
    $lines = (Get-Content $path).Count
    Write-Host "$f = $lines baris"
}
