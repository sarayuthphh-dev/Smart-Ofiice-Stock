$ErrorActionPreference = 'Stop'
$project = Split-Path -Parent $MyInvocation.MyCommand.Path
$bundledPhp = Join-Path $project '.runtime\php\php.exe'
$php = if (Test-Path -LiteralPath $bundledPhp) { $bundledPhp } else { (Get-Command php -ErrorAction Stop).Source }
$arguments = @()
if ($php -eq $bundledPhp) {
    $arguments += @('-n','-d',"extension_dir=$project\.runtime\php\ext",'-d','extension=php_pdo_mysql.dll','-d','extension=php_mbstring.dll','-d','extension=php_fileinfo.dll','-d','extension=php_openssl.dll')
}
$arguments += (Join-Path $project 'bin\maintenance.php')
$logDirectory = Join-Path $project 'storage\logs'
if (!(Test-Path -LiteralPath $logDirectory)) { New-Item -ItemType Directory -Path $logDirectory | Out-Null }
$logFile = Join-Path $logDirectory 'maintenance-utf8.log'
("[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')]") | Out-File -LiteralPath $logFile -Encoding utf8 -Append
$output = @(& $php @arguments 2>&1)
$result = $LASTEXITCODE
$output | ForEach-Object { $_.ToString() } | Out-File -LiteralPath $logFile -Encoding utf8 -Append
$output | ForEach-Object { Write-Output $_ }
'' | Out-File -LiteralPath $logFile -Encoding utf8 -Append
exit $result
