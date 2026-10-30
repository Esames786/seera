# SEERA test runner for Windows / Laragon (development only).
# Examples:
#   powershell -NoProfile -ExecutionPolicy Bypass -File scripts/test-speed.ps1 -Mode focused -Filter ProjectReportsOverviewTest
#   powershell -NoProfile -ExecutionPolicy Bypass -File scripts/test-speed.ps1 -Mode parallel -Processes 4
#   powershell -NoProfile -ExecutionPolicy Bypass -File scripts/test-speed.ps1 -Mode parallel -Processes 8
# The parallel mode requires brianium/paratest as a Composer dev dependency.
[CmdletBinding()]
param(
    [ValidateSet('focused', 'parallel', 'serial')]
    [string] $Mode = 'focused',

    [ValidateRange(1, 32)]
    [int] $Processes = 8,

    [string] $Filter = '',

    [string] $Php = 'php',

    [switch] $Profile
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Push-Location $root
try {
    if (-not (Test-Path -LiteralPath 'artisan')) {
        throw 'Not in the SEERA Laravel project root.'
    }
    if (-not (Test-Path -LiteralPath 'vendor/autoload.php')) {
        throw 'Missing vendor/autoload.php. Run composer install --no-interaction in this DEV checkout first.'
    }
    if (Test-Path -LiteralPath 'bootstrap/cache/config.php') {
        throw 'A cached Laravel config exists. In your DEV checkout run php artisan config:clear so phpunit.xml SQLite :memory: test settings apply.'
    }

    $argsForArtisan = @('artisan', 'test')
    switch ($Mode) {
        'focused' {
            if ([string]::IsNullOrWhiteSpace($Filter)) {
                throw '-Mode focused requires -Filter to avoid accidental full-suite runs.'
            }
            $argsForArtisan += ('--filter=' + $Filter)
        }
        'parallel' {
            if (-not (Test-Path -LiteralPath 'vendor/brianium/paratest')) {
                throw 'Parallel testing is not installed. In this DEV checkout run: composer require --dev brianium/paratest (commit BOTH composer.json and composer.lock after verification).'
            }
            $argsForArtisan += '--parallel'
            $argsForArtisan += ('--processes=' + $Processes)
            if (-not [string]::IsNullOrWhiteSpace($Filter)) {
                $argsForArtisan += ('--filter=' + $Filter)
            }
        }
        'serial' {
            if (-not [string]::IsNullOrWhiteSpace($Filter)) {
                $argsForArtisan += ('--filter=' + $Filter)
            }
        }
    }

    if ($Profile) {
        $argsForArtisan += '--profile'
    }

    Write-Host ('SEERA test mode: ' + $Mode + '; workers: ' + $(if ($Mode -eq 'parallel') { $Processes } else { 1 }))
    Write-Host ('Command: ' + $Php + ' ' + ($argsForArtisan -join ' '))
    Write-Host 'Use the configured phpunit.xml SQLite in-memory database; never run this against production.'
    $timer = [System.Diagnostics.Stopwatch]::StartNew()
    & $Php @argsForArtisan
    $result = $LASTEXITCODE
    $timer.Stop()
    Write-Host ('SEERA_TEST_DURATION_SECONDS=' + [Math]::Round($timer.Elapsed.TotalSeconds, 2))
    Write-Host ('SEERA_TEST_EXIT_CODE=' + $result)
    exit $result
}
finally {
    Pop-Location
}
