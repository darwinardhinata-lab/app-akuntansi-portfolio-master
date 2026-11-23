param(
    [string]$DbConnectionString = $env:R1_DRY_RUN_DB_URL,
    [Parameter(Mandatory = $true)][string]$ExpectedSchema,
    [Parameter(Mandatory = $true)][string]$ExpectedServer,
    [string]$ConnectionAlias = 'r1_disposable',
    [string]$PhpExecutable = 'php'
)

$ErrorActionPreference = 'Stop'
try {
    if (!$DbConnectionString) { throw 'Missing target' }
    $payload = @{ url = $DbConnectionString; schema = $ExpectedSchema; server = $ExpectedServer; alias = $ConnectionAlias } | ConvertTo-Json -Compress
    $payload | & $PhpExecutable (Join-Path $PSScriptRoot 'r1-migration-dry-run.php')
    exit $LASTEXITCODE
} catch {
    [Console]::Error.WriteLine('AC09 FAIL: launcher')
    exit 1
}