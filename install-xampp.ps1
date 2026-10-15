[CmdletBinding()]
param(
    [string]$XamppPath = 'C:\xampp',
    [string]$PhpPath = '',
    [string]$MysqlClientPath = '',
    [string]$BaseUrl = '',
    [ValidateSet('127.0.0.1', 'localhost')]
    [string]$DatabaseHost = '127.0.0.1',
    [ValidateRange(1, 65535)]
    [int]$DatabasePort = 3306,
    [ValidatePattern('^[A-Za-z0-9_]{1,64}$')]
    [string]$DatabaseName = 'smart_office_stock',
    [ValidatePattern('^[A-Za-z0-9_]{1,32}$')]
    [string]$DatabaseUser = 'smart_stock',
    [switch]$ForceConfig,
    [switch]$CheckOnly
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$configPath = Join-Path $projectRoot 'config\config.php'
$schemaPath = Join-Path $projectRoot 'database\schema.sql'
$checkPath = Join-Path $projectRoot 'bin\check.php'
$installPath = Join-Path $projectRoot 'bin\install.php'

function Assert-ProjectLayout {
    foreach ($path in @($schemaPath, $checkPath, $installPath, (Join-Path $projectRoot 'public\index.php'))) {
        if (!(Test-Path -LiteralPath $path -PathType Leaf)) { throw "Missing required file: $path" }
    }
}

function Resolve-ToolPaths {
    if ([string]::IsNullOrWhiteSpace($script:PhpPath)) {
        $script:PhpPath = Join-Path $script:XamppPath 'php\php.exe'
    }
    if ([string]::IsNullOrWhiteSpace($script:MysqlClientPath)) {
        $mysql = Join-Path $script:XamppPath 'mysql\bin\mysql.exe'
        $mariadb = Join-Path $script:XamppPath 'mysql\bin\mariadb.exe'
        $script:MysqlClientPath = if (Test-Path -LiteralPath $mysql) { $mysql } else { $mariadb }
    }
    if (!(Test-Path -LiteralPath $script:PhpPath -PathType Leaf)) { throw "PHP executable not found: $script:PhpPath" }
    if (!(Test-Path -LiteralPath $script:MysqlClientPath -PathType Leaf)) { throw "MySQL client not found: $script:MysqlClientPath" }
}

function ConvertFrom-SecureValue([Security.SecureString]$Value) {
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($Value)
    try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
}

function New-RandomSecret([int]$Length = 32) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'
    $bytes = New-Object byte[] $Length
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    return -join ($bytes | ForEach-Object { $alphabet[$_ % $alphabet.Length] })
}

function ConvertTo-PhpString([string]$Value) {
    return "'" + $Value.Replace('\', '\\').Replace("'", "\'") + "'"
}

function Invoke-MysqlSql([string]$Sql, [string]$AdminUser, [string]$AdminPassword) {
    if ($AdminUser -notmatch '^[A-Za-z0-9_.@-]{1,80}$') { throw 'Invalid MySQL administrator username.' }
    $start = New-Object Diagnostics.ProcessStartInfo
    $start.FileName = $MysqlClientPath
    $start.Arguments = "--protocol=tcp --host=$DatabaseHost --port=$DatabasePort --user=$AdminUser --default-character-set=utf8mb4 --batch"
    $start.UseShellExecute = $false
    $start.RedirectStandardInput = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    $start.CreateNoWindow = $true
    $start.EnvironmentVariables['MYSQL_PWD'] = $AdminPassword
    $process = New-Object Diagnostics.Process
    $process.StartInfo = $start
    [void]$process.Start()
    $process.StandardInput.WriteLine($Sql)
    $process.StandardInput.Close()
    $stdout = $process.StandardOutput.ReadToEnd()
    $stderr = $process.StandardError.ReadToEnd()
    $process.WaitForExit()
    if ($process.ExitCode -ne 0) { throw "MySQL command failed: $stderr" }
    return $stdout
}

function Write-AppConfig([string]$AppDatabasePassword, [string]$AppBaseUrl) {
    $dsn = "mysql:host=$DatabaseHost;port=$DatabasePort;dbname=$DatabaseName;charset=utf8mb4"
    $namePhp = ConvertTo-PhpString 'Smart Office Stock'
    $officePhp = ConvertTo-PhpString 'Office'
    $basePhp = ConvertTo-PhpString $AppBaseUrl
    $dsnPhp = ConvertTo-PhpString $dsn
    $userPhp = ConvertTo-PhpString $DatabaseUser
    $passwordPhp = ConvertTo-PhpString $AppDatabasePassword
    $content = @"
<?php
declare(strict_types=1);

return [
    'app' => [
        'name' => $namePhp,
        'office_name' => $officePhp,
        'base_url' => getenv('APP_BASE_URL') ?: $basePhp,
        'timezone' => 'Asia/Bangkok',
        'session_name' => 'sos_session',
        'debug' => false,
    ],
    'db' => [
        'dsn' => getenv('DB_DSN') ?: $dsnPhp,
        'user' => getenv('DB_USER') ?: $userPhp,
        'password' => getenv('DB_PASSWORD') ?: $passwordPhp,
    ],
    'upload' => [
        'directory' => dirname(__DIR__) . '/storage/uploads',
        'max_bytes' => 2 * 1024 * 1024,
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    ],
    'line' => [
        'channel_access_token' => getenv('LINE_CHANNEL_ACCESS_TOKEN') ?: '',
        'api_url' => 'https://api.line.me/v2/bot/message/push',
    ],
];
"@
    $utf8 = New-Object Text.UTF8Encoding($false)
    [IO.File]::WriteAllText($configPath, $content, $utf8)
}

Assert-ProjectLayout
Resolve-ToolPaths

Write-Host 'Checking PHP requirements...'
& $PhpPath $checkPath
if ($LASTEXITCODE -ne 0) { throw 'PHP requirements are not complete. Enable the extensions reported above, then restart Apache.' }
if ($CheckOnly) {
    Write-Host 'Preflight check completed. No files or database records were changed.' -ForegroundColor Green
    exit 0
}

if (Test-Path -LiteralPath $configPath) {
    if (!$ForceConfig) {
        Write-Host 'Existing config/config.php was preserved. Running the idempotent schema installer...'
        & $PhpPath $installPath
        if ($LASTEXITCODE -ne 0) { throw 'Schema installation failed.' }
        Write-Host 'Installation check completed with the existing configuration.' -ForegroundColor Green
        exit 0
    }
    $backup = "$configPath.bak-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
    Copy-Item -LiteralPath $configPath -Destination $backup
    Write-Host "Existing configuration backed up to $backup"
}

if ([string]::IsNullOrWhiteSpace($BaseUrl)) {
    $htdocs = [IO.Path]::GetFullPath((Join-Path $XamppPath 'htdocs'))
    $fullRoot = [IO.Path]::GetFullPath($projectRoot)
    if (!$fullRoot.StartsWith($htdocs, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Project is not under XAMPP htdocs. Extract it there or pass -BaseUrl for a configured VirtualHost.'
    }
    $relative = $fullRoot.Substring($htdocs.Length).TrimStart('\').Replace('\', '/')
    $encoded = (($relative -split '/') | ForEach-Object { [Uri]::EscapeDataString($_) }) -join '/'
    $BaseUrl = "http://localhost/$encoded/public"
}
$uri = $null
if (![Uri]::TryCreate($BaseUrl, [UriKind]::Absolute, [ref]$uri) -or $uri.Scheme -notin @('http', 'https')) {
    throw 'BaseUrl must be an absolute http:// or https:// URL.'
}
$BaseUrl = $BaseUrl.TrimEnd('/')

$databasePassword = New-RandomSecret
$adminUser = Read-Host 'MySQL administrator username [root]'
if ([string]::IsNullOrWhiteSpace($adminUser)) { $adminUser = 'root' }
$adminPassword = ConvertFrom-SecureValue (Read-Host 'MySQL administrator password (blank is allowed for a new local XAMPP)' -AsSecureString)

$sql = @"
CREATE DATABASE IF NOT EXISTS ``$DatabaseName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DatabaseUser'@'localhost' IDENTIFIED BY '$databasePassword';
ALTER USER '$DatabaseUser'@'localhost' IDENTIFIED BY '$databasePassword';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES ON ``$DatabaseName``.* TO '$DatabaseUser'@'localhost';
CREATE USER IF NOT EXISTS '$DatabaseUser'@'127.0.0.1' IDENTIFIED BY '$databasePassword';
ALTER USER '$DatabaseUser'@'127.0.0.1' IDENTIFIED BY '$databasePassword';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES ON ``$DatabaseName``.* TO '$DatabaseUser'@'127.0.0.1';
FLUSH PRIVILEGES;
"@
try { [void](Invoke-MysqlSql $sql $adminUser $adminPassword) }
finally { $adminPassword = $null }

Write-AppConfig $databasePassword $BaseUrl
foreach ($directory in @('storage\uploads', 'storage\branding', 'storage\logs')) {
    [void](New-Item -ItemType Directory -Force -Path (Join-Path $projectRoot $directory))
}

$appAdminUser = Read-Host 'Application administrator username'
$appAdminName = Read-Host 'Application administrator full name'
$appAdminPassword = ConvertFrom-SecureValue (Read-Host 'Application administrator password (minimum 6 characters; 12+ recommended)' -AsSecureString)
if ($appAdminUser -notmatch '^[A-Za-z0-9._-]{3,80}$') { throw 'Application username must contain 3-80 letters, numbers, dot, underscore, or hyphen.' }
if ([string]::IsNullOrWhiteSpace($appAdminName) -or $appAdminName.Trim().Length -lt 2) { throw 'Application administrator full name is required.' }
if ($appAdminPassword.Length -lt 6) { throw 'Application administrator password must contain at least 6 characters.' }

$oldAdminUser = $env:SOS_ADMIN_USERNAME
$oldAdminName = $env:SOS_ADMIN_FULL_NAME
$oldAdminPassword = $env:SOS_ADMIN_PASSWORD
try {
    $env:SOS_ADMIN_USERNAME = $appAdminUser
    $env:SOS_ADMIN_FULL_NAME = $appAdminName.Trim()
    $env:SOS_ADMIN_PASSWORD = $appAdminPassword
    & $PhpPath $installPath
    if ($LASTEXITCODE -ne 0) { throw 'Application schema or administrator installation failed.' }
}
finally {
    $env:SOS_ADMIN_USERNAME = $oldAdminUser
    $env:SOS_ADMIN_FULL_NAME = $oldAdminName
    $env:SOS_ADMIN_PASSWORD = $oldAdminPassword
    $appAdminPassword = $null
    $databasePassword = $null
}

Write-Host ''
Write-Host 'Smart Office Stock installation completed.' -ForegroundColor Green
Write-Host "Open: $BaseUrl"
Write-Host 'Delete any config.php.bak-* file after verifying the new installation.'
