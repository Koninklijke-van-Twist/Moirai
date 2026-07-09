# Moirai print bridge – Windows auto-start via Taakplanner
#
# Gebruik (PowerShell):
#   .\install-windows-autostart.ps1           # installeer + start nu
#   .\install-windows-autostart.ps1 -Status
#   .\install-windows-autostart.ps1 -Uninstall
#   .\install-windows-autostart.ps1 -Start    # handmatig via taak
#   .\install-windows-autostart.ps1 -Stop

param(
    [switch]$Uninstall,
    [switch]$Status,
    [switch]$Start,
    [switch]$Stop
)

$ErrorActionPreference = 'Stop'

$TaskName = 'MoiraiPrintBridge'
$BridgeDir = $PSScriptRoot
$RunnerScript = Join-Path $BridgeDir 'run-bridge-background.ps1'
$LogFile = Join-Path $BridgeDir 'logs\bridge.log'

function Show-Usage {
    @'
Moirai print bridge – Windows auto-start (Taakplanner)

Gebruik:
  .\install-windows-autostart.ps1           Installeer taak bij inloggen + start nu
  .\install-windows-autostart.ps1 -Status   Toon taak- en bridge-status
  .\install-windows-autostart.ps1 -Start    Start de taak handmatig
  .\install-windows-autostart.ps1 -Stop     Stop de taak
  .\install-windows-autostart.ps1 -Uninstall  Verwijder de taak

Handmatig (zonder auto-start): .\start-bridge.bat
'@ | Write-Host
}

function Test-BridgeHealth {
    try {
        $response = Invoke-WebRequest -Uri 'http://127.0.0.1:9173/health' -UseBasicParsing -TimeoutSec 2
        return $response.StatusCode -eq 200
    } catch {
        return $false
    }
}

function Show-Status {
    $task = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    if ($task) {
        $info = Get-ScheduledTaskInfo -TaskName $TaskName
        Write-Host "Taak:     $TaskName" -ForegroundColor Cyan
        Write-Host "Status:   $($task.State)"
        Write-Host "Laatste:  $($info.LastRunTime) (resultaat $($info.LastTaskResult))"
    } else {
        Write-Host "Taak '$TaskName' is niet geïnstalleerd." -ForegroundColor Yellow
    }

    if (Test-BridgeHealth) {
        Write-Host 'Bridge:   actief op http://127.0.0.1:9173' -ForegroundColor Green
    } else {
        Write-Host 'Bridge:   niet bereikbaar op poort 9173' -ForegroundColor Yellow
    }

    if (Test-Path $LogFile) {
        Write-Host ''
        Write-Host "Log ($LogFile, laatste regels):" -ForegroundColor Cyan
        Get-Content -Path $LogFile -Tail 15 -ErrorAction SilentlyContinue
    }
}

function Install-AutoStart {
    if (-not (Test-Path $RunnerScript)) {
        throw "Runner niet gevonden: $RunnerScript"
    }

    $node = Get-Command node -ErrorAction SilentlyContinue
    if (-not $node) {
        throw 'Node.js (>= 18) is vereist maar niet gevonden in PATH.'
    }

    $action = New-ScheduledTaskAction `
        -Execute 'powershell.exe' `
        -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$RunnerScript`"" `
        -WorkingDirectory $BridgeDir

    $trigger = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME

    $settings = New-ScheduledTaskSettingsSet `
        -AllowStartIfOnBatteries `
        -DontStopIfGoingOnBatteries `
        -StartWhenAvailable `
        -RestartCount 3 `
        -RestartInterval (New-TimeSpan -Minutes 1) `
        -MultipleInstances IgnoreNew

    $principal = New-ScheduledTaskPrincipal `
        -UserId $env:USERNAME `
        -LogonType Interactive `
        -RunLevel Limited

    Register-ScheduledTask `
        -TaskName $TaskName `
        -Action $action `
        -Trigger $trigger `
        -Settings $settings `
        -Principal $principal `
        -Description 'Moirai ESC/POS print bridge (GOOJPRT PT-210)' `
        -Force | Out-Null

    Write-Host "Taak '$TaskName' geïnstalleerd (start bij inloggen)." -ForegroundColor Green
    Start-ScheduledTask -TaskName $TaskName
    Start-Sleep -Seconds 2
    Show-Status
}

function Uninstall-AutoStart {
    $task = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    if (-not $task) {
        Write-Host "Taak '$TaskName' was niet geïnstalleerd." -ForegroundColor Yellow
        return
    }

    Stop-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
    Write-Host "Taak '$TaskName' verwijderd." -ForegroundColor Green
}

if ($PSBoundParameters.Count -eq 0) {
    Install-AutoStart
    exit 0
}

if ($Uninstall) {
    Uninstall-AutoStart
    exit 0
}

if ($Status) {
    Show-Status
    exit 0
}

if ($Start) {
    $task = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    if (-not $task) {
        Write-Host "Taak niet gevonden. Installeer eerst met: .\install-windows-autostart.ps1" -ForegroundColor Yellow
        exit 1
    }
    Start-ScheduledTask -TaskName $TaskName
    Start-Sleep -Seconds 2
    Show-Status
    exit 0
}

if ($Stop) {
    $task = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    if ($task) {
        Stop-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
        Write-Host "Taak '$TaskName' gestopt." -ForegroundColor Green
    } else {
        Write-Host "Taak '$TaskName' niet gevonden." -ForegroundColor Yellow
    }
    exit 0
}

Show-Usage
exit 1
