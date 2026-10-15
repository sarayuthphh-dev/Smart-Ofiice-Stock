[CmdletBinding()]
param(
    [string]$Version = (Get-Date -Format 'yyyy.MM.dd'),
    [string]$OutputDirectory = '',
    [switch]$SkipPhpLint
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
if ($Version -notmatch '^[A-Za-z0-9._-]+$') { throw 'Version may contain only letters, numbers, dot, underscore, and hyphen.' }
if ([string]::IsNullOrWhiteSpace($OutputDirectory)) { $OutputDirectory = Join-Path $projectRoot 'dist' }
$outputFull = [IO.Path]::GetFullPath($OutputDirectory)
[void](New-Item -ItemType Directory -Force -Path $outputFull)

$archiveName = "Smart-Office-Stock-$Version-XAMPP.zip"
$archivePath = Join-Path $outputFull $archiveName
$checksumPath = "$archivePath.sha256"
$stageRoot = Join-Path $outputFull ('.release-stage-' + [Guid]::NewGuid().ToString('N'))
$verifyRoot = Join-Path $outputFull ('.release-verify-' + [Guid]::NewGuid().ToString('N'))
$packageRoot = Join-Path $stageRoot 'smart-office-stock'

function Remove-SafeDirectory([string]$Path) {
    $full = [IO.Path]::GetFullPath($Path)
    $allowedPrefix = $outputFull.TrimEnd('\') + [IO.Path]::DirectorySeparatorChar
    if (!$full.StartsWith($allowedPrefix, [StringComparison]::OrdinalIgnoreCase)) {
        throw "Refusing to remove path outside output directory: $full"
    }
    if (Test-Path -LiteralPath $full) { Remove-Item -LiteralPath $full -Recurse -Force }
}

function Copy-Directory([string]$Name) {
    $source = Join-Path $projectRoot $Name
    if (!(Test-Path -LiteralPath $source -PathType Container)) { throw "Missing source directory: $source" }
    Copy-Item -LiteralPath $source -Destination (Join-Path $packageRoot $Name) -Recurse
}

function Invoke-PhpLint([string]$Root) {
    if ($SkipPhpLint) { return }
    $phpCandidates = @(
        (Join-Path $projectRoot '.runtime\php\php.exe'),
        'C:\xampp\php\php.exe'
    )
    $php = $phpCandidates | Where-Object { Test-Path -LiteralPath $_ -PathType Leaf } | Select-Object -First 1
    if (!$php) { throw 'PHP was not found for release lint. Install XAMPP/PHP or use -SkipPhpLint explicitly.' }
    $failed = @()
    foreach ($file in Get-ChildItem -LiteralPath $Root -Recurse -Filter '*.php' -File) {
        & $php -n -l $file.FullName *> $null
        if ($LASTEXITCODE -ne 0) { $failed += $file.FullName }
    }
    if ($failed.Count -gt 0) { throw "PHP syntax check failed:`n$($failed -join "`n")" }
}

function Assert-ReleaseContents([string]$Root) {
    $required = @(
        'smart-office-stock\app\bootstrap.php',
        'smart-office-stock\bin\install.php',
        'smart-office-stock\config\config.example.php',
        'smart-office-stock\database\schema.sql',
        'smart-office-stock\public\index.php',
        'smart-office-stock\public\vendor\adminlte\adminlte.min.css',
        'smart-office-stock\storage\.htaccess',
        'smart-office-stock\storage\uploads\.gitkeep',
        'smart-office-stock\storage\branding\.gitkeep',
        'smart-office-stock\INSTALL_WINDOWS.md',
        'smart-office-stock\install-xampp.ps1'
    )
    foreach ($relative in $required) {
        if (!(Test-Path -LiteralPath (Join-Path $Root $relative) -PathType Leaf)) { throw "Release is missing: $relative" }
    }
    $forbidden = @(
        'smart-office-stock\config\config.php',
        'smart-office-stock\.env',
        'smart-office-stock\.runtime',
        'smart-office-stock\.git',
        'smart-office-stock\.vscode'
    )
    foreach ($relative in $forbidden) {
        if (Test-Path -LiteralPath (Join-Path $Root $relative)) { throw "Forbidden release content found: $relative" }
    }
    $privateFiles = Get-ChildItem -LiteralPath (Join-Path $Root 'smart-office-stock\storage') -Recurse -File | Where-Object { $_.Name -notin @('.gitkeep', '.htaccess') }
    if ($privateFiles) { throw 'Release contains storage data. Only skeleton files are permitted.' }
}

try {
    [void](New-Item -ItemType Directory -Force -Path $packageRoot)
    foreach ($directory in @('app', 'bin', 'docs', 'public', 'tests')) { Copy-Directory $directory }

    [void](New-Item -ItemType Directory -Force -Path (Join-Path $packageRoot 'config'))
    Copy-Item -LiteralPath (Join-Path $projectRoot 'config\config.example.php') -Destination (Join-Path $packageRoot 'config\config.example.php')
    [void](New-Item -ItemType Directory -Force -Path (Join-Path $packageRoot 'database'))
    foreach ($file in @('schema.sql', 'sample_catalog.sql')) {
        Copy-Item -LiteralPath (Join-Path $projectRoot "database\$file") -Destination (Join-Path $packageRoot "database\$file")
    }
    foreach ($file in @('.dockerignore', '.env.example', '.htaccess', 'docker-compose.yml', 'Dockerfile', 'README.md', 'INSTALL_WINDOWS.md', 'install-xampp.ps1')) {
        $source = Join-Path $projectRoot $file
        if (!(Test-Path -LiteralPath $source -PathType Leaf)) { throw "Missing release file: $file" }
        Copy-Item -LiteralPath $source -Destination (Join-Path $packageRoot $file)
    }

    foreach ($directory in @('storage', 'storage\uploads', 'storage\branding', 'storage\logs')) {
        [void](New-Item -ItemType Directory -Force -Path (Join-Path $packageRoot $directory))
    }
    Copy-Item -LiteralPath (Join-Path $projectRoot 'storage\.htaccess') -Destination (Join-Path $packageRoot 'storage\.htaccess')
    foreach ($directory in @('uploads', 'branding', 'logs')) {
        Copy-Item -LiteralPath (Join-Path $projectRoot "storage\$directory\.gitkeep") -Destination (Join-Path $packageRoot "storage\$directory\.gitkeep")
    }

    $manifest = @"
Smart Office Stock deployment package
Version: $Version
Built: $((Get-Date).ToString('yyyy-MM-dd HH:mm:ss zzz'))
Target: Windows + XAMPP (PHP 8.2+ / MySQL or MariaDB)

This clean package intentionally excludes config/config.php, .env, runtime binaries,
database data, uploads, logos, logs, and backups. Read INSTALL_WINDOWS.md first.
"@
    $utf8 = New-Object Text.UTF8Encoding($false)
    [IO.File]::WriteAllText((Join-Path $packageRoot 'RELEASE-MANIFEST.txt'), $manifest, $utf8)

    Assert-ReleaseContents $stageRoot
    Invoke-PhpLint $packageRoot
    $parseErrors = $null
    [System.Management.Automation.Language.Parser]::ParseFile((Join-Path $packageRoot 'install-xampp.ps1'), [ref]$null, [ref]$parseErrors) | Out-Null
    if ($parseErrors.Count -gt 0) { throw "PowerShell installer syntax error: $($parseErrors[0].Message)" }

    if (Test-Path -LiteralPath $archivePath) { Remove-Item -LiteralPath $archivePath -Force }
    if (Test-Path -LiteralPath $checksumPath) { Remove-Item -LiteralPath $checksumPath -Force }
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zipStream = [IO.File]::Open($archivePath, [IO.FileMode]::CreateNew)
    $zipArchive = New-Object IO.Compression.ZipArchive($zipStream, [IO.Compression.ZipArchiveMode]::Create, $false)
    try {
        foreach ($file in Get-ChildItem -LiteralPath $stageRoot -Recurse -File) {
            $entryName = $file.FullName.Substring($stageRoot.Length + 1).Replace('\', '/')
            $entry = $zipArchive.CreateEntry($entryName, [IO.Compression.CompressionLevel]::Optimal)
            $entry.LastWriteTime = $file.LastWriteTime
            $entryStream = $entry.Open()
            $fileStream = [IO.File]::OpenRead($file.FullName)
            try { $fileStream.CopyTo($entryStream) }
            finally { $fileStream.Dispose(); $entryStream.Dispose() }
        }
    }
    finally { $zipArchive.Dispose(); $zipStream.Dispose() }

    [IO.Compression.ZipFile]::ExtractToDirectory($archivePath, $verifyRoot)
    Assert-ReleaseContents $verifyRoot
    Invoke-PhpLint (Join-Path $verifyRoot 'smart-office-stock')

    $hash = (Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash.ToLowerInvariant()
    [IO.File]::WriteAllText($checksumPath, "$hash  $archiveName`r`n", [Text.Encoding]::ASCII)
    $sizeMb = [Math]::Round((Get-Item -LiteralPath $archivePath).Length / 1MB, 2)
    Write-Host "RELEASE_BUILD_PASS: $archivePath ($sizeMb MB)" -ForegroundColor Green
    Write-Host "SHA256: $hash"
}
finally {
    Remove-SafeDirectory $stageRoot
    Remove-SafeDirectory $verifyRoot
}
