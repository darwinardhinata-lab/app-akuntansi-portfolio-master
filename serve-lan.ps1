param(
    [ValidateRange(1, 65535)]
    [int]$Port = 8000
)

$ErrorActionPreference = 'Stop'
$phpCommand = Get-Command php -CommandType Application -ErrorAction SilentlyContinue
$phpPath = if ($phpCommand) { $phpCommand.Source } else {
    # Project XAMPP biasanya berada di <xampp>\htdocs\<project>.
    Join-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) 'php\php.exe'
}

if (-not (Test-Path $phpPath)) {
    throw 'PHP tidak ditemukan. Tambahkan folder PHP ke PATH lalu jalankan kembali.'
}
if (-not (Test-Path (Join-Path $PSScriptRoot 'vendor\autoload.php'))) {
    throw 'Dependency belum tersedia. Jalankan composer install terlebih dahulu.'
}
if (-not (Test-Path (Join-Path $PSScriptRoot '.env'))) {
    throw 'File .env belum tersedia. Siapkan konfigurasi Laravel terlebih dahulu.'
}

Write-Host "Server development LAN pada port $Port. Hentikan dengan Ctrl+C."
Write-Host 'Gunakan hanya pada jaringan tepercaya; jangan buka port ini ke internet.'
Write-Host "Akses di komputer ini: http://localhost:$Port"
try {
    Get-NetIPConfiguration | Where-Object { $_.IPv4DefaultGateway } | ForEach-Object {
        foreach ($address in $_.IPv4Address) {
            Write-Host ("Akses dari HP ({0}): http://{1}:{2}" -f $_.InterfaceAlias, $address.IPAddress, $Port)
        }
    }
} catch {
    Write-Warning 'IP lokal tidak dapat dideteksi. Gunakan ipconfig untuk mencari IPv4 Wi-Fi/Ethernet.'
}
Write-Host 'Jika akses gagal, periksa Wi-Fi yang sama, firewall Private, dan AP isolation.'

Push-Location $PSScriptRoot
try {
    & $phpPath artisan serve --host=0.0.0.0 "--port=$Port" --tries=1
    $serverExitCode = $LASTEXITCODE
} finally {
    Pop-Location
}
exit $serverExitCode