$base = 'D:\xampp\htdocs\app-akuntansi-portfolio-master\lang'
$c = Get-Content (Join-Path $base 'en\erp.php') -Raw
Write-Host '=== 10 karakter terakhir en/erp.php ==='
Write-Host $c.Substring($c.Length - 10)
Write-Host ''
$c = Get-Content (Join-Path $base 'id\erp.php') -Raw
Write-Host '=== 10 karakter terakhir id/erp.php ==='
Write-Host $c.Substring($c.Length - 10)
Write-Host ''
$c = Get-Content (Join-Path $base 'zh_CN\erp.php') -Raw
Write-Host '=== 10 karakter terakhir zh_CN/erp.php ==='
Write-Host $c.Substring($c.Length - 10)
