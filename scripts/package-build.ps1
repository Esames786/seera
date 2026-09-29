$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

# Run after Vite. Archive CONTENTS, not the build folder, for:
# unzip -oq public/build.zip -d public/build
$releaseRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$buildDirectory = Join-Path $releaseRoot 'public\build'
$manifestPath = Join-Path $buildDirectory 'manifest.json'
$archivePath = Join-Path $releaseRoot 'public\build.zip'
$temporaryArchive = Join-Path $releaseRoot ('public\build-release-' + [Guid]::NewGuid().ToString('N') + '.zip')
if (-not (Test-Path -LiteralPath $manifestPath -PathType Leaf)) {
    throw 'Build manifest missing. Run npm run build:release.'
}
$manifest = Get-Content -LiteralPath $manifestPath -Raw -Encoding UTF8 | ConvertFrom-Json
$buildPrefix = $buildDirectory + [IO.Path]::DirectorySeparatorChar
foreach ($property in $manifest.PSObject.Properties) {
    foreach ($asset in @($property.Value.file) + @($property.Value.css) + @($property.Value.assets)) {
        if (-not $asset) { continue }
        $assetPath = [IO.Path]::GetFullPath((Join-Path $buildDirectory $asset))
        if (-not $assetPath.StartsWith($buildPrefix, [StringComparison]::OrdinalIgnoreCase) -or
            -not (Test-Path -LiteralPath $assetPath -PathType Leaf)) {
            throw "Missing or invalid manifest asset: $asset"
        }
    }
}

try {
    $files = @(Get-ChildItem -LiteralPath $buildDirectory -Recurse -File -Force)
    $archive = [IO.Compression.ZipFile]::Open($temporaryArchive, [IO.Compression.ZipArchiveMode]::Create)
    try {
        foreach ($file in $files) {
            $entryName = $file.FullName.Substring($buildPrefix.Length).Replace('\', '/')
            [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $file.FullName, $entryName, [IO.Compression.CompressionLevel]::Optimal) | Out-Null
        }
    } finally { $archive.Dispose() }

    # Reopen and verify every archived byte against the current build.
    $archive = [IO.Compression.ZipFile]::OpenRead($temporaryArchive)
    try {
        if (-not $archive.GetEntry('manifest.json') -or $archive.Entries.Count -ne $files.Count) {
            throw 'ZIP layout/count verification failed.'
        }
        foreach ($file in $files) {
            $entryName = $file.FullName.Substring($buildPrefix.Length).Replace('\', '/')
            $entry = $archive.GetEntry($entryName)
            if (-not $entry) { throw "ZIP entry missing: $entryName" }
            $stream = $entry.Open()
            $sha = [Security.Cryptography.SHA256]::Create()
            try { $actual = [BitConverter]::ToString($sha.ComputeHash($stream)).Replace('-', '') }
            finally { $stream.Dispose(); $sha.Dispose() }
            if ($actual -ne (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash) {
                throw "ZIP content mismatch: $entryName"
            }
        }
    } finally { $archive.Dispose() }

    # Replace the release artifact only after successful validation.
    Move-Item -LiteralPath $temporaryArchive -Destination $archivePath -Force
    Write-Output "Release ZIP verified: $archivePath ($($files.Count) files; manifest.json and assets/ at ZIP root)"
    Write-Output ('SHA256: ' + (Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash)
} finally {
    if (Test-Path -LiteralPath $temporaryArchive) {
        Remove-Item -LiteralPath $temporaryArchive
    }
}
