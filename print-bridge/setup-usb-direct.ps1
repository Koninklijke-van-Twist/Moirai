# Direct USB setup for Moirai print bridge (GOOJPRT PT-210)
#
# Windows blocks libusb while the POS58/usbprint driver owns the device.
# You need WinUSB via Zadig AND remove the Windows printer queue entries.
#
# Steps:
# 1. Download Zadig: https://zadig.akeo.ie/
# 2. Options -> List All Devices
# 3. Select the PT-210 / POS58 device (USB ID 28E9:0289)
# 4. Driver target: WinUSB -> Replace Driver
# 5. Run this script with -RemovePrinters to delete queue entries
# 6. Start bridge with: $env:MOIRAI_USE_USB_DIRECT='1'; npm start
#
# To restore Windows printing: uninstall device in Device Manager,
# reconnect USB, reinstall POS58 driver from manufacturer.

param(
    [switch]$RemovePrinters
)

$ErrorActionPreference = 'Stop'

Write-Host 'Moirai USB direct setup' -ForegroundColor Cyan
Write-Host ''
Write-Host 'USB device to target in Zadig: VID 28E9 / PID 0289'
Write-Host ''

$printers = Get-Printer | Where-Object {
    $_.PortName -match '^USB' -or $_.Name -match 'POS|Moirai'
}

if ($printers) {
    Write-Host 'Printers on USB / POS:' -ForegroundColor Yellow
    $printers | Format-Table Name, PortName, DriverName -AutoSize
} else {
    Write-Host 'No POS/USB printers found in queue.'
}

if ($RemovePrinters) {
    foreach ($printer in $printers) {
        Write-Host "Removing printer: $($printer.Name)"
        Remove-Printer -Name $printer.Name
    }
    Write-Host 'Printer queue entries removed.' -ForegroundColor Green
} else {
    Write-Host ''
    Write-Host 'Dry run only. Re-run with -RemovePrinters after Zadig WinUSB install.'
}

Write-Host ''
Write-Host 'Then start bridge:' -ForegroundColor Cyan
Write-Host "  `$env:MOIRAI_USE_USB_DIRECT='1'"
Write-Host '  npm start'
