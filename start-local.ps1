$ErrorActionPreference = 'Stop'
$project = Split-Path -Parent $MyInvocation.MyCommand.Path
$runtime = Join-Path $project '.runtime'
$dbServer = Join-Path $runtime 'mariadb\mariadb-11.4.0-winx64\bin\mariadbd.exe'
$dbIni = Join-Path $runtime 'mariadb-data\my.ini'
$php = Join-Path $runtime 'php\php.exe'

function Test-LocalPort([int]$Port) {
    $client = [Net.Sockets.TcpClient]::new()
    try { $client.Connect('127.0.0.1', $Port); return $true } catch { return $false } finally { $client.Dispose() }
}

if (!(Test-Path $dbServer) -or !(Test-Path $php) -or !(Test-Path (Join-Path $project 'config\config.php'))) {
    throw 'ยังติดตั้ง local runtime ไม่ครบ กรุณาดู README.md'
}

if (!(Test-LocalPort 3307)) {
    Start-Process -FilePath $dbServer -ArgumentList "--defaults-file=`"$dbIni`"", '--port=3307', '--bind-address=127.0.0.1' -WorkingDirectory $project -WindowStyle Hidden
    for ($i = 0; $i -lt 30 -and !(Test-LocalPort 3307); $i++) { Start-Sleep -Milliseconds 500 }
}

if (!(Test-LocalPort 8080)) {
    $args = '-n -d extension_dir=.runtime/php/ext -d extension=php_pdo_mysql.dll -d extension=php_mbstring.dll -d extension=php_fileinfo.dll -d extension=php_openssl.dll -S 127.0.0.1:8080 -t public'
    Start-Process -FilePath $php -ArgumentList $args -WorkingDirectory $project -WindowStyle Hidden
}

Write-Output 'ระบบพร้อมที่ http://127.0.0.1:8080'
