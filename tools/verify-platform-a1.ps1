param([switch]$FullSuite)
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$php = (Get-Command php -ErrorAction Stop).Source
if (-not (Test-Path (Join-Path $root 'vendor/autoload.php'))) { throw 'Install dependency composer.lock dahulu pada development.' }
$manifest = Get-Content (Join-Path $root 'docs/erp-merge/CHANGED_FILES.json') -Raw | ConvertFrom-Json
$keys = @('APP_ENV','APP_CONFIG_CACHE','DB_CONNECTION','DB_DATABASE','DB_URL','CACHE_STORE','SESSION_DRIVER','QUEUE_CONNECTION','MAIL_MAILER')
$previous = @{}
foreach ($key in $keys) { $previous[$key] = [Environment]::GetEnvironmentVariable($key, 'Process') }
Push-Location $root
try {
    $env:APP_ENV = 'testing'
    $env:APP_CONFIG_CACHE = Join-Path ([IO.Path]::GetTempPath()) (([Guid]::NewGuid().ToString()) + '-platform-config.php')
    $env:DB_CONNECTION = 'sqlite'
    $env:DB_DATABASE = ':memory:'
    $env:DB_URL = ''
    $env:CACHE_STORE = 'array'
    $env:SESSION_DRIVER = 'array'
    $env:QUEUE_CONNECTION = 'sync'
    $env:MAIL_MAILER = 'array'
    foreach ($entry in $manifest.files) {
        if ($entry.path.EndsWith('.php') -and -not $entry.path.EndsWith('.blade.php')) {
            & $php -l $entry.path
            if ($LASTEXITCODE -ne 0) { throw "PHP lint gagal: $($entry.path)" }
        }
    }
    if ($FullSuite) {
        & $php vendor/bin/phpunit -c phpunit.xml
    } else {
        & $php vendor/bin/phpunit -c phpunit.xml --filter 'PlatformAccessTest|PartyLinkageTest'
    }
    if ($LASTEXITCODE -ne 0) { throw 'Pengujian gagal; jangan aktifkan patch sebelum ditinjau.' }
} finally {
    foreach ($key in $keys) { [Environment]::SetEnvironmentVariable($key, $previous[$key], 'Process') }
    Pop-Location
}
