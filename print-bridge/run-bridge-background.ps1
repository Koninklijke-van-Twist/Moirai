# Start Moirai print bridge in de achtergrond (voor Taakplanner / auto-start).
# Handmatig testen: powershell -File .\run-bridge-background.ps1

$ErrorActionPreference = 'Stop'

$BridgeDir = $PSScriptRoot
Set-Location $BridgeDir

$env:MOIRAI_USE_USB_DIRECT = '1'

$logDir = Join-Path $BridgeDir 'logs'
$logFile = Join-Path $logDir 'bridge.log'
New-Item -ItemType Directory -Force -Path $logDir | Out-Null

function Write-BridgeLog {
    param([string]$Message)
    $line = '{0} {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message
    Add-Content -Path $logFile -Value $line -Encoding UTF8
}

try {
    $health = Invoke-WebRequest -Uri 'http://127.0.0.1:9173/health' -UseBasicParsing -TimeoutSec 2
    if ($health.StatusCode -eq 200) {
        Write-BridgeLog 'Bridge draait al op poort 9173; overslaan.'
        exit 0
    }
} catch {
    # Nog niet actief; doorgaan met starten.
}

$node = Get-Command node -ErrorAction SilentlyContinue
if (-not $node) {
    Write-BridgeLog 'FOUT: Node.js niet gevonden in PATH (Node 18+ vereist).'
    exit 1
}

$nodeMajor = [int](& node -p "process.versions.node.split('.')[0]")
if ($nodeMajor -lt 18) {
    Write-BridgeLog "FOUT: Node.js 18+ vereist (gevonden: $(& node -v))."
    exit 1
}

Write-BridgeLog 'npm install...'
& npm install --omit=dev 2>&1 | ForEach-Object { Write-BridgeLog $_ }
if ($LASTEXITCODE -ne 0) {
    Write-BridgeLog 'FOUT: npm install mislukt.'
    exit 1
}

Write-BridgeLog 'Bridge starten op http://127.0.0.1:9173'
& node index.js 2>&1 | ForEach-Object { Write-BridgeLog $_ }
