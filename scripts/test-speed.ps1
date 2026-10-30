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

    [switch] $Profile,

    # Run the tests with CLI OPcache enabled through an isolated php.ini copy (PHPRC), so the
    # machine's own php.ini is never edited and the setting reaches the worker processes too.
    [switch] $OpCache
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
    if (Get-ChildItem -LiteralPath 'bootstrap/cache' -Filter 'routes-*.php' -ErrorAction SilentlyContinue) {
        throw 'A cached Laravel route file exists. In your DEV checkout run php artisan route:clear so route changes on this branch are tested.'
    }

    # Resolve the PHP executable. Laragon machines often have no php on PATH, so fall back to
    # the SEERA_PHP environment variable, then to the newest PHP 8.3 under a Laragon install.
    if ($Php -eq 'php' -and -not (Get-Command php -ErrorAction SilentlyContinue)) {
        if ($env:SEERA_PHP -and (Test-Path -LiteralPath $env:SEERA_PHP)) {
            $Php = $env:SEERA_PHP
        }
        else {
            $candidates = @()
            foreach ($laragon in @($env:LARAGON_ROOT, 'C:\laragon', 'D:\laragon', 'D:\laragon2')) {
                if ($laragon -and (Test-Path -LiteralPath (Join-Path $laragon 'bin\php'))) {
                    $candidates += Get-ChildItem -Path (Join-Path $laragon 'bin\php') -Directory -Filter 'php-8.3*' -ErrorAction SilentlyContinue |
                        ForEach-Object { Join-Path $_.FullName 'php.exe' } |
                        Where-Object { Test-Path -LiteralPath $_ }
                }
            }
            if ($candidates.Count -eq 0) {
                throw 'php is not on PATH. Pass -Php <full path to php.exe> or set SEERA_PHP.'
            }
            $Php = ($candidates | Sort-Object -Descending | Select-Object -First 1)
        }
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

    if ($OpCache) {
        $loadedIni = (& $Php -r 'echo php_ini_loaded_file();').Trim()
        if (-not $loadedIni -or -not (Test-Path -LiteralPath $loadedIni)) {
            throw 'Cannot locate the php.ini that this PHP loads; -OpCache needs one to copy.'
        }
        $iniDir = Join-Path ([System.IO.Path]::GetTempPath()) 'seera-test-opcache'
        New-Item -ItemType Directory -Force -Path $iniDir | Out-Null
        $iniCopy = Join-Path $iniDir 'php.ini'
        $opcacheLines = @(
            '',
            '; --- appended by scripts/test-speed.ps1 -OpCache (development test runs only) ---',
            'zend_extension=opcache',
            'opcache.enable=1',
            'opcache.enable_cli=1',
            'opcache.memory_consumption=256',
            'opcache.interned_strings_buffer=16',
            'opcache.max_accelerated_files=20000',
            'opcache.validate_timestamps=1',
            'opcache.revalidate_freq=0',
            'opcache.jit=off'
        )
        Set-Content -LiteralPath $iniCopy -Value ((Get-Content -LiteralPath $loadedIni) + $opcacheLines) -Encoding ascii
        $env:PHPRC = $iniDir
        # No double quotes inside this PHP snippet: Windows PowerShell strips them when calling a native exe.
        $effective = (& $Php -r 'echo (int) extension_loaded(''Zend OPcache''), '':'', ini_get(''opcache.enable_cli'');').Trim()
        if ($effective -ne '1:1') {
            throw ('OPcache did not take effect through PHPRC (got "' + $effective + '"). Check that php_opcache.dll exists in the extension_dir of ' + $loadedIni)
        }
        Write-Host ('CLI OPcache enabled through isolated ini copy: ' + $iniCopy)
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
