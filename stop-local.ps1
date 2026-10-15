$project = Split-Path -Parent $MyInvocation.MyCommand.Path
$runtimePrefix = (Join-Path $project '.runtime') + [IO.Path]::DirectorySeparatorChar
$targets = Get-Process -Name php,mariadbd -ErrorAction SilentlyContinue | Where-Object { $_.Path -and $_.Path.StartsWith($runtimePrefix, [StringComparison]::OrdinalIgnoreCase) }
foreach ($process in $targets) { Stop-Process -Id $process.Id -Force }
Write-Output "หยุด local runtime แล้ว ($($targets.Count) process)"
