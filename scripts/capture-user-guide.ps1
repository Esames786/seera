# Local-only training capture. New SQLite fixture; never reads .env credentials.
[CmdletBinding()]
param(
    [string] $Php = 'php',
    [string] $Node = 'node',
    [string] $Browser = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    [ValidateRange(1024,65535)] [int] $Port = 18773
)
$ErrorActionPreference = 'Stop'
$project = Split-Path $PSScriptRoot -Parent
if (-not (Test-Path -LiteralPath (Join-Path $project 'vendor/autoload.php'))) {
    throw 'Install local checkout dependencies first; do not use a shared vendor junction.'
}
if (-not (Test-Path -LiteralPath (Join-Path $project 'public/build/manifest.json'))) {
    throw 'Extract the reviewed public/build.zip into public/build in this local checkout first.'
}
if (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue) {
    throw "Port $Port already in use; no existing server will be stopped."
}
$phpPath = (Get-Command $Php).Source
$nodePath = (Get-Command $Node).Source
$fixture = Join-Path ([IO.Path]::GetTempPath()) ('seera-guide-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $fixture | Out-Null
New-Item -ItemType File -Path (Join-Path $fixture 'training.sqlite') | Out-Null
New-Item -ItemType Directory -Path (Join-Path $fixture 'views') | Out-Null
$keyBytes = New-Object byte[] 32
$rng = [Security.Cryptography.RandomNumberGenerator]::Create()
$rng.GetBytes($keyBytes)
$rng.Dispose()
$settings = @{
    APP_NAME='Seera ERP'; APP_ENV='testing'; APP_DEBUG='false'
    APP_KEY=('base64:'+[Convert]::ToBase64String($keyBytes))
    APP_URL="http://127.0.0.1:$Port"
    DB_CONNECTION='sqlite'; DB_DATABASE=(Join-Path $fixture 'training.sqlite'); DB_URL=''
    CACHE_STORE='array'; SESSION_DRIVER='file'; QUEUE_CONNECTION='sync'; MAIL_MAILER='array'
    APP_CONFIG_CACHE=(Join-Path $fixture 'config.php')
    APP_ROUTES_CACHE=(Join-Path $fixture 'routes.php')
    APP_EVENTS_CACHE=(Join-Path $fixture 'events.php')
    APP_SERVICES_CACHE=(Join-Path $fixture 'services.php')
    APP_PACKAGES_CACHE=(Join-Path $fixture 'packages.php')
    VIEW_COMPILED_PATH=(Join-Path $fixture 'views')
    ZATCA_PRODUCTION_ENABLED='false'; ZATCA_ENVIRONMENT='sandbox'
    SEERA_GUIDE_ROOT=$fixture; SEERA_GUIDE_CONFIRM_SYNTHETIC='YES'
    SEERA_GUIDE_BASE="http://127.0.0.1:$Port"; SEERA_PHP=$phpPath; SEERA_BROWSER=$Browser
}
[IO.File]::WriteAllText((Join-Path $fixture 'process-env.private.json'),($settings | ConvertTo-Json),(New-Object Text.UTF8Encoding($false)))
$prior = @{}
$server = $null
Push-Location $project
try {
    foreach ($key in $settings.Keys) {
        $prior[$key] = [Environment]::GetEnvironmentVariable($key)
        [Environment]::SetEnvironmentVariable($key,[string]$settings[$key])
    }
    & $phpPath -d opcache.enable_cli=1 tests/manual/user-guide-fixture.php $fixture
    if ($LASTEXITCODE -ne 0) { throw 'Fixture initialization failed; existing databases were not used.' }
    $serverArguments = @{
        FilePath=$phpPath; WorkingDirectory=$project; WindowStyle='Hidden'; PassThru=$true
        ArgumentList=@('-d','opcache.enable_cli=1','-S',"127.0.0.1:$Port",'-t','public','tests/manual/user-guide-server.php')
        RedirectStandardOutput=(Join-Path $fixture 'server.stdout.txt')
        RedirectStandardError=(Join-Path $fixture 'server.stderr.txt')
    }
    $server = Start-Process @serverArguments
    $ready = $false
    for ($attempt=0; $attempt -lt 30; $attempt++) {
        if ($server.HasExited) { throw 'Training server stopped unexpectedly; inspect its private log.' }
        try {
            $proof = Invoke-RestMethod -Uri "$($settings.APP_URL)/__guide_fixture_attestation" -TimeoutSec 10
            if ($proof.synthetic -eq $true -and $proof.id -eq (Split-Path $fixture -Leaf)) { $ready=$true; break }
        } catch { Start-Sleep -Milliseconds 250 }
    }
    if (-not $ready) { throw 'Synthetic server attestation failed.' }
    & $nodePath tests/manual/user-guide-screenshots.cjs $fixture
    if ($LASTEXITCODE -ne 0) { throw 'Capture verification failed. Do not publish partial screenshots.' }
    & $phpPath docs/user-guide/build-html.php
    if ($LASTEXITCODE -ne 0) { throw 'Guide home regeneration failed.' }
    & $phpPath scripts/validate-user-guide.php
    if ($LASTEXITCODE -ne 0) { throw 'Generated guide validation failed.' }
    Write-Output 'Training guide captured and validated. Review screenshots before committing.'
    Write-Output "Private fixture retained: $fixture (do not publish credentials/database)."
} finally {
    if ($server -and -not $server.HasExited) { Stop-Process -Id $server.Id }
    foreach ($key in $settings.Keys) { [Environment]::SetEnvironmentVariable($key,$prior[$key]) }
    Pop-Location
}
