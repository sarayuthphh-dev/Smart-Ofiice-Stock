$ErrorActionPreference = 'Stop'
$project = Split-Path -Parent $MyInvocation.MyCommand.Path
$php = Join-Path $project '.runtime\php\php.exe'

try {
    Write-Host 'ตั้งค่า LINE Messaging API สำหรับ Smart Office Stock' -ForegroundColor Cyan
    Write-Host 'ค่าที่กรอกจะไม่แสดงบนหน้าจอและไม่ถูกส่งเข้าบทสนทนา'
    Write-Host ''

    $secureToken = Read-Host 'วาง Channel Access Token แล้วกด Enter' -AsSecureString
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureToken)
    try {
        $token = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }
    if ([string]::IsNullOrWhiteSpace($token)) { throw 'Channel Access Token ห้ามว่าง' }

    $secureSecret = Read-Host 'วาง Channel Secret แล้วกด Enter' -AsSecureString
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureSecret)
    try {
        $channelSecret = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }
    if ([string]::IsNullOrWhiteSpace($channelSecret)) { throw 'Channel Secret ห้ามว่าง' }

    $userId = (Read-Host 'วาง Your user ID (ขึ้นต้น U) แล้วกด Enter').Trim()
    if ($userId -notmatch '^U[0-9a-fA-F]{32}$') { throw 'LINE User ID ต้องเป็น U ตามด้วยอักขระฐานสิบหก 32 ตัว' }

    [Environment]::SetEnvironmentVariable('LINE_CHANNEL_ACCESS_TOKEN', $token, 'User')
    [Environment]::SetEnvironmentVariable('LINE_CHANNEL_SECRET', $channelSecret, 'User')
    $env:LINE_CHANNEL_ACCESS_TOKEN = $token
    $env:LINE_CHANNEL_SECRET = $channelSecret

    & $php -n `
        -d 'extension_dir=.runtime/php/ext' `
        -d extension=php_pdo_mysql.dll `
        -d extension=php_mbstring.dll `
        -d extension=php_openssl.dll `
        (Join-Path $project 'bin\configure-line-test.php') $userId
    if ($LASTEXITCODE -ne 0) { throw 'การตั้งค่าหรือส่งข้อความทดสอบไม่สำเร็จ' }

    $line = netstat -ano -p tcp | Select-String '0\.0\.0\.0:8080\s+.*LISTENING' | Select-Object -First 1
    if ($line -and ($line.ToString() -match '\s(\d+)\s*$')) {
        $serverPid = [int]$Matches[1]
        $process = Get-Process -Id $serverPid -ErrorAction Stop
        if ($process.ProcessName -eq 'php') { Stop-Process -Id $serverPid -Force }
    }
    & (Join-Path $project 'start-local.ps1')

    Write-Host ''
    Write-Host 'ตั้งค่าและส่งข้อความทดสอบสำเร็จ กรุณาตรวจ LINE ในโทรศัพท์' -ForegroundColor Green
} catch {
    Write-Host ''
    Write-Host ('ไม่สำเร็จ: ' + $_.Exception.Message) -ForegroundColor Red
} finally {
    $token = $null
    $channelSecret = $null
    $secureToken = $null
    $secureSecret = $null
    Write-Host ''
    Read-Host 'กด Enter เพื่อปิดหน้าต่าง'
}
