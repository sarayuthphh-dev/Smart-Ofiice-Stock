param([string]$DailyAt = '08:00')
$ErrorActionPreference = 'Stop'
$project = Split-Path -Parent $MyInvocation.MyCommand.Path
$runner = Join-Path $project 'run-maintenance.ps1'
if (!(Test-Path -LiteralPath $runner)) { throw 'run-maintenance.ps1 was not found' }
if ($DailyAt -notmatch '^([01]\d|2[0-3]):[0-5]\d$') { throw 'DailyAt must use HH:mm format' }
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$runner`"" -WorkingDirectory $project
$trigger = New-ScheduledTaskTrigger -Daily -At $DailyAt
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Minutes 10)
Register-ScheduledTask -TaskName 'SmartOfficeStock-Maintenance' -Description 'Send overdue reminders and verify the stock ledger' -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null
Write-Output "SmartOfficeStock-Maintenance was installed for $DailyAt"
